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

$sql = $db->prepare("SELECT * FROM messages WHERE receiver_id = :user_id AND notification = 0");
$sql->bindParam(':user_id', $_SESSION['id']);
$sql->execute();
$unread_messages = $sql->fetchAll(PDO::FETCH_ASSOC);

if (!empty($unread_messages)) {
    $sql = $db->prepare("UPDATE messages SET notification = 1 WHERE receiver_id = :user_id AND notification = 0");
    $sql->bindParam(':user_id', $_SESSION['id']);
    $sql->execute();

    echo json_encode($unread_messages);
} else {
    echo json_encode([]);
}
?>