<?php
// ============================================================
// RESET_PASSWORD.PHP - RÉINITIALISATION DU MOT DE PASSE
// ============================================================
// Page accessible via lien email avec jeton (token)
// Permet de:
// - Vérifier la validité du jeton (non expiré)
// - Saisir un nouveau mot de passe (avec critères de sécurité)
// - Mettre à jour le mot de passe en BDD et invalider le jeton
// ============================================================

require 'assets/php/db_connect.php'; // Charge le .env et se connecte à la BDD ($pdo)
require 'assets/php/log_activity.php'; // Système de journalisation

$message = null;
$token = $_GET['token'] ?? null;
$valid_token = false;
$user_id = null; // Utilisateur trouvé

// ============================================================
// 1. VÉRIFICATION DU JETON
// ============================================================
// - Jeton doit être présent dans l'URL (?token=...)
// - Recherche en BDD: reset_token correspond ET token_expires_at > NOW()
// - Si valide: $valid_token = true, récupère $user_id
// - Si invalide/expiré: message d'erreur
// ============================================================

// --- VÉRIFICATION DU JETON ---
if (!$token) {
    $message = "Lien de réinitialisation incomplet ou manquant.";
} else {
    try {
        // 1. Recherche de l'utilisateur par jeton valide et non expiré
        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE reset_token = ? AND token_expires_at > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $valid_token = true;
            $user_id = $user['id'];
        } else {
            // Le jeton est invalide ou expiré
            $message = "Le lien de réinitialisation est invalide ou a expiré. Veuillez refaire une demande.";
        }
    } catch (\PDOException $e) {
        $message = "Erreur technique du serveur lors de la vérification du jeton.";
    }
}

// ============================================================
// 2. TRAITEMENT DU FORMULAIRE (POST)
// ============================================================
// - Vérifie correspondance entre password et password_confirm
// - Valide critères de sécurité (8 car., maj, min, chiffre, spécial)
// - Hash le nouveau mot de passe avec Argon2id
// - UPDATE utilisateurs: mot_de_passe + suppression du jeton (sécurité)
// - Redirige vers auth.php avec message de succès
// ============================================================

// --- TRAITEMENT DU FORMULAIRE DE NOUVEAU MOT DE PASSE (POST) ---
if ($valid_token && isset($_POST['submit_reset'])) {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['password_confirm'];

    // Validation des mots de passe
    if ($new_password !== $confirm_password) {
        $message = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($new_password) < 8 || !preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/\d/', $new_password) || !preg_match('/[^a-zA-Z\d]/', $new_password)) {
        $message = "Le mot de passe ne respecte pas les critères de sécurité (8 caractères, majuscule, minuscule, chiffre, spécial).";
    } else {
        try {
            // Hash avec Argon2id (algorithme recommandé)
            $hashed_password = password_hash($new_password, PASSWORD_ARGON2ID);

            // Mise à jour du mot de passe et suppression du jeton pour empêcher la réutilisation
            $stmt = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ?, reset_token = NULL, token_expires_at = NULL WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);

            // Log de la réinitialisation du mot de passe
            log_activity($pdo, $user_id, 'password_reset', 'Mot de passe réinitialisé via lien oublié');

            header("Location: auth.php?success=Votre mot de passe a été réinitialisé avec succès. Vous pouvez vous connecter.");
            exit();

        } catch (\PDOException $e) {
            $message = "Erreur technique lors de la mise à jour du mot de passe.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>Réinitialisation du Mot de Passe</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/reset_password.css">
</head>

<body>
    <!-- ============================ -->
    <!-- FORMULAIRE DE RÉINITIALISATION -->
    <!-- ============================ -->
    <!-- Deux modes d'affichage: -->
    <!-- - Jeton invalide: message d'erreur + lien retour -->
    <!-- - Jeton valide: formulaire nouveau mot de passe -->
    <div class="reset-box">

        <?php if (!$valid_token): ?>
            <h1 class="reset-title-error">
                <?php echo $token ? "Lien invalide" : "Erreur"; ?>
            </h1>
            <p class="reset-subtitle-error">
                <?php echo htmlspecialchars($message ?: "Une erreur est survenue lors de la vérification du lien."); ?>
            </p>
            <a href="auth.php" class="auth-button">Retour à la connexion</a>

        <?php else: ?>
            <!-- Formulaire de saisie nouveau mot de passe -->
            <h1 class="auth-title">Nouveau Mot de Passe</h1>
            <p class="auth-subtitle">Définissez votre nouveau mot de passe sécurisé.</p>

            <form class="auth-form active" method="POST"
                action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>">

                <?php if ($message): ?>
                    <div class='message-alert error'><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="new-password">Nouveau Mot de Passe <span style="color: #e74c3c;">*</span></label>
                    <div class="password-container">
                        <input type="password" id="new-password" name="password" placeholder="********" required>
                        <button type="button" class="password-toggle" data-target="new-password"
                            aria-label="Afficher/Masquer le mot de passe">👁️</button>
                    </div>
                    <p class="password-hint">Minimum 8 caractères, une majuscule, une minuscule, un chiffre et un caractère
                        spécial</p>
                </div>

                <div class="form-group">
                    <label for="confirm-password">Confirmer le Mot de Passe <span style="color: #e74c3c;">*</span></label>
                    <div class="password-container">
                        <input type="password" id="confirm-password" name="password_confirm" placeholder="********"
                            required>
                        <button type="button" class="password-toggle" data-target="confirm-password"
                            aria-label="Afficher/Masquer le mot de passe">👁️</button>
                    </div>
                </div>

                <button type="submit" class="auth-button" name="submit_reset">Changer le Mot de Passe</button>
            </form>
        <?php endif; ?>
        <!-- ============================ -->
        <!-- FIN FORMULAIRE -->
        <!-- ============================ -->
    </div>

    <footer>
        <div class="footer-content" style="text-align:center;margin-top:20px;color:#64748b;font-size:13px">
            <p>© <?php echo date('Y'); ?> Gestion Projets. Tous droits réservés. · <a href="privacy.php">Politique de
                    confidentialité</a></p>
        </div>
    </footer>
    <script src="assets/javascript/reset_password.js"></script>
</body>

</html>