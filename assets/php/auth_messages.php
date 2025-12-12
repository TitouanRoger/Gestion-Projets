<?php
// ============================================================
// AUTH_MESSAGES.PHP - MESSAGES D'AUTHENTIFICATION
// ============================================================
// Construit le HTML des messages d'alerte (succès/erreur)
// à partir des paramètres de l'URL (GET: success, error).
// Sécurise le contenu via htmlspecialchars().
// Utilisation: inclus au début de auth.php pour afficher les alertes.
// ============================================================

$auth_messages_html = '';

// Si un message de succès est présent dans l'URL
if (isset($_GET['success'])) {
    $message = htmlspecialchars($_GET['success']);
    $auth_messages_html .= "<div class='message-alert success'>$message</div>";
}

// Si un message d'erreur est présent dans l'URL
if (isset($_GET['error'])) {
    $message = htmlspecialchars($_GET['error']);
    $auth_messages_html .= "<div class='message-alert error'>$message</div>";
}
?>