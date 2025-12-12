<?php
// ============================================================
// ENV.PHP - UTILITAIRE .ENV MINIMALISTE
// ============================================================
// Fournit:
// - load_env($baseDir): lit un fichier .env (KEY=VALUE)
// - env($key, $default): récupère une valeur avec cache en mémoire
// Emplacement: .env à la racine de gestion_projet/
// Commentaires (#) et lignes vides ignorés.
// ============================================================

function load_env(string $baseDir): array {
    $envPath = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
    $vars = [];
    if (!is_file($envPath)) {
        return $vars;
    }
    $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) { continue; }
        $pos = strpos($line, '=');
        if ($pos === false) { continue; }
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        if ($key !== '') { $vars[$key] = $val; }
    }
    return $vars;
}

function env(string $key, ?string $default = null): ?string {
    static $cache = null;
    if ($cache === null) {
        $cache = load_env(dirname(__DIR__, 2)); // remonte jusqu'à gestion_projet/
    }
    return $cache[$key] ?? $default;
}
?>
