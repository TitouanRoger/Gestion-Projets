<?php
// ============================================================
// LOGOUT.PHP - DÉCONNEXION UTILISATEUR SÉCURISÉE
// ============================================================
// - Journalise la déconnexion si user_id présent
// - Utilise secure_session_destroy() pour nettoyage complet
// - Détruit la session et redirige vers auth.php
// - Supporte raison=timeout pour message spécifique
// - Ajoute un fallback HTML/JS si headers déjà envoyés
// ============================================================
require 'secure_session.php';
secure_session_start();
require 'db_connect.php';
require 'log_activity.php';

// Log de déconnexion avant de détruire la session
if (isset($_SESSION['user_id'])) {
    log_activity($pdo, $_SESSION['user_id'], 'logout', 'Déconnexion');
}

// Destruction sécurisée de la session (gère cookies et nettoyage)
secure_session_destroy();

// Construire l'URL de redirection de manière robuste
$reason = isset($_GET['reason']) ? $_GET['reason'] : null;
$msg = $reason === 'timeout' ? 'Session expirée pour inactivité.' : 'Vous avez été déconnecté avec succès.';
$redirectUrl = '../../auth.php?success=' . urlencode($msg);

// Redirection HTTP
header('Location: ' . $redirectUrl);
// Fallback HTML + JS au cas où les en-têtes seraient déjà envoyés
echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl, ENT_QUOTES) . '"></head>';
echo '<body><script>window.location.href = ' . json_encode($redirectUrl) . ';</script></body></html>';
exit();
?>