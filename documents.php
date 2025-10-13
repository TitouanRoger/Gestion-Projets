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
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents</title>
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

        .content h2 {
            font-weight: bold;
            font-size: 20px;
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

        .button-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            padding: 20px;
        }

        .document-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 10px;
            padding: 5px;
            border: 1px solid white;
            border-radius: 5px;
            background-color: white;
            grid-column: span 1;
        }

        .content {
            overflow-x: hidden;
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

            .content h2 {
                font-size: 24px;
            }

            .content p {
                font-size: 18px;
            }

            .retour {
                left: 30%;
            }

            .button-grid {
                gap: 1px;
                padding: 10px;
                overflow-wrap: break-word;
            }

            .documents {
                display: grid;
                grid-template-columns: repeat(1, 1fr);
                gap: 20px;
                padding: 20px;
            }

            .button {
                padding: 10px;
                font-size: 14px;
                width: 75px;
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

            const params = new URLSearchParams(window.location.search);
            const page = params.get('page');

            showDocuments();
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

        function changeContent(content, section) {
            const toggleButton = document.querySelector('.toggle-sidebar');
            const sidebar = document.querySelector('.sidebar');
            document.getElementById('content').innerHTML = content;

            history.pushState(null, null, `?page=${section}`);

            if (window.innerWidth <= 435) {
                sidebar.style.transform = 'translateX(-90%)';
            }
        }

        function showDocuments() {
            fetch('get_folders.php')
                .then(response => response.json())
                .then(folders => {
                    const html = `
                    <div class="section">
                        <h1>Documents</h1>
                        <br>
                        <div class="button-grid">
                            ${folders.map(folder => `
                                <button class="button" onclick="showFichiers('${folder}');">${folder}</button>
                            `).join('')}
                        </div>
                    </div>
                `;
                    changeContent(html, 'documents');
                })
                .catch(error => console.error(error));
        }

        function showFichiers(folder) {
            const user = <?php echo json_encode($user); ?>;
            fetch(`get_folders.php?dossier=${folder}`)
                .then(response => response.json())
                .then(files => {
                    const html = `
                        <div class="section">
                            <h1>Documents</h1>
                            <h2>${folder}</h2>
                            <br>
                            <div style="display: flex; justify-content: center; align-items: center;">
                                <button class="button" style="margin: 0 10px;" onclick="showDocuments()">Retour</button>
                                ${(user.role === 'administrateur' || user.role === 'responsable') ?
                            `<button class="button" style="margin: 0 10px;" onclick="addFile('${folder}')">Ajouter</button>` :
                            ''}
                                ${(user.role === 'rédacteur') && (folder === 'Cahier des charges' || folder === 'Comités Projet' || folder === 'Guide utilisateur') ?
                            `<button class="button" style="margin: 0 10px;" onclick="addFile('${folder}')">Ajouter</button>` :
                            ''}
                                ${(user.role === 'recetteur') && (folder === 'Rapports Propositions' || folder === 'Recettes') ?
                            `<button class="button" style="margin: 0 10px;" onclick="addFile('${folder}')">Ajouter</button>` :
                            ''}
                            </div>
                            <br>
                            <div class="button-grid documents">
                                ${files.map(file => {
                                return `
                                        <div class="document-item">
                                            <a href="documents/${folder}/${file}" target="_blank">${file}</a>
                                            ${(user.role === 'administrateur' || user.role === 'responsable') ?
                                        `<button class="button" onclick="return confirm('Etes-vous sûr de vouloir supprimer le fichier ${file} ?') && deleteFile('${folder}', '${file}');">Supprimer</button>` :
                                        ''}
                                            ${(user.role === 'rédacteur') && (folder === 'Cahier des charges' || folder === 'Comités Projet' || folder === 'Guide utilisateur') ?
                                        `<button class="button" onclick="return confirm('Etes-vous sûr de vouloir supprimer le fichier ${file} ?') && deleteFile('${folder}', '${file}');">Supprimer</button>` :
                                        ''}
                                            ${(user.role === 'recetteur') && (folder === 'Rapports Propositions' || folder === 'Recettes') ?
                                        `<button class="button" onclick="return confirm('Etes-vous sûr de vouloir supprimer le fichier ${file} ?') && deleteFile('${folder}', '${file}');">Supprimer</button>` :
                                        ''}
                                        </div>
                                    `;
                            }).join('')}
                            </div>
                        </div>
                    `;
                    changeContent(html, 'fichiers');
                })
                .catch(error => console.error(error));
        }

        function addFile(folder) {
            const input = document.createElement('input');
            input.type = 'file';

            input.addEventListener('change', function () {
                const file = input.files[0];
                const formData = new FormData();
                formData.append('file', file);
                formData.append('dossier', folder);

                fetch('add_file.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        showFichiers(folder);
                    })
                    .catch(error => console.error(error));
            });

            input.click();
        }

        function deleteFile(folder, file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('folder', folder);

            fetch(`delete_file.php`, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    showFichiers(folder);
                })
                .catch(error => console.error(error));
        }
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
            <button onclick="location.href='index.php'" class="retour"
                style="position: absolute; bottom: 100px;">Retour</button>
        </div>
    </div>

    <div class="content" id="content">
        <!-- Default content can go here -->
    </div>
</body>

</html>