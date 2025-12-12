<?php
// Page d'erreur 404
http_response_code(404);
?><!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>Page introuvable</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/404.css">
</head>

<body>
    <div class="card">
        <div class="code">Erreur 404</div>
        <h1>Page introuvable</h1>
        <p>La page que vous cherchez n'existe pas ou a été déplacée.<br>Vérifiez l'URL ou revenez au tableau de bord.
        </p>
        <div class="actions">
            <a class="button primary" href="index.php">Retour au dashboard</a>
            <a class="button secondary" href="auth.php">Se connecter</a>
        </div>
    </div>
</body>

</html>