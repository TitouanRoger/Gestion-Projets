<?php
session_start();

if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit;
}

function loadEnv($file)
{
    if (!file_exists($file)) {
        throw new Exception("Le fichier .env est introuvable");
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);

        putenv(trim($key) . '=' . trim($value));
    }
}

loadEnv(__DIR__ . '/../.env');

$dbhost = getenv('DB_HOST');
$dbname = getenv('DB_NAME');
$dbuser = getenv('DB_USER');
$dbpass = getenv('DB_PASS');

try {
    $db = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

$chat_with = isset($_GET['select']) ? $_GET['select'] : null;

if ($chat_with) {
    $sql = $db->prepare("SELECT * FROM messages WHERE (sender_id = :user_id AND receiver_id = :chat_with) OR (sender_id = :chat_with AND receiver_id = :user_id) ORDER BY created_at ASC");
    $sql->bindParam(':user_id', $_SESSION['id']);
    $sql->bindParam(':chat_with', $chat_with);
    $sql->execute();
    $messages = $sql->fetchAll(PDO::FETCH_ASSOC);

    if (isset($messages) && !empty($messages)) {
        foreach ($messages as $message) {
            echo '<div class="message">';
            echo '<div class="sender">' . ($message['sender_id'] == $_SESSION['id'] ? 'Moi' : $message['sender_id']) . '</div>';
            echo '<div>' . nl2br(htmlspecialchars($message['message'])) . '</div>';

            $attachment_sql = $db->prepare("SELECT * FROM attachments WHERE message_id = :message_id");
            $attachment_sql->bindParam(':message_id', $message['id']);
            $attachment_sql->execute();
            $attachments = $attachment_sql->fetchAll(PDO::FETCH_ASSOC);

            if ($attachments) {
                echo '<div class="attachments">';
                foreach ($attachments as $attachment) {
                    echo '<a href="' . $attachment['file_path'] . '" download="' . htmlspecialchars($attachment['file_name']) . '">' . htmlspecialchars($attachment['file_name']) . '</a><br>';
                }
                echo '</div>';
            }

            echo '<div class="time">' . $message['created_at'] . '</div>';
            echo '</div>';
            echo '<br>';
        }
    } else {
        echo '<div class="message">Aucun message</div>';
    }
}