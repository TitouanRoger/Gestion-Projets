<?php
// ============================================================
// DOWNLOAD_MESSAGE_FILE.PHP - TÉLÉCHARGEMENT PIÈCE JOINTE MESSAGE
// ============================================================
// Valide: project_id, message_id, file_id
// Autorise: émetteur, destinataire ou créateur (membre projet vérifié)
// Déchiffre la pièce jointe (AES-256-GCM) avant l'envoi au client.
// ============================================================
require_once 'secure_session.php';
secure_session_start();
require_once 'db_connect.php';
require_once 'message_crypto.php';

if (!validate_session()) { http_response_code(401); echo 'Accès refusé'; exit(); }
$user_id = (int)$_SESSION['user_id'];
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$message_id = isset($_GET['message_id']) ? (int)$_GET['message_id'] : 0;
$file_id = isset($_GET['file_id']) ? (int)$_GET['file_id'] : 0;
if ($project_id<=0 || $message_id<=0 || $file_id<=0) { http_response_code(400); echo 'Paramètres invalides'; exit(); }

try {
    // Vérifier message et droits (être sender ou recipient et membre projet)
    $stmtMsg = $pdo->prepare('SELECT m.projet_id, m.sender_id, m.recipient_id, p.createur_id FROM messages_prives m JOIN projets p ON p.id = m.projet_id WHERE m.id = ?');
    $stmtMsg->execute([$message_id]);
    $info = $stmtMsg->fetch(PDO::FETCH_ASSOC);
    if (!$info || (int)$info['projet_id'] !== $project_id) { http_response_code(404); echo 'Message inconnu'; exit(); }
    $authorized = ($user_id == $info['sender_id'] || $user_id == $info['recipient_id'] || $user_id == $info['createur_id']);
    if (!$authorized) {
        $stmtM = $pdo->prepare('SELECT COUNT(*) FROM projet_membres WHERE projet_id = ? AND utilisateur_id = ?');
        $stmtM->execute([$project_id, $user_id]);
        $authorized = $stmtM->fetchColumn() > 0 && ($user_id == $info['sender_id'] || $user_id == $info['recipient_id']);
    }
    if (!$authorized) { http_response_code(403); echo 'Accès interdit'; exit(); }

    $stmtF = $pdo->prepare('SELECT original_name, ciphertext, nonce, tag, size FROM messages_prives_files WHERE id = ? AND message_id = ?');
    $stmtF->execute([$file_id, $message_id]);
    $fileRow = $stmtF->fetch(PDO::FETCH_ASSOC);
    if (!$fileRow) { http_response_code(404); echo 'Fichier introuvable'; exit(); }

    $plaintext = decryptMessage($fileRow['ciphertext'], $fileRow['nonce'], $fileRow['tag']);
    if ($plaintext === false) { http_response_code(500); echo 'Erreur déchiffrement'; exit(); }

    $name = $fileRow['original_name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mimeMap = [
        'png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','pdf'=>'application/pdf','txt'=>'text/plain','zip'=>'application/zip','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    ];
    $mime = isset($mimeMap[$ext]) ? $mimeMap[$ext] : 'application/octet-stream';

    header('Content-Type: '.$mime);
    header('Content-Length: '.strlen($plaintext));
    header('Content-Disposition: attachment; filename="'.basename($name).'"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo $plaintext;
    exit();

} catch (PDOException $e) {
    http_response_code(500); error_log('Err dl msg file: '.$e->getMessage()); echo 'Erreur serveur'; exit();
}
