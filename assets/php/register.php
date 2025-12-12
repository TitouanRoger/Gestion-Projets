<?php
// ============================================================
// REGISTER.PHP - TRAITEMENT DE L'INSCRIPTION
// ============================================================
// Traite le formulaire d'inscription (auth.php):
// - Valide tous les champs (prénom, nom, email, mot de passe)
// - Vérifie la complexité du mot de passe (8 car., maj, min, chiffre, spécial)
// - Vérifie que l'email n'existe pas déjà
// - Hash le mot de passe avec bcrypt (PASSWORD_BCRYPT)
// - INSERT dans la table utilisateurs
// - Log de création de compte
// ============================================================

require 'db_connect.php';
require 'log_activity.php';

if (isset($_POST['submit_register'])) {
    // ============================================================
    // 1. RÉCUPÉRATION ET NETTOYAGE DES DONNÉES
    // ============================================================
    // htmlspecialchars() prévient les injections XSS
    // trim() supprime les espaces inutiles
    // 1. Nettoyage et récupération des données
    $prenom = htmlspecialchars(trim($_POST['prenom']));
    $nom = htmlspecialchars(trim($_POST['nom']));
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    // URL de retour vers le fichier auth.php
    $redirect_url = '../../auth.php';

    // 2. Validation de base
    if (empty($prenom) || empty($nom) || empty($email) || empty($password) || empty($password_confirm)) {
        header("Location: $redirect_url?tab=register&error=Veuillez remplir tous les champs.");
        exit();
    }
    if ($password !== $password_confirm) {
        header("Location: $redirect_url?tab=register&error=Les mots de passe ne correspondent pas.");
        exit();
    }

    // Validation de la complexité du mot de passe
    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^a-zA-Z\d]/', $password)) {
        header("Location: $redirect_url?tab=register&error=Le mot de passe doit contenir minimum 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.");
        exit();
    }


    // ============================================================
    // 3. VÉRIFICATION DE L'EMAIL UNIQUE
    // ============================================================
    // SELECT id WHERE email = ? pour détecter les doublons
    // ============================================================
    try {
        // 3. Vérification de l'existence de l'email
        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            // Email déjà utilisé, retour avec erreur
            header("Location: $redirect_url?tab=register&error=Cette adresse email est déjà utilisée.");
            exit();
        }

        // ============================================================
        // 4. HACHAGE DU MOT DE PASSE AVEC ARGON2ID
        // ============================================================
        // Argon2id est l'algorithme recommandé en 2025:
        // - Résistant aux attaques GPU/ASIC
        // - Protection contre timing et side-channel
        // - Mémoire-coût adaptatif
        // Ne JAMAIS stocker les mots de passe en clair
        // ============================================================

        // 4. Hachage sécurisé du mot de passe avec Argon2id
        $hashed_password = password_hash($password, PASSWORD_ARGON2ID);

        // 5. Insertion dans la BDD
        $stmt = $pdo->prepare("INSERT INTO utilisateurs (prenom, nom, email, mot_de_passe) VALUES (?, ?, ?, ?)");

        if ($stmt->execute([$prenom, $nom, $email, $hashed_password])) {
            // ============================================================
            // 6. SUCCÈS - LOG ET REDIRECTION
            // ============================================================
            // - lastInsertId() récupère l'ID du nouvel utilisateur
            // - log_activity() enregistre l'événement
            // - Redirige vers login avec email pré-rempli
            // ============================================================
            // Récupérer l'ID du nouvel utilisateur
            $new_user_id = $pdo->lastInsertId();

            // Log de création de compte
            log_activity($pdo, $new_user_id, 'register', 'Nouveau compte créé');

            // Succès ! Rediriger vers la connexion avec email prérempli
            header("Location: $redirect_url?tab=login&success=" . urlencode("Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter.") . "&prefill_email=" . urlencode($email));
            exit();
        } else {
            header("Location: $redirect_url?tab=register&error=Erreur lors de l'enregistrement de l'utilisateur.");
            exit();
        }

    } catch (\PDOException $e) {
        // En cas d'erreur BDD
        header("Location: $redirect_url?tab=register&error=Erreur technique du serveur lors de l'accès à la base de données.");
        exit();
    }

} else {
    // Redirection si accès direct au script
    header("Location: $redirect_url");
    exit();
}
?>