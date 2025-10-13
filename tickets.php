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
$project = getenv('PROJECT');

try {
    $db = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['id'];
$sql = $db->prepare("SELECT * FROM gestion_utilisateurs WHERE id = :id");
$sql->bindParam(':id', $user_id);
$sql->execute();
$user = $sql->fetch();

if (isset($_POST['create_ticket'])) {
    $description = $_POST['description'];
    $sql = $db->prepare("INSERT INTO tickets (creator_id, description) VALUES (:creator_id, :description)");
    $sql->bindParam(':creator_id', $user_id);
    $sql->bindParam(':description', $description);
    $sql->execute();
    header("Location: tickets.php");
    exit;
}

if (isset($_POST['resolve_ticket'])) {
    $ticket_id = $_POST['ticket_id'];
    $sql = $db->prepare("UPDATE tickets SET `read` = 1 WHERE id = :ticket_id");
    $sql->bindParam(':ticket_id', $ticket_id);
    $sql->execute();
    header("Location: tickets.php");
    exit;
}

$query = $db->query("SELECT * FROM tickets");
$tickets = $query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets</title>
    <link rel="icon" href="images/logo.jpg">
    <style>
        :root {
            --background-color: #d1d5db;
            --primary-color: #fa8619;
            --secondary-color: #e69017;
            --text-color: #ffffff;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: lightgrey;
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

        .toggle-sidebar {
            position: absolute;
            right: 3%;
        }

        .toggle-sidebar:hover {
            cursor: pointer;
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

        textarea {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 90%;
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

            .sidebar .logo {
                width: 150px;
            }

            .content h1 {
                font-size: 32px;
            }

            .content p {
                font-size: 18px;
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

    <div class="sidebar">
        <div class="onglets">
            <div class="toggle-sidebar">&#9776;</div>
            <img src="images/logo.jpg" alt="Logo" class="logo">
            <h2><?php echo $project; ?></h2>
            <a href="telecharger_dossier_projet.php">Télécharger le projet</a>
            <br><br>
            <a href="versions.php">Versions</a>
            <br><br>
            <a href="documents.php">Documents</a>
            <button onclick="location.href='index.php'" class="retour"
                style="position: absolute; bottom: 100px;">Retour</button>
        </div>
    </div>

    <div class="content">
        <h1>Tickets</h1>
        <form method="post" action="">
            <label for="description">Description du ticket :</label>
            <textarea name="description" id="description" required></textarea>
            <button type="submit" name="create_ticket">Créer un ticket</button>
        </form>
        <br>
        <h1>Liste des tickets</h1>
        <table class="table" border="1">
            <tr>
                <th>Créé par</th>
                <th>Description</th>
                <th>Statut</th>
                <?php if ($user['role'] === 'administrateur'): ?>
                    <th>Action</th>
                <?php endif; ?>
            </tr>

            <?php
            foreach ($tickets as $ticket) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($ticket['creator_id']) . "</td>";
                echo "<td>" . htmlspecialchars($ticket['description']) . "</td>";
                echo "<td>" . ($ticket['read'] == 1 ? "Vu" : "Pas Vu") . "</td>";

                if ($user['role'] === 'administrateur') {
                    if ($ticket['read'] == 0) {
                        echo "<td>";
                        echo "<form method='post' action='' style='display:inline;'>";
                        echo "<input type='hidden' name='ticket_id' value='" . htmlspecialchars($ticket['id']) . "' />";
                        echo "<button type='submit' name='resolve_ticket'>Vu</button>";
                        echo "</form>";
                        echo "</td>";
                    } else {
                        echo "<td></td>";
                    }
                }

                echo "</tr>";
            }
            ?>
        </table>
    </div>
</body>

</html>