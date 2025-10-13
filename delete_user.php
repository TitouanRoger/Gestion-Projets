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

if (isset($_SESSION['id'])) {
    $id = $_SESSION['id'];
    if (isset($_GET['id'])) {
        $user_id_to_delete = htmlspecialchars($_GET['id']);

        $sql = $db->prepare("SELECT * FROM gestion_utilisateurs WHERE id = :id");
        $sql->bindParam(':id', $user_id_to_delete);
        $sql->execute();
        $user = $sql->fetch();

        if ($user) {
            $delete_sql = $db->prepare("DELETE FROM gestion_utilisateurs WHERE id = :id");
            $delete_sql->bindParam(':id', $user_id_to_delete);

            if ($delete_sql->execute()) {
                $_SESSION['message'] = 'Utilisateur supprimé avec succès.';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Erreur lors de la suppression de l\'utilisateur.';
                $_SESSION['message_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = 'Utilisateur non trouvé.';
            $_SESSION['message_type'] = 'error';
        }
    } else {
        $_SESSION['message'] = 'Aucun utilisateur à supprimer.';
        $_SESSION['message_type'] = 'error';
    }
} else {
    $_SESSION['message'] = 'Vous devez être connecté pour effectuer cette action.';
    $_SESSION['message_type'] = 'error';
}

header("Location: gestion.php");
exit;
