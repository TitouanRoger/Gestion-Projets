<?php
// ============================================================
// MESSAGES.PHP - API MESSAGES PRIVÉS (PROJET)
// ============================================================
// Endpoints via action GET/POST:
// - action=send: envoi message chiffré + pièces jointes chiffrées
// - action=list: liste paginée/diff des messages (plus bas)
// Sécurité:
// - Vérifie que l'utilisateur et le destinataire appartiennent au projet
// - Chiffrement via message_crypto.php (AES-256-GCM)
// - Réponses JSON avec codes HTTP appropriés
// ============================================================
session_start();
require_once 'db_connect.php';
require_once 'message_crypto.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Accès refusé';
    exit();
}
$user_id = (int) $_SESSION['user_id'];

$project_id = isset($_POST['project_id']) ? (int) $_POST['project_id'] : (isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0);
if ($project_id <= 0) {
    http_response_code(400);
    echo 'Projet invalide';
    exit();
}

try {
    // Vérifier appartenance au projet
    $stmtP = $pdo->prepare('SELECT createur_id FROM projets WHERE id = ?');
    $stmtP->execute([$project_id]);
    $owner = $stmtP->fetchColumn();
    if (!$owner) {
        http_response_code(404);
        echo 'Projet inconnu';
        exit();
    }
    $is_member = ($user_id == $owner);
    if (!$is_member) {
        $stmtM = $pdo->prepare('SELECT COUNT(*) FROM projet_membres WHERE projet_id = ? AND utilisateur_id = ?');
        $stmtM->execute([$project_id, $user_id]);
        $is_member = $stmtM->fetchColumn() > 0;
    }
    if (!$is_member) {
        http_response_code(403);
        echo 'Non membre';
        exit();
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'send') {
        header('Content-Type: application/json; charset=utf-8');
        $recipient_id = isset($_POST['recipient_id']) ? (int) $_POST['recipient_id'] : 0;
        $message = trim($_POST['message'] ?? '');
        $hasFiles = !empty($_FILES['files']) && is_array($_FILES['files']['name']);
        if ($recipient_id <= 0 || $recipient_id === $user_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Destinataire invalide']);
            exit();
        }
        if ($message === '' && !$hasFiles) {
            http_response_code(400);
            echo json_encode(['error' => 'Message vide']);
            exit();
        }
        $stmtR = $pdo->prepare('SELECT id FROM utilisateurs WHERE id = ?');
        $stmtR->execute([$recipient_id]);
        if (!$stmtR->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['error' => 'Utilisateur inconnu']);
            exit();
        }
        $is_dest_member = ($recipient_id == $owner);
        if (!$is_dest_member) {
            $stmtDM = $pdo->prepare('SELECT COUNT(*) FROM projet_membres WHERE projet_id = ? AND utilisateur_id = ?');
            $stmtDM->execute([$project_id, $recipient_id]);
            $is_dest_member = $stmtDM->fetchColumn() > 0;
        }
        if (!$is_dest_member) {
            http_response_code(403);
            echo json_encode(['error' => 'Destinataire hors projet']);
            exit();
        }
        $nonce = '';
        $tag = '';
        $cipher = ($message !== '') ? encryptMessage($message, $nonce, $tag) : '';
        if ($message !== '' && $cipher === '') {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur chiffrement']);
            exit();
        }
        $stmtIns = $pdo->prepare('INSERT INTO messages_prives (projet_id, sender_id, recipient_id, ciphertext, nonce, tag) VALUES (?,?,?,?,?,?)');
        $stmtIns->execute([$project_id, $user_id, $recipient_id, $cipher, $nonce, $tag]);
        $msgId = (int) $pdo->lastInsertId();
        $filesSaved = [];
        if ($hasFiles) {
            $allowedExt = ['png', 'jpg', 'jpeg', 'gif', 'pdf', 'txt', 'zip', 'docx', 'xlsx', 'pptx'];
            for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
                $err = $_FILES['files']['error'][$i];
                if ($err === UPLOAD_ERR_NO_FILE)
                    continue;
                $orig = $_FILES['files']['name'][$i];
                if ($err !== UPLOAD_ERR_OK) {
                    $filesSaved[] = ['name' => $orig, 'status' => 'upload_error'];
                    continue;
                }
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt)) {
                    $filesSaved[] = ['name' => $orig, 'status' => 'extension_refusee'];
                    continue;
                }
                $bin = file_get_contents($_FILES['files']['tmp_name'][$i]);
                if ($bin === false) {
                    $filesSaved[] = ['name' => $orig, 'status' => 'lecture_echouee'];
                    continue;
                }
                $fN = '';
                $fT = '';
                $cipherFile = encryptMessage($bin, $fN, $fT);
                if ($cipherFile === '') {
                    $filesSaved[] = ['name' => $orig, 'status' => 'chiffrement_echoue'];
                    continue;
                }
                $stmtF = $pdo->prepare('INSERT INTO messages_prives_files (message_id, original_name, ciphertext, nonce, tag, size) VALUES (?,?,?,?,?,?)');
                $stmtF->execute([$msgId, $orig, $cipherFile, $fN, $fT, strlen($bin)]);
                $filesSaved[] = ['name' => $orig, 'status' => 'ok'];
            }
        }
        $stmtRU = $pdo->prepare('INSERT INTO messages_reads (projet_id,user_id,other_user_id,last_read_message_id) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE last_read_message_id = GREATEST(last_read_message_id, VALUES(last_read_message_id))');
        $stmtRU->execute([$project_id, $user_id, $recipient_id, $msgId]);
        echo json_encode(['status' => 'OK', 'message_id' => $msgId, 'files' => $filesSaved]);
        exit();
    } elseif ($action === 'thread') {
        $other_id = isset($_GET['other_id']) ? (int) $_GET['other_id'] : 0;
        if ($other_id <= 0) {
            http_response_code(400);
            echo 'Paramètre manquant';
            exit();
        }
        $stmtdest = $pdo->prepare('SELECT id FROM utilisateurs WHERE id = ?');
        $stmtdest->execute([$other_id]);
        if (!$stmtdest->fetchColumn()) {
            http_response_code(404);
            echo 'Autre inconnu';
            exit();
        }
        $is_dest_member = ($other_id == $owner);
        if (!$is_dest_member) {
            $stmtDM = $pdo->prepare('SELECT COUNT(*) FROM projet_membres WHERE projet_id = ? AND utilisateur_id = ?');
            $stmtDM->execute([$project_id, $other_id]);
            $is_dest_member = $stmtDM->fetchColumn() > 0;
        }
        if (!$is_dest_member) {
            http_response_code(403);
            echo 'Accès interdit';
            exit();
        }
        $stmtMsg = $pdo->prepare('SELECT id, sender_id, recipient_id, ciphertext, nonce, tag, created_at FROM messages_prives WHERE projet_id = ? AND ((sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)) ORDER BY created_at ASC');
        $stmtMsg->execute([$project_id, $user_id, $other_id, $other_id, $user_id]);
        $out = [];
        while ($r = $stmtMsg->fetch(PDO::FETCH_ASSOC)) {
            $plain = '';
            if (!empty($r['ciphertext'])) {
                $plain = decryptMessage($r['ciphertext'], $r['nonce'], $r['tag']);
            }
            $stmtFiles = $pdo->prepare('SELECT id, original_name, size FROM messages_prives_files WHERE message_id = ?');
            $stmtFiles->execute([$r['id']]);
            $files = [];
            while ($f = $stmtFiles->fetch(PDO::FETCH_ASSOC)) {
                $files[] = ['file_id' => (int) $f['id'], 'name' => $f['original_name'], 'size' => (int) $f['size']];
            }
            $out[] = ['id' => (int) $r['id'], 'from' => (int) $r['sender_id'], 'to' => (int) $r['recipient_id'], 'text' => $plain === false ? '[Erreur déchiffrement]' : $plain, 'at' => $r['created_at'], 'files' => $files];
        }
        $lastId = 0;
        foreach ($out as $m) {
            if ($m['from'] == $other_id && $m['id'] > $lastId)
                $lastId = $m['id'];
        }
        if ($lastId > 0) {
            $stmtRU = $pdo->prepare('INSERT INTO messages_reads (projet_id,user_id,other_user_id,last_read_message_id) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE last_read_message_id = GREATEST(last_read_message_id, VALUES(last_read_message_id))');
            $stmtRU->execute([$project_id, $user_id, $other_id, $lastId]);
        }
        $stmtOtherRead = $pdo->prepare('SELECT last_read_message_id FROM messages_reads WHERE projet_id = ? AND user_id = ? AND other_user_id = ?');
        $stmtOtherRead->execute([$project_id, $other_id, $user_id]);
        $other_last_read = (int) $stmtOtherRead->fetchColumn();
        $stmtTY = $pdo->prepare('SELECT last_typing FROM messages_typing WHERE projet_id = ? AND user_id = ? AND other_user_id = ?');
        $stmtTY->execute([$project_id, $other_id, $user_id]);
        $typing = false;
        if ($rowTY = $stmtTY->fetch(PDO::FETCH_ASSOC)) {
            $ts = strtotime($rowTY['last_typing']);
            if ($ts && (time() - $ts) <= 5)
                $typing = true;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['messages' => $out, 'typing' => $typing, 'other_last_read' => $other_last_read], JSON_UNESCAPED_UNICODE);
        exit();
    } elseif ($action === 'list') {
        $stmtList = $pdo->prepare('SELECT m.id, m.sender_id, m.recipient_id, m.ciphertext, m.nonce, m.tag, m.created_at FROM messages_prives m WHERE m.projet_id = ? AND (m.sender_id = ? OR m.recipient_id = ?) ORDER BY m.created_at DESC');
        $stmtList->execute([$project_id, $user_id, $user_id]);
        $threads = [];
        while ($r = $stmtList->fetch(PDO::FETCH_ASSOC)) {
            $other = ($r['sender_id'] == $user_id) ? (int) $r['recipient_id'] : (int) $r['sender_id'];
            if (!isset($threads[$other])) {
                $plain = '';
                if (!empty($r['ciphertext'])) {
                    $plain = decryptMessage($r['ciphertext'], $r['nonce'], $r['tag']);
                }
                $threads[$other] = ['other_id' => $other, 'last' => $plain === false ? '[Erreur déchiffrement]' : $plain, 'at' => $r['created_at'], 'last_message_id' => (int) $r['id'], 'last_sender_id' => (int) $r['sender_id']];
            }
        }
        foreach ($threads as $oid => &$tinfo) {
            $stmtUnread = $pdo->prepare('SELECT last_read_message_id FROM messages_reads WHERE projet_id = ? AND user_id = ? AND other_user_id = ?');
            $stmtUnread->execute([$project_id, $user_id, $oid]);
            $lastRead = (int) $stmtUnread->fetchColumn();
            $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM messages_prives WHERE projet_id = ? AND sender_id = ? AND recipient_id = ? AND id > ?');
            $stmtCount->execute([$project_id, $oid, $user_id, $lastRead]);
            $tinfo['unread'] = (int) $stmtCount->fetchColumn();
            // Ajouter le nom de l'utilisateur
            $stmtUser = $pdo->prepare('SELECT prenom, nom FROM utilisateurs WHERE id = ?');
            $stmtUser->execute([$oid]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            $tinfo['other_name'] = $user ? trim($user['prenom'] . ' ' . $user['nom']) : 'Utilisateur ' . $oid;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_values($threads), JSON_UNESCAPED_UNICODE);
        exit();
    } elseif ($action === 'typing') {
        $other_id = isset($_POST['other_id']) ? (int) $_POST['other_id'] : 0;
        if ($other_id <= 0) {
            http_response_code(400);
            echo 'Param manquant';
            exit();
        }
        $stmtDest = $pdo->prepare('SELECT id FROM utilisateurs WHERE id = ?');
        $stmtDest->execute([$other_id]);
        if (!$stmtDest->fetchColumn()) {
            http_response_code(404);
            echo 'Utilisateur inconnu';
            exit();
        }
        $stmtTy = $pdo->prepare('INSERT INTO messages_typing (projet_id,user_id,other_user_id,last_typing) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE last_typing = NOW()');
        $stmtTy->execute([$project_id, $user_id, $other_id]);
        echo 'OK';
        exit();
    } else {
        http_response_code(400);
        echo 'Action inconnue';
        exit();
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Err messages: ' . $e->getMessage());
    echo 'Erreur serveur';
    exit();
}
