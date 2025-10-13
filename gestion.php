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

            .retour {
                left: 30%;
            }

            .deconnexion {
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

        .message-gestion {
            max-width: 300px;
            padding: 15px;
            color: var(--text-color);
            font-size: 18px;
            font-weight: bold;
            border-radius: 10px;
            text-align: center;
            width: 300px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .retour {
            display: block;
            margin: 50px auto;
            padding: 10px;
            background-color: var(--primary-color);
            color: var(--text-color);
            text-align: center;
            border: 2px solid white;
            border-radius: 20px;
            text-decoration: none;
            width: 200px;
            box-sizing: border-box;
        }

        .retour:hover {
            background-color: var(--secondary-color);
        }

        .deconnexion {
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
        }

        .deconnexion:hover {
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

        input[type="text"],
        input[type="password"] {
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
        let inactivityTimer;

        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(() => {
                localStorage.clear();
            }, 600000);
        }

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

        function showPasswordInput(userId) {
            const actionsElement = document.getElementById(`actions_${userId}`);

            if (!actionsElement) {
                console.error("Element pour l'ID " + userId + " non trouvé !");
                return;
            }

            const inputHtml = `
                <input type="password" id="new_password_${userId}" placeholder="Nouveau mot de passe">
                <button type="submit" onclick="confirmPasswordChange('${userId}'); cancelPasswordChange('${userId}')">Confirmer</button>
                <button type="submit" onclick="cancelPasswordChange('${userId}')">Annuler</button>
            `;
            actionsElement.innerHTML = inputHtml;
        }

        function cancelPasswordChange(userId) {
            const actionsElement = document.getElementById(`actions_${userId}`);
            const oldHtml = `
                <a href="#" onclick="showPasswordInput('${userId}')">Modifier le mot de passe</a> | <a href="delete_user.php?id=${userId}" onclick="return confirm('Etes-vous sûr de vouloir supprimer cet utilisateur ?')">Supprimer l'utilisateur</a>
            `;
            actionsElement.innerHTML = oldHtml;
        }

        function confirmPasswordChange(userId) {
            const newPassword = document.getElementById(`new_password_${userId}`).value;
            if (newPassword) {
                const formData = new FormData();
                formData.append('new_password', newPassword);
                formData.append('user_id', userId);

                fetch('update_password.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Mot de passe mis à jour avec succès pour l\'utilisateur ' + userId);
                        } else {
                            alert('Erreur lors de la mise à jour du mot de passe : ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Erreur du réseau :', error);
                        alert('Erreur du réseau, veuillez réessayer plus tard.');
                    });
            } else {
                alert('Veuillez entrer un nouveau mot de passe.');
            }
        }

        function updateRole(userId, newRole) {
            if (newRole !== document.getElementById(`role_${userId}`).textContent.trim()) {
                const formData = new FormData();
                formData.append('new_role', newRole);
                formData.append('user_id', userId);

                fetch('update_role.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Rôle mis à jour avec succès pour l\'utilisateur ' + userId);
                            location.reload();
                        } else {
                            alert('Erreur lors de la mise à jour du rôle : ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Erreur du réseau :', error);
                        alert('Erreur du réseau, veuillez réessayer plus tard.');
                    });
            }
        }
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

        $_SESSION['last_activity'] = time();

    } else {
        echo '<div class="message" style="background-color: #f44336;">Vous n\'êtes pas connecté.</div>';
        setcookie(session_name(), '', 0, '/');
        header("Refresh: 2; URL=index.php");
    }
    ?>

    <?php
    $sql = $db->prepare("SELECT * FROM gestion_utilisateurs WHERE id = :id");
    $sql->bindParam(':id', $id);
    $sql->execute();
    $user = $sql->fetch();

    if (isset($_SESSION['id'])): ?>
        <?php if ($user['role'] === 'administrateur'): ?>
            <div class="sidebar">
                <div class="onglets">
                    <div class="toggle-sidebar" style="display: none;">&#9776;</div>
                    <img src="images/logo.jpg" alt="Logo" class="logo">
                </div>
                <a href="index.php" class="retour" style="position: absolute; bottom: 100px;">Retour</a>
                <a href="index.php?logout=true" class="deconnexion" style="position: absolute; bottom: 100px;">Deconnexion</a>
            </div>
            <div class="content" id="content">
                <h1>Gestion des utilisateurs</h1>
                <?php
                if (isset($_SESSION['message'])) {
                    $message = $_SESSION['message'];
                    $message_type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'error';
                    $message_class = ($message_type === 'success') ? 'message-gestion' : 'message-gestion error';
                    echo "<div class=\"$message_class\" style=\"background-color: " . ($message_type === 'success' ? '#4CAF50' : '#f44336') . ";\">$message</div>";

                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                }

                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_user_id']) && isset($_POST['new_user_password'])) {
                    $new_user_id = htmlspecialchars($_POST['new_user_id']);
                    $new_user_password = password_hash($_POST['new_user_password'], PASSWORD_DEFAULT);

                    $sql = $db->prepare("SELECT COUNT(*) FROM gestion_utilisateurs WHERE id = :id");
                    $sql->bindParam(':id', $new_user_id);
                    $sql->execute();

                    if ($sql->fetchColumn() > 0) {
                        echo '<div class="message-gestion" style="background-color: #f44336;">Erreur : l\'ID ' . $new_user_id . ' est déjà utilisé.</div>';
                    } else {
                        $sql = $db->prepare("INSERT INTO gestion_utilisateurs (id, password) VALUES (:id, :password)");
                        $sql->bindParam(':id', $new_user_id);
                        $sql->bindParam(':password', $new_user_password);

                        if ($sql->execute()) {
                            echo '<div class="message-gestion" style="background-color: #4CAF50;">Nouvel utilisateur créé avec succès.</div>';
                        } else {
                            echo '<div class="message-gestion" style="background-color: #f44336;">Erreur lors de la création de l\'utilisateur.</div>';
                        }
                    }
                }
                ?>

                <form method="POST" action="">
                    <label for="new_user_id">ID de l'utilisateur :</label>
                    <input type="text" id="new_user_id" name="new_user_id" required>

                    <label for="new_user_password">Mot de passe :</label>
                    <input type="password" id="new_user_password" name="new_user_password" required>

                    <button type="submit">Créer l'utilisateur</button>
                </form>

                <br>

                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Rôle</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = $db->query("SELECT * FROM gestion_utilisateurs");
                        while ($row = $sql->fetch()) {
                            echo "<tr>";
                            echo "<td>" . $row['id'] . "</td>";
                            echo "<td>" . $row['role'] . "</td>";
                            if ($row['role'] !== 'administrateur') {
                                echo "<td id='actions_" . $row['id'] . "'>";
                                echo "<select id='role_" . $row['id'] . "' onchange=\"updateRole('" . $row['id'] . "', this.value)\">";
                                $roles = ['utilisateur', 'recetteur', 'rédacteur', 'développeur', 'responsable'];
                                foreach ($roles as $role) {
                                    $selected = $row['role'] === $role ? 'selected' : '';
                                    echo "<option value='" . $role . "' " . $selected . ">" . $role . "</option>";
                                }
                                echo "</select>";
                                echo " | <a href='#' onclick=\"showPasswordInput('" . $row['id'] . "')\">Modifier le mot de passe</a>";
                                echo " | <a href='delete_user.php?id=" . $row['id'] . "' onclick=\"return confirm('Etes-vous sûr de vouloir supprimer cet utilisateur ?')\">Supprimer l'utilisateur</a></td>";
                            } else {
                                echo "<td></td>";
                            }
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        <?php else:
            echo '<div class="message" style="background-color: #f44336;">Vous n\'avez pas accès à cette page.</div>';
            header("Refresh: 2; URL=index.php");
        endif;
        ?>
    <?php endif; ?>
</body>

</html>