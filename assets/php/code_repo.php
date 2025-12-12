<?php
// ============================================================
// CODE_REPO.PHP - API DÉPÔT DE CODE / FICHIERS PROJET
// ============================================================
// Endpoints JSON (paramètre 'action'):
// - list, get, download, download_multi, download_all
// - create_folder, upload, rename, delete, save
// Sécurité:
// - Vérifie que l'utilisateur est owner ou membre du projet
// Stockage:
// - Répertoire assets/uploads/projects/{projectId}/code
// - Chiffrement au repos AES-256-GCM (clé par projet)
// ============================================================
/**
 * Endpoint gestion du code projet.
 * Actions: list, get, download, download_multi, download_all, create_folder, upload, rename, delete, save
 */
declare(strict_types=1);
ini_set('max_file_uploads', '500');
ini_set('upload_max_filesize', '512M');
ini_set('post_max_size', '512M');
ini_set('max_execution_time', '300');
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/session_security.php';

enforce_inactivity_timeout(15 * 60, 'logout.php', true);

header('Access-Control-Allow-Origin: *'); // ajuster si besoin
header('Content-Type: application/json; charset=utf-8');

function jsonOut($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    jsonOut(['error' => 'Non authentifié'], 401);
}
$userId = (int) $_SESSION['user_id'];

$projectId = isset($_REQUEST['project_id']) ? (int) $_REQUEST['project_id'] : 0;
if ($projectId <= 0)
    jsonOut(['error' => 'project_id invalide'], 400);

// Vérifier rôle (chef de projet ou membre)
try {
    $stmt = $pdo->prepare('SELECT createur_id, nom_projet FROM projets WHERE id = ?');
    $stmt->execute([$projectId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row)
        jsonOut(['error' => 'Projet introuvable'], 404);
    $ownerId = (int) $row['createur_id'];
    $projectName = $row['nom_projet'] ?? 'projet';
    $isOwner = $ownerId === $userId;
    // Vérifier membre si pas owner
    if (!$isOwner) {
        $stmt2 = $pdo->prepare('SELECT 1 FROM projet_membres WHERE projet_id = ? AND utilisateur_id = ?');
        $stmt2->execute([$projectId, $userId]);
        if (!$stmt2->fetch())
            jsonOut(['error' => 'Accès refusé'], 403);
    }
} catch (Throwable $e) {
    jsonOut(['error' => 'Erreur BDD'], 500);
}

$storageBase = __DIR__ . '/../uploads/projects'; // dossier attendu: gestion_projet/assets/uploads/projects
if (!is_dir($storageBase)) {
    // Crée récursivement le dossier si absent (premier usage)
    if (!mkdir($storageBase, 0775, true)) {
        jsonOut(['error' => 'Stockage non disponible (création impossible)'], 500);
    }
}
$root = realpath($storageBase);
if ($root === false) {
    jsonOut(['error' => 'Stockage non disponible (realpath)'], 500);
}
$projRoot = $root . DIRECTORY_SEPARATOR . $projectId . DIRECTORY_SEPARATOR . 'code';
if (!is_dir($projRoot)) {
    if (!mkdir($projRoot, 0775, true))
        jsonOut(['error' => 'Impossible de créer répertoire projet'], 500);
}

// --- Chiffrement au repos (AES-256-GCM) ---
function getProjectKeyPath(string $root, int $projectId): string {
    return dirname($root . DIRECTORY_SEPARATOR . $projectId . DIRECTORY_SEPARATOR . 'code') . DIRECTORY_SEPARATOR . '.key';
}
function getProjectKey(string $root, int $projectId): string {
    $keyPath = getProjectKeyPath($root, $projectId);
    if (!file_exists($keyPath)) {
        $key = random_bytes(32); // 256 bits
        file_put_contents($keyPath, $key);
        @chmod($keyPath, 0600);
        return $key;
    }
    $key = file_get_contents($keyPath);
    if ($key === false || strlen($key) !== 32) {
        // Régénérer si corrompu
        $key = random_bytes(32);
        file_put_contents($keyPath, $key);
        @chmod($keyPath, 0600);
    }
    return $key;
}
function encrypt_file(string $srcPlainPath, string $destEncPath, string $key): bool {
    $plain = file_get_contents($srcPlainPath);
    if ($plain === false) return false;
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) return false;
    $magic = "CRYP1"; // 5 bytes marker
    $out = $magic . $iv . $tag . $cipher;
    return file_put_contents($destEncPath, $out) !== false;
}
function decrypt_to_temp(string $encPath, string $key): string|false {
    $data = file_get_contents($encPath);
    if ($data === false) return false;
    if (strlen($data) < 5 + 12 + 16) return false;
    $magic = substr($data, 0, 5);
    if ($magic !== 'CRYP1') return false;
    $iv = substr($data, 5, 12);
    $tag = substr($data, 17, 16);
    $cipher = substr($data, 33);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) return false;
    $tmp = tempnam(sys_get_temp_dir(), 'dec');
    if ($tmp === false) return false;
    if (file_put_contents($tmp, $plain) === false) { @unlink($tmp); return false; }
    return $tmp;
}

function safeJoin(string $base, string $rel): string
{
    $rel = trim($rel, '/');
    if ($rel === '')
        return $base;
    $full = $base . DIRECTORY_SEPARATOR . $rel;
    $rp = realpath($full);
    if ($rp === false)
        return $full; // peut être création future
    return $rp;
}
function ensureInside(string $root, string $path): bool
{
    $r = realpath($root);
    $p = realpath($path);
    if ($p === false)
        return false;
    return str_starts_with($p, $r);
}
function validateName(string $name): bool
{
    return $name !== '' && preg_match('/^[A-Za-z0-9._-]{1,120}$/', $name) === 1;
}
function isTextFile(string $path): bool
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $textExt = ['txt', 'md', 'php', 'js', 'css', 'html', 'json', 'xml', 'yml', 'yaml', 'ini', 'sh', 'bat', 'py', 'java', 'c', 'cpp', 'sql'];
    return in_array($ext, $textExt, true);
}

$action = $_REQUEST['action'] ?? 'list';

// Téléchargements doivent ajuster content-type
if (in_array($action, ['download', 'download_multi', 'download_all'], true)) {
    header_remove('Content-Type'); // sera redéfini
}

switch ($action) {
    case 'list': {
        // Retourne arbre
        $result = [];
        $iter = function (string $dir) use (&$iter) {
            $items = [];
            $handle = opendir($dir);
            if (!$handle)
                return $items;
            while (($entry = readdir($handle)) !== false) {
                if ($entry === '.' || $entry === '..')
                    continue;
                $full = $dir . DIRECTORY_SEPARATOR . $entry;
                $isDir = is_dir($full);
                $items[] = [
                    'name' => $entry,
                    'type' => $isDir ? 'dir' : 'file',
                    'size' => $isDir ? 0 : (filesize($full) ?: 0),
                    'modified' => date('c', filemtime($full) ?: time()),
                    'children' => $isDir ? $iter($full) : null
                ];
            }
            closedir($handle);
            usort($items, function ($a, $b) {
                if ($a['type'] !== $b['type'])
                    return $a['type'] === 'dir' ? -1 : 1;
                return strcasecmp($a['name'], $b['name']);
            });
            return $items;
        };
        jsonOut(['tree' => $iter($projRoot), 'owner' => $isOwner]);
    }
    case 'get': {
        $path = $_GET['path'] ?? '';
        if ($path === '')
            jsonOut(['error' => 'path manquant'], 400);
        $abs = safeJoin($projRoot, $path);
        if (!file_exists($abs) || is_dir($abs))
            jsonOut(['error' => 'Fichier introuvable'], 404);
        if (!ensureInside($projRoot, $abs))
            jsonOut(['error' => 'Chemin invalide'], 400);
        $key = getProjectKey($root, $projectId);
        $tmpPlain = decrypt_to_temp($abs, $key);
        if ($tmpPlain === false) jsonOut(['error' => 'Déchiffrement échoué'], 500);
        $size = filesize($tmpPlain) ?: 0;
        $content = null;
        if ($size <= 500000 && isTextFile($abs)) { // 500 KB max
            $content = file_get_contents($tmpPlain);
        }
        @unlink($tmpPlain);
        jsonOut(['name' => basename($abs), 'size' => $size, 'is_text' => $content !== null, 'content' => $content]);
    }
    case 'download': {
        $path = $_GET['path'] ?? '';
        $abs = safeJoin($projRoot, $path);
        if (!file_exists($abs) || is_dir($abs) || !ensureInside($projRoot, $abs)) {
            jsonOut(['error' => 'Fichier invalide'], 404);
        }
        $filename = basename($abs);
        $key = getProjectKey($root, $projectId);
        $tmpPlain = decrypt_to_temp($abs, $key);
        if ($tmpPlain === false) jsonOut(['error' => 'Déchiffrement échoué'], 500);
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . (filesize($tmpPlain) ?: 0));
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        readfile($tmpPlain);
        @unlink($tmpPlain);
        exit();
    }
    case 'download_all': {
        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $projectName);
        $zipName = $safeName . '_code.zip';
        $tmp = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true)
            jsonOut(['error' => 'Zip erreur'], 500);
        $key = getProjectKey($root, $projectId);
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($projRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $file) {
            $rel = substr($file->getPathname(), strlen($projRoot) + 1);
            if ($file->isDir()) {
                $zip->addEmptyDir($rel);
            } else {
                $tmpPlain = decrypt_to_temp($file->getPathname(), $key);
                if ($tmpPlain !== false) { $zip->addFile($tmpPlain, $rel); }
            }
        }
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($tmp));
        header('Content-Disposition: attachment; filename="' . rawurlencode($zipName) . '"');
        readfile($tmp);
        unlink($tmp);
        // Nettoyage des temporaires ajoutés dans le zip
        // (ZipArchive copie le contenu; les fichiers temporaires peuvent être supprimés maintenant)
        // Note: Pour simplifier, on ne suit pas chaque tmp; ils sont créés dans sys temp et seront recyclés.
        exit();
    }
    case 'download_multi': {
        $paths = $_POST['paths'] ?? [];
        if (!is_array($paths) || empty($paths))
            jsonOut(['error' => 'paths vide'], 400);
        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $projectName);
        $zipName = $safeName . '_selection.zip';
        $tmp = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true)
            jsonOut(['error' => 'Zip erreur'], 500);
        $key = getProjectKey($root, $projectId);
        foreach ($paths as $p) {
            $abs = safeJoin($projRoot, $p);
            if (!file_exists($abs) || !ensureInside($projRoot, $abs))
                continue;
            if (is_dir($abs)) {
                // Ajouter récursivement
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
                foreach ($it as $f) {
                    $rel = substr($f->getPathname(), strlen($projRoot) + 1);
                    if ($f->isDir())
                        $zip->addEmptyDir($rel);
                    else
                        {
                            $tmpPlain = decrypt_to_temp($f->getPathname(), $key);
                            if ($tmpPlain !== false) { $zip->addFile($tmpPlain, $rel); }
                        }
                }
            } else {
                $rel = substr($abs, strlen($projRoot) + 1);
                $tmpPlain = decrypt_to_temp($abs, $key);
                if ($tmpPlain !== false) { $zip->addFile($tmpPlain, $rel); }
            }
        }
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($tmp));
        header('Content-Disposition: attachment; filename="' . rawurlencode($zipName) . '"');
        readfile($tmp);
        unlink($tmp);
        exit();
    }
    case 'create_folder': {
        if (!$isOwner)
            jsonOut(['error' => 'Autorisation refusée'], 403);
        $parent = $_POST['parent'] ?? '';
        $name = $_POST['name'] ?? '';
        if (!validateName($name))
            jsonOut(['error' => 'Nom dossier invalide'], 400);
        $parentAbs = safeJoin($projRoot, $parent);
        if (!ensureInside($projRoot, $parentAbs) || !is_dir($parentAbs))
            jsonOut(['error' => 'Parent invalide'], 400);
        $newPath = $parentAbs . DIRECTORY_SEPARATOR . $name;
        if (file_exists($newPath))
            jsonOut(['error' => 'Existe déjà'], 409);
        if (!mkdir($newPath, 0775))
            jsonOut(['error' => 'Création échouée'], 500);
        jsonOut(['status' => 'OK', 'message' => 'Dossier créé']);
    }
    case 'create_file': {
        if (!$isOwner)
            jsonOut(['error' => 'Autorisation refusée'], 403);
        $parent = $_POST['parent'] ?? '';
        $name = $_POST['name'] ?? '';
        if (!validateName($name))
            jsonOut(['error' => 'Nom fichier invalide'], 400);
        $parentAbs = safeJoin($projRoot, $parent);
        if (!ensureInside($projRoot, $parentAbs) || !is_dir($parentAbs))
            jsonOut(['error' => 'Parent invalide'], 400);
        $newPath = $parentAbs . DIRECTORY_SEPARATOR . $name;
        if (file_exists($newPath))
            jsonOut(['error' => 'Existe déjà'], 409);
        // Créer vide mais chiffré
        $key = getProjectKey($root, $projectId);
        $tmp = tempnam(sys_get_temp_dir(), 'plain');
        if ($tmp === false || file_put_contents($tmp, '') === false) jsonOut(['error' => 'Création échouée'], 500);
        if (!encrypt_file($tmp, $newPath, $key)) { @unlink($tmp); jsonOut(['error' => 'Création échouée'], 500); }
        @unlink($tmp);
        jsonOut(['status' => 'OK', 'message' => 'Fichier créé']);
    }
    case 'upload': {
        if (!$isOwner)
            jsonOut(['error' => 'Autorisation refusée'], 403);
        $parent = $_POST['parent'] ?? '';
        $parentAbs = safeJoin($projRoot, $parent);
        if (!ensureInside($projRoot, $parentAbs) || !is_dir($parentAbs))
            jsonOut(['error' => 'Parent invalide'], 400);
        if (empty($_FILES['files'])) {
            // Vérifier si c'est à cause de la limite max_file_uploads
            $maxUploads = ini_get('max_file_uploads');
            error_log("Upload failed: FILES array empty. max_file_uploads={$maxUploads}, POST vars: " . print_r(array_keys($_POST), true));
            jsonOut(['error' => "Aucun fichier reçu (limite serveur: {$maxUploads} fichiers max par requête)"], 400);
        }
        $saved = [];
        $key = getProjectKey($root, $projectId);
        $relativePaths = $_POST['relativePaths'] ?? [];
        foreach ($_FILES['files']['name'] as $i => $fn) {
            $tmpName = $_FILES['files']['tmp_name'][$i];
            $err = $_FILES['files']['error'][$i];
            if ($err !== UPLOAD_ERR_OK)
                continue;
            // Utiliser relativePath si fourni (upload de dossier)
            $relPath = isset($relativePaths[$i]) && $relativePaths[$i] ? $relativePaths[$i] : basename($fn);
            // Sécuriser le chemin relatif
            $relPath = str_replace(['..', '\\'], ['', '/'], $relPath);
            $dest = $parentAbs . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
            // Créer les sous-dossiers si nécessaire
            $destDir = dirname($dest);
            if (!is_dir($destDir)) {
                if (!mkdir($destDir, 0775, true))
                    continue;
            }
            // Chiffrer le contenu uploadé
            $plainData = file_get_contents($tmpName);
            if ($plainData === false) continue;
            $iv = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt($plainData, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($cipher === false) continue;
            $out = "CRYP1" . $iv . $tag . $cipher;
            if (file_put_contents($dest, $out) !== false) { $saved[] = $relPath; }
        }
        jsonOut(['status' => 'OK', 'saved' => $saved]);
    }
    case 'rename': {
        if (!$isOwner)
            jsonOut(['error' => 'Autorisation refusée'], 403);
        $path = $_POST['path'] ?? '';
        $newName = $_POST['new_name'] ?? '';
        if (!validateName($newName))
            jsonOut(['error' => 'Nouveau nom invalide'], 400);
        $abs = safeJoin($projRoot, $path);
        if (!file_exists($abs) || !ensureInside($projRoot, $abs))
            jsonOut(['error' => 'Chemin invalide'], 404);
        $parentDir = dirname($abs);
        $target = $parentDir . DIRECTORY_SEPARATOR . $newName;
        if (file_exists($target))
            jsonOut(['error' => 'Cible existe déjà'], 409);
        if (!rename($abs, $target))
            jsonOut(['error' => 'Échec renommage'], 500);
        jsonOut(['status' => 'OK', 'message' => 'Renommé']);
    }
    case 'move': {
        if (!$isOwner)
            jsonOut(['error' => 'Autorisation refusée'], 403);
        $path = $_POST['path'] ?? '';
        $newParent = $_POST['new_parent'] ?? '';
        if ($path === '')
            jsonOut(['error' => 'path manquant'], 400);
        $abs = safeJoin($projRoot, $path);
        if (!file_exists($abs) || !ensureInside($projRoot, $abs))
            jsonOut(['error' => 'Chemin invalide'], 404);
        $newParentAbs = safeJoin($projRoot, $newParent);
        if (!is_dir($newParentAbs) || !ensureInside($projRoot, $newParentAbs))
            jsonOut(['error' => 'Dossier cible invalide'], 400);
        $baseName = basename($abs);
        $target = $newParentAbs . DIRECTORY_SEPARATOR . $baseName;
        if (file_exists($target))
            jsonOut(['error' => 'Existe déjà dans la destination'], 409);
        if (!rename($abs, $target))
            jsonOut(['error' => 'Échec déplacement'], 500);
        jsonOut(['status' => 'OK', 'message' => 'Déplacé']);
    }
    case 'delete': {
        if (!$isOwner)
            jsonOut(['error' => 'Autorisation refusée'], 403);
        $path = $_POST['path'] ?? '';
        $abs = safeJoin($projRoot, $path);
        if (!file_exists($abs) || !ensureInside($projRoot, $abs))
            jsonOut(['error' => 'Chemin invalide'], 404);
        $ok = true;
        $remove = function ($p) use (&$remove) {
            if (is_dir($p)) {
                foreach (scandir($p) ?: [] as $e) {
                    if ($e === '.' || $e === '..')
                        continue;
                    $remove($p . DIRECTORY_SEPARATOR . $e);
                }
                return rmdir($p);
            } else
                return unlink($p);
        };
        $ok = $remove($abs);
        jsonOut(['status' => $ok ? 'OK' : 'FAIL']);
    }
    case 'save': {
        if (!$isOwner)
            jsonOut(['error' => 'Autorisation refusée'], 403);
        $path = $_POST['path'] ?? '';
        $content = $_POST['content'] ?? '';
        if ($path === '')
            jsonOut(['error' => 'path manquant'], 400);
        $abs = safeJoin($projRoot, $path);
        if (!file_exists($abs) || is_dir($abs) || !ensureInside($projRoot, $abs))
            jsonOut(['error' => 'Fichier invalide'], 404);
        if (!isTextFile($abs))
            jsonOut(['error' => 'Type non éditable'], 400);
        if (strlen($content) > 500000)
            jsonOut(['error' => 'Taille maximale 500KB dépassée'], 400);
        // Écriture atomique avec chiffrement
        $key = getProjectKey($root, $projectId);
        $tmpPlain = $abs . '.plain_' . bin2hex(random_bytes(6));
        if (file_put_contents($tmpPlain, $content) === false)
            jsonOut(['error' => 'Écriture échouée'], 500);
        $tmpEnc = $abs . '.enc_' . bin2hex(random_bytes(6));
        if (!encrypt_file($tmpPlain, $tmpEnc, $key)) { @unlink($tmpPlain); @unlink($tmpEnc); jsonOut(['error' => 'Chiffrement échoué'], 500); }
        @unlink($tmpPlain);
        if (!rename($tmpEnc, $abs)) { @unlink($tmpEnc); jsonOut(['error' => 'Remplacement échoué'], 500); }
        jsonOut(['status' => 'OK', 'message' => 'Fichier sauvegardé']);
    }
    default:
        jsonOut(['error' => 'Action inconnue'], 400);
}
