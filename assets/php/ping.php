<?php
// ============================================================
// PING.PHP - KEEP-ALIVE DE SESSION
// ============================================================
// Endpoint minimal pour rafraîchir l'activité de session côté serveur.
// Utilisé par le système d'inactivité JS (bannière "Rester connecté").
// Répond 204 No Content en cas de succès, 500 en cas d'erreur.
// ============================================================

declare(strict_types=1);

session_start();
require_once __DIR__ . '/session_security.php';

try {
    // Met à jour l'activité (timestamp) sans produire de sortie
    session_touch_activity();
    http_response_code(204);
} catch (Throwable $e) {
    // En cas d'erreur, retourner 500 sans fuite d'information
    http_response_code(500);
}
