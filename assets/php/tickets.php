<?php
// ============================================================
// TICKETS.PHP - CRÉATION DE TICKETS
// ============================================================
// Traite la création d'un ticket depuis la page projet:
// - Valide les champs requis (titre, description, type)
// - Vérifie le type parmi une liste contrôlée
// - Vérifie l'accès au projet (créateur ou membre)
// - Insère le ticket avec statut initial 'nouveau'
// ============================================================
require_once 'secure_session.php';
secure_session_start();
require 'db_connect.php';

if (!validate_session()) {
    // Redirection si non connecté ou session invalide
    header("Location: ../../auth.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_ticket'])) {

    $user_id = $_SESSION['user_id'];
    $projet_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = trim($_POST['type'] ?? '');

    // 1. Validation de base
    if (!$projet_id || empty($titre) || empty($description) || empty($type)) {
        header("Location: ../../projet.php?id={$projet_id}&tab=tickets&error=" . urlencode("Tous les champs obligatoires doivent être remplis."));
        exit();
    }

    // 2. Validation du type
    $valid_types = ['bug critique', 'bug', 'amélioration', 'idée'];
    if (!in_array($type, $valid_types)) {
        header("Location: ../../projet.php?id={$projet_id}&tab=tickets&error=" . urlencode("Type de ticket invalide."));
        exit();
    }

    // 3. Vérification de l'accès au projet (Sécurité)
    try {
        // Vérifier si l'utilisateur est le créateur OU un membre
        $stmt_check = $pdo->prepare("
            SELECT COUNT(*) 
            FROM projets p
            LEFT JOIN projet_membres pm ON p.id = pm.projet_id
            WHERE p.id = ? AND (p.createur_id = ? OR pm.utilisateur_id = ?)
        ");
        $stmt_check->execute([$projet_id, $user_id, $user_id]);

        if ($stmt_check->fetchColumn() == 0) {
            header("Location: ../../index.php?error=" . urlencode("Accès au projet refusé."));
            exit();
        }

        // 4. Insertion du nouveau ticket (Statut par défaut: 'nouveau')
        $stmt = $pdo->prepare("
            INSERT INTO tickets (projet_id, titre, description, type, createur_id, statut) 
            VALUES (?, ?, ?, ?, ?, 'nouveau')
        ");

        if ($stmt->execute([$projet_id, $titre, $description, $type, $user_id])) {
            header("Location: ../../projet.php?id={$projet_id}&tab=tickets&success=" . urlencode("Ticket créé avec succès !"));
            exit();
        } else {
            header("Location: ../../projet.php?id={$projet_id}&tab=tickets&error=" . urlencode("Erreur lors de la création du ticket."));
            exit();
        }

    } catch (\PDOException $e) {
        error_log("Erreur de BDD lors de la création du ticket: " . $e->getMessage());
        header("Location: ../../projet.php?id={$projet_id}&tab=tickets&error=" . urlencode("Erreur technique de la base de données."));
        exit();
    }

} else {
    // Si l'accès est direct sans formulaire
    header("Location: ../../index.php");
    exit();
}