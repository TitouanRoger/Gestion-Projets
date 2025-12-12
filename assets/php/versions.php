<?php
// ============================================================
// VERSIONS.PHP - GESTION DES VERSIONS DE PROJET (SNAPSHOTS)
// ============================================================
// Actions:
//   list             -> liste des versions disponibles
//   create           -> crée une nouvelle version (propriétaire uniquement)
//   download_version -> télécharge un ZIP de la version demandée
//   download_code    -> télécharge un ZIP du code d'une version
//   download_documents -> télécharge un ZIP des documents d'une version
//   list_contents    -> liste les fichiers code et documents d'une version
//   view_code        -> affiche un fichier code déchiffré d'une version
//   view_document    -> affiche un document déchiffré d'une version
// Mécanique:
//   - Une version capture un instantané des documents (dossier documents/)
//     et du code (uploads/code/{projectId})
//   - Après création: les dossiers vivants documents & code sont réinitialisés (vides)
//   - Stockage: uploads/projects/{projectId}/versions/v{N}/
//     contient sous-dossiers documents/ (copie brute, conserve manifest.json & blobs)
//     + code/ (copie brute)
//   - Manifest global: uploads/projects/{projectId}/versions/manifest.json
//     [{"number":1,"created_at":1700000000,"description":"..."}, ...]
// Sécurité:
//   - list & download_version: membres + propriétaire
//   - create: uniquement propriétaire
// ============================================================

declare(strict_types=1);
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/file_crypto.php';

function jsonOut($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if (!isset($_SESSION['user_id']))
    jsonOut(['error' => 'Non authentifié'], 401);
$userId = (int) $_SESSION['user_id'];
$projectId = isset($_REQUEST['project_id']) ? (int) $_REQUEST['project_id'] : 0;
$action = $_REQUEST['action'] ?? '';
if ($projectId <= 0)
    jsonOut(['error' => 'project_id invalide'], 400);

// Vérifier propriétaire + membre
try {
    $stmt = $pdo->prepare('SELECT createur_id, nom_projet FROM projets WHERE id = ?');
    $stmt->execute([$projectId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $ownerId = (int) ($row['createur_id'] ?? 0);
    $projectName = $row['nom_projet'] ?? 'projet';
    if (!$ownerId)
        jsonOut(['error' => 'Projet introuvable'], 404);
    $isOwner = ($ownerId === $userId);
    $isMember = $isOwner;
    if (!$isMember) {
        $stmtM = $pdo->prepare('SELECT 1 FROM projet_membres WHERE projet_id = ? AND utilisateur_id = ?');
        $stmtM->execute([$projectId, $userId]);
        $isMember = (bool) $stmtM->fetch();
    }
    if (!$isMember)
        jsonOut(['error' => 'Accès refusé'], 403);
} catch (Throwable $e) {
    jsonOut(['error' => 'Erreur BDD'], 500);
}

$projectsBase = realpath(__DIR__ . '/../uploads/projects');
if ($projectsBase === false) {
    $basePath = __DIR__ . '/../uploads/projects';
    if (!@mkdir($basePath, 0775, true))
        jsonOut(['error' => 'Stockage projets indisponible'], 500);
    $projectsBase = realpath($basePath);
}
$projectRoot = $projectsBase . DIRECTORY_SEPARATOR . $projectId;
if (!is_dir($projectRoot))
    @mkdir($projectRoot, 0775, true);
$versionsRoot = $projectRoot . DIRECTORY_SEPARATOR . 'versions';
if (!is_dir($versionsRoot))
    @mkdir($versionsRoot, 0775, true);

$projectsUploads = realpath(__DIR__ . '/../uploads/projects');
if ($projectsUploads === false) {
    $tmpBase = __DIR__ . '/../uploads/projects';
    @mkdir($tmpBase, 0775, true);
    $projectsUploads = realpath($tmpBase) ?: $tmpBase;
}
$codeProjectRoot = $projectsUploads . DIRECTORY_SEPARATOR . $projectId . DIRECTORY_SEPARATOR . 'code';
if (!is_dir($codeProjectRoot))
    @mkdir($codeProjectRoot, 0775, true);
$documentsRoot = $projectRoot . DIRECTORY_SEPARATOR . 'documents';

$manifestFile = $versionsRoot . DIRECTORY_SEPARATOR . 'manifest.json';
$versionsManifest = is_file($manifestFile) ? (json_decode(@file_get_contents($manifestFile), true) ?: []) : [];

function recursiveCopy(string $src, string $dst): bool
{
    if (!is_dir($src))
        return false;
    if (!is_dir($dst) && !@mkdir($dst, 0775, true))
        return false;
    $items = @scandir($src);
    if (!$items)
        return false;
    foreach ($items as $it) {
        if ($it === '.' || $it === '..')
            continue;
        $from = $src . DIRECTORY_SEPARATOR . $it;
        $to = $dst . DIRECTORY_SEPARATOR . $it;
        if (is_dir($from)) {
            recursiveCopy($from, $to);
        } elseif (is_file($from)) {
            @copy($from, $to);
        }
    }
    return true;
}

function recursiveDelete(string $path): void
{
    if (!is_dir($path) && !is_file($path))
        return;
    if (is_file($path)) {
        @unlink($path);
        return;
    }
    $items = @scandir($path) ?: [];
    foreach ($items as $it) {
        if ($it === '.' || $it === '..')
            continue;
        $target = $path . DIRECTORY_SEPARATOR . $it;
        if (is_dir($target))
            recursiveDelete($target);
        else
            @unlink($target);
    }
    @rmdir($path);
}

if ($action === 'list') {
    $normalized = [];
    foreach ($versionsManifest as $v) {
        if (!isset($v['name'])) {
            $v['name'] = isset($v['number']) ? (string) $v['number'] : 'inconnu';
        }
        if (!isset($v['dir'])) {
            $v['dir'] = 'v' . $v['name'];
        }
        $normalized[] = $v;
    }
    // Tri naturel sur le nom (permet 1 < 1.0 < 1.0.1 etc.)
    usort($normalized, fn($a, $b) => strnatcmp($a['name'], $b['name']));
    jsonOut(['status' => 'OK', 'versions' => $normalized]);
}

if ($action === 'create') {
    if (!$isOwner)
        jsonOut(['error' => 'Seul le chef de projet peut créer une version'], 403);
    $description = trim($_POST['description'] ?? '');
    $name = trim($_POST['version_name'] ?? '');
    if ($name === '') {
        // Auto incrément si pas de nom explicite
        $next = 1;
        foreach ($versionsManifest as $v) {
            $existingName = $v['name'] ?? (isset($v['number']) ? (string) $v['number'] : null);
            if ($existingName !== null && ctype_digit($existingName)) {
                $val = (int) $existingName;
                if ($val >= $next)
                    $next = $val + 1;
            }
        }
        $name = (string) $next;
    }
    if (!preg_match('/^[A-Za-z0-9]+([._-][A-Za-z0-9]+)*$/', $name)) {
        jsonOut(['error' => 'Nom de version invalide (alphanum + . _ -)'], 400);
    }
    foreach ($versionsManifest as $v) {
        $existingName = $v['name'] ?? (isset($v['number']) ? (string) $v['number'] : null);
        if ($existingName === $name)
            jsonOut(['error' => 'Nom de version déjà utilisé'], 409);
    }
    $dirName = 'v' . $name;
    $versionDir = $versionsRoot . DIRECTORY_SEPARATOR . $dirName;
    if (!@mkdir($versionDir, 0775, true))
        jsonOut(['error' => 'Création dossier version échouée'], 500);
    // Documents: snapshot BRUT (chiffré) — copier manifest.json et blobs stockés sans déchiffrement
    if (is_dir($documentsRoot)) {
        $dstDocs = $versionDir . DIRECTORY_SEPARATOR . 'documents';
        @mkdir($dstDocs, 0775, true);
        $cats = @scandir($documentsRoot) ?: [];
        foreach ($cats as $cat) {
            if ($cat === '.' || $cat === '..')
                continue;
            $catSrc = $documentsRoot . DIRECTORY_SEPARATOR . $cat;
            if (!is_dir($catSrc))
                continue;
            $catDst = $dstDocs . DIRECTORY_SEPARATOR . $cat;
            @mkdir($catDst, 0775, true);
            // Copier manifest.json s'il existe
            $catManifestFile = $catSrc . DIRECTORY_SEPARATOR . 'manifest.json';
            if (is_file($catManifestFile)) {
                @copy($catManifestFile, $catDst . DIRECTORY_SEPARATOR . 'manifest.json');
                $manifest = json_decode(@file_get_contents($catManifestFile), true) ?: [];
                foreach ($manifest as $m) {
                    if (!isset($m['stored']))
                        continue;
                    $blob = $catSrc . DIRECTORY_SEPARATOR . $m['stored'];
                    if (is_file($blob)) {
                        @copy($blob, $catDst . DIRECTORY_SEPARATOR . $m['stored']);
                    }
                }
            }
            // Ne pas copier les fichiers legacy en clair pour conserver documents chiffrés uniquement
        }
    }
    // Code: snapshot complet depuis assets/uploads/projects/{projectId}/code
    if (is_dir($codeProjectRoot)) {
        $dstCode = $versionDir . DIRECTORY_SEPARATOR . 'code';
        @mkdir($dstCode, 0775, true);
        recursiveCopy($codeProjectRoot, $dstCode);
    }
    // Reset espaces vivants
    if (is_dir($documentsRoot))
        recursiveDelete($documentsRoot);
    @mkdir($documentsRoot, 0775, true);
    if (is_dir($codeProjectRoot)) {
        recursiveDelete($codeProjectRoot);
    }
    @mkdir($codeProjectRoot, 0775, true);
    $versionsManifest[] = ['name' => $name, 'dir' => $dirName, 'created_at' => time()];
    @file_put_contents($manifestFile, json_encode($versionsManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    jsonOut(['status' => 'OK', 'created_name' => $name]);
}

if ($action === 'download_version') {
    $reqName = trim($_GET['name'] ?? '');
    $legacyNum = isset($_GET['number']) ? (int) $_GET['number'] : 0;
    $targetDir = '';
    $displayName = '';
    if ($reqName !== '') {
        foreach ($versionsManifest as $v) {
            $vName = $v['name'] ?? (isset($v['number']) ? (string) $v['number'] : null);
            $vDir = $v['dir'] ?? ('v' . $vName);
            if ($vName === $reqName) {
                $targetDir = $versionsRoot . DIRECTORY_SEPARATOR . $vDir;
                $displayName = $vName;
                break;
            }
        }
        if ($targetDir === '') {
            http_response_code(404);
            echo 'Version non trouvée';
            exit();
        }
    } else {
        if ($legacyNum <= 0) {
            http_response_code(400);
            echo 'Paramètre version manquant';
            exit();
        }
        $targetDir = $versionsRoot . DIRECTORY_SEPARATOR . 'v' . $legacyNum;
        $displayName = (string) $legacyNum;
        if (!is_dir($targetDir)) {
            http_response_code(404);
            echo 'Version introuvable';
            exit();
        }
    }
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        echo 'ZipArchive indisponible';
        exit();
    }
    $tmp = tempnam(sys_get_temp_dir(), 'verzip_');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        echo 'Création ZIP impossible';
        exit();
    }
    // Fonction de déchiffrement CRYP1 (code) et documents via manifest
    $decryptCryp1 = function (string $encPath, int $projectId) {
        $keyFile = realpath(__DIR__ . '/../uploads/projects/' . $projectId . '/.key');
        if ($keyFile === false)
            return false;
        $key = @file_get_contents($keyFile);
        if ($key === false || strlen($key) !== 32)
            return false;
        $data = @file_get_contents($encPath);
        if ($data === false || strlen($data) < 33)
            return false;
        if (substr($data, 0, 5) !== 'CRYP1')
            return false;
        $iv = substr($data, 5, 12);
        $tag = substr($data, 17, 16);
        $cipher = substr($data, 33);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false)
            return false;
        $tmp = tempnam(sys_get_temp_dir(), 'verdec_');
        if ($tmp === false)
            return false;
        if (@file_put_contents($tmp, $plain) === false) {
            @unlink($tmp);
            return false;
        }
        return $tmp;
    };
    // Ajout récursif avec déchiffrement approprié
    $addRecursive = function (string $base, string $rel = '') use (&$addRecursive, $zip, $projectId, $decryptCryp1) {
        $dir = $base . ($rel ? DIRECTORY_SEPARATOR . $rel : '');
        // Cas spécial documents: déchiffrer via manifest.json et ajouter fichiers originaux
        if (preg_match('#(^|/)documents(/|$)#', $rel)) {
            // On attend structure: documents/<categorie>/
            // Si un manifest.json existe dans ce dossier, utiliser pour ajouter les fichiers
            $items = @scandir($dir) ?: [];
            $hasManifest = in_array('manifest.json', $items, true);
            if ($hasManifest) {
                $manifest = json_decode(@file_get_contents($dir . DIRECTORY_SEPARATOR . 'manifest.json'), true) ?: [];
                foreach ($manifest as $m) {
                    if (!isset($m['original'], $m['stored'], $m['nonce'], $m['tag']))
                        continue;
                    $encPath = $dir . DIRECTORY_SEPARATOR . $m['stored'];
                    if (!is_file($encPath))
                        continue;
                    $cipher = @file_get_contents($encPath);
                    if ($cipher === false)
                        continue;
                    $nonce = base64_decode($m['nonce']);
                    $tag = base64_decode($m['tag']);
                    $plain = decryptFileData($cipher, $nonce, $tag);
                    if ($plain === false)
                        continue;
                    $tmp = tempnam(sys_get_temp_dir(), 'docdec_');
                    if ($tmp === false)
                        continue;
                    if (@file_put_contents($tmp, $plain) === false) {
                        @unlink($tmp);
                        continue;
                    }
                    $zipPath = ($rel ? $rel . '/' : '') . $m['original'];
                    $zip->addFile($tmp, $zipPath);
                }
                return; // Ne pas ajouter blobs/manifests bruts
            }
        }
        // Cas code: déchiffrer les fichiers CRYP1
        $items = @scandir($dir) ?: [];
        foreach ($items as $it) {
            if ($it === '.' || $it === '..')
                continue;
            $full = $dir . DIRECTORY_SEPARATOR . $it;
            $zipPath = ($rel ? $rel . '/' : '') . $it;
            if (is_dir($full)) {
                $addRecursive($base, ($rel ? $rel . '/' : '') . $it);
            } else if (is_file($full)) {
                // Si fichier CRYP1 (code), le déchiffrer; sinon ajouter brut
                $isCryp1 = false;
                $fh = @fopen($full, 'rb');
                if ($fh) {
                    $magic = @fread($fh, 5);
                    @fclose($fh);
                    $isCryp1 = ($magic === 'CRYP1');
                }
                if ($isCryp1) {
                    $tmpPlain = $decryptCryp1($full, $projectId);
                    if ($tmpPlain !== false) {
                        $zip->addFile($tmpPlain, $zipPath);
                    }
                } else {
                    $zip->addFile($full, $zipPath);
                }
            }
        }
    };
    $addRecursive($targetDir);
    $zip->close();
    $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $projectName);
    $fname = $safeName . '-version-' . $displayName . '.zip';
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($tmp));
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($tmp);
    @unlink($tmp);
    exit();
}

// Téléchargement uniquement du code d'une version
if ($action === 'download_code' || $action === 'download_documents') {
    $reqName = trim($_GET['name'] ?? '');
    $legacyNum = isset($_GET['number']) ? (int)$_GET['number'] : 0;
    $targetDir = '';
    $displayName = '';
    if ($reqName !== '') {
        foreach ($versionsManifest as $v) {
            $vName = $v['name'] ?? (isset($v['number']) ? (string)$v['number'] : null);
            $vDir = $v['dir'] ?? ('v' . $vName);
            if ($vName === $reqName) { $targetDir = $versionsRoot . DIRECTORY_SEPARATOR . $vDir; $displayName = $vName; break; }
        }
        if ($targetDir === '') { http_response_code(404); echo 'Version non trouvée'; exit(); }
    } else {
        if ($legacyNum <= 0) { http_response_code(400); echo 'Paramètre version manquant'; exit(); }
        $targetDir = $versionsRoot . DIRECTORY_SEPARATOR . 'v' . $legacyNum;
        $displayName = (string)$legacyNum;
        if (!is_dir($targetDir)) { http_response_code(404); echo 'Version introuvable'; exit(); }
    }
    $sub = $action === 'download_code' ? 'code' : 'documents';
    $baseSub = $targetDir . DIRECTORY_SEPARATOR . $sub;
    if (!is_dir($baseSub)) { http_response_code(404); echo ucfirst($sub) . ' introuvable'; exit(); }
    if (!class_exists('ZipArchive')) { http_response_code(500); echo 'ZipArchive indisponible'; exit(); }
    $tmp = tempnam(sys_get_temp_dir(),'verzip_');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) { http_response_code(500); echo 'Création ZIP impossible'; exit(); }
    // Utiliser les mêmes helpers de déchiffrement que download_version
    $decryptCryp1 = function(string $encPath, int $projectId) {
        $keyFile = realpath(__DIR__ . '/../uploads/projects/' . $projectId . '/.key');
        if ($keyFile === false) return false;
        $key = @file_get_contents($keyFile);
        if ($key === false || strlen($key) !== 32) return false;
        $data = @file_get_contents($encPath);
        if ($data === false || strlen($data) < 33) return false;
        if (substr($data,0,5) !== 'CRYP1') return false;
        $iv = substr($data,5,12);
        $tag = substr($data,17,16);
        $cipher = substr($data,33);
        $plain = openssl_decrypt($cipher,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
        if ($plain === false) return false;
        $tmpf = tempnam(sys_get_temp_dir(),'verdec_');
        if ($tmpf === false) return false;
        if (@file_put_contents($tmpf,$plain) === false) { @unlink($tmpf); return false; }
        return $tmpf;
    };
    $addRecursive = function(string $dir, string $rel='') use (&$addRecursive,$zip,$projectId,$decryptCryp1,$sub) {
        // Documents: déchiffrer via manifest.json
        if ($sub === 'documents') {
            $items = @scandir($dir) ?: [];
            $hasManifest = in_array('manifest.json',$items,true);
            if ($hasManifest) {
                $manifest = json_decode(@file_get_contents($dir . DIRECTORY_SEPARATOR . 'manifest.json'), true) ?: [];
                foreach ($manifest as $m) {
                    if (!isset($m['original'],$m['stored'],$m['nonce'],$m['tag'])) continue;
                    $encPath = $dir . DIRECTORY_SEPARATOR . $m['stored'];
                    if (!is_file($encPath)) continue;
                    $cipher = @file_get_contents($encPath);
                    if ($cipher === false) continue;
                    $nonce = base64_decode($m['nonce']);
                    $tag = base64_decode($m['tag']);
                    $plain = decryptFileData($cipher, $nonce, $tag);
                    if ($plain === false) continue;
                    $tmpf = tempnam(sys_get_temp_dir(),'docdec_');
                    if ($tmpf === false) continue;
                    if (@file_put_contents($tmpf,$plain) === false) { @unlink($tmpf); continue; }
                    $zipPath = ($rel ? $rel . '/' : '') . $m['original'];
                    $zip->addFile($tmpf, $zipPath);
                }
                return; // Ne pas inclure blobs/manifests
            }
        }
        // Code: déchiffrer CRYP1 sinon ajouter brut
        $items = @scandir($dir) ?: [];
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;
            $full = $dir . DIRECTORY_SEPARATOR . $it;
            $zipPath = ($rel ? $rel . '/' : '') . $it;
            if (is_dir($full)) {
                $addRecursive($full, $zipPath);
            } else if (is_file($full)) {
                if ($sub === 'code') {
                    $isCryp1 = false; $fh = @fopen($full,'rb'); if ($fh) { $magic = @fread($fh,5); @fclose($fh); $isCryp1 = ($magic === 'CRYP1'); }
                    if ($isCryp1) { $tmpPlain = $decryptCryp1($full, $projectId); if ($tmpPlain !== false) { $zip->addFile($tmpPlain,$zipPath); } }
                    else { $zip->addFile($full,$zipPath); }
                } else {
                    // Documents sans manifest: ajouter tel quel (rare)
                    $zip->addFile($full,$zipPath);
                }
            }
        }
    };
    $addRecursive($baseSub);
    $zip->close();
    $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $projectName);
    $suffix = $action === 'download_code' ? 'code' : 'documents';
    $fname = $safeName . '-version-' . $displayName . '-' . $suffix . '.zip';
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($tmp));
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($tmp);
    @unlink($tmp);
    exit();
}

// Lister le contenu d'une version (code + documents)
if ($action === 'list_contents') {
    $reqName = trim($_GET['name'] ?? '');
    $legacyNum = isset($_GET['number']) ? (int)$_GET['number'] : 0;
    $targetDir = '';
    if ($reqName !== '') {
        foreach ($versionsManifest as $v) {
            $vName = $v['name'] ?? (isset($v['number']) ? (string)$v['number'] : null);
            $vDir = $v['dir'] ?? ('v' . $vName);
            if ($vName === $reqName) { $targetDir = $versionsRoot . DIRECTORY_SEPARATOR . $vDir; break; }
        }
        if ($targetDir === '') jsonOut(['error'=>'Version non trouvée'],404);
    } else {
        if ($legacyNum <= 0) jsonOut(['error'=>'Paramètre version manquant'],400);
        $targetDir = $versionsRoot . DIRECTORY_SEPARATOR . 'v' . $legacyNum;
        if (!is_dir($targetDir)) jsonOut(['error'=>'Version introuvable'],404);
    }
    $codeDir = $targetDir . DIRECTORY_SEPARATOR . 'code';
    $docsDir = $targetDir . DIRECTORY_SEPARATOR . 'documents';
    $code = [];
    if (is_dir($codeDir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($codeDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $file) {
            if ($file->isFile()) {
                $rel = substr($file->getPathname(), strlen($codeDir) + 1);
                $code[] = $rel;
            }
        }
    }
    $documents = [];
    if (is_dir($docsDir)) {
        foreach (scandir($docsDir) ?: [] as $cat) {
            if ($cat === '.' || $cat === '..') continue;
            $catDir = $docsDir . DIRECTORY_SEPARATOR . $cat;
            if (!is_dir($catDir)) continue;
            $manFile = $catDir . DIRECTORY_SEPARATOR . 'manifest.json';
            $list = [];
            if (is_file($manFile)) {
                $manifest = json_decode(@file_get_contents($manFile), true) ?: [];
                foreach ($manifest as $m) { if (isset($m['original'])) $list[] = $m['original']; }
            }
            $documents[$cat] = $list;
        }
    }
    jsonOut(['status'=>'OK','code'=>$code,'documents'=>$documents]);
}

// Afficher un fichier code déchiffré depuis une version
if ($action === 'view_code') {
    $reqName = trim($_GET['name'] ?? '');
    $path = trim($_GET['path'] ?? '');
    if ($path === '') { http_response_code(400); echo 'Chemin manquant'; exit(); }
    $targetDir = '';
    foreach ($versionsManifest as $v) {
        $vName = $v['name'] ?? (isset($v['number']) ? (string)$v['number'] : null);
        $vDir = $v['dir'] ?? ('v' . $vName);
        if ($vName === $reqName) { $targetDir = $versionsRoot . DIRECTORY_SEPARATOR . $vDir; break; }
    }
    if ($targetDir === '') { http_response_code(404); echo 'Version non trouvée'; exit(); }
    $codeDir = $targetDir . DIRECTORY_SEPARATOR . 'code';
    $abs = realpath($codeDir . DIRECTORY_SEPARATOR . $path);
    if ($abs === false || !is_file($abs) || !str_starts_with($abs, realpath($codeDir))) { http_response_code(404); echo 'Fichier introuvable'; exit(); }
    // Déchiffrer CRYP1
    $keyFile = realpath(__DIR__ . '/../uploads/projects/' . $projectId . '/.key');
    if ($keyFile === false) { http_response_code(500); echo 'Clé absente'; exit(); }
    $key = @file_get_contents($keyFile);
    if ($key === false || strlen($key) !== 32) { http_response_code(500); echo 'Clé invalide'; exit(); }
    $data = @file_get_contents($abs);
    if ($data === false || substr($data,0,5) !== 'CRYP1') { http_response_code(400); echo 'Format inconnu'; exit(); }
    $iv = substr($data,5,12); $tag = substr($data,17,16); $cipher = substr($data,33);
    $plain = openssl_decrypt($cipher,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
    if ($plain === false) { http_response_code(500); echo 'Déchiffrement échoué'; exit(); }
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo $plain; exit();
}

// Afficher un document déchiffré depuis une version
if ($action === 'view_document') {
    $reqName = trim($_GET['name'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $filename = trim($_GET['filename'] ?? '');
    if ($category === '' || $filename === '') { http_response_code(400); echo 'Paramètres manquants'; exit(); }
    $targetDir = '';
    foreach ($versionsManifest as $v) {
        $vName = $v['name'] ?? (isset($v['number']) ? (string)$v['number'] : null);
        $vDir = $v['dir'] ?? ('v' . $vName);
        if ($vName === $reqName) { $targetDir = $versionsRoot . DIRECTORY_SEPARATOR . $vDir; break; }
    }
    if ($targetDir === '') { http_response_code(404); echo 'Version non trouvée'; exit(); }
    $catDir = $targetDir . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $category;
    $manFile = $catDir . DIRECTORY_SEPARATOR . 'manifest.json';
    if (!is_file($manFile)) { http_response_code(404); echo 'Manifest introuvable'; exit(); }
    $manifest = json_decode(@file_get_contents($manFile), true) ?: [];
    $entry = null;
    foreach ($manifest as $m) { if (($m['original'] ?? '') === $filename) { $entry = $m; break; } }
    if (!$entry) { http_response_code(404); echo 'Document introuvable'; exit(); }
    $blob = $catDir . DIRECTORY_SEPARATOR . $entry['stored'];
    if (!is_file($blob)) { http_response_code(404); echo 'Blob introuvable'; exit(); }
    $cipher = @file_get_contents($blob);
    if ($cipher === false) { http_response_code(500); echo 'Lecture échouée'; exit(); }
    $nonce = base64_decode($entry['nonce']); $tag = base64_decode($entry['tag']);
    $plain = decryptFileData($cipher, $nonce, $tag);
    if ($plain === false) { http_response_code(500); echo 'Déchiffrement échoué'; exit(); }
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $types = ['pdf'=>'application/pdf','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml','txt'=>'text/plain; charset=utf-8'];
    $ctype = $types[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $ctype);
    header('X-Content-Type-Options: nosniff');
    echo $plain; exit();
}

jsonOut(['error' => 'Action inconnue'], 400);
