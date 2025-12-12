<?php
// ============================================================
// PROJETS_ACTIONS.PHP - ACTIONS SUR LES PROJETS
// ============================================================
// Traite les formulaires liés à l'administration d'un projet:
// - add_member: ajoute un membre par email avec un rôle
// - delete_project: supprime le projet et toutes ses données liées
// - remove_member: retire un membre du projet
// - edit_member_role: change le rôle d'un membre
// Chaque action vérifie que l'utilisateur courant est autorisé
// (propriétaire du projet) avant de modifier les données.
// ============================================================
session_start();
require 'db_connect.php';
require 'log_activity.php';

// Redirection de sécurité : si non connecté, renvoyer à l'authentification
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];


// ------------------------------------------------------------------
// --- Traitement de l'ajout d'un membre ---
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {

    $project_id = $_POST['project_id'] ?? null;
    $member_email = trim($_POST['member_email'] ?? '');
    $member_role = $_POST['member_role'] ?? '';

    // Vérification de base
    if (!$project_id || !is_numeric($project_id) || empty($member_email) || empty($member_role)) {
        header("Location: ../../projet_admin.php?id=" . $project_id . "&error=" . urlencode("Tous les champs sont requis."));
        exit();
    }

    try {
        // 1. VÉRIFICATION D'AUTORISATION : L'utilisateur connecté est-il le créateur du projet ?
        $stmt_check = $pdo->prepare("SELECT createur_id FROM projets WHERE id = ?");
        $stmt_check->execute([$project_id]);
        $project_owner = $stmt_check->fetchColumn();

        if (!$project_owner || $project_owner != $user_id) {
            header("Location: ../../projet.php?id=" . $project_id . "&error=" . urlencode("Action non autorisée. Seul le propriétaire peut ajouter des membres."));
            exit();
        }

        // 2. TROUVER L'ID DE L'UTILISATEUR À PARTIR DE SON EMAIL
        $stmt_find_user = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt_find_user->execute([$member_email]);
        $target_user_id = $stmt_find_user->fetchColumn();

        if (!$target_user_id) {
            header("Location: ../../projet_admin.php?id=" . $project_id . "&error=" . urlencode("Utilisateur avec cet email non trouvé."));
            exit();
        }

        // Empêcher d'ajouter le propriétaire lui-même
        if ($target_user_id == $project_owner) {
            header("Location: ../../projet_admin.php?id=" . $project_id . "&error=" . urlencode("Le propriétaire est déjà lié au projet."));
            exit();
        }


        // 3. VÉRIFIER SI LE MEMBRE EST DÉJÀ DANS LE PROJET
        $stmt_is_member = $pdo->prepare("SELECT COUNT(*) FROM projet_membres WHERE projet_id = ? AND utilisateur_id = ?");
        $stmt_is_member->execute([$project_id, $target_user_id]);
        if ($stmt_is_member->fetchColumn() > 0) {
            header("Location: ../../projet_admin.php?id=" . $project_id . "&error=" . urlencode("Cet utilisateur est déjà membre du projet."));
            exit();
        }

        // 4. AJOUTER LE MEMBRE
        $stmt_add_member = $pdo->prepare("INSERT INTO projet_membres (projet_id, utilisateur_id, role) VALUES (?, ?, ?)");
        $stmt_add_member->execute([$project_id, $target_user_id, $member_role]);

        // Log de l'ajout de membre
        log_activity($pdo, $user_id, 'member_add', "Membre ajouté au projet ID {$project_id}: utilisateur ID {$target_user_id}");

        // 5. Redirection avec succès
        $formatted_role = ucfirst(str_replace('_', ' ', $member_role));
        header("Location: ../../projet_admin.php?id=" . $project_id . "&success=" . urlencode("Le membre a été ajouté avec le rôle de " . $formatted_role . "."));
        exit();

    } catch (\PDOException $e) {
        // Erreur d'intégrité (clé étrangère) ou autre erreur BDD
        error_log("Erreur de BDD lors de l'ajout du membre au projet {$project_id}: " . $e->getMessage());
        header("Location: ../../projet_admin.php?id=" . $project_id . "&error=" . urlencode("Erreur technique lors de l'ajout du membre."));
        exit();
    }
}


// ------------------------------------------------------------------
// --- Traitement de la suppression du projet ---
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {

    $project_id = $_POST['project_id'] ?? null;

    if (!$project_id || !is_numeric($project_id)) {
        header("Location: ../../index.php?error=" . urlencode("ID de projet manquant ou invalide."));
        exit();
    }

    try {
        // 1. VÉRIFICATION D'AUTORISATION STRICTE : L'utilisateur est-il le créateur ?
        $stmt_check = $pdo->prepare("SELECT createur_id, nom_projet FROM projets WHERE id = ?");
        $stmt_check->execute([$project_id]);
        $project_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$project_data) {
            header("Location: ../../index.php?error=" . urlencode("Projet non trouvé."));
            exit();
        }

        $project_name = $project_data['nom_projet'];

        if ($project_data['createur_id'] != $user_id) {
            // Tentative de suppression non autorisée
            header("Location: ../../projet.php?id=" . $project_id . "&error=" . urlencode("Action non autorisée. Vous devez être le propriétaire pour supprimer le projet."));
            exit();
        }

        // 2. PURGE DES DONNÉES LIÉES AU PROJET
        // 2.1 Supprimer les membres liés
        $stmt_del_members = $pdo->prepare("DELETE FROM projet_membres WHERE projet_id = ?");
        $stmt_del_members->execute([$project_id]);

        // 2.2 Supprimer les fichiers du dépôt et les documents (uploads/projects/{project_id})
        $uploadsRoot = realpath(__DIR__ . '/../uploads/projects');
        if ($uploadsRoot !== false) {
            $projDir = $uploadsRoot . DIRECTORY_SEPARATOR . $project_id;
            if (is_dir($projDir)) {
                // Suppression récursive prudente
                $remove = function ($p) use (&$remove) {
                    if (is_dir($p)) {
                        foreach (scandir($p) ?: [] as $e) {
                            if ($e === '.' || $e === '..') continue;
                            $remove($p . DIRECTORY_SEPARATOR . $e);
                        }
                        @rmdir($p);
                    } else {
                        @unlink($p);
                    }
                };
                // Supprimer explicitement les documents chiffrés + manifestes si présents
                $docsDir = $projDir . DIRECTORY_SEPARATOR . 'documents';
                if (is_dir($docsDir)) { $remove($docsDir); }
                $remove($projDir);
            }
        }


        // 2.3 Tickets
        $stmt_del_tickets = $pdo->prepare("DELETE FROM tickets WHERE projet_id = ?");
        $stmt_del_tickets->execute([$project_id]);

        // 2.3.b Supprimer les commits liés aux tickets du projet si table présente
        try {
            $stmt_del_tickets_commites = $pdo->prepare("DELETE FROM tickets_commites WHERE projet_id = ?");
            $stmt_del_tickets_commites->execute([$project_id]);
        } catch (\PDOException $e) {
            // Table éventuellement absente selon schéma; journaliser sans bloquer
            error_log("Suppression tickets_commites échouée (peut-être table absente): " . $e->getMessage());
        }

        // 2.4 Tâches et assignations
        $stmt_sel_tasks = $pdo->prepare("SELECT id FROM tâches WHERE projet_id = ?");
        $stmt_sel_tasks->execute([$project_id]);
        $taskIds = $stmt_sel_tasks->fetchAll(PDO::FETCH_COLUMN);
        if ($taskIds && count($taskIds) > 0) {
            // Supprimer assignations
            $stmt_del_assign = $pdo->prepare("DELETE FROM tâches_assignations WHERE tâche_id = ?");
            foreach ($taskIds as $tid) { $stmt_del_assign->execute([$tid]); }
            // Supprimer dossiers uploads de tâches
            $tasksUploadRoot = realpath(__DIR__ . '/../uploads/tasks');
            if ($tasksUploadRoot !== false) {
                foreach ($taskIds as $tid) {
                    $tdir = $tasksUploadRoot . DIRECTORY_SEPARATOR . $tid;
                    if (is_dir($tdir)) {
                        $remove = function ($p) use (&$remove) {
                            if (is_dir($p)) {
                                foreach (scandir($p) ?: [] as $e) {
                                    if ($e === '.' || $e === '..') continue;
                                    $remove($p . DIRECTORY_SEPARATOR . $e);
                                }
                                @rmdir($p);
                            } else { @unlink($p); }
                        };
                        $remove($tdir);
                    }
                }
            }
            // Supprimer les tâches
            $stmt_del_tasks = $pdo->prepare("DELETE FROM tâches WHERE id = ?");
            foreach ($taskIds as $tid) { $stmt_del_tasks->execute([$tid]); }
        }

        // 2.5 Messages privés + fichiers + états
        // Supprimer les fichiers liés d'abord (référencés en DB)
        $stmt_sel_msg = $pdo->prepare("SELECT id FROM messages_prives WHERE projet_id = ?");
        $stmt_sel_msg->execute([$project_id]);
        $msgIds = $stmt_sel_msg->fetchAll(PDO::FETCH_COLUMN);
        if ($msgIds && count($msgIds) > 0) {
            $stmt_del_msg_files = $pdo->prepare("DELETE FROM messages_prives_files WHERE message_id = ?");
            foreach ($msgIds as $mid) { $stmt_del_msg_files->execute([$mid]); }
            // Supprimer messages
            $stmt_del_msgs = $pdo->prepare("DELETE FROM messages_prives WHERE id = ?");
            foreach ($msgIds as $mid) { $stmt_del_msgs->execute([$mid]); }
        }
        // Supprimer états de lecture et typing pour le projet
        $pdo->prepare("DELETE FROM messages_reads WHERE projet_id = ?")->execute([$project_id]);
        $pdo->prepare("DELETE FROM messages_typing WHERE projet_id = ?")->execute([$project_id]);

        // 2.6 (Optionnel) Autres tables liées si présentes: logs, pièces jointes spécifiques, etc.

        // 3. SUPPRESSION DU PROJET
        $stmt_delete = $pdo->prepare("DELETE FROM projets WHERE id = ?");
        $stmt_delete->execute([$project_id]);

        // Log de suppression du projet
        log_activity($pdo, $user_id, 'project_delete', "Projet supprimé: '{$project_name}' (ID {$project_id})");

        // 4. Redirection avec succès vers la liste des projets
        header("Location: ../../index.php?section=projets&success=" . urlencode("Le projet '{$project_name}' a été supprimé avec succès."));
        exit();

    } catch (\PDOException $e) {
        error_log("Erreur de BDD lors de la suppression du projet {$project_id}: " . $e->getMessage());
        header("Location: ../../index.php?error=" . urlencode("Erreur technique lors de la suppression du projet."));
        exit();
    }
}


// ------------------------------------------------------------------
// --- Traitement du retrait d'un membre du projet (NOUVEAU) ---
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_member'])) {

    $project_id = $_POST['project_id'] ?? null;
    $member_id_to_remove = $_POST['member_id_to_remove'] ?? null;

    if (!$project_id || !is_numeric($project_id) || !$member_id_to_remove || !is_numeric($member_id_to_remove)) {
        header("Location: ../../projet_admin.php?id=" . $project_id . "&error=" . urlencode("Données de retrait de membre invalides."));
        exit();
    }

    try {
        // 1. VÉRIFICATION D'AUTORISATION : L'utilisateur connecté est-il le créateur du projet ?
        $stmt_check = $pdo->prepare("SELECT createur_id FROM projets WHERE id = ?");
        $stmt_check->execute([$project_id]);
        $project_owner = $stmt_check->fetchColumn();

        if (!$project_owner || $project_owner != $user_id) {
            header("Location: ../../projet_admin.php?id=" . $project_id . "&error=" . urlencode("Action non autorisée. Seul le propriétaire peut retirer des membres."));
            exit();
        }

        // 2. Empêcher la suppression du propriétaire lui-même
        if ($member_id_to_remove == $project_owner) {
            header("Location: ../../projet_admin.php?id=" . $project_id . "&error=" . urlencode("Impossible de retirer le propriétaire du projet via cette action."));
            exit();
        }

        // 3. RETIRER LE MEMBRE
        // Supprime l'entrée dans la table de liaison projet_membres
        $stmt_remove = $pdo->prepare("DELETE FROM projet_membres WHERE projet_id = ? AND utilisateur_id = ?");
        $stmt_remove->execute([$project_id, $member_id_to_remove]);

        // Log du retrait de membre
        log_activity($pdo, $user_id, 'member_remove', "Membre retiré du projet ID {$project_id}: utilisateur ID {$member_id_to_remove}");

        // 4. Redirection avec succès
        header("Location: ../../projet_admin.php?id=" . $project_id . "&success=" . urlencode("Le membre a été retiré du projet."));
        exit();

    } catch (\PDOException $e) {
        error_log("Erreur de BDD lors du retrait du membre {$member_id_to_remove} du projet {$project_id}: " . $e->getMessage());
        header("Location: ../../projet_admin.php?id=" . $project_id . "&error=" . urlencode("Erreur technique lors du retrait du membre."));
        exit();
    }
}


// ------------------------------------------------------------------
// --- Traitement de la modification du rôle d'un membre (NOUVEAU) ---
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_member_role'])) {

    $project_id = $_POST['project_id'] ?? null;
    $member_id_to_edit = $_POST['member_id_to_edit'] ?? null;
    $new_role = $_POST['new_member_role'] ?? '';

    // Vérification de base
    if (!$project_id || !is_numeric($project_id) || !$member_id_to_edit || !is_numeric($member_id_to_edit) || empty($new_role)) {
        header("Location: ../../projet_admin.php?id=" . $project_id . "&error=" . urlencode("Données de modification de rôle invalides."));
        exit();
    }

    try {
        // 1. VÉRIFICATION D'AUTORISATION : L'utilisateur connecté est-il le créateur du projet ?
        $stmt_check = $pdo->prepare("SELECT createur_id FROM projets WHERE id = ?");
        $stmt_check->execute([$project_id]);
        $project_owner = $stmt_check->fetchColumn();

        if (!$project_owner || $project_owner != $user_id) {
            header("Location: ../../projet_admin.php?id=" . $project_id . "&error=" . urlencode("Action non autorisée. Seul le propriétaire peut modifier les rôles."));
            exit();
        }

        // 2. Empêcher la modification du rôle du propriétaire (son rôle est implicitement "Propriétaire")
        if ($member_id_to_edit == $project_owner) {
            header("Location: ../../projet_admin.php?id=" . $project_id . "&error=" . urlencode("Impossible de modifier le rôle du propriétaire."));
            exit();
        }

        // 3. MODIFIER LE RÔLE DU MEMBRE
        $stmt_edit = $pdo->prepare("UPDATE projet_membres SET role = ? WHERE projet_id = ? AND utilisateur_id = ?");
        $stmt_edit->execute([$new_role, $project_id, $member_id_to_edit]);

        // 4. Redirection avec succès
        $formatted_role = ucfirst(str_replace('_', ' ', $new_role));
        header("Location: ../../projet_admin.php?id=" . $project_id . "&success=" . urlencode("Le rôle du membre a été mis à jour à: " . $formatted_role));
        exit();

    } catch (\PDOException $e) {
        error_log("Erreur de BDD lors de la modification du rôle du membre {$member_id_to_edit} pour le projet {$project_id}: " . $e->getMessage());
        header("Location: ../../projet_admin.php?id=" . $project_id . "&error=" . urlencode("Erreur technique lors de la modification du rôle."));
        exit();
    }
}


// Si aucune action POST valide n'a été trouvée
header("Location: ../../index.php");
exit();
?>