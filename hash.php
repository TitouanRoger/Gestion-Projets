<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasher mon mot de passe</title>
</head>

<body>
    <form method="post">
        <label for="password">Mot de passe</label>
        <input type="password" name="password" id="password">
        <button type="submit">Hasher</button>
    </form>
    <?php
    if (isset($_POST['password'])) {
        $password = $_POST['password'];
        $hash = password_hash($password, PASSWORD_DEFAULT);
        echo "Votre mot de passe en hash est : <code>$hash</code>";
    }
    ?>
</body>

</html>