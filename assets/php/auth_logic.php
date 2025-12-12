<?php
// ============================================================
// AUTH_LOGIC.PHP - CHARGEMENT DU .ENV
// ============================================================
// Fournit loadEnv($path) pour charger les variables d'environnement
// depuis un fichier .env à la racine du projet. Compatible sans
// dépendances externes. Définit également des constantes si absentes.
// ============================================================
// --- DÉFINITION DE CONSTANTES POUR LA COMPATIBILITÉ ---
if (!defined('FILE_IGNORE_EMPTY_LINES')) {
    define('FILE_IGNORE_EMPTY_LINES', 4);
}
if (!defined('FILE_SKIP_NEW_LINES')) {
    define('FILE_SKIP_NEW_LINES', 2);
}

/**
 * Charge les variables d'environnement à partir du fichier .env.
 * @param string $path Chemin vers le répertoire contenant le fichier .env.
 */
function loadEnv(string $path): void
{
    $filePath = $path . '/.env';
    if (!file_exists($filePath)) {
        die("Erreur de configuration : Le fichier .env est introuvable à l'emplacement spécifié.");
    }

    $lines = file($filePath, FILE_IGNORE_EMPTY_LINES | FILE_SKIP_NEW_LINES);

    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || strpos($line, '=') === false) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);

        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// 1. Définir le chemin vers le répertoire racine
$rootPath = __DIR__ . '/../..';

// 2. Charger les variables d'environnement
loadEnv($rootPath);
?>