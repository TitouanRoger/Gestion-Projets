<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <link rel="icon" href="images/logo.jpg">
    <?php
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

    ob_start();
    session_start();
    ?>
</head>
<style>
    :root {
        --background-color: #d1d5db;
        --primary-color: #fa8619;
        --secondary-color: #e69017;
        --text-color: #ffffff;
    }

    html,
    body {
        margin: 0;
        padding: 0;
        height: 100%;
        overflow: hidden;
        font-family: Arial, sans-serif;
        box-sizing: border-box;
        background-color: var(--background-color);
    }

    body {
        background: var(--background-color);
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }

    .logo {
        margin-bottom: 20px;
        width: 150px;
        height: auto;
    }

    .container {
        background: var(--primary-color);
        text-align: center;
        width: 300px;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        color: var(--text-color);
    }

    label {
        display: block;
        text-align: left;
        margin: 10px 0 5px;
        color: var(--text-color);
    }

    input {
        width: 100%;
        padding: 8px;
        margin-bottom: 15px;
        border: 1px solid white;
        border-radius: 5px;
        box-sizing: border-box;
    }

    .button {
        width: 100%;
        padding: 10px;
        margin: 10px 0;
        border: none;
        border-radius: 20px;
        background-color: #fa8619;
        color: var(--text-color);
        font-size: 1em;
        cursor: pointer;
    }

    .button:hover {
        background-color: var(--secondary-color);
        color: var(--text-color);
        border: 1px solid white;
    }

    .change_password {
        background: transparent;
        border: 1px solid white;
    }

    .message {
        padding: 15px;
        color: var(--text-color);
        font-size: 18px;
        font-weight: bold;
        border-radius: 10px;
        text-align: center;
        width: 310px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        margin-top: 20px;
    }

    .message.success {
        background-color: #4CAF50;
    }

    .message.error {
        background-color: #FA193B;
    }

    @media (max-width: 600px) {
        .container {
            width: 90%;
        }

        .message {
            width: 90%;
        }

        .logo {
            width: 120px;
        }
    }
</style>

<body>
    <?php if (isset($_SESSION['id'])): ?>
        <img src="images/logo.jpg" alt="Logo" class="logo">
        <div class="container">
            <form method="POST" class="form">
                <label for="new_password">Nouveau mot de passe</label>
                <input type="password" name="new_password" id="new_password" required>
                <label for="new_password_confirm">Confirmez le mot de passe</label>
                <input type="password" name="new_password_confirm" id="new_password_confirm" required>
                <button type="submit" class="button change_password" name="change_password">Changer le mot de passe</button>
            </form>
        </div>
    <?php else:
        echo '<div class="message" style="background-color: #f44336;">Vous n\'êtes pas connecté.</div>';
        setcookie(session_name(), '', 0, '/');
        header("Refresh: 2; URL=index.php");
    endif;
    ?>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_password = $_POST['new_password'];
        $new_password_confirm = $_POST['new_password_confirm'];
        $user_id = $_SESSION['id'];

        if ($new_password === $new_password_confirm) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $sql = $db->prepare("UPDATE gestion_utilisateurs SET password = :password, password_changed = TRUE WHERE id = :id");
            $sql->bindParam(':password', $hashed_password);
            $sql->bindParam(':id', $user_id);

            if ($sql->execute()) {
                echo '<div class="message success">Mot de passe changé avec succès !</div>';
                header("Refresh: 2; URL=index.php");
                exit;
            } else {
                echo '<div class="message error">Erreur lors du changement de mot de passe.</div>';
            }
        } else {
            echo '<div class="message error">Les deux mots de passe ne sont pas identiques.</div>';
        }
    }
    ?>
</body>

</html>