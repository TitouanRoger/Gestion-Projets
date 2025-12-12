<?php
// ============================================================
// AUTH.PHP - PAGE DE CONNEXION ET INSCRIPTION
// ============================================================
// Page principale d'authentification permettant:
// - Connexion avec email/mot de passe
// - Inscription avec validation du mot de passe
// - Réinitialisation du mot de passe via modale
// - Affichage des messages d'alerte (succès/erreur)
// ============================================================

// Vérifier si l'utilisateur est déjà connecté
require_once 'assets/php/secure_session.php';
secure_session_start();

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // Utilisateur déjà connecté, rediriger vers index.php
    header('Location: index.php');
    exit();
}

include 'assets/php/auth_messages.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>Gestion de Projets - Connexion/Inscription</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/footer.css">
</head>

<body>
    <!-- ============================ -->
    <!-- CONTENEUR PRINCIPAL -->
    <!-- ============================ -->
    <!-- Boîte centrée contenant les onglets -->
    <!-- et formulaires de connexion/inscription -->
    <div class="auth-container">
        <div class="auth-box">

            <?php echo $auth_messages_html; ?>


            <!-- Titre principal de l'application -->
            <h1 class="auth-title">Gestion de Projets</h1>
            <p class="auth-subtitle">Connectez-vous ou créez votre compte</p>

            <div class="tabs-header">
                <button class="tab-button active" data-tab="login">Connexion</button>
                <button class="tab-button" data-tab="register">Inscription</button>
            </div>


            <!-- ============================ -->
            <!-- FORMULAIRE DE CONNEXION -->
            <!-- ============================ -->
            <!-- Champs: email, mot de passe -->
            <!-- Bouton toggle pour afficher/masquer le mot de passe -->
            <!-- Lien vers modale de réinitialisation -->
            <form class="auth-form active" id="login" method="POST" action="assets/php/login.php">
                <div class="form-group">
                    <label for="login-email">Email</label>
                    <input type="email" id="login-email" name="email" placeholder="votre@email.com" required
                        value="<?php echo isset($_GET['prefill_email']) ? htmlspecialchars($_GET['prefill_email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="login-password">Mot de passe</label>
                    <div class="password-container">
                        <input type="password" id="login-password" name="password" placeholder="********" required>
                        <button type="button" class="password-toggle" data-target="login-password"
                            aria-label="Afficher/Masquer le mot de passe">👁️</button>
                    </div>
                </div>

                <div class="form-group remember-me-group"
                    style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                    <label for="remember-me"
                        style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px;">
                        <input type="checkbox" id="remember-me" name="remember_me" value="1"
                            style="width:16px;height:16px;"> Se souvenir de moi
                    </label>
                </div>

                <button type="submit" class="auth-button" name="submit_login">Se connecter</button>

                <a href="#" class="forgot-password" id="show-forgot-modal">Mot de passe oublié ?</a>
            </form>


            <!-- ============================ -->
            <!-- FORMULAIRE D'INSCRIPTION -->
            <!-- ============================ -->
            <!-- Champs: prénom, nom, email, mot de passe, confirmation -->
            <!-- Validation côté client et serveur (8 car., maj, min, chiffre, spécial) -->
            <form class="auth-form" id="register" method="POST" action="assets/php/register.php" style="display: none;">
                <div class="form-row">
                    <div class="form-group half-width">
                        <label for="register-firstname">Prénom <span style="color: #e74c3c;">*</span></label>
                        <input type="text" id="register-firstname" name="prenom" placeholder="Jean" required>
                    </div>
                    <div class="form-group half-width">
                        <label for="register-lastname">Nom <span style="color: #e74c3c;">*</span></label>
                        <input type="text" id="register-lastname" name="nom" placeholder="Dupont" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="register-email">Email <span style="color: #e74c3c;">*</span></label>
                    <input type="email" id="register-email" name="email" placeholder="votre@email.com" required>
                </div>

                <div class="form-group">
                    <label for="register-password">Mot de passe <span style="color: #e74c3c;">*</span></label>
                    <div class="password-container">
                        <input type="password" id="register-password" name="password" placeholder="********" required>
                        <button type="button" class="password-toggle" data-target="register-password"
                            aria-label="Afficher/Masquer le mot de passe">👁️</button>
                    </div>
                    <p class="password-hint">Minimum 8 caractères, une majuscule, une minuscule, un chiffre et un
                        caractère spécial</p>
                </div>

                <div class="form-group">
                    <label for="register-confirm-password">Confirmer le mot de passe <span
                            style="color: #e74c3c;">*</span></label>
                    <div class="password-container">
                        <input type="password" id="register-confirm-password" name="password_confirm"
                            placeholder="********" required>
                        <button type="button" class="password-toggle" data-target="register-confirm-password"
                            aria-label="Afficher/Masquer le mot de passe">👁️</button>
                    </div>
                </div>

                <button type="submit" class="auth-button" name="submit_register">S'inscrire</button>
            </form>
        </div>
    </div>


    <!-- ============================ -->
    <!-- MODALE MOT DE PASSE OUBLIÉ -->
    <!-- ============================ -->
    <!-- Formulaire pour demander un email de réinitialisation -->
    <!-- Gérée par assets/php/forgot_password.php -->
    <div class="modal-overlay" id="forgot-modal-overlay">
        <div class="modal-content">
            <h2 class="modal-title">Réinitialiser le mot de passe</h2>
            <button class="modal-close" id="close-forgot-modal" aria-label="Fermer la modale">×</button>

            <p class="modal-subtitle">Entrez votre email pour recevoir un lien de réinitialisation</p>

            <form class="modal-form" id="forgot-form" method="POST" action="assets/php/forgot_password.php">
                <div class="form-group">
                    <label for="forgot-email">Email</label>
                    <input type="email" id="forgot-email" name="email" placeholder="votre@email.com" required>
                </div>
                <button type="submit" class="auth-button" name="submit_forgot">Envoyer le lien de
                    réinitialisation</button>
            </form>
        </div>
    </div>


    <!-- ============================ -->
    <!-- FOOTER -->
    <!-- ============================ -->
    <!-- Copyright et lien vers politique de confidentialité -->
    <footer>
        <div class="footer-content"
            style="text-align:center;margin-top:20px;color:#64748b;font-size:13px;justify-content:center">
            <p>© <?php echo date('Y'); ?> Gestion Projets. Tous droits réservés. · <a href="privacy.php">Politique
                    de confidentialité</a></p>
        </div>
    </footer>

    <!-- ============================ -->
    <!-- JAVASCRIPT -->
    <!-- ============================ -->
    <!-- Gestion des onglets, toggles de mot de passe, modale -->
    <script src="assets/javascript/auth.js"></script>
</body>

</html>