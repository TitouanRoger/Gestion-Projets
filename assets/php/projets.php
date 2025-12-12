<?php
// ============================================================
// PROJETS.PHP - CRÉATION DE PROJET
// ============================================================
// Traite le formulaire de création d'un projet depuis index.php:
// - Vérifie l'authentification
// - Valide le nom du projet (obligatoire)
// - Insère (nom, description nullable, createur_id)
// - Redirige vers l'onglet Projets avec un message (success/error)
// ============================================================
require_once 'secure_session.php';
secure_session_start();
require 'db_connect.php'; // Connexion à la BDD ($pdo)

// Assurez-vous que l'utilisateur est connecté et que la session est valide
if (!validate_session()) {
    header("Location: ../../auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fonction pour rediriger vers la page des projets avec un message
function redirect_to_projets($message, $status = 'success')
{
    // Le paramètre 'from=projet' permet de s'assurer que l'index.php affiche bien l'onglet Projets
    header("Location: ../../index.php?section=projets&from=projet&{$status}=" . urlencode($message));
    exit();
}

// --- TRAITEMENT DE LA CRÉATION DE PROJET ---
if (isset($_POST['create_project'])) {
    $nom_projet = trim($_POST['nom_projet'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($nom_projet)) {
        redirect_to_projets("Le nom du projet est obligatoire.", 'error');
    }

    // Si la description est vide, on la définit comme NULL.
    $description = empty($description) ? null : $description;

    try {
        // Insertion du nouveau projet.
        $stmt = $pdo->prepare("INSERT INTO projets (nom_projet, description, createur_id) VALUES (?, ?, ?)");
        $stmt->execute([$nom_projet, $description, $user_id]);

        redirect_to_projets("Le projet '{$nom_projet}' a été créé avec succès.");

    } catch (\PDOException $e) {
        // En cas d'erreur de base de données
        error_log("Erreur lors de la création de projet pour l'utilisateur {$user_id}: " . $e->getMessage());
        redirect_to_projets("Erreur technique lors de la création du projet. Veuillez contacter l'administrateur.", 'error');
    }
}

// Si on arrive ici sans action POST, revenir simplement à la page
header("Location: ../../index.php?section=projets");
exit();
?>