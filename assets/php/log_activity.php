<?php
// ============================================================
// LOG_ACTIVITY.PHP - JOURNALISATION DES ACTIONS
// ============================================================
// Fournit:
// - log_activity($pdo, $user_id, $action, $description): trace une action
// - purge_old_logs($pdo): supprime logs > 12 mois
// - purge_user_logs($pdo, $user_id): supprime tous les logs d'un utilisateur
// Usage: appelé après login/register, ajout membre, etc. Ne bloque pas
// l'appli en cas d'erreur (try/catch + error_log).
// ============================================================
/**
 * Système de journalisation des activités
 * Enregistre les actions importantes dans la base de données
 */

function log_activity(PDO $pdo, ?int $user_id, string $action, ?string $description = null): void
{
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Limiter la taille du user agent
        if ($user_agent && strlen($user_agent) > 255) {
            $user_agent = substr($user_agent, 0, 255);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO logs_activite (utilisateur_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $action,
            $description,
            $ip_address,
            $user_agent
        ]);
    } catch (PDOException $e) {
        // Ne pas bloquer l'application si le logging échoue
        error_log("Erreur lors de l'enregistrement du log: " . $e->getMessage());
    }
}

/**
 * Purge automatique des logs de plus de 12 mois
 */
function purge_old_logs(PDO $pdo): void
{
    try {
        $stmt = $pdo->prepare("DELETE FROM logs_activite WHERE timestamp < DATE_SUB(NOW(), INTERVAL 12 MONTH)");
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la purge des logs anciens: " . $e->getMessage());
    }
}

/**
 * Purge tous les logs d'un utilisateur spécifique
 */
function purge_user_logs(PDO $pdo, int $user_id): void
{
    try {
        $stmt = $pdo->prepare("DELETE FROM logs_activite WHERE utilisateur_id = ?");
        $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        error_log("Erreur lors de la purge des logs utilisateur: " . $e->getMessage());
    }
}
?>
