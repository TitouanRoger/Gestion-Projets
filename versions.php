<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Projet</title>
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
    $project = getenv('PROJECT');

    try {
        $db = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Erreur de connexion à la base de données : " . $e->getMessage());
    }

    ob_start();
    session_start();
    ?>
    <style>
        :root {
            --background-color: #d1d5db;
            --primary-color: #fa8619;
            --secondary-color: #e69017;
            --text-color: #ffffff;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: var(--background-color);
            margin: 0;
            overflow-x: hidden;
        }

        .sidebar {
            background-color: var(--primary-color);
            color: var(--text-color);
            width: 200px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            transition: transform 0.5s;
        }

        .sidebar a {
            text-decoration: none;
            color: inherit;
        }

        .logo {
            width: 100%;
            margin-bottom: 20px;
        }

        .content {
            margin-left: 250px;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            box-sizing: border-box;
            flex-direction: column;
            overflow-x: auto;
        }

        .content h1 {
            font-weight: bold;
            font-size: 24px;
            margin-bottom: 10px;
            text-align: center;
        }

        .content p {
            font-size: 16px;
            line-height: 1.5;
        }

        .table {
            border-collapse: collapse;
            width: 75%;
            overflow-x: auto;
        }

        .table th,
        .table td {
            border: 1px solid #dddddd;
            text-align: left;
            padding: 8px;
        }

        .table th {
            background-color: #f2f2f2;
        }

        .table tr {
            background-color: #ffffff;
        }

        .toggle-sidebar {
            position: absolute;
            right: 3%;
        }

        .toggle-sidebar:hover {
            cursor: pointer;
        }

        @media (max-width: 435px) {
            .sidebar {
                width: 100%;
                height: 100vh;
                position: fixed;
                transform: translateX(-90%);
                padding: 10px;
            }

            .content {
                margin-left: 30px;
                padding-top: 20px;
            }

            .sidebar .logo {
                width: 150px;
            }

            .sidebar .onglets {
                padding-left: 30%;
            }

            .sidebar a {
                padding-left: 30%;
            }

            .gestion {
                left: 30%;
            }

            .connexion {
                left: 30%;
            }

            .content h1 {
                font-size: 32px;
            }

            .content p {
                font-size: 18px;
            }
        }

        .message {
            padding: 15px;
            color: var(--text-color);
            font-size: 18px;
            font-weight: bold;
            border-radius: 10px;
            text-align: center;
            width: 300px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            margin: auto;
        }

        .gestion {
            display: block;
            margin: 80px auto;
            padding: 10px;
            background-color: var(--primary-color);
            color: var(--text-color);
            text-align: center;
            border: 2px solid white;
            border-radius: 20px;
            text-decoration: none;
            width: 200px;
            box-sizing: border-box;
            font-size: 16px;
        }

        .gestion:hover {
            background-color: var(--secondary-color);
        }

        .connexion {
            display: block;
            margin: 10px auto;
            padding: 10px;
            background-color: var(--primary-color);
            color: var(--text-color);
            text-align: center;
            border: 2px solid white;
            border-radius: 20px;
            text-decoration: none;
            width: 200px;
            box-sizing: border-box;
            font-size: 16px;
        }

        .connexion:hover {
            background-color: var(--secondary-color);
        }

        .button {
            display: block;
            padding: 15px;
            font-size: 18px;
            background-color: var(--primary-color);
            border: none;
            color: var(--text-color);
            border-radius: 10px;
            text-align: center;
        }

        .button:hover {
            background-color: var(--secondary-color);
        }

        form {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 20px;
        }

        label {
            margin-bottom: 10px;
        }

        input[type="text"] {
            padding: 10px;
            margin-bottom: 20px;
            border: none;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        button[type="submit"] {
            background-color: var(--primary-color);
            color: var(--text-color);
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button[type="submit"]:hover {
            background-color: var(--secondary-color);
        }

        select {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: none;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .retour {
            display: block;
            margin: 0px auto;
            padding: 10px;
            background-color: var(--primary-color);
            color: var(--text-color);
            text-align: center;
            border: 2px solid white;
            border-radius: 20px;
            text-decoration: none;
            width: 200px;
            box-sizing: border-box;
            font-size: 16px;
        }

        .retour:hover {
            background-color: var(--secondary-color);
        }

        @media (max-width: 435px) {
            .message {
                max-width: 150px;
                width: 100%;
            }

            .button {
                padding: 10px;
                font-size: 14px;
            }

            .retour {
                left: 30%;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleButton = document.querySelector('.toggle-sidebar');
            const sidebar = document.querySelector('.sidebar');

            if (window.innerWidth <= 435) {
                toggleButton.style.display = 'block';
            } else {
                toggleButton.style.display = 'none';
            }

            toggleButton.addEventListener('click', function () {
                if (sidebar.style.transform === 'translateX(-20%)') {
                    sidebar.style.transform = 'translateX(-90%)';
                } else {
                    sidebar.style.transform = 'translateX(-20%)';
                }
            });

            document.addEventListener('click', function (e) {
                if (sidebar.style.transform === 'translateX(-20%)' && !sidebar.contains(e.target) && !toggleButton.contains(e.target)) {
                    sidebar.style.transform = 'translateX(-90%)';
                }
            });
        });

        window.addEventListener('resize', function () {
            const toggleButton = document.querySelector('.toggle-sidebar');
            const sidebar = document.querySelector('.sidebar');

            if (window.innerWidth <= 435) {
                toggleButton.style.display = 'block';
                sidebar.style.transform = 'translateX(-90%)';
            } else {
                toggleButton.style.display = 'none';
            }
        });
    </script>
</head>

<body>
    <?php
    if (isset($_SESSION['id'])) {
        $id = $_SESSION['id'];
        $sql = $db->prepare("SELECT * FROM gestion_utilisateurs WHERE id = :id");
        $sql->bindParam(':id', $id);
        $sql->execute();

        if ($sql->rowCount() == 0) {
            session_unset();
            session_destroy();
            echo '<div class="message" style="background-color: #f44336;">Votre compte a été supprimé. Vous allez être déconnecté.</div>';
            setcookie(session_name(), '', 0, '/');
            header("Refresh: 2; URL=index.php");
            exit;
        }

        $inactive_time = 900;
        if (isset($_SESSION['last_activity'])) {
            $session_lifetime = time() - $_SESSION['last_activity'];

            if ($session_lifetime > $inactive_time) {
                session_unset();
                session_destroy();
                setcookie(session_name(), '', 0, '/');
                header("Location: index.php");
                exit;
            }
        }
    }

    $_SESSION['last_activity'] = time();

    if (isset($_GET['logout'])) {
        session_destroy();
        setcookie(session_name(), '', 0, '/');
        header("Location: index.php");
        exit;
    }
    ?>

    <div class="sidebar">
        <div class="onglets">
            <div class="toggle-sidebar" style="display: none;">&#9776;</div>
            <img src="images/logo.jpg" alt="Logo" class="logo">
            <h2><?php echo $project; ?></h2>
        </div>
        <?php if (isset($_SESSION['id'])): ?>
            <a href="telecharger_dossier_projet.php" class="telecharger-dossier">Télécharger le projet</a>
            <br><br>
            <a href="documents.php">Documents</a>
            <?php if ($user['role'] === 'administrateur'): ?>
                <button onclick="location.href='gestion.php'" class="gestion"
                    style="position: absolute; bottom: 100px;">Gestion</button>
            <?php endif; ?>
            <button onclick="location.href='index.php'" class="retour"
                style="position: absolute; bottom: 100px;">Retour</button>
        <?php else: ?>
            <script>
                window.location.href = 'index.php';
            </script>
        <?php endif; ?>
    </div>
    <div class="content" id="content">
        <h1>Versions</h1>
        <?php
        $dir = scandir('versions');
        foreach ($dir as $file) {
            if ($file !== '.' && $file !== '..') {
                echo '<a href="telecharger_versions.php?dir=' . $file . '"><button class="button">' . $file . '</button></a><br>';
            }
        }
        ?>
    </div>
</body>

</html>