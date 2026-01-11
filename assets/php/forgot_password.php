<?php
// ============================================================
// FORGOT_PASSWORD.PHP - DEMANDE DE RÉINITIALISATION
// ============================================================
// Reçoit l'email (POST) depuis la modale de auth.php
// - Si l'utilisateur existe: génère un token + expiration (1h),
//   stocke en BDD et envoie un email avec lien de reset.
// - Si l'utilisateur n'existe pas: renvoie un message générique.
// - En cas d'échec d'email: informe l'utilisateur sans exposer d'infos.
// ============================================================
session_start();
// db_connect.php inclut auth_logic.php, ce qui charge le .env et se connecte à la BDD ($pdo)
require 'db_connect.php';

if (isset($_POST['submit_forgot'])) {
    // Nettoyage et validation de l'email
    $email = htmlspecialchars(trim($_POST['email']));
    $redirect_url = '../../auth.php'; // Redirige toujours vers la page d'authentification

    if (empty($email)) {
        header("Location: $redirect_url?error=Veuillez entrer une adresse email.");
        exit();
    }

    try {
        // 1. Vérification de l'existence de l'utilisateur
        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // 2. Traitement uniquement si l'utilisateur existe
        if ($user) {
            $user_id = $user['id'];

            // Génération d'un jeton sécurisé de 64 caractères hexadécimaux
            $token = bin2hex(random_bytes(32));

            // Expiration du jeton dans 1 heure (3600 secondes)
            $expires_at = date("Y-m-d H:i:s", time() + 3600);

            // 3. Stockage du jeton et de son expiration dans la table 'utilisateurs'
            $stmt = $pdo->prepare("UPDATE utilisateurs SET reset_token = ?, token_expires_at = ? WHERE id = ?");
            $stmt->execute([$token, $expires_at, $user_id]);

            // 4. Préparation et envoi de l'email

            // Construction du lien de réinitialisation complet
            // NOTE : Remplacez VOTRE-DOMAINE.COM par l'URL réelle de votre projet en production
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $reset_link = "$protocol://$host/reset_password.php?token=" . $token;

            // Récupération des données d'envoi du .env
            $mail_from_name = $_ENV['MAIL_FROM_NAME'] ?? 'Gestion de Projets';
            $mail_from_address = $_ENV['MAIL_FROM_ADDRESS'] ?? 'ne-pas-repondre@localhost';


            $to = $email;
            $subject = "Réinitialisation de votre mot de passe - Gestion de Projets";
            $message = "Bonjour,\n\n";
            $message .= "Vous avez demandé la réinitialisation de votre mot de passe.\n\n";
            $message .= "Cliquez sur le lien suivant pour définir un nouveau mot de passe. Ce lien expirera dans 1 heure (à $expires_at) :\n\n";
            $message .= $reset_link . "\n\n";
            $message .= "Si vous n'êtes pas à l'origine de cette demande, veuillez ignorer cet email.\n\n";
            $message .= "L'équipe $mail_from_name.";

            $headers = "From: $mail_from_name <$mail_from_address>\r\n";
            $headers .= "Reply-To: $mail_from_address\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            // Utilisation de la fonction mail() native de PHP
            if (mail($to, $subject, $message, $headers)) {
                // Succès : on redirige avec un message générique pour des raisons de sécurité (ne pas confirmer l'existence du mail)
                header("Location: $redirect_url?success=Si l'adresse email existe, un lien de réinitialisation vous a été envoyé.");
                exit();
            } else {
                // Erreur d'envoi du mail (souvent un problème de configuration d'hébergeur)
                // Laisse le jeton en BDD mais signale l'échec de l'envoi
                header("Location: $redirect_url?error=Erreur technique lors de l'envoi de l'email. Veuillez réessayer plus tard.");
                exit();
            }
        }

        // Si l'utilisateur n'est pas trouvé, on affiche le même message générique pour la sécurité
        header("Location: $redirect_url?success=Si l'adresse email existe, un lien de réinitialisation vous a été envoyé.");
        exit();

    } catch (\PDOException $e) {
        // Erreur BDD (connexion ou requête)
        // Vous pouvez logguer $e->getMessage() ici pour le débogage
        header("Location: $redirect_url?error=Erreur technique du serveur lors de l'accès à la base de données.");
        exit();
    }
} else {
    // Accès direct au script sans POST
    header("Location: $redirect_url");
    exit();
}
?>