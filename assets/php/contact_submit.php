<?php
// ============================================================
// CONTACT_SUBMIT.PHP - TRAITEMENT DU FORMULAIRE CONTACT
// ============================================================
// - Valide les champs (format email, longueurs)
// - Enregistre le message en base (contact_messages)
// - Journalise l'action
// - Envoie un email au support (adresse lue via env())
// - Redirige vers contact.php avec succès/erreur
// ============================================================
session_start();
require 'db_connect.php';
require 'log_activity.php';

$redirect = '../../contact.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit_contact'])) {
    header("Location: {$redirect}");
    exit();
}

$prenom = trim($_POST['prenom'] ?? '');
$nom = trim($_POST['nom'] ?? '');
$email = trim($_POST['email'] ?? '');
$sujet = trim($_POST['sujet'] ?? '');
$message = trim($_POST['message'] ?? '');
$userId = $_SESSION['user_id'] ?? null;

// Validation basique
if ($prenom === '' || $nom === '' || $email === '' || $sujet === '' || $message === '') {
    header("Location: {$redirect}?error=" . urlencode('Tous les champs sont requis.'));
    exit();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: {$redirect}?error=" . urlencode("Email invalide."));
    exit();
}
if (mb_strlen($sujet) > 150 || mb_strlen($message) > 2000) {
    header("Location: {$redirect}?error=" . urlencode("Longueur maximale dépassée."));
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO contact_messages (utilisateur_id, nom, email, sujet, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, ($prenom ? ($prenom . ' ') : '') . $nom, $email, $sujet, $message]);

    // Log de l'envoi de message de contact
    log_activity($pdo, $userId, 'contact_message', 'Message de contact envoyé');

    // Charger l'email de destination depuis .env
    require_once __DIR__ . '/env.php';
    $to = env('CONTACT_EMAIL');

    // Construire et envoyer l'email
    $subject = '[Contact] ' . $sujet;
    $body = "Prénom: {$prenom}\nNom: {$nom}\nEmail: {$email}\n\nMessage:\n{$message}";
    $headers = "From: " . $_ENV['MAIL_FROM_ADDRESS'] . "\r\n" .
        "Reply-To: " . $email . "\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n";
    @mail($to, $subject, $body, $headers);

    header("Location: {$redirect}?success=" . urlencode("Merci ! Votre message a été envoyé."));
    exit();
} catch (PDOException $e) {
    error_log('Erreur lors de l\'enregistrement du message de contact: ' . $e->getMessage());
    header("Location: {$redirect}?error=" . urlencode("Erreur technique. Veuillez réessayer plus tard."));
    exit();
}
