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

$chat_with = isset($_GET['select']) ? $_GET['select'] : null;

$messages = [];
if ($chat_with) {
    $update_sql = $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = :chat_with AND receiver_id = :user_id AND is_read = 0");
    $update_sql->bindParam(':user_id', $_SESSION['id']);
    $update_sql->bindParam(':chat_with', $chat_with);
    $update_sql->execute();

    $sql = $db->prepare("SELECT * FROM messages WHERE (sender_id = :user_id AND receiver_id = :chat_with) OR (sender_id = :chat_with AND receiver_id = :user_id) ORDER BY created_at ASC");
    $sql->bindParam(':user_id', $_SESSION['id']);
    $sql->bindParam(':chat_with', $chat_with);
    $sql->execute();
    $messages = $sql->fetchAll(PDO::FETCH_ASSOC);
}

if (isset($_POST['send_message'])) {
    $sender_id = $_SESSION['id'];
    $receiver_id = $chat_with;
    $message = $_POST['message'];

    if (!empty($receiver_id) && !empty($message)) {
        $sql = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (:sender_id, :receiver_id, :message)");
        $sql->bindParam(':sender_id', $sender_id);
        $sql->bindParam(':receiver_id', $receiver_id);
        $sql->bindParam(':message', $message);
        $sql->execute();

        $message_id = $db->lastInsertId();

        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            $uploads_dir = 'uploads_mp/';
            if (!file_exists($uploads_dir)) {
                mkdir($uploads_dir, 0777, true);
            }
            foreach ($_FILES['attachments']['name'] as $key => $filename) {
                $tmp_name = $_FILES['attachments']['tmp_name'][$key];
                $file_error = $_FILES['attachments']['error'][$key];

                if ($file_error == 0) {
                    $unique_name = uniqid() . '_' . str_replace(' ', '_', basename($filename));
                    $file_path = $uploads_dir . $unique_name;

                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $file_sql = $db->prepare("INSERT INTO attachments (message_id, file_path, file_name) VALUES (:message_id, :file_path, :file_name)");
                        $file_sql->bindParam(':message_id', $message_id);
                        $file_sql->bindParam(':file_path', $file_path);
                        $file_sql->bindParam(':file_name', $filename);
                        $file_sql->execute();
                    }
                } else {
                    echo '<div style="text-align: center; color: red;">Erreur lors du téléchargement des fichiers.</div>';
                }
            }
        }

        header("Location: messages.php?select=" . $receiver_id);
        exit;
    } else {
        echo '<div style="text-align: center;">Veuillez remplir tous les champs.</div>';
    }
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
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages Privés</title>
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

        .content p {
            font-size: 16px;
            line-height: 1.5;
        }

        .form-container,
        .message-container {
            background-color: white;
            padding: 20px;
            margin: 10px;
            width: 60%;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .form-container form {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .form-container label {
            margin-bottom: 5px;
        }

        .form-container select,
        .form-container textarea {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 90%;
        }

        .form-container button {
            padding: 10px;
            background-color: var(--primary-color);
            color: var(--text-color);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 90%;
        }

        .form-container button:hover {
            background-color: var(--secondary-color);
        }

        .message {
            background-color: var(--primary-color);
            padding: 15px;
            color: var(--text-color);
            font-size: 18px;
            font-weight: bold;
            border-radius: 10px;
            text-align: center;
            width: 90%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            margin: auto;
            word-wrap: break-word;
        }

        .message-container {
            max-height: 400px;
            overflow-y: auto;
            width: 60%;
            margin: 20px 0;
        }

        .sender {
            font-weight: bold;
        }

        .time {
            font-size: 0.8em;
            color: #888;
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

            .form-container {
                width: 75%;
            }

            .message-container {
                width: 75%;
            }

            .message {
                width: 80%;
            }

            .form-container select,
            .form-container textarea {
                width: 100%;
            }

            .retour {
                left: 30%;
            }
        }

        .unread-count {
            background-color: red;
            color: var(--text-color);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            font-size: 12px;
            margin-left: 10px;
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
            <ul>
                <?php
                $sql = $db->query("SELECT id FROM gestion_utilisateurs");
                while ($row = $sql->fetch(PDO::FETCH_ASSOC)) {
                    if ($row['id'] != $_SESSION['id']) {
                        $unread_sql = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :user_id AND sender_id = :receiver_id AND is_read = 0");
                        $unread_sql->bindParam(':user_id', $_SESSION['id']);
                        $unread_sql->bindParam(':receiver_id', $row['id']);
                        $unread_sql->execute();
                        $unread_count = $unread_sql->fetchColumn();

                        echo '<li id="' . $row['id'] . '" onclick="window.location=\'messages.php?select=' . $row['id'] . '\'">';
                        echo $row['id'];
                        if ($unread_count > 0) {
                            echo ' <span class="unread-count">' . $unread_count . '</span>';
                        }
                        echo '</li>';
                    }
                }
                ?>
            </ul>
            <button onclick="location.href='index.php'" class="retour"
                style="position: absolute; bottom: 100px;">Retour</button>
        </div>
    </div>

    <div class="content">
        <?php if (!$chat_with): ?>
            <div style="text-align: center;">
                <h1>Bienvenue sur les messages privés</h1>
                <p>Choisissez un utilisateur pour commencer la conversation.</p>
            </div>
        <?php else: ?>
            <h1>Messages Privés</h1>

            <div style="text-align: center;">
                <strong><?php echo htmlspecialchars($chat_with); ?></strong>
            </div>

            <div class="form-container">
                <form action="messages.php?select=<?php echo htmlspecialchars($chat_with); ?>" method="POST"
                    enctype="multipart/form-data">
                    <input type="hidden" name="select" value="<?php echo htmlspecialchars($chat_with); ?>">
                    <label for="message">Message :</label><br>
                    <textarea name="message" id="message" rows="4" cols="50" required></textarea><br><br>

                    <label for="attachments">Pièces jointes :</label><br>
                    <input type="file" name="attachments[]" id="attachments" multiple><br><br>

                    <button type="submit" name="send_message">Envoyer</button>
                </form>
            </div>

            <div class="message-container">
                <?php
                if (isset($messages) && !empty($messages)) {
                    foreach ($messages as $message) {
                        echo '<div class="message">';
                        echo '<div class="sender">' . ($message['sender_id'] == $_SESSION['id'] ? 'Moi' : $message['sender_id']) . '</div>';
                        echo '<div>' . nl2br(htmlspecialchars($message['message'])) . '</div>';

                        $attachment_sql = $db->prepare("SELECT * FROM attachments WHERE message_id = :message_id");
                        $attachment_sql->bindParam(':message_id', $message['id']);
                        $attachment_sql->execute();
                        $attachments = $attachment_sql->fetchAll(PDO::FETCH_ASSOC);

                        if ($attachments) {
                            echo '<div class="attachments">';
                            foreach ($attachments as $attachment) {
                                echo '<a href="' . $attachment['file_path'] . '" download="' . htmlspecialchars($attachment['file_name']) . '">' . htmlspecialchars($attachment['file_name']) . '</a><br>';
                            }
                            echo '</div>';
                        }

                        echo '<div class="time">' . $message['created_at'] . '</div>';
                        echo '</div>';
                        echo '<br>';
                    }
                } else {
                    echo '<div class="message">Aucun message</div>';
                }
                ?>
            </div>
        <?php endif; ?>
    </div>
    <script>
        setInterval(function () {
            var receiverId = '<?php echo $chat_with; ?>';
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_messages.php?select=' + receiverId);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    document.querySelector('.message-container').innerHTML = xhr.responseText;
                }
            };
            xhr.send();
        }, 1000);

        window.onload = function () {
            var messageContainer = document.querySelector('.message-container');
            messageContainer.scrollTop = messageContainer.scrollHeight;
        };
    </script>

    <script>
        setInterval(function () {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_unread_messages.php');
            xhr.onload = function () {
                if (xhr.status === 200) {
                    var unread_messages = JSON.parse(xhr.responseText);
                    unread_messages.forEach(function (message) {
                        var notification = document.createElement("div");
                        notification.style.position = "fixed";
                        notification.style.top = "10px";
                        notification.style.right = "10px";
                        notification.style.background = "#4CAF50";
                        notification.style.color = "white";
                        notification.style.padding = "10px";
                        notification.style.marginBottom = "5px";
                        notification.style.borderRadius = "5px";
                        notification.innerHTML = "Vous avez reçu un message de " + message.sender_id;
                        document.body.appendChild(notification);
                        setTimeout(function () {
                            notification.remove();
                        }, 5000);

                        var audio = new Audio('sons/notification.wav');
                        audio.play();
                    });
                }
            };
            xhr.send();
        }, 1000);

        setInterval(function () {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_users.php');
            xhr.onload = function () {
                if (xhr.status === 200) {
                    document.querySelector('.sidebar ul').innerHTML = xhr.responseText;
                }
            };
            xhr.send();
        }, 1000);
    </script>
</body>

</html>