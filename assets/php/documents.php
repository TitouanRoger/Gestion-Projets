<?php
// ============================================================
// DOCUMENTS.PHP - GESTION DES DOCUMENTS DE PROJET (CHIFFRÉS)
// ============================================================
// Actions: list, upload, delete, view, download, download_category
// Règles:
// - Tout membre peut voir/télécharger
// - Propriétaire: ajout/suppression partout; autres rôles: selon catégorie
// Stockage: assets/uploads/projects/{projectId}/documents/{category}/
//   - Fichiers chiffrés avec manifeste par catégorie (AES-256-GCM)
// ============================================================

declare(strict_types=1);
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/file_crypto.php';

function jsonOut($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if (!isset($_SESSION['user_id'])) jsonOut(['error' => 'Non authentifié'], 401);

$userId = (int)($_SESSION['user_id']);
$projectId = isset($_REQUEST['project_id']) ? (int)$_REQUEST['project_id'] : 0;
$action = $_REQUEST['action'] ?? '';
if ($projectId <= 0) jsonOut(['error' => 'project_id invalide'], 400);

// Vérifier owner ou membre
try {
    $stmt = $pdo->prepare('SELECT createur_id FROM projets WHERE id = ?');
    $stmt->execute([$projectId]);
    $ownerId = (int)($stmt->fetchColumn() ?: 0);
    if (!$ownerId) jsonOut(['error' => 'Projet introuvable'], 404);
    $isOwner = ($ownerId === $userId);
    $isMember = $isOwner;
    if (!$isMember) {
        $stmt2 = $pdo->prepare('SELECT 1 FROM projet_membres WHERE projet_id = ? AND utilisateur_id = ?');
        $stmt2->execute([$projectId, $userId]);
        $isMember = (bool)$stmt2->fetch();
    }
    if (!$isMember) jsonOut(['error' => 'Accès refusé'], 403);
} catch (Throwable $e) {
    jsonOut(['error' => 'Erreur BDD'], 500);
}

$categories = [
    'cahier_des_charges',
    'comites_projet',
    'guide_utilisateur',
    'rapport_activites',
    'rapports_propositions',
    'recettes',
    'reponses_techniques'
];

// Règles: rédacteur = rédacteur_technique ; recetteur = recetteur_technique
$rolePermissions = [
    'cahier_des_charges'    => ['proprietaire','redacteur','redacteur_technique'],
    'comites_projet'        => ['proprietaire','redacteur','redacteur_technique'],
    'guide_utilisateur'     => ['proprietaire','redacteur','redacteur_technique'],
    'rapport_activites'     => ['proprietaire','recetteur','recetteur_technique'],
    'rapports_propositions' => ['proprietaire','redacteur','redacteur_technique'],
    'recettes'              => ['proprietaire','recetteur','recetteur_technique'],
    'reponses_techniques'   => ['proprietaire','redacteur','redacteur_technique'],
];

$currentRole = $isOwner ? 'proprietaire' : '';
if (!$isOwner) {
    $stmtR = $pdo->prepare('SELECT role FROM projet_membres WHERE projet_id = ? AND utilisateur_id = ?');
    $stmtR->execute([$projectId, $userId]);
    $currentRole = (string)($stmtR->fetchColumn() ?: '');
}

$storageBase = realpath(__DIR__ . '/../uploads/projects');
if ($storageBase === false) {
    $basePath = __DIR__ . '/../uploads/projects';
    if (!@mkdir($basePath, 0775, true)) jsonOut(['error' => 'Stockage indisponible'], 500);
    $storageBase = realpath($basePath);
}
$projRoot = $storageBase . DIRECTORY_SEPARATOR . $projectId . DIRECTORY_SEPARATOR . 'documents';
if (!is_dir($projRoot)) @mkdir($projRoot, 0775, true);

function ensureCategory(string $root, string $cat): string {
    $path = $root . DIRECTORY_SEPARATOR . $cat;
    if (!is_dir($path)) @mkdir($path, 0775, true);
    return realpath($path) ?: $path;
}

function canManage(string $role, string $category, array $rolePermissions): bool {
    if ($role === 'proprietaire') return true;
    $allowed = $rolePermissions[$category] ?? [];
    return in_array($role, $allowed, true);
}

// LISTE
if ($action === 'list') {
    $out = [];
    foreach ($categories as $cat) {
        $catPath = ensureCategory($projRoot, $cat);
        $manifestFile = $catPath . DIRECTORY_SEPARATOR . 'manifest.json';
        $manifest = is_file($manifestFile) ? (json_decode(@file_get_contents($manifestFile), true) ?: []) : [];
        $listed = [];
        foreach ($manifest as $m) {
            if (!isset($m['original'], $m['stored'])) continue;
            $storedPath = $catPath . DIRECTORY_SEPARATOR . $m['stored'];
            $size = isset($m['size']) ? (int)$m['size'] : (is_file($storedPath) ? filesize($storedPath) : 0);
            $mtime = isset($m['mtime']) ? (int)$m['mtime'] : (is_file($storedPath) ? filemtime($storedPath) : time());
            $listed[$m['original']] = ['name'=>$m['original'],'size'=>$size,'mtime'=>$mtime];
        }
        $files = @scandir($catPath) ?: [];
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || $f === 'manifest.json') continue;
            $isStoredEnc = false;
            foreach ($manifest as $m) { if (($m['stored'] ?? '') === $f) { $isStoredEnc = true; break; } }
            if ($isStoredEnc) continue;
            $full = $catPath . DIRECTORY_SEPARATOR . $f;
            if (is_file($full) && !isset($listed[$f])) {
                $listed[$f] = ['name'=>$f,'size'=>filesize($full),'mtime'=>filemtime($full)];
            }
        }
        $out[$cat] = array_values($listed);
    }
    jsonOut(['status' => 'OK', 'categories' => $categories, 'files' => $out]);
}

// UPLOAD (toujours chiffré)
if ($action === 'upload') {
    $category = $_POST['category'] ?? '';
    if (!in_array($category, $categories, true)) jsonOut(['error' => 'Catégorie invalide'], 400);
    if (!canManage($currentRole, $category, $rolePermissions)) jsonOut(['error' => 'Permission refusée'], 403);
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) jsonOut(['error' => 'Upload invalide'], 400);
    $catPath = ensureCategory($projRoot, $category);
    $orig = basename($_FILES['file']['name']);
    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $orig);
    $data = @file_get_contents($_FILES['file']['tmp_name']);
    if ($data === false) jsonOut(['error' => 'Lecture upload échouée'], 500);
    $nonce = $tag = '';
    $cipher = encryptFileData($data, $nonce, $tag);
    if ($cipher === false) jsonOut(['error' => 'Chiffrement échoué'], 500);
    $stored = 'enc_' . bin2hex(random_bytes(10)) . '.bin';
    if (@file_put_contents($catPath . DIRECTORY_SEPARATOR . $stored, $cipher) === false) jsonOut(['error' => 'Écriture échouée'], 500);
    $manifestFile = $catPath . DIRECTORY_SEPARATOR . 'manifest.json';
    $manifest = is_file($manifestFile) ? (json_decode(@file_get_contents($manifestFile), true) ?: []) : [];
    $manifest[] = [
        'original' => $safe,
        'stored'   => $stored,
        'nonce'    => base64_encode($nonce),
        'tag'      => base64_encode($tag),
        'size'     => strlen($data),
        'mtime'    => time(),
    ];
    @file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    jsonOut(['status' => 'OK', 'saved' => $safe]);
}

// DELETE
if ($action === 'delete') {
    $category = $_POST['category'] ?? '';
    $name = $_POST['name'] ?? '';
    if (!in_array($category, $categories, true)) jsonOut(['error' => 'Catégorie invalide'], 400);
    if (!canManage($currentRole, $category, $rolePermissions)) jsonOut(['error' => 'Permission refusée'], 403);
    if ($name === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $name)) jsonOut(['error' => 'Nom invalide'], 400);
    $catPath = ensureCategory($projRoot, $category);
    $manifestFile = $catPath . DIRECTORY_SEPARATOR . 'manifest.json';
    $manifest = is_file($manifestFile) ? (json_decode(@file_get_contents($manifestFile), true) ?: []) : [];
    $idx = -1; $stored = null;
    foreach ($manifest as $i => $m) {
        if (($m['original'] ?? '') === $name) { $idx = $i; $stored = $m['stored'] ?? null; break; }
    }
    if ($idx >= 0 && $stored) {
        $encPath = realpath($catPath . DIRECTORY_SEPARATOR . $stored);
        if ($encPath && strpos($encPath, $catPath) === 0 && is_file($encPath)) { @unlink($encPath); }
        array_splice($manifest, $idx, 1);
        @file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        jsonOut(['status' => 'OK', 'deleted' => $name]);
    }
    // Legacy clair
    $plain = realpath($catPath . DIRECTORY_SEPARATOR . $name);
    if (!$plain || strpos($plain, $catPath) !== 0 || !is_file($plain)) jsonOut(['error' => 'Fichier introuvable'], 404);
    if (!@unlink($plain)) jsonOut(['error' => 'Suppression échouée'], 500);
    jsonOut(['status' => 'OK', 'deleted' => $name]);
}

// VIEW (inline)
if ($action === 'view') {
    $category = $_GET['category'] ?? '';
    $name = $_GET['name'] ?? '';
    if (!in_array($category, $categories, true)) { http_response_code(400); echo 'Catégorie invalide'; exit(); }
    if ($name === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $name)) { http_response_code(400); echo 'Nom invalide'; exit(); }
    $catPath = ensureCategory($projRoot, $category);
    $manifestFile = $catPath . DIRECTORY_SEPARATOR . 'manifest.json';
    $manifest = is_file($manifestFile) ? (json_decode(@file_get_contents($manifestFile), true) ?: []) : [];
    $entry = null;
    foreach ($manifest as $m) { if (($m['original'] ?? '') === $name) { $entry = $m; break; } }
    $dataOut = null;
    if ($entry) {
        $encPath = realpath($catPath . DIRECTORY_SEPARATOR . $entry['stored']);
        if (!$encPath || strpos($encPath, $catPath) !== 0 || !is_file($encPath)) { http_response_code(404); echo 'Fichier introuvable'; exit(); }
        $cipher = file_get_contents($encPath);
        $nonce = base64_decode($entry['nonce']);
        $tag = base64_decode($entry['tag']);
        $plain = decryptFileData($cipher, $nonce, $tag);
        if ($plain === false) { http_response_code(500); echo 'Erreur déchiffrement'; exit(); }
        $dataOut = $plain;
    } else {
        $file = realpath($catPath . DIRECTORY_SEPARATOR . $name);
        if (!$file || strpos($file, $catPath) !== 0 || !is_file($file)) { http_response_code(404); echo 'Fichier introuvable'; exit(); }
        $dataOut = file_get_contents($file);
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $map = [
        'pdf'=>'application/pdf','txt'=>'text/plain; charset=utf-8','log'=>'text/plain; charset=utf-8','md'=>'text/plain; charset=utf-8','csv'=>'text/csv; charset=utf-8',
        'png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml'
    ];
    $mime = $map[$ext] ?? 'application/octet-stream';
    header('Content-Type: '.$mime);
    header('Content-Length: '.strlen($dataOut));
    header('Content-Disposition: inline; filename="'.basename($name).'"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo $dataOut; exit();
}

// DOWNLOAD (attachment)
if ($action === 'download') {
    $category = $_GET['category'] ?? '';
    $name = $_GET['name'] ?? '';
    if (!in_array($category, $categories, true)) { http_response_code(400); echo 'Catégorie invalide'; exit(); }
    if ($name === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $name)) { http_response_code(400); echo 'Nom invalide'; exit(); }
    $catPath = ensureCategory($projRoot, $category);
    $manifestFile = $catPath . DIRECTORY_SEPARATOR . 'manifest.json';
    $manifest = is_file($manifestFile) ? (json_decode(@file_get_contents($manifestFile), true) ?: []) : [];
    $entry = null;
    foreach ($manifest as $m) { if (($m['original'] ?? '') === $name) { $entry = $m; break; } }
    $dataOut = null;
    if ($entry) {
        $encPath = realpath($catPath . DIRECTORY_SEPARATOR . $entry['stored']);
        if (!$encPath || strpos($encPath, $catPath) !== 0 || !is_file($encPath)) { http_response_code(404); echo 'Fichier introuvable'; exit(); }
        $cipher = file_get_contents($encPath);
        $nonce = base64_decode($entry['nonce']);
        $tag = base64_decode($entry['tag']);
        $plain = decryptFileData($cipher, $nonce, $tag);
        if ($plain === false) { http_response_code(500); echo 'Erreur déchiffrement'; exit(); }
        $dataOut = $plain;
    } else {
        $file = realpath($catPath . DIRECTORY_SEPARATOR . $name);
        if (!$file || strpos($file, $catPath) !== 0 || !is_file($file)) { http_response_code(404); echo 'Fichier introuvable'; exit(); }
        $dataOut = file_get_contents($file);
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = 'application/octet-stream';
    $map = ['pdf'=>'application/pdf','txt'=>'text/plain','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','zip'=>'application/zip','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
    if (isset($map[$ext])) $mime = $map[$ext];
    header('Content-Type: '.$mime);
    header('Content-Length: '.strlen($dataOut));
    header('Content-Disposition: attachment; filename="'.basename($name).'"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo $dataOut; exit();
}

// ZIP par catégorie (décrypté)
if ($action === 'download_category') {
    $category = $_GET['category'] ?? '';
    if (!in_array($category, $categories, true)) { http_response_code(400); echo 'Catégorie invalide'; exit(); }
    $catPath = ensureCategory($projRoot, $category);
    $manifestFile = $catPath . DIRECTORY_SEPARATOR . 'manifest.json';
    $manifest = is_file($manifestFile) ? (json_decode(@file_get_contents($manifestFile), true) ?: []) : [];
    $logical = [];
    foreach ($manifest as $m) {
        if (!isset($m['original'], $m['stored'])) continue;
        $logical[] = ['type'=>'enc','original'=>$m['original'],'stored'=>$m['stored'],'nonce'=>$m['nonce'],'tag'=>$m['tag']];
    }
    $files = @scandir($catPath) ?: [];
    foreach ($files as $f) {
        if ($f === '.' || $f === '..' || $f === 'manifest.json') continue;
        $isStoredEnc = false;
        foreach ($manifest as $m) { if (($m['stored'] ?? '') === $f) { $isStoredEnc = true; break; } }
        if ($isStoredEnc) continue;
        $full = realpath($catPath . DIRECTORY_SEPARATOR . $f);
        if ($full && strpos($full, $catPath) === 0 && is_file($full)) {
            $logical[] = ['type'=>'plain','original'=>$f,'path'=>$full];
        }
    }
    if (empty($logical)) { http_response_code(404); echo 'Aucun fichier à télécharger'; exit(); }
    if (!class_exists('ZipArchive')) { http_response_code(500); echo 'ZipArchive indisponible'; exit(); }
    $tmp = tempnam(sys_get_temp_dir(), 'doczip_');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) { http_response_code(500); echo 'Création ZIP impossible'; exit(); }
    foreach ($logical as $item) {
        if ($item['type'] === 'enc') {
            $encPath = realpath($catPath . DIRECTORY_SEPARATOR . $item['stored']);
            if ($encPath && strpos($encPath, $catPath) === 0 && is_file($encPath)) {
                $cipher = file_get_contents($encPath);
                $nonce = base64_decode($item['nonce']);
                $tag = base64_decode($item['tag']);
                $plain = decryptFileData($cipher, $nonce, $tag);
                if ($plain !== false) { $zip->addFromString($item['original'], $plain); }
            }
        } else {
            $zip->addFile($item['path'], $item['original']);
        }
    }
    $zip->close();
    $fname = 'projet-' . $projectId . '-' . $category . '-' . date('Ymd-His') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($tmp));
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    readfile($tmp);
    @unlink($tmp);
    exit();
}

jsonOut(['error' => 'Action inconnue'], 400);
