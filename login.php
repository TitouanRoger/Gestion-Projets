<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <link rel="icon" href="images/logo.jpg">
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

    .password-container {
        position: relative;
    }

    .toggle-password {
        position: absolute;
        right: 10px;
        top: 35%;
        transform: translateY(-50%);
        cursor: pointer;
    }

    .button {
        width: 100%;
        padding: 10px;
        margin: 10px 0;
        border: none;
        border-radius: 20px;
        background-color: var(--primary-color);
        color: var(--text-color);
        font-size: 1em;
        cursor: pointer;
    }

    .button:hover {
        background-color: var(--secondary-color);
        color: var(--text-color);
        border: 1px solid white;
    }

    .connexion {
        background: transparent;
        border: 1px solid white;
    }

    .retour {
        background: transparent;
        border: 1px solid white;
    }

    .forgot {
        text-decoration: none;
        color: white;
        font-size: 0.8em;
    }

    .message {
        padding: 15px;
        color: white;
        font-size: 18px;
        font-weight: bold;
        border-radius: 10px;
        text-align: center;
        width: 300px;
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
    <img src="images/logo.jpg" alt="Logo" class="logo">
    <div class="container">
        <form method="post" class="form">
            <label>Identifiant</label>
            <input type="text" name="id" placeholder="Identifiant" required>
            <label>Mot de passe</label>
            <div class="password-container">
                <input type="password" name="password" placeholder="Mot de passe" required>
                <span class="toggle-password" onclick="togglePassword()">👁️</span>
            </div>
            <a href="#" class="forgot" onclick="alert('Veuillez contacter l\'administrateur !'); return false;">Mot de
                passe oublié ?</a>
            <button type="submit" class="button connexion" name="connexion">Connexion</button>
            <button type="button" class="button retour" onclick="location.href='index.php'">Retour</button>
        </form>
    </div>
    <?php
    function connexion()
    {
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
        if (isset($_POST['connexion'])) {
            $id = htmlspecialchars($_POST['id']);
            $password = htmlspecialchars($_POST['password']);
            $sql = $db->prepare("SELECT * FROM gestion_utilisateurs WHERE id = :id");
            $sql->bindParam(':id', $id);
            $sql->execute();

            if ($sql->rowCount() == 1) {
                $sql = $db->prepare("SELECT password FROM gestion_utilisateurs WHERE id = :id");
                $sql->bindParam(':id', $id);
                $sql->execute();
                $row = $sql->fetch();
                $hashed_password = $row['password'];
                session_start();

                if (password_verify($password, $hashed_password)) {
                    $_SESSION['id'] = $id;
                    $sql = $db->prepare("SELECT password_changed FROM gestion_utilisateurs WHERE id = :id");
                    $sql->bindParam(':id', $id);
                    $sql->execute();
                    $user = $sql->fetch();
                    if ($user['password_changed'] === 0) {
                        echo '<div class="message success">Connexion réussie !</div>';
                        header("Refresh: 2; URL=change_password.php");
                        exit;
                    }

                    echo '<div class="message success">Connexion réussie !</div>';
                    header("Refresh: 2; URL=index.php");
                    exit;
                } else {
                    session_unset();
                    session_destroy();
                    echo '<div class="message error">Identifiant ou mot de passe incorrect.</div>';
                }
            } else {
                echo '<div class="message error">Identifiant ou mot de passe incorrect.</div>';
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        connexion();
    }
    ?>
    <script>
        function togglePassword() {
            var password = document.querySelector("input[name='password']");
            var eyeIcon = document.querySelector(".toggle-password");
            if (password.type === "password") {
                password.type = "text";
                eyeIcon.textContent = "🙈";
            } else {
                password.type = "password";
                eyeIcon.textContent = "👁️";
            }
        }
    </script>
</body>

</html>