<?php
// ============================================================
// TASKS.PHP - ACTIONS SUR LES TÂCHES
// ============================================================
// Traite les actions liées aux tâches d'un projet:
// - Création de tâche (titre, type, priorité, assignations)
// - (Plus bas dans le fichier) édition, changement de statut,
//   gestion des assignations, uploads de fichiers, etc.
// Vérifications:
// - Utilisateur connecté
// - project_id valide
// - Rôle (certaines actions réservées au Chef de projet)
// ============================================================
require_once 'secure_session.php';
secure_session_start();
// Remonte d'un niveau supplémentaire pour atteindre le dossier racine si c'est nécessaire
require 'db_connect.php';
require_once 'file_crypto.php';

// --- Redirection de Sécurité si non connecté ---
if (!validate_session()) {
    header("Location: ../../auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$project_id = $_POST['project_id'] ?? null;

// Vérification de l'ID du projet
if (!$project_id || !is_numeric($project_id)) {
    header("Location: ../../index.php?error=" . urlencode("ID de projet invalide."));
    exit();
}

// Fonction utilitaire pour rediriger
function redirect_to_tasks($project_id, $message, $status = 'success')
{
    $location = "../../projet.php?id=" . htmlspecialchars($project_id) . "&tab=tasks&" . $status . "=" . urlencode($message);
    header("Location: " . $location);
    exit();
}

// Vérification du statut de Chef de Projet (Propriétaire)
$is_proprietaire = false;
$stmt_check_owner = $pdo->prepare("SELECT createur_id FROM projets WHERE id = ?");
$stmt_check_owner->execute([$project_id]);
if ($owner_id = $stmt_check_owner->fetchColumn()) {
    $is_proprietaire = ($user_id == $owner_id);
}


// ----------------------------------------
// --- 1. CREATION DE TACHE (POST) ---
// ----------------------------------------
if (isset($_POST['create_task'])) {
    if (!$is_proprietaire) {
        redirect_to_tasks($project_id, "Seul le Chef de projet peut créer une tâche.", 'error');
    }

    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $priorite = trim($_POST['priorite'] ?? '');
    $assigne_ids = $_POST['utilisateur_assigne_id'] ?? [];
    $assigne_ids = is_array($assigne_ids) ? $assigne_ids : [];

    if (empty($titre) || empty($type) || empty($priorite)) {
        redirect_to_tasks($project_id, "Le titre, le type et la priorité de la tâche sont obligatoires.", 'error');
    }

    try {
        $pdo->beginTransaction();

        // statut initial basé sur assignations
        $statut_initial = (!empty($assigne_ids)) ? 'en cours' : 'a faire';

        $sql = "INSERT INTO tâches (projet_id, titre, description, type, priorite, statut, createur_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $project_id,
            $titre,
            $description,
            $type,
            $priorite,
            $statut_initial,
            $user_id
        ]);
        $new_task_id = $pdo->lastInsertId();

        if (!empty($assigne_ids)) {
            $values = [];
            foreach ($assigne_ids as $uid) {
                if (is_numeric($uid)) {
                    $values[] = $new_task_id;
                    $values[] = $uid;
                }
            }
            if (!empty($values)) {
                $placeholders = implode(',', array_fill(0, count($assigne_ids), '(?, ?)'));
                $sql_insert_assign = "INSERT INTO tâches_assignations (tâche_id, utilisateur_id) VALUES " . $placeholders;
                $stmt_insert_assign = $pdo->prepare($sql_insert_assign);
                $stmt_insert_assign->execute($values);
            }
        }

        $pdo->commit();
        redirect_to_tasks($project_id, "La tâche '{$titre}' a été créée avec succès !");
    } catch (\PDOException $e) {
        $pdo->rollBack();
        error_log("Erreur BDD création tâche: " . $e->getMessage());
        redirect_to_tasks($project_id, "Erreur technique lors de la création de la tâche.", 'error');
    }
}

// -----------------------------------------------------
// --- 2. MISE A JOUR DES DETAILS DE TACHE (POST) ---
// -----------------------------------------------------
if (isset($_POST['update_task'])) {
    if (!$is_proprietaire) {
        redirect_to_tasks($project_id, "Seul le Chef de projet peut modifier les détails de la tâche.", 'error');
    }

    $task_id = $_POST['task_id'] ?? null;
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $priorite = trim($_POST['priorite'] ?? '');
    $statut_souhaite = trim($_POST['statut'] ?? '');
    $assigne_ids = $_POST['utilisateur_assigne_id'] ?? [];
    $assigne_ids = is_array($assigne_ids) ? $assigne_ids : [];

    if (!$task_id || !is_numeric($task_id) || empty($titre) || empty($type) || empty($priorite) || empty($statut_souhaite)) {
        redirect_to_tasks($project_id, "Données de mise à jour de tâche invalides.", 'error');
    }

    try {
        $pdo->beginTransaction();

        // Récupérer statut actuel pour savoir si terminé/validé (on ne force pas alors)
        $stmt_cur = $pdo->prepare("SELECT statut FROM tâches WHERE id = ? AND projet_id = ?");
        $stmt_cur->execute([$task_id, $project_id]);
        $statut_actuel = $stmt_cur->fetchColumn();

        // Mise à jour des champs de base (provisoirement garder statut entrant)
        $sql = "UPDATE tâches SET titre = ?, description = ?, type = ?, priorite = ?, statut = ? WHERE id = ? AND projet_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$titre, $description, $type, $priorite, $statut_souhaite, $task_id, $project_id]);

        // Réinitialiser les assignations
        $stmt_delete_assign = $pdo->prepare("DELETE FROM tâches_assignations WHERE tâche_id = ?");
        $stmt_delete_assign->execute([$task_id]);

        if (!empty($assigne_ids)) {
            $placeholders = implode(',', array_fill(0, count($assigne_ids), '(?, ?)'));
            $sql_insert_assign = "INSERT INTO tâches_assignations (tâche_id, utilisateur_id) VALUES " . $placeholders;
            $values = [];
            foreach ($assigne_ids as $uid) {
                if (is_numeric($uid)) {
                    $values[] = $task_id;
                    $values[] = $uid;
                }
            }
            if (!empty($values)) {
                $stmt_insert_assign = $pdo->prepare($sql_insert_assign);
                $stmt_insert_assign->execute($values);
            }
        }

        // Ajustement automatique du statut (si pas terminé/validé)
        if (!in_array($statut_actuel, ['terminée', 'validée'])) {
            $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM tâches_assignations WHERE tâche_id = ?");
            $stmt_count->execute([$task_id]);
            $nbAssignes = (int) $stmt_count->fetchColumn();
            $nouveau_statut = $nbAssignes > 0 ? 'en cours' : 'a faire';
            $stmt_upd_statut = $pdo->prepare("UPDATE tâches SET statut = ? WHERE id = ?");
            $stmt_upd_statut->execute([$nouveau_statut, $task_id]);
        }

        $pdo->commit();
        redirect_to_tasks($project_id, "La tâche '{$titre}' a été mise à jour avec succès.");
    } catch (\PDOException $e) {
        $pdo->rollBack();
        error_log("Erreur BDD mise à jour tâche: " . $e->getMessage());
        redirect_to_tasks($project_id, "Erreur technique lors de la mise à jour de la tâche.", 'error');
    }
}

// -----------------------------------------------------
// --- 2 bis. COMPLETION AVEC FICHIERS (POST) ---
// -----------------------------------------------------
if (isset($_POST['complete_task'])) {
    $task_id = $_POST['task_id'] ?? null;
    if (!$task_id || !is_numeric($task_id)) {
        redirect_to_tasks($project_id, "ID de tâche invalide.", 'error');
    }

    try {
        // Vérifier statut actuel + droits (doit être assigné, non terminé/validé)
        $stmt_info = $pdo->prepare("SELECT statut, titre FROM tâches WHERE id = ? AND projet_id = ?");
        $stmt_info->execute([$task_id, $project_id]);
        $rowInfo = $stmt_info->fetch(PDO::FETCH_ASSOC);
        if (!$rowInfo) {
            redirect_to_tasks($project_id, "Tâche introuvable.", 'error');
        }
        if (in_array($rowInfo['statut'], ['terminée', 'validée'])) {
            redirect_to_tasks($project_id, "La tâche est déjà clôturée.", 'error');
        }

        $stmt_assigned = $pdo->prepare("SELECT COUNT(*) FROM tâches_assignations WHERE tâche_id = ? AND utilisateur_id = ?");
        $stmt_assigned->execute([$task_id, $user_id]);
        if ($stmt_assigned->fetchColumn() == 0) {
            redirect_to_tasks($project_id, "Vous devez être assigné pour terminer la tâche.", 'error');
        }

        // Gestion des fichiers optionnels avec dossier d'attempt + historique
        $uploadErrors = [];
        $allowedExt = ['png', 'jpg', 'jpeg', 'gif', 'pdf', 'txt', 'zip'];
        $rootUploads = dirname(__DIR__) . '/uploads/tasks/' . $task_id; // assets/uploads/tasks/{id}
        if (!is_dir($rootUploads) && !mkdir($rootUploads, 0775, true)) {
            redirect_to_tasks($project_id, "Impossible de créer le dossier principal.", 'error');
        }
        $timestamp = date('Ymd_His');
        $attemptDirName = 'attempt_' . $timestamp;
        $attemptDirFs = $rootUploads . '/' . $attemptDirName;
        if (!mkdir($attemptDirFs, 0775, true)) {
            redirect_to_tasks($project_id, "Impossible de créer le dossier d'upload.", 'error');
        }
        $savedFiles = [];
        if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
            $count = count($_FILES['attachments']['name']);
            for ($i = 0; $i < $count; $i++) {
                $err = $_FILES['attachments']['error'][$i];
                if ($err === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($err !== UPLOAD_ERR_OK) {
                    $uploadErrors[] = 'Erreur fichier #' . ($i + 1);
                    continue;
                }
                $origName = $_FILES['attachments']['name'][$i];
                $tmpName = $_FILES['attachments']['tmp_name'][$i];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt)) {
                    $uploadErrors[] = 'Extension refusée: ' . htmlspecialchars($origName);
                    continue;
                }
                $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $origName);
                $destPath = $attemptDirFs . '/' . $safeName;
                $suffix = 1;
                while (file_exists($destPath)) {
                    $destPath = $attemptDirFs . '/' . $suffix . '_' . $safeName;
                    $suffix++;
                }
                $data = file_get_contents($tmpName);
                if ($data === false) {
                    $uploadErrors[] = 'Lecture échouée: ' . htmlspecialchars($origName);
                    continue;
                }
                $nonce = '';
                $tag = '';
                $cipher = encryptFileData($data, $nonce, $tag);
                $encPath = $destPath . '.enc';
                if (file_put_contents($encPath, $cipher) !== false) {
                    $savedFiles[] = basename($safeName);
                    $manifest[] = [
                        'original' => basename($safeName),
                        'stored' => basename($safeName) . '.enc',
                        'nonce' => base64_encode($nonce),
                        'tag' => base64_encode($tag)
                    ];
                } else {
                    $uploadErrors[] = 'Echec écriture: ' . htmlspecialchars($origName);
                }
            }
        }
        if (!isset($manifest)) $manifest = [];
        file_put_contents($attemptDirFs . '/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE));
        // Mettre le statut à terminée
        $stmt_close = $pdo->prepare("UPDATE tâches SET statut = 'terminée' WHERE id = ?");
        $stmt_close->execute([$task_id]);

        // Historique JSON
        $historyFile = $rootUploads . '/history.json';
        $history = [];
        if (file_exists($historyFile)) {
            $json = file_get_contents($historyFile);
            $history = json_decode($json, true) ?: [];
        }
        $history[] = [
            'timestamp' => $timestamp,
            'action' => 'terminée',
            'user_id' => $user_id,
            'reason' => null,
            'files' => $savedFiles,
            'attempt_dir' => $attemptDirName
        ];
        file_put_contents($historyFile, json_encode($history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $msg = "La tâche '" . htmlspecialchars($rowInfo['titre']) . "' a été terminée" . (empty($uploadErrors) ? ' avec succès.' : ' (erreurs sur certains fichiers).');
        redirect_to_tasks($project_id, $msg, empty($uploadErrors) ? 'success' : 'error');
    } catch (\PDOException $e) {
        error_log('Erreur completion tâche: ' . $e->getMessage());
        redirect_to_tasks($project_id, "Erreur technique lors de la complétion.", 'error');
    }
}

// -----------------------------------------------------
// --- 2 ter. VALIDATION / REJET PAR CHEF DE PROJET ---
// -----------------------------------------------------
if (isset($_POST['validate_task'])) {
    $task_id = $_POST['task_id'] ?? null;
    if (!$task_id || !is_numeric($task_id)) {
        redirect_to_tasks($project_id, "ID de tâche invalide.", 'error');
    }
    if (!$is_proprietaire) {
        redirect_to_tasks($project_id, "Action réservée au Chef de projet.", 'error');
    }
    try {
        $stmt_chk = $pdo->prepare("SELECT statut, titre FROM tâches WHERE id = ? AND projet_id = ?");
        $stmt_chk->execute([$task_id, $project_id]);
        $row = $stmt_chk->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            redirect_to_tasks($project_id, "Tâche introuvable.", 'error');
        }
        if ($row['statut'] !== 'terminée') {
            redirect_to_tasks($project_id, "La tâche doit être 'terminée' avant validation.", 'error');
        }
        $stmt_val = $pdo->prepare("UPDATE tâches SET statut = 'validée' WHERE id = ?");
        $stmt_val->execute([$task_id]);
        // Append history entry referencing last attempt
        $rootUploads = dirname(__DIR__) . '/uploads/tasks/' . $task_id;
        $historyFile = $rootUploads . '/history.json';
        $history = [];
        if (file_exists($historyFile)) {
            $history = json_decode(file_get_contents($historyFile), true) ?: [];
        }
        // Find last terminée attempt
        $lastAttempt = null;
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if ($history[$i]['action'] === 'terminée') {
                $lastAttempt = $history[$i];
                break;
            }
        }
        $timestamp = date('Ymd_His');
        $history[] = [
            'timestamp' => $timestamp,
            'action' => 'validée',
            'user_id' => $user_id,
            'reason' => null,
            'files' => $lastAttempt ? $lastAttempt['files'] : [],
            'attempt_dir' => $lastAttempt ? $lastAttempt['attempt_dir'] : null
        ];
        file_put_contents($historyFile, json_encode($history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        redirect_to_tasks($project_id, "Tâche '" . htmlspecialchars($row['titre']) . "' validée.");
    } catch (\PDOException $e) {
        error_log('Erreur validation tâche: ' . $e->getMessage());
        redirect_to_tasks($project_id, "Erreur technique validation.", 'error');
    }
}

if (isset($_POST['reject_task'])) {
    $task_id = $_POST['task_id'] ?? null;
    $reason = trim($_POST['reason'] ?? '');
    if (!$task_id || !is_numeric($task_id)) {
        redirect_to_tasks($project_id, "ID de tâche invalide.", 'error');
    }
    if (!$is_proprietaire) {
        redirect_to_tasks($project_id, "Action réservée au Chef de projet.", 'error');
    }
    if (empty($reason)) {
        redirect_to_tasks($project_id, "Motif de refus requis.", 'error');
    }
    try {
        $stmt_chk = $pdo->prepare("SELECT statut, description, titre FROM tâches WHERE id = ? AND projet_id = ?");
        $stmt_chk->execute([$task_id, $project_id]);
        $row = $stmt_chk->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            redirect_to_tasks($project_id, "Tâche introuvable.", 'error');
        }
        if ($row['statut'] !== 'terminée') {
            redirect_to_tasks($project_id, "La tâche doit être 'terminée' pour être refusée.", 'error');
        }
        // Charger historique
        $rootUploads = dirname(__DIR__) . '/uploads/tasks/' . $task_id;
        $historyFile = $rootUploads . '/history.json';
        $history = [];
        if (file_exists($historyFile)) {
            $history = json_decode(file_get_contents($historyFile), true) ?: [];
        }
        // Trouver dernière tentative terminée
        $lastAttempt = null;
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if ($history[$i]['action'] === 'terminée') {
                $lastAttempt = $history[$i];
                break;
            }
        }
        // Ajouter entrée rejet
        $timestamp = date('Ymd_His');
        $history[] = [
            'timestamp' => $timestamp,
            'action' => 'rejetée',
            'user_id' => $user_id,
            'reason' => $reason,
            'files' => $lastAttempt ? $lastAttempt['files'] : [],
            'attempt_dir' => $lastAttempt ? $lastAttempt['attempt_dir'] : null
        ];
        file_put_contents($historyFile, json_encode($history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        // Ne pas supprimer les fichiers : l'historique conserve tout
        // Revenir statut selon assignations
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM tâches_assignations WHERE tâche_id = ?");
        $stmt_count->execute([$task_id]);
        $nbAssignes = (int) $stmt_count->fetchColumn();
        $newStatus = $nbAssignes > 0 ? 'en cours' : 'a faire';
        $stmt_upd = $pdo->prepare("UPDATE tâches SET statut = ? WHERE id = ?");
        $stmt_upd->execute([$newStatus, $task_id]);
        redirect_to_tasks($project_id, "Tâche '" . htmlspecialchars($row['titre']) . "' refusée.", 'error');
    } catch (\PDOException $e) {
        error_log('Erreur rejet tâche: ' . $e->getMessage());
        redirect_to_tasks($project_id, "Erreur technique rejet.", 'error');
    }
}

// --------------------------------------------------
// --- 3. MISE A JOUR VIA AJAX (Assignation/Statut) ---
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['task_id'])) {
    $task_id = $_POST['task_id'];

    try {
        switch ($_POST['action']) {
            case 'toggle_assign':
                // Récupérer statut actuel
                $stmt_statut = $pdo->prepare("SELECT statut FROM tâches WHERE id = ? AND projet_id = ?");
                $stmt_statut->execute([$task_id, $project_id]);
                $statut_courant = $stmt_statut->fetchColumn();
                if (in_array($statut_courant, ['terminée', 'validée'])) {
                    echo json_encode(['success' => false, 'message' => 'Tâche clôturée: assignations interdites.']);
                    break;
                }

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tâches_assignations WHERE tâche_id = ? AND utilisateur_id = ?");
                $stmt->execute([$task_id, $user_id]);
                $is_assigned = $stmt->fetchColumn() > 0;

                if ($is_assigned) {
                    $stmt_update = $pdo->prepare("DELETE FROM tâches_assignations WHERE tâche_id = ? AND utilisateur_id = ?");
                    $stmt_update->execute([$task_id, $user_id]);
                    $message = "Vous avez été retiré de la tâche.";
                } else {
                    $stmt_update = $pdo->prepare("INSERT INTO tâches_assignations (tâche_id, utilisateur_id) VALUES (?, ?)");
                    $stmt_update->execute([$task_id, $user_id]);
                    $message = "Vous vous êtes assigné la tâche.";
                }
                // Recompter
                $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM tâches_assignations WHERE tâche_id = ?");
                $stmt_count->execute([$task_id]);
                $nbAssignes = (int) $stmt_count->fetchColumn();
                $nouveau_statut = $nbAssignes > 0 ? 'en cours' : 'a faire';
                $stmt_upd = $pdo->prepare("UPDATE tâches SET statut = ? WHERE id = ?");
                $stmt_upd->execute([$nouveau_statut, $task_id]);
                echo json_encode(['success' => true, 'message' => $message, 'new_status' => $nouveau_statut, 'assigne_count' => $nbAssignes]);
                break;

            case 'toggle_status':
                $new_statut = $_POST['statut'] ?? 'a faire';
                if (!in_array($new_statut, ['a faire', 'terminée', 'validée'])) {
                    echo json_encode(['success' => false, 'message' => 'Statut invalide.']);
                    break;
                }
                // Vérifier assignation si demande de terminer
                if ($new_statut === 'terminée') {
                    $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM tâches_assignations WHERE tâche_id = ? AND utilisateur_id = ?");
                    $stmt_chk->execute([$task_id, $user_id]);
                    if ($stmt_chk->fetchColumn() == 0) {
                        echo json_encode(['success' => false, 'message' => 'Vous devez être assigné pour terminer cette tâche.']);
                        break;
                    }
                }
                $stmt_update = $pdo->prepare("UPDATE tâches SET statut = ? WHERE id = ? AND projet_id = ?");
                $stmt_update->execute([$new_statut, $task_id, $project_id]);
                echo json_encode(['success' => true, 'message' => "Statut mis à jour à '{$new_statut}'.", 'new_status' => $new_statut]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Action invalide.']);
                break;
        }

    } catch (\PDOException $e) {
        http_response_code(500);
        error_log("Erreur BDD AJAX tâche: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur de base de données.']);
    }
    exit;
}

// Redirection si aucune action valide n'est trouvée
redirect_to_tasks($project_id, "Requête non reconnue.", 'error');
?>