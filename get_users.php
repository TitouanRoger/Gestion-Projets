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

$sql = $db->query("SELECT id FROM gestion_utilisateurs");
$users = [];

while ($row = $sql->fetch(PDO::FETCH_ASSOC)) {
    if ($row['id'] != $_SESSION['id']) {
        $unread_sql = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :user_id AND sender_id = :receiver_id AND is_read = 0");
        $unread_sql->bindParam(':user_id', $_SESSION['id']);
        $unread_sql->bindParam(':receiver_id', $row['id']);
        $unread_sql->execute();
        $unread_count = $unread_sql->fetchColumn();

        $users[] = [
            'id' => $row['id'],
            'unread_count' => $unread_count
        ];
    }
}

foreach ($users as $user) {
    echo '<li id="' . $user['id'] . '" onclick="window.location=\'messages.php?select=' . $user['id'] . '\'">';
    echo $user['id'];
    if ($user['unread_count'] > 0) {
        echo ' <span class="unread-count">' . $user['unread_count'] . '</span>';
    }
    echo '</li>';
}
?>
