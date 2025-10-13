<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur 404 - Page non trouvée</title>
    <link rel="icon" href="images/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap');

        body {
            font-family: 'Roboto', sans-serif;
        }

        :root {
            --background-color: #d1d5db;
            --primary-color: #fa8619;
            --secondary-color: #e69017;
            --text-color: #1f2937;
        }
    </style>
</head>

<body class="bg-[var(--background-color)] text-[var(--text-color)] flex items-center justify-center min-h-screen">
    <div class="text-center">
        <img alt="Logo" class="mx-auto mb-8" width="400" src="images/logo.jpg" />
        <h1 class="text-6xl font-bold text-[var(--primary-color)] mb-4">404</h1>
        <p class="text-2xl mb-8">Oups! La page que vous cherchez n'existe pas.</p>
        <a class="text-white bg-[var(--primary-color)] hover:bg-[var(--secondary-color)] font-bold py-2 px-4 rounded"
            href="index.php">Retour à l'accueil</a>
    </div>
</body>

</html>