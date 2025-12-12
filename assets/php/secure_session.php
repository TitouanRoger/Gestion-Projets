<?php
// ============================================================
// SECURE_SESSION.PHP - GESTION SÉCURISÉE DES SESSIONS
// ============================================================
// Configuration et sécurisation complète des sessions PHP:
// - Paramètres de cookie sécurisés (HttpOnly, SameSite=Strict)
// - Régénération d'ID après login pour prévenir fixation
// - Validation de l'empreinte utilisateur (User-Agent, IP)
// - Timeout d'inactivité configurable (30 minutes par défaut)
// - Protection contre le vol de session
// ============================================================

/**
 * Démarre une session sécurisée avec tous les paramètres de sécurité
 * À appeler en début de chaque page protégée
 */
function secure_session_start(): void
{
    // Si la session est déjà démarrée, ne rien faire
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // ============================================================
    // CONFIGURATION DES PARAMÈTRES DE COOKIE SÉCURISÉS
    // ============================================================
    $secure = true; // true en production avec HTTPS
    $httponly = true; // Empêche l'accès JavaScript aux cookies de session
    $samesite = 'Lax'; // Lax au lieu de Strict pour compatibilité redirections

    // Durée de vie du cookie: 0 = jusqu'à fermeture du navigateur
    // Ou 30 jours si "Se souvenir de moi" est activé (géré dans login.php)
    $lifetime = 0;

    // Configuration des paramètres de cookie
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '', // Laissez vide pour auto-détection
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => $samesite
    ]);

    // ============================================================
    // PARAMÈTRES DE SESSION SUPPLÉMENTAIRES
    // ============================================================
    // Nom de session personnalisé (évite le PHPSESSID par défaut)
    session_name('GESTION_PROJET_SID');

    // Utiliser uniquement les cookies pour stocker l'ID de session
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    // Démarrer la session
    session_start();
}

/**
 * Régénère l'ID de session après authentification réussie
 * Prévient les attaques de fixation de session
 * 
 * @param bool $delete_old_session Supprimer l'ancienne session
 */
function regenerate_session_id(bool $delete_old_session = true): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id($delete_old_session);
    }
}

/**
 * Crée une empreinte de sécurité pour l'utilisateur
 * Basée sur User-Agent et IP pour détecter le vol de session
 * 
 * @return string Hash de l'empreinte
 */
function get_user_fingerprint(): string
{
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Utiliser les 3 premiers octets de l'IP pour tolérer les changements de proxy
    $ip_parts = explode('.', $ip_address);
    $ip_prefix = implode('.', array_slice($ip_parts, 0, 3));

    return hash('sha256', $user_agent . $ip_prefix);
}

/**
 * Valide l'empreinte de session actuelle
 * Détecte les tentatives de vol de session
 * 
 * @return bool True si l'empreinte est valide
 */
function validate_session_fingerprint(): bool
{
    if (!isset($_SESSION['fingerprint'])) {
        return false;
    }

    $current_fingerprint = get_user_fingerprint();
    return $_SESSION['fingerprint'] === $current_fingerprint;
}

/**
 * Initialise l'empreinte de session après login
 * À appeler immédiatement après authentification réussie
 */
function set_session_fingerprint(): void
{
    $_SESSION['fingerprint'] = get_user_fingerprint();
    $_SESSION['created_at'] = time();
    $_SESSION['last_activity'] = time();
}

/**
 * Vérifie le timeout d'inactivité de la session
 * 
 * @param int $timeout_seconds Durée d'inactivité maximale (défaut: 30 minutes)
 * @return bool True si la session a expiré
 */
function is_session_expired(int $timeout_seconds = 1800): bool
{
    if (!isset($_SESSION['last_activity'])) {
        return false;
    }

    // Si "Se souvenir de moi" est activé, prolonger le timeout à 30 jours
    if (isset($_SESSION['remember_me']) && $_SESSION['remember_me'] === true) {
        $timeout_seconds = 30 * 24 * 3600; // 30 jours
    }

    return (time() - $_SESSION['last_activity']) > $timeout_seconds;
}

/**
 * Met à jour le timestamp de dernière activité
 */
function update_session_activity(): void
{
    $_SESSION['last_activity'] = time();
}

/**
 * Vérifie la validité complète de la session
 * - Empreinte utilisateur
 * - Timeout d'inactivité
 * - Existence de user_id
 * 
 * @param int $timeout_seconds Durée d'inactivité maximale
 * @return bool True si la session est valide
 */
function validate_session(int $timeout_seconds = 1800): bool
{
    // Vérifier si l'utilisateur est connecté
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Vérifier l'empreinte de sécurité
    if (!validate_session_fingerprint()) {
        session_destroy();
        return false;
    }

    // Vérifier le timeout d'inactivité
    if (is_session_expired($timeout_seconds)) {
        session_destroy();
        return false;
    }

    // Mettre à jour l'activité
    update_session_activity();

    return true;
}

/**
 * Détruit complètement la session de manière sécurisée
 */
function secure_session_destroy(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Effacer toutes les variables de session
        $_SESSION = [];

        // Détruire le cookie de session
        if (isset($_COOKIE[session_name()])) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Détruire la session
        session_destroy();
    }
}

/**
 * Vérifie et protège une page nécessitant authentification
 * Redirige vers la page de login si session invalide
 * 
 * @param string $login_url URL de la page de connexion
 * @param int $timeout_seconds Durée d'inactivité maximale
 */
function require_login(string $login_url = '../../auth.php', int $timeout_seconds = 1800): void
{
    secure_session_start();

    if (!validate_session($timeout_seconds)) {
        $reason = '';

        if (is_session_expired($timeout_seconds)) {
            $reason = '?error=' . urlencode('Votre session a expiré. Veuillez vous reconnecter.');
        } elseif (!validate_session_fingerprint()) {
            $reason = '?error=' . urlencode('Session invalide. Veuillez vous reconnecter.');
        } else {
            $reason = '?error=' . urlencode('Vous devez être connecté pour accéder à cette page.');
        }

        header("Location: $login_url$reason");
        exit();
    }
}

/**
 * Rafraîchit le cookie de session pour "Se souvenir de moi"
 * À appeler périodiquement sur les pages actives
 */
function refresh_remember_me_cookie(): void
{
    if (isset($_SESSION['remember_me']) && $_SESSION['remember_me'] === true) {
        $lifetime = 30 * 24 * 3600; // 30 jours
        $secure = true; // true avec HTTPS en production
        setcookie(
            session_name(),
            session_id(),
            time() + $lifetime,
            '/',
            '',
            $secure,
            true
        );
    }
}

/**
 * Configure les en-têtes de sécurité pour les sessions
 * À appeler après secure_session_start()
 */
function set_session_security_headers(): void
{
    // Empêcher la mise en cache des pages protégées
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');
}
?>