<?php
// ============================================================
// DOWNLOAD_TASK_FILE.PHP - TÉLÉCHARGEMENT D'UN FICHIER DE TÂCHE
// ============================================================
// Télécharge un fichier précis d'une tentative d'upload:
// - Valide les paramètres (task_id, attempt, file)
// - Autorisations: owner, membre du projet ou assigné à la tâche
// - Si manifest indique chiffrement: déchiffre avant l'envoi
// ============================================================
session_start();
require 'db_connect.php';
require_once 'file_crypto.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Accès refusé';
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$task_id = isset($_GET['task_id']) ? (int) $_GET['task_id'] : 0;
$attempt = $_GET['attempt'] ?? '';
$file = $_GET['file'] ?? '';

if ($task_id <= 0 || !preg_match('/^attempt_\d{8}_\d{6}$/', $attempt) || !preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
    http_response_code(400);
    echo 'Paramètres invalides';
    exit();
}

try {
    // Récupérer projet_id et createur
    $stmt = $pdo->prepare("SELECT projet_id FROM tâches WHERE id = ?");
    $stmt->execute([$task_id]);
    $projet_id = $stmt->fetchColumn();
    if (!$projet_id) {
        http_response_code(404);
        echo 'Tâche inconnue';
        exit();
    }
    // Vérifier propriétaire
    $stmt_owner = $pdo->prepare("SELECT createur_id FROM projets WHERE id = ?");
    $stmt_owner->execute([$projet_id]);
    $createur_id = $stmt_owner->fetchColumn();

    $authorized = ($user_id == $createur_id);
    if (!$authorized) {
        // Vérifier membre du projet
        $stmt_mem = $pdo->prepare("SELECT COUNT(*) FROM projet_membres WHERE projet_id = ? AND utilisateur_id = ?");
        $stmt_mem->execute([$projet_id, $user_id]);
        $authorized = $stmt_mem->fetchColumn() > 0;
    }
    if (!$authorized) {
        // Vérifier assigné à la tâche
        $stmt_assign = $pdo->prepare("SELECT COUNT(*) FROM tâches_assignations WHERE tâche_id = ? AND utilisateur_id = ?");
        $stmt_assign->execute([$task_id, $user_id]);
        $authorized = $stmt_assign->fetchColumn() > 0;
    }

    if (!$authorized) {
        http_response_code(403);
        echo 'Accès non autorisé';
        exit();
    }

    // Remonter de 2 niveaux depuis assets/php/ pour atteindre racine du projet
    $baseDir = dirname(dirname(__DIR__)) . '/assets/uploads/tasks/' . $task_id . '/' . $attempt;
    $expectedBase = realpath($baseDir);
    $plainPath = $expectedBase ? realpath($baseDir . '/' . $file) : false;
    $isEncrypted = false;
    $dataOut = null;
    if ($plainPath && strpos($plainPath, $expectedBase) === 0 && is_file($plainPath)) {
        $dataOut = file_get_contents($plainPath);
    } else {
        $manifestFile = $baseDir . '/manifest.json';
        if (!file_exists($manifestFile)) {
            http_response_code(404);
            echo 'Fichier introuvable';
            exit();
        }
        $manifest = json_decode(file_get_contents($manifestFile), true) ?: [];
        $entry = null;
        foreach ($manifest as $m) {
            if (isset($m['original']) && $m['original'] === $file) { $entry = $m; break; }
        }
        if (!$entry) {
            http_response_code(404);
            echo 'Fichier introuvable';
            exit();
        }
        $encPath = realpath($baseDir . '/' . $entry['stored']);
        if (!$encPath || strpos($encPath, $expectedBase) !== 0 || !is_file($encPath)) {
            http_response_code(404);
            echo 'Fichier introuvable';
            exit();
        }
        $cipher = file_get_contents($encPath);
        $nonce = base64_decode($entry['nonce']);
        $tag = base64_decode($entry['tag']);
        $plain = decryptFileData($cipher, $nonce, $tag);
        if ($plain === false) {
            http_response_code(500);
            echo 'Erreur déchiffrement';
            exit();
        }
        $dataOut = $plain;
        $isEncrypted = true;
    }

    // Détermination type MIME simple
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = 'application/octet-stream';
    $mimeMap = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'zip' => 'application/zip'
    ];
    if (isset($mimeMap[$ext])) {
        $mime = $mimeMap[$ext];
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($dataOut));
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    echo $dataOut;
    exit();

} catch (\PDOException $e) {
    http_response_code(500);
    error_log('Erreur download file: ' . $e->getMessage());
    echo 'Erreur serveur';
    exit();
}
