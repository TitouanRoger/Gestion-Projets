<?php
// ============================================================
// LOGIN.PHP - TRAITEMENT DE LA CONNEXION
// ============================================================
// Traite le formulaire de connexion (auth.php):
// - Vérifie email/mot de passe en base de données
// - Compare le hash avec password_verify()
// - Crée la session utilisateur si authentification réussie
// - Log toutes les tentatives (réussies et échouées)
// ============================================================

// Démarrer le buffer de sortie pour éviter les problèmes de headers
ob_start();

// ============================================================
// LOGIN.PHP - CONNEXION UTILISATEUR
// ============================================================
// - Récupère email/mot de passe (POST)
// - Vérifie les identifiants (password_verify)
// - Démarre la session sécurisée et journalise en cas de succès
// - Redirige vers index.php ou renvoie vers auth.php avec erreur
// ============================================================
require 'secure_session.php';
secure_session_start();
require 'db_connect.php';
require 'log_activity.php';

if (isset($_POST['submit_login'])) {
    // ============================================================
    // 1. RÉCUPÉRATION DES DONNÉES DU FORMULAIRE
    // ============================================================
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password'];

    $redirect_url_fail = '../../auth.php'; // En cas d'échec, on retourne à auth

    // 1. Récupérer l'utilisateur par email
    // ============================================================
    // 2. VÉRIFICATION EN BASE DE DONNÉES
    // ============================================================
    // - SELECT id, prenom, mot_de_passe WHERE email = ?
    // - password_verify() compare le mot de passe saisi avec le hash
    // - Si succès: crée la session et redirige vers index.php
    // - Si échec: log de tentative échouée et message d'erreur
    // ============================================================
    try {
        $stmt = $pdo->prepare("SELECT id, prenom, mot_de_passe FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            // 2. Mot de passe vérifié ! Configuration de la session sécurisée

            // Régénérer l'ID de session pour prévenir la fixation
            regenerate_session_id(true);

            // Définir les variables de session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_prenom'] = $user['prenom'];
            $_SESSION['user_email'] = $email; // Préremplissage du contact

            // Créer l'empreinte de sécurité
            set_session_fingerprint();

            // Option 'Se souvenir de moi' : prolonger la durée du cookie de session
            if (!empty($_POST['remember_me'])) {
                $_SESSION['remember_me'] = true;
                refresh_remember_me_cookie();
            } else {
                $_SESSION['remember_me'] = false;
            }

            // Log de connexion réussie
            log_activity($pdo, $user['id'], 'login', 'Connexion réussie');

            // 3. Redirection vers le tableau de bord
            $redirect_url = '../../index.php';

            // Vider le buffer et forcer la redirection
            ob_end_clean();

            // Forcer la redirection avec plusieurs méthodes
            if (!headers_sent()) {
                header("Location: $redirect_url");
                exit();
            }

            // Fallback JavaScript au cas où les headers sont déjà envoyés
            echo '<!DOCTYPE html><html><head>';
            echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirect_url) . '">';
            echo '</head><body>';
            echo '<script>window.location.href = ' . json_encode($redirect_url) . ';</script>';
            echo '<p>Redirection en cours... <a href="' . htmlspecialchars($redirect_url) . '">Cliquez ici</a> si vous n\'êtes pas redirigé.</p>';
            echo '</body></html>';
            exit();

        } else {
            // Utilisateur non trouvé ou mot de passe incorrect
            log_activity($pdo, null, 'login_failed', "Tentative échouée pour: {$email}");
            header("Location: $redirect_url_fail?error=Email ou mot de passe incorrect.");
            exit();
        }

    } catch (\PDOException $e) {
        header("Location: $redirect_url_fail?error=Erreur technique lors de la connexion.");
        exit();
    }

} else {
    // ============================================================
    // PROTECTION CONTRE ACCÈS DIRECT
    // ============================================================
    // Redirige vers auth.php si le script est appelé sans POST
    // ============================================================
    // Redirection si accès direct au script
    header("Location: $redirect_url_fail");
    exit();
}
?>