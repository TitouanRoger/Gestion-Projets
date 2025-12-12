<?php
// ============================================================
// DB_CONNECT.PHP - CONNEXION À LA BASE DE DONNÉES
// ============================================================
// Établit une connexion PDO à MySQL en utilisant:
// - Variables d'environnement du fichier .env (via auth_logic.php)
// - Options PDO: mode erreur EXCEPTION, fetch ASSOC, prepares natifs
// - Charset UTF8MB4 pour support des émojis et caractères spéciaux
// ============================================================

require_once 'auth_logic.php'; // Charge les variables du .env

// --- PARAMÈTRES DE CONNEXION CHARGÉS DE .env ---
$host = $_ENV['DB_HOST'] ?? 'localhost';
$db = $_ENV['DB_NAME'] ?? 'gestion_projet';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
// ============================================================
// OPTIONS PDO
// ============================================================
// - ERRMODE_EXCEPTION: lance des exceptions en cas d'erreur SQL
// - FETCH_ASSOC: retourne tableaux associatifs par défaut
// - EMULATE_PREPARES=false: utilise vraies requêtes préparées MySQL
// ============================================================
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
// ============================================================
// GESTION DES ERREURS
// ============================================================
// En cas d'échec de connexion, affiche message générique
// (évite d'exposer les détails techniques aux utilisateurs)
// ============================================================
} catch (\PDOException $e) {
    die("Erreur de connexion à la base de données. Vérifiez votre fichier .env et vos identifiants.");
}
?>