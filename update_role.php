<?php
session_start();
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

if (isset($_POST['new_role']) && isset($_POST['user_id'])) {
    $new_role = $_POST['new_role'];
    $user_id = $_POST['user_id'];

    $sql = $db->prepare("UPDATE gestion_utilisateurs SET role = :role WHERE id = :id");
    $sql->bindParam(':role', $new_role);
    $sql->bindParam(':id', $user_id);

    if ($sql->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour du rôle']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
}
