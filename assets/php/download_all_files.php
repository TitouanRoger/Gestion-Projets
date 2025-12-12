<?php
// ============================================================
// DOWNLOAD_ALL_FILES.PHP - TÉLÉCHARGEMENT ZIP D'UNE TENTATIVE
// ============================================================
// Construit une archive ZIP contenant tous les fichiers d'une
// tentative d'upload de tâche (attempt_YYYYMMDD_HHMMSS):
// - Valide l'accès (owner, membre, ou assigné à la tâche)
// - Déchiffre les fichiers si manifest.json indique un stockage chiffré
// - Stream le ZIP en sortie avec en-têtes HTTP appropriés
// ============================================================
session_start();
require_once 'db_connect.php';
require_once 'file_crypto.php';

// Vérifier uniquement ZIP (RAR en création n'est pas supporté nativement par PHP)
$zipAvailable = class_exists('ZipArchive');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Accès refusé';
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$task_id = isset($_GET['task_id']) ? (int) $_GET['task_id'] : 0;
$attempt = $_GET['attempt'] ?? '';

if ($task_id <= 0 || !preg_match('/^attempt_\d{8}_\d{6}$/', $attempt)) {
    http_response_code(400);
    echo 'Paramètres invalides';
    exit();
}

try {
    // Vérifications d'autorisation (identiques à download_task_file.php)
    $stmt = $pdo->prepare("SELECT projet_id FROM tâches WHERE id = ?");
    $stmt->execute([$task_id]);
    $projet_id = $stmt->fetchColumn();
    if (!$projet_id) {
        http_response_code(404);
        echo 'Tâche inconnue';
        exit();
    }

    $stmt_owner = $pdo->prepare("SELECT createur_id FROM projets WHERE id = ?");
    $stmt_owner->execute([$projet_id]);
    $createur_id = $stmt_owner->fetchColumn();

    $authorized = ($user_id == $createur_id);
    if (!$authorized) {
        $stmt_mem = $pdo->prepare("SELECT COUNT(*) FROM projet_membres WHERE projet_id = ? AND utilisateur_id = ?");
        $stmt_mem->execute([$projet_id, $user_id]);
        $authorized = $stmt_mem->fetchColumn() > 0;
    }
    if (!$authorized) {
        $stmt_assign = $pdo->prepare("SELECT COUNT(*) FROM tâches_assignations WHERE tâche_id = ? AND utilisateur_id = ?");
        $stmt_assign->execute([$task_id, $user_id]);
        $authorized = $stmt_assign->fetchColumn() > 0;
    }

    if (!$authorized) {
        http_response_code(403);
        echo 'Accès non autorisé';
        exit();
    }

    $baseDir = dirname(dirname(__DIR__)) . '/assets/uploads/tasks/' . $task_id . '/' . $attempt;
    $baseDirReal = realpath($baseDir);

    if (!$baseDirReal || !is_dir($baseDirReal)) {
        http_response_code(404);
        echo 'Dossier introuvable';
        exit();
    }

    // Construire archive ZIP (exclusivement)
    $files = scandir($baseDirReal);
    $fileList = [];
    foreach ($files as $file) {
        if ($file === '.' || $file === '..')
            continue;
        $filePath = $baseDirReal . '/' . $file;
        if (is_file($filePath)) {
            $fileList[] = $file;
        }
    }

    if (empty($fileList)) {
        http_response_code(404);
        echo 'Aucun fichier à télécharger';
        exit();
    }

    if (!$zipAvailable) {
        http_response_code(500);
        echo 'ZIP indisponible.';
        exit();
    }

    $zipName = 'task_' . $task_id . '_' . $attempt . '.zip';
    $zipPath = sys_get_temp_dir() . '/' . uniqid('zip_') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        echo 'Erreur création archive ZIP';
        exit();
    }

    $manifestFile = $baseDirReal . '/manifest.json';
    $manifest = [];
    if (file_exists($manifestFile)) {
        $manifest = json_decode(file_get_contents($manifestFile), true) ?: [];
    }
    foreach ($fileList as $f) {
        $storedPath = $baseDirReal . '/' . $f;
        $entryMeta = null;
        if (!is_file($storedPath) && !empty($manifest)) {
            foreach ($manifest as $m) { if (isset($m['original']) && $m['original'] === $f) { $entryMeta = $m; break; } }
            if ($entryMeta) {
                $encPath = $baseDirReal . '/' . $entryMeta['stored'];
                if (is_file($encPath)) {
                    $cipher = file_get_contents($encPath);
                    $nonce = base64_decode($entryMeta['nonce']);
                    $tag = base64_decode($entryMeta['tag']);
                    $plain = decryptFileData($cipher, $nonce, $tag);
                    if ($plain !== false) {
                        $zip->addFromString($f, $plain);
                        continue;
                    }
                }
            }
        }
        if (is_file($storedPath)) {
            $zip->addFile($storedPath, $f);
        }
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($zipPath));
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    readfile($zipPath);
    @unlink($zipPath);
    exit();

} catch (\PDOException $e) {
    http_response_code(500);
    error_log('Erreur download all: ' . $e->getMessage());
    echo 'Erreur serveur';
    exit();
}
