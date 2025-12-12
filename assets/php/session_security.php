<?php
// ============================================================
// SESSION_SECURITY.PHP - SÉCURITÉ DE SESSION
// ============================================================
// À inclure juste après session_start() sur les pages protégées.
// Fournit:
// - session_is_expired(limit): vérifie l'inactivité côté serveur
// - session_touch_activity(): met à jour le timestamp d'activité
// - enforce_inactivity_timeout(limit, logout, update): redirige si expiré
// La redirection passe par logout.php pour centraliser le nettoyage.
// ============================================================

if (!function_exists('session_is_expired')) {
    function session_is_expired(int $limitSeconds): bool
    {
        if (!isset($_SESSION) || !isset($_SESSION['user_id']))
            return false;
        if (!isset($_SESSION['last_activity']))
            return false;
        return (time() - (int) $_SESSION['last_activity']) > $limitSeconds;
    }
}

if (!function_exists('session_touch_activity')) {
    function session_touch_activity(): void
    {
        if (isset($_SESSION)) {
            $_SESSION['last_activity'] = time();
        }
    }
}

if (!function_exists('enforce_inactivity_timeout')) {
    function enforce_inactivity_timeout(int $limitSeconds, string $logoutPathRelative, bool $updateOnHit = true): void
    {
        if (!isset($_SESSION) || !isset($_SESSION['user_id']))
            return;
        if (session_is_expired($limitSeconds)) {
            // Nettoyage et redirection centralisée via logout.php
            session_unset();
            session_destroy();
            // Redirection vers le logout (qui gère message et robustesse de redirect)
            $redirect = $logoutPathRelative . (strpos($logoutPathRelative, '?') === false ? '?' : '&') . 'reason=timeout';
            header('Location: ' . $redirect);
            echo '<!DOCTYPE html><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirect, ENT_QUOTES) . '"><script>window.location.href=' . json_encode($redirect) . ';</script>';
            exit();
        }
        if ($updateOnHit) {
            session_touch_activity();
        }
    }
}
