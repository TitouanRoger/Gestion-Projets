<?php
// ============================================================
// TICKETS_COMMITTEE.PHP - MARQUEUR DE COMITÉ
// ============================================================
// Ajoute un séparateur/repère dans la liste des tickets pour
// démarquer une nouvelle phase de comité. Réservé au créateur
// du projet. Journalise l'action.
// ============================================================
require_once 'secure_session.php';
secure_session_start();
require 'db_connect.php';
require 'log_activity.php';

if (!validate_session()) {
    header('Location: ../../auth.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['add_committee'])) {
    header('Location: ../../index.php');
    exit();
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);

if (!$project_id) {
    header('Location: ../../index.php?error=' . urlencode('Projet invalide.'));
    exit();
}

try {
    // Vérifier que l'utilisateur est bien le créateur du projet
    $stmt = $pdo->prepare('SELECT createur_id FROM projets WHERE id = ?');
    $stmt->execute([$project_id]);
    $ownerId = (int) $stmt->fetchColumn();

    if ($ownerId !== $user_id) {
        header('Location: ../../projet.php?id=' . $project_id . '&tab=tickets&error=' . urlencode('Action réservée au chef de projet.'));
        exit();
    }

    // Insérer un marqueur de comité
    $stmtIns = $pdo->prepare('INSERT INTO tickets_commites (projet_id, created_by) VALUES (?, ?)');
    $stmtIns->execute([$project_id, $user_id]);

    // Log activité
    log_activity($pdo, $user_id, 'committee_add', 'Création d\'un marqueur de comité pour le projet #' . $project_id);

    header('Location: ../../projet.php?id=' . $project_id . '&tab=tickets&success=' . urlencode('Séparation de comité ajoutée.'));
    exit();
} catch (\PDOException $e) {
    error_log('Erreur ajout comité: ' . $e->getMessage());
    header('Location: ../../projet.php?id=' . $project_id . '&tab=tickets&error=' . urlencode('Erreur technique lors de la création de la séparation.'));
    exit();
}
