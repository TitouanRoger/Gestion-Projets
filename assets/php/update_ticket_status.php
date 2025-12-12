<?php
// ============================================================
// UPDATE_TICKET_STATUS.PHP - MAJ DU STATUT D'UN TICKET
// ============================================================
// Traite les demandes de mise à jour du statut d'un ticket:
// - Valide les entrées (ticket_id, project_id, statut)
// - Vérifie que l'utilisateur est le créateur du projet
// - Met à jour le statut: 'nouveau' | 'approuvé' | 'refusé'
// ============================================================
require_once 'secure_session.php';
secure_session_start();
require 'db_connect.php';

if (!validate_session()) {
    header("Location: ../../auth.php");
    exit();
}

$projet_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);

// Fonction de redirection simplifiée pour éviter la répétition
function redirect_with_error($projet_id, $message)
{
    // Si l'ID du projet est invalide, renvoyer à l'accueil
    $target_url = $projet_id ? "../../projet.php?id={$projet_id}&tab=tickets&error=" : "../../index.php?error=";
    header("Location: " . $target_url . urlencode($message));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {

    $user_id = $_SESSION['user_id'];
    $ticket_id = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
    $nouveau_statut = trim($_POST['statut'] ?? '');

    // 1. Validation de base
    if (!$ticket_id || !$projet_id || empty($nouveau_statut)) {
        redirect_with_error($projet_id, "Données manquantes pour la mise à jour.");
    }

    // 2. Validation du nouveau statut (selon les statuts restants)
    $valid_statuts = ['nouveau', 'approuvé', 'refusé'];
    if (!in_array($nouveau_statut, $valid_statuts)) {
        redirect_with_error($projet_id, "Statut invalide.");
    }

    // 3. Vérification de la permission (Seul le créateur du projet peut modifier le statut)
    try {
        // Cette vérification garantit que l'utilisateur est bien le chef du projet
        $stmt_check = $pdo->prepare("
            SELECT p.createur_id
            FROM projets p
            WHERE p.id = ? AND p.createur_id = ?
        ");
        $stmt_check->execute([$projet_id, $user_id]);

        if ($stmt_check->rowCount() == 0) {
            redirect_with_error($projet_id, "Permission refusée. Seul le chef de projet peut modifier le statut.");
        }

        // 4. Mise à jour du statut
        $stmt_update = $pdo->prepare("
            UPDATE tickets
            SET statut = ?
            WHERE id = ? AND projet_id = ?
        ");

        if ($stmt_update->execute([$nouveau_statut, $ticket_id, $projet_id])) {
            header("Location: ../../projet.php?id={$projet_id}&tab=tickets&success=" . urlencode("Statut du ticket mis à jour avec succès."));
            exit();
        } else {
            // Si l'exécution échoue (e.g. contrainte de BDD non respectée)
            redirect_with_error($projet_id, "Erreur lors de la mise à jour du statut (PDO).");
        }

    } catch (\PDOException $e) {
        // 🚨 IMPORTANT POUR LE DÉBOGAGE 🚨
        // Remplacez cette ligne par 'error_log("...")' pour la production.
        // Pour l'instant, on affiche l'erreur SQL pour trouver la cause.
        $debug_message = "Erreur SQL: " . $e->getMessage();
        error_log("Erreur BDD maj statut: " . $e->getMessage());
        redirect_with_error($projet_id, "Erreur technique de la base de données. (" . $debug_message . ")");
    }

} else {
    header("Location: ../../index.php");
    exit();
}