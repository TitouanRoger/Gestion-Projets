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
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            display: block;
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
            margin: 60px auto;
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
            cursor: pointer;
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
            cursor: pointer;
        }

        .connexion:hover {
            background-color: var(--secondary-color);
        }

        .button {
            display: block;
            padding: 10px;
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

        @media (max-width: 435px) {
            .message {
                max-width: 150px;
                width: 100%;
            }

            .button {
                padding: 10px;
                font-size: 14px;
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
    <?php
    $sql = $db->prepare("SELECT * FROM gestion_utilisateurs WHERE id = :id");
    $sql->bindParam(':id', $_SESSION['id']);
    $sql->execute();
    $user = $sql->fetch();

    if (isset($_POST['add_task'])) {
        $task = $_POST['task'];
        $type = htmlspecialchars($_POST['type']);
        $priority = htmlspecialchars($_POST['priority']);
        $sql = $db->prepare("INSERT INTO gestion_projet (description, type, priorite) VALUES (:task, :type, :priority)");
        $sql->bindParam(':task', $task);
        $sql->bindParam(':type', $type);
        $sql->bindParam(':priority', $priority);
        $sql->execute();
        header("Location: index.php");
        exit;
    }

    if (isset($_POST['assign_task'])) {
        $task_id = htmlspecialchars($_POST['task_id']);
        $date_debut = date('Y-m-d');
        $sql = $db->prepare("UPDATE gestion_projet SET attribuee = :id_utilisateur, etat = 'En cours', date_debut = :date_debut WHERE id = :id");
        $sql->bindParam(':id', $task_id);
        $sql->bindParam(':id_utilisateur', $_SESSION['id']);
        $sql->bindParam(':date_debut', $date_debut);
        $sql->execute();
        header("Location: index.php");
        exit;
    }


    if (isset($_POST['delete_assign_task'])) {
        $task_id = htmlspecialchars($_POST['task_id']);
        $sql = $db->prepare("UPDATE gestion_projet SET attribuee = NULL, etat = 'A faire', date_debut = NULL, date_fin = NULL WHERE id = :id");
        $sql->bindParam(':id', $task_id);
        $sql->execute();
        header("Location: index.php");
        exit;
    }

    if (isset($_POST['end_task'])) {
        $task_id = htmlspecialchars($_POST['task_id']);

        $upload_dir = 'uploads/' . $task_id . '/';
        if (file_exists($upload_dir)) {
            array_map('unlink', glob($upload_dir . "*"));

            $sql = $db->prepare("DELETE FROM task_files WHERE task_id = :task_id");
            $sql->bindParam(':task_id', $task_id);
            $sql->execute();
        }

        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        if (isset($_FILES['task_files']) && count($_FILES['task_files']['name']) > 0) {
            foreach ($_FILES['task_files']['tmp_name'] as $key => $tmp_name) {
                $file_name = str_replace(' ', '_', basename($_FILES['task_files']['name'][$key]));
                $file_path = $upload_dir . $file_name;

                if (move_uploaded_file($tmp_name, $file_path)) {
                    $sql = $db->prepare("INSERT INTO task_files (task_id, file_path) VALUES (:task_id, :file_path)");
                    $sql->bindParam(':task_id', $task_id);
                    $sql->bindParam(':file_path', $file_path);
                    $sql->execute();
                }
            }
        }

        $sql = $db->prepare("UPDATE gestion_projet SET etat = 'En attente de validation' WHERE id = :id");
        $sql->bindParam(':id', $task_id);
        $sql->execute();
        header("Location: index.php");
        exit;
    }


    if (isset($_POST['edit_task'])) {
        $task_id = htmlspecialchars($_POST['task_id']);
        $sql = $db->prepare("UPDATE gestion_projet SET etat = 'A modifier' WHERE id = :id");
        $sql->bindParam(':id', $task_id);
        $sql->execute();
        header("Location: index.php");
        exit;
    }

    if (isset($_POST['validate_task'])) {
        $task_id = htmlspecialchars($_POST['task_id']);
        $date_fin = date('Y-m-d');
        $sql = $db->prepare("UPDATE gestion_projet SET etat = 'Terminé', date_fin = :date_fin WHERE id = :id");
        $sql->bindParam(':id', $task_id);
        $sql->bindParam(':date_fin', $date_fin);
        $sql->execute();

        header("Location: index.php");
        exit;
    }


    if (isset($_POST['delete_task'])) {
        $task_id = htmlspecialchars($_POST['task_id']);
        $upload_dir = 'uploads/' . $task_id . '/';
        echo "<script>
            var confirmDelete = confirm('Voulez-vous vraiment supprimer cette tâche ?');
            if (confirmDelete) {
                var sql = 'DELETE FROM gestion_projet WHERE id = ' + $task_id;
                location.href = 'index.php?delete_task=' + $task_id;
            } else {
                location.href = 'index.php';
            }
        </script>";
        exit;
    }

    if (isset($_GET['delete_task'])) {
        $task_id = htmlspecialchars($_GET['delete_task']);
        $upload_dir = 'uploads/' . $task_id . '/';

        if (file_exists($upload_dir)) {
            array_map('unlink', glob($upload_dir . "*"));
            rmdir($upload_dir);
        }

        $sql = $db->prepare("DELETE FROM task_files WHERE task_id = :task_id");
        $sql->bindParam(':task_id', $task_id);
        $sql->execute();

        $sql = $db->prepare("DELETE FROM gestion_projet WHERE id = :id");
        $sql->bindParam(':id', $task_id);
        $sql->execute();

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
        <?php if (!isset($_SESSION['id'])): ?>
            <button onclick="location.href='login.php'" class="connexion"
                style="position: absolute; bottom: 100px;">Connexion</button>
        <?php else: ?>
            <div class="onglets">
                <a href="telecharger_dossier_projet.php">Télécharger le projet</a>
                <br><br>
                <a href="versions.php">Versions</a>
                <br><br>
                <a href="documents.php">Documents</a>
                <br><br>
                <a href="messages.php">Messages Privés</a>
                <br><br>
                <a href="tickets.php">Tickets</a>
            </div>
            <?php if ($user['role'] === 'administrateur'): ?>
                <button onclick="location.href='gestion.php'" class="gestion"
                    style="position: absolute; bottom: 100px;">Gestion</button>
            <?php endif; ?>
            <button onclick="location.href='index.php?logout=true'" class="connexion"
                style="position: absolute; bottom: 100px;">Deconnexion</button>
        <?php endif; ?>
    </div>
    <div class="content" id="content">
        <h1>Tâches</h1>
        <?php if (isset($_SESSION['id']) && $user['role'] === 'administrateur'): ?>
            <form method="post" action="">
                <label for="task">Ajouter une tâche :</label>
                <input type="text" id="task" name="task" required>
                <label for="type">Type de tâche :</label>
                <select id="type" name="type">
                    <option value="Feature">Feature</option>
                    <option value="Bug">Bug</option>
                    <option value="Test">Test</option>
                    <option value="Documentation">Documentation</option>
                </select>
                <label for="priority">Priorité de tâche :</label>
                <select id="priority" name="priority">
                    <option value="Basse">Basse</option>
                    <option value="Moyenne">Moyenne</option>
                    <option value="Elevée">Elevée</option>
                </select>
                <button type="submit" name="add_task">Ajouter</button>
            </form>
            <br><br>
        <?php endif; ?>
        <?php
        $query = $db->query("SELECT * FROM gestion_projet");
        $columns = $query->fetchAll(PDO::FETCH_ASSOC);

        if (count($columns) === 0) {
            echo "<p>Il n'y a aucune tâche.</p>";
        } else {

            echo "<table class='table' border='1'>";
            echo "<tr>";

            foreach ($columns[0] as $field => $value) {
                echo "<th>" . htmlspecialchars($field) . "</th>";
            }

            if (isset($_SESSION['id'])) {
                echo "<th>Attribuer</th>";
                echo "<th>Action</th>";
                echo "<th>Fichier(s)</th>";
            }

            if (isset($_SESSION['id']) && $user['role'] === 'administrateur') {
                echo "<th>Supprimer</th>";
            }

            echo "</tr>";

            foreach ($columns as $column) {
                echo "<tr>";

                foreach ($column as $value) {
                    echo "<td>" . htmlspecialchars($value) . "</td>";
                }

                if (isset($_SESSION['id']) && empty($column['attribuee'])) {
                    echo "<td>";
                    echo "<form method='post' action='' style='display:inline;'>";
                    echo "<input type='hidden' name='task_id' value='" . htmlspecialchars($column['id']) . "' />";
                    echo "<button type='submit' name='assign_task'>Attribuer</button>";
                    echo "</form>";
                    echo "</td>";
                } elseif (isset($_SESSION['id']) && !empty($column['attribuee'])) {
                    if ($user['role'] === 'administrateur') {
                        echo "<td>";
                        echo "<form method='post' action='' style='display:inline;'>";
                        echo "<input type='hidden' name='task_id' value='" . htmlspecialchars($column['id']) . "' />";
                        echo "<button type='submit' name='delete_assign_task'>Supprimer</button>";
                        echo "</form>";
                        echo "</td>";
                    } else {
                        echo "<td>" . "" . "</td>";
                    }
                }

                if (isset($_SESSION['id']) && $column['attribuee'] === $user['id'] && ($column['etat'] === 'En cours' || $column['etat'] === 'A modifier')) {
                    echo "<td>";
                    echo "<form method='post' action='' style='display:inline;' enctype='multipart/form-data'>";
                    echo "<input type='hidden' name='task_id' value='" . htmlspecialchars($column['id']) . "' />";
                    echo "<label for='file_upload'>Uploader des fichiers :</label>";
                    echo "<input type='file' name='task_files[]' multiple />";
                    echo "<button type='submit' name='end_task'>Terminer</button>";
                    echo "</form>";
                    echo "</td>";
                } elseif (isset($_SESSION['id']) && $user['role'] === 'administrateur' && $column['etat'] === 'En attente de validation') {
                    echo "<td>";
                    echo "<form method='post' action='' style='display:inline;'>";
                    echo "<input type='hidden' name='task_id' value='" . htmlspecialchars($column['id']) . "' />";
                    echo "<button type='submit' name='edit_task'>A modifier</button> <button type='submit' name='validate_task'>Valider</button>";
                    echo "</form>";
                    echo "</td>";
                } elseif (isset($_SESSION['id'])) {
                    echo "<td>" . "" . "</td>";
                }

                if (isset($_SESSION['id'])) {
                    $sql_files = $db->prepare("SELECT * FROM task_files WHERE task_id = :task_id");
                    $sql_files->bindParam(':task_id', $column['id']);
                    $sql_files->execute();
                    $files = $sql_files->fetchAll(PDO::FETCH_ASSOC);

                    if (count($files) > 0) {
                        echo "<td>";
                        foreach ($files as $file) {
                            echo "<a href='" . htmlspecialchars($file['file_path']) . "' download>" . htmlspecialchars(basename($file['file_path'])) . "</a><br><br>";
                        }
                        echo "</td>";
                    } else {
                        echo "<td>Aucun fichier</td>";
                    }

                }

                if (isset($_SESSION['id']) && $user['role'] === 'administrateur') {
                    echo "<td>";
                    echo "<form method='post' action='' style='display:inline;'>";
                    echo "<input type='hidden' name='task_id' value='" . htmlspecialchars($column['id']) . "' />";
                    echo "<button type='submit' name='delete_task'>Supprimer</button>";
                    echo "</form>";
                    echo "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
        ?>
    </div>
</body>

</html>