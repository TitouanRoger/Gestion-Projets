<?php
// ============================================================
// PARAMETRES.PHP - ACTIONS PARAMÈTRES UTILISATEUR
// ============================================================
// Traite 3 formulaires depuis l'onglet Paramètres:
// 1) update_info: mise à jour prénom/nom (et session)
// 2) change_password: mise à jour mot de passe (règles robustes)
// 3) delete_account: purge complète des données et suppression
// - Utilise log_activity() et purge_user_logs()
// - Supprime fichiers uploadés (tâches, messages, dépôt de code)
// ============================================================
require_once 'secure_session.php';
secure_session_start();
require 'db_connect.php'; // Connexion à la BDD ($pdo)
require 'log_activity.php'; // Système de journalisation

// Assurez-vous que l'utilisateur est connecté (et rafraîchit le remember-me)
if (!validate_session()) {
    header("Location: ../../auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['status' => 'error', 'message' => 'Requête invalide.'];

// Fonction pour rediriger vers la page d'accueil avec un message
function redirect_with_message($message, $status = 'success')
{
    // Redirige toujours vers l'index, le JS devra lire les messages d'erreur depuis l'URL si besoin
    header("Location: ../../index.php?section=parametres&{$status}=" . urlencode($message));
    exit();
}

// --- 1. CHANGER LES INFORMATIONS PERSONNELLES ---
if (isset($_POST['update_info'])) {
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');

    if (empty($prenom) || empty($nom)) {
        redirect_with_message("Le prénom et le nom ne peuvent être vides.", 'error');
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE utilisateurs SET prenom = ?, nom = ? WHERE id = ?");
            $stmt->execute([$prenom, $nom, $user_id]);

            // Mise à jour de la session pour afficher les nouveaux noms immédiatement
            $_SESSION['user_prenom'] = $prenom;
            $_SESSION['user_nom'] = $nom;

            redirect_with_message("Vos informations personnelles ont été mises à jour avec succès.");
        } catch (\PDOException $e) {
            redirect_with_message("Erreur technique lors de la mise à jour des informations.", 'error');
        }
    }
}

// --- 2. CHANGER LE MOT DE PASSE ---
elseif (isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation des mots de passe
    if ($new_password !== $confirm_password) {
        redirect_with_message("Les mots de passe ne correspondent pas.", 'error');
    } elseif (strlen($new_password) < 8 || !preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/\d/', $new_password) || !preg_match('/[^a-zA-Z\d]/', $new_password)) {
        // Validation RGPD (critères stricts)
        redirect_with_message("Le mot de passe ne respecte pas les critères de sécurité (8 caractères, majuscule, minuscule, chiffre, spécial).", 'error');
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);

            log_activity($pdo, $user_id, 'password_change', 'Mot de passe modifié');

            redirect_with_message("Votre mot de passe a été modifié avec succès.");

        } catch (\PDOException $e) {
            redirect_with_message("Erreur technique lors de la modification du mot de passe.", 'error');
        }
    }
}

// --- 3. SUPPRIMER LE COMPTE ---
elseif (isset($_POST['delete_account'])) {
    // Dans une application réelle, vous devriez demander le mot de passe actuel ici pour double sécurité.

    try {
        // Désactiver temporairement les contraintes de clés étrangères pour éviter les erreurs techniques
        // lors des suppressions en cascade manuelles sur des installations hétérogènes.
        try { $pdo->exec("SET FOREIGN_KEY_CHECKS=0"); } catch (\PDOException $e) {}
        // Commencer une transaction pour purger toutes les données liées
        $pdo->beginTransaction();

        // Retirer l'utilisateur de tous les projets où il est membre AVANT toute autre suppression
        try {
            $stmt = $pdo->prepare("DELETE FROM projet_membres WHERE utilisateur_id = ?");
            $stmt->execute([$user_id]);
        } catch (\PDOException $e) {
            // On ignore si la table/contrainte varie selon l'installation
        }

        // 1. Récupérer tous les projets créés par cet utilisateur pour les supprimer avec leurs données
        $stmt_projets = $pdo->prepare("SELECT id FROM projets WHERE createur_id = ?");
        $stmt_projets->execute([$user_id]);
        $projets_utilisateur = $stmt_projets->fetchAll(PDO::FETCH_COLUMN);

        // 2. Pour chaque projet créé, purger toutes les données associées
        foreach ($projets_utilisateur as $project_id) {
            // Etape: purge projet
            // Supprimer les membres du projet
            try {
                $stmt = $pdo->prepare("DELETE FROM projet_membres WHERE projet_id = ?");
                $stmt->execute([$project_id]);
            } catch (\PDOException $e) { /* table/contrainte optionnelle */ }

            // Supprimer les tickets du projet
            try {
                $stmt = $pdo->prepare("DELETE FROM tickets WHERE projet_id = ?");
                $stmt->execute([$project_id]);
            } catch (\PDOException $e) { /* table/contrainte optionnelle */ }

            // Supprimer les commits liés aux tickets du projet (si table présente)
            try {
                $stmt = $pdo->prepare("DELETE FROM tickets_commites WHERE projet_id = ?");
                $stmt->execute([$project_id]);
            } catch (\PDOException $e) { /* table optionnelle */ }

            // Supprimer les assignations de tâches
            try {
                $stmt = $pdo->prepare("DELETE FROM tâches_assignations WHERE tâche_id IN (SELECT id FROM tâches WHERE projet_id = ?)");
                $stmt->execute([$project_id]);
            } catch (\PDOException $e) { /* table/contrainte optionnelle */ }

            // Supprimer les tâches et leurs fichiers
            $task_ids = [];
            try {
                $stmt_tasks = $pdo->prepare("SELECT id FROM tâches WHERE projet_id = ?");
                $stmt_tasks->execute([$project_id]);
                $task_ids = $stmt_tasks->fetchAll(PDO::FETCH_COLUMN);
            } catch (\PDOException $e) { /* table/contrainte optionnelle */ }
            foreach ($task_ids as $task_id) {
                $task_upload_dir = __DIR__ . '/../uploads/tasks/' . $task_id;
                if (is_dir($task_upload_dir)) {
                    @array_map('unlink', glob("$task_upload_dir/*/*"));
                    @array_map('rmdir', glob("$task_upload_dir/*", GLOB_ONLYDIR));
                    @rmdir($task_upload_dir);
                }
            }
            try {
                $stmt = $pdo->prepare("DELETE FROM tâches WHERE projet_id = ?");
                $stmt->execute([$project_id]);
            } catch (\PDOException $e) { /* table/contrainte optionnelle */ }

            // Supprimer les fichiers de messages
            $stmt_msg = $pdo->prepare("SELECT id FROM messages_prives WHERE projet_id = ?");
            $stmt_msg->execute([$project_id]);
            $message_ids = $stmt_msg->fetchAll(PDO::FETCH_COLUMN);
            foreach ($message_ids as $msg_id) {
                $msg_upload_dir = __DIR__ . '/../uploads/messages/' . $msg_id;
                if (is_dir($msg_upload_dir)) {
                    array_map('unlink', glob("$msg_upload_dir/*"));
                    @rmdir($msg_upload_dir);
                }
            }
            // Purger les pièces jointes (DB) liées aux messages du projet
            if (!empty($message_ids)) {
                try {
                    $placeholders = implode(',', array_fill(0, count($message_ids), '?'));
                    $stmtDelFiles = $pdo->prepare("DELETE FROM messages_prives_files WHERE message_id IN ($placeholders)");
                    $stmtDelFiles->execute($message_ids);
                } catch (\PDOException $e) {
                    throw $e;
                }
            }

            // Supprimer les messages et métadonnées
            try {
                $stmt = $pdo->prepare("DELETE FROM messages_reads WHERE projet_id = ?");
                $stmt->execute([$project_id]);
            } catch (\PDOException $e) { /* table/contrainte optionnelle */ }
            try {
                $stmt = $pdo->prepare("DELETE FROM messages_typing WHERE projet_id = ?");
                $stmt->execute([$project_id]);
            } catch (\PDOException $e) {
                throw $e;
            }
            try {
                $stmt = $pdo->prepare("DELETE FROM messages_prives WHERE projet_id = ?");
                $stmt->execute([$project_id]);
            } catch (\PDOException $e) {
                throw $e;
            }

            // Supprimer dépôt de code & documents sous uploads/projects/{project_id}/code et /documents
            $delete_dir_recursive = function ($dir) {
                if (!is_dir($dir))
                    return;
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileinfo) {
                    if ($fileinfo->isDir()) {
                        @rmdir($fileinfo->getRealPath());
                    } else {
                        @unlink($fileinfo->getRealPath());
                    }
                }
                @rmdir($dir);
            };

            $project_upload_dir = __DIR__ . '/../uploads/projects/' . $project_id;

            // Dossier code du projet
            $code_dir = $project_upload_dir . '/code';
            $delete_dir_recursive($code_dir);

            // Dossier documents du projet
            $docs_dir = $project_upload_dir . '/documents';
            $delete_dir_recursive($docs_dir);

            // Supprimer le dossier du projet s'il est vide
            $delete_dir_recursive($project_upload_dir);

            // Note: le code live est sous uploads/projects/{project_id}/code et déjà supprimé ci-dessus.
        }

        // 3. Supprimer les projets créés par l'utilisateur
        try {
            $stmt = $pdo->prepare("DELETE FROM projets WHERE createur_id = ?");
            $stmt->execute([$user_id]);
        } catch (\PDOException $e) { /* table/contrainte optionnelle */ }


        // 4. Supprimer les tickets créés par l'utilisateur dans d'autres projets
        try {
            $stmt = $pdo->prepare("DELETE FROM tickets WHERE createur_id = ?");
            $stmt->execute([$user_id]);
        } catch (\PDOException $e) { /* table/contrainte optionnelle */ }

        // 4.b Supprimer les commits liés créés par l'utilisateur
        try {
            $stmt = $pdo->prepare("DELETE FROM tickets_commites WHERE createur_id = ?");
            $stmt->execute([$user_id]);
        } catch (\PDOException $e) { /* table/contrainte optionnelle */ }

        // 5. Retirer les assignations de tâches de l'utilisateur
        try {
            $stmt = $pdo->prepare("DELETE FROM tâches_assignations WHERE utilisateur_id = ?");
            $stmt->execute([$user_id]);
        } catch (\PDOException $e) { /* table/contrainte optionnelle */ }

        // 7. Supprimer les messages privés envoyés ou reçus par l'utilisateur
        try {
            $stmt = $pdo->prepare("DELETE FROM messages_reads WHERE user_id = ? OR other_user_id = ?");
            $stmt->execute([$user_id, $user_id]);
        } catch (\PDOException $e) { /* table/contrainte optionnelle */ }
        try {
            $stmt = $pdo->prepare("DELETE FROM messages_typing WHERE user_id = ? OR other_user_id = ?");
            $stmt->execute([$user_id, $user_id]);
        } catch (\PDOException $e) { /* table/contrainte optionnelle */ }
        // Supprimer d'abord les pièces jointes des messages du user
        try {
            $stmtMsgIds = $pdo->prepare("SELECT id FROM messages_prives WHERE sender_id = ? OR recipient_id = ?");
            $stmtMsgIds->execute([$user_id, $user_id]);
            $userMsgIds = $stmtMsgIds->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($userMsgIds)) {
                foreach ($userMsgIds as $mid) {
                    $msg_upload_dir = __DIR__ . '/../uploads/messages/' . $mid;
                    if (is_dir($msg_upload_dir)) {
                        array_map('unlink', glob("$msg_upload_dir/*"));
                        @rmdir($msg_upload_dir);
                    }
                }
                $placeholders = implode(',', array_fill(0, count($userMsgIds), '?'));
                $stmtFiles = $pdo->prepare("DELETE FROM messages_prives_files WHERE message_id IN ($placeholders)");
                $stmtFiles->execute($userMsgIds);
            }
        } catch (\PDOException $e) { /* table/contrainte optionnelle */ }
        try {
            $stmt = $pdo->prepare("DELETE FROM messages_prives WHERE sender_id = ? OR recipient_id = ?");
            $stmt->execute([$user_id, $user_id]);
        } catch (\PDOException $e) { /* table/contrainte optionnelle */ }

        // 8. Log de la suppression avant la purge des logs
        log_activity($pdo, $user_id, 'account_delete', 'Suppression du compte utilisateur');

        // 9. Purger tous les logs d'activité de l'utilisateur
        purge_user_logs($pdo, $user_id);

        // 10. Suppression finale de l'utilisateur
        try {
            $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
            $stmt->execute([$user_id]);
        } catch (\PDOException $e) { /* table/contrainte optionnelle */ }

        $pdo->commit();
        try { $pdo->exec("SET FOREIGN_KEY_CHECKS=1"); } catch (\PDOException $e) {}

        // Destruction de la session et redirection vers l'authentification
        session_destroy();
        header("Location: ../../auth.php?success=Votre compte a été supprimé définitivement.");
        exit();

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        try { $pdo->exec("SET FOREIGN_KEY_CHECKS=1"); } catch (\PDOException $e2) {}
        redirect_with_message("Erreur technique lors de la suppression du compte.", 'error');
    }
}

// Si on arrive ici sans action POST, revenir simplement à la page
header("Location: ../../index.php?section=parametres");
exit();
?>