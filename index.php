<?php
// ============================================================================
// PAGE PRINCIPALE DU DASHBOARD
// ============================================================================
// Affiche la liste des projets et les paramètres utilisateur
// Accessible uniquement aux utilisateurs connectés

require_once 'assets/php/secure_session.php';
require 'assets/php/db_connect.php';

// Démarrer la session sécurisée
secure_session_start();

// Vérification de l'authentification avec timeout de 30 minutes (ou 30 jours si "remember me")
$timeout = 30 * 60; // 30 minutes par défaut
if (isset($_SESSION['remember_me']) && $_SESSION['remember_me']) {
    $timeout = 30 * 24 * 3600; // 30 jours
}

// Vérifier la session et rediriger si invalide
if (!validate_session($timeout)) {
    header("Location: auth.php?error=" . urlencode("Veuillez vous connecter pour accéder à cette page."));
    exit();
}

$user_id = $_SESSION['user_id'];

// Vérifier que le compte existe toujours en base de données
try {
    $stmt_check = $pdo->prepare("SELECT id FROM utilisateurs WHERE id = ?");
    $stmt_check->execute([$user_id]);
    if (!$stmt_check->fetch()) {
        // Le compte n'existe plus, détruire la session
        secure_session_destroy();
        header("Location: auth.php?error=" . urlencode("Votre compte n'existe plus. Veuillez contacter l'administrateur."));
        exit();
    }
} catch (\PDOException $e) {
    // En cas d'erreur BDD, on continue mais on log l'erreur
    error_log("Erreur vérification compte: " . $e->getMessage());
}

// ============================================================================
// RÉCUPÉRATION DES DONNÉES UTILISATEUR
// ============================================================================
try {
    $stmt = $pdo->prepare("SELECT prenom, nom, email FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $user_data = ['prenom' => 'Erreur', 'nom' => 'BDD', 'email' => 'erreur@bdd.com'];
}

// ============================================================================
// GESTION DES MESSAGES D'ALERTE ET SECTION ACTIVE
// ============================================================================
$alert_message = $_GET['success'] ?? $_GET['error'] ?? null;
$alert_status = isset($_GET['success']) ? 'success' : (isset($_GET['error']) ? 'error' : null);

// Section initiale par défaut
$initial_section = 'projets';

// Si la section est explicitement demandée, on la respecte en priorité
if (isset($_GET['section'])) {
    $initial_section = $_GET['section'];
} else {
    // Sinon, on déduit la section selon la provenance et les messages
    if ($alert_message) {
        if (isset($_GET['from']) && $_GET['from'] === 'projet') {
            $initial_section = 'projets';
        } elseif (!isset($_GET['from']) && (isset($_GET['success']) || isset($_GET['error']))) {
            $initial_section = 'parametres';
        }
    }
}

// Mise à jour des données en session
$_SESSION['user_prenom'] = $user_data['prenom'] ?? '';
$_SESSION['user_nom'] = $user_data['nom'] ?? '';

// ============================================================================
// RÉCUPÉRATION DES PROJETS DE L'UTILISATEUR
// ============================================================================
// Récupère tous les projets où l'utilisateur est créateur OU membre

$projets = [];
$project_member_counts = [];

try {
    // Requête UNION pour récupérer projets créés ET projets où l'utilisateur est membre
    $final_query = "
        (
            SELECT 
                p.id, 
                p.nom_projet, 
                p.description, 
                'Chef de projet' AS user_role
            FROM projets p
            WHERE p.createur_id = ?
        )
        UNION DISTINCT
        (
            SELECT 
                p.id, 
                p.nom_projet, 
                p.description, 
                pm.role AS user_role
            FROM projets p
            JOIN projet_membres pm ON p.id = pm.projet_id
            WHERE pm.utilisateur_id = ?
        )
        ORDER BY id DESC
    ";

    $stmt = $pdo->prepare($final_query);
    $stmt->execute([$user_id, $user_id]);
    $projets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Compte le nombre de membres pour chaque projet
    if (!empty($projets)) {
        $project_ids = array_column($projets, 'id');
        $placeholders = str_repeat('?,', count($project_ids) - 1) . '?';

        // Compte les membres ajoutés (hors créateur)
        $stmt_count = $pdo->prepare("
            SELECT projet_id, COUNT(*) AS member_count 
            FROM projet_membres 
            WHERE projet_id IN ($placeholders) 
            GROUP BY projet_id
        ");
        $stmt_count->execute($project_ids);

        // +1 pour inclure le créateur dans le total
        while ($row = $stmt_count->fetch(PDO::FETCH_ASSOC)) {
            $project_member_counts[$row['projet_id']] = (int) $row['member_count'] + 1;
        }

        // Projets sans membres ajoutés = seulement le créateur (1)
        foreach ($projets as $projet) {
            if (!isset($project_member_counts[$projet['id']])) {
                $project_member_counts[$projet['id']] = 1;
            }
        }
    }

} catch (\PDOException $e) {
    error_log("Erreur de BDD lors de la récupération des projets/membres: " . $e->getMessage());
    $projets = [];
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="robots" content="index,follow">
    <meta name="description"
        content="Gestion Projets – créez, suivez et collaborez sur vos projets, tâches, tickets, documents et messages en équipe.">
    <meta name="keywords"
        content="gestion de projet, collaboration, tâches, tickets, documents, messages, équipe, dashboard">
    <link rel="canonical"
        href="<?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/index.php'); ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Dashboard – Gestion Projets">
    <meta property="og:description"
        content="Gérez vos projets et collaborez avec votre équipe: tâches, tickets, documents et messages.">
    <meta property="og:url"
        content="<?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/index.php'); ?>">
    <meta property="og:image"
        content="<?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']); ?>/assets/img/favicon.svg">
    <meta property="og:locale" content="fr_FR">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="Dashboard – Gestion Projets">
    <meta name="twitter:description" content="Gérez vos projets et collaborez avec votre équipe.">
    <meta name="twitter:image"
        content="<?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']); ?>/assets/img/favicon.svg">

    <!-- Schema.org JSON-LD: WebSite + SearchAction -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "WebSite",
            "name": "Gestion Projets",
            "url": "<?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/'); ?>",
            "inLanguage": "fr-FR",
            "description": "Plateforme de gestion de projets: tâches, tickets, documents et messages.",
            "potentialAction": {
                "@type": "SearchAction",
                "target": "<?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/index.php'); ?>?q={search_term_string}",
                "query-input": "required name=search_term_string"
            }
        }
        </script>
    <title>Gestion Projets – Dashboard et Collaboration</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="assets/css/projets.css">
    <link rel="stylesheet" href="assets/css/parametres.css">
</head>

<body>
    <div class="app-container">

        <aside class="sidebar">
            <div class="sidebar-header">
                <span class="logo-icon">📊</span> Gestion Projets
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item <?php echo $initial_section === 'projets' ? 'active' : ''; ?>">
                        <a href="#" class="nav-link" data-section="projets">
                            <span class="icon">📁</span> Projets
                        </a>
                    </li>
                    <li class="nav-item <?php echo $initial_section === 'parametres' ? 'active' : ''; ?>">
                        <a href="#" class="nav-link" data-section="parametres">
                            <span class="icon">⚙️</span> Paramètres
                        </a>
                    </li>
                </ul>
                <div class="sidebar-footer">
                    <a href="assets/php/logout.php" class="logout-link">
                        <span class="icon">→</span> Déconnexion
                    </a>
                </div>
        </aside>

        <main class="main-content">

            <section id="projets"
                class="dashboard-section <?php echo $initial_section === 'projets' ? 'active' : ''; ?>">
                <header class="main-header">
                    <div>
                        <h1>Mes Projets</h1>
                        <p class="subtitle">Gérez vos projets et collaborez avec votre équipe</p>
                    </div>
                    <button class="new-project-button" id="open-new-project-modal">+ Nouveau projet</button>
                </header>

                <?php if ($alert_message && $initial_section === 'projets'): ?>
                    <div class="message-alert <?php echo $alert_status; ?>"><?php echo htmlspecialchars($alert_message); ?>
                    </div>
                <?php endif; ?>

                <div class="project-grid">
                    <?php if (empty($projets)): ?>
                        <p class="no-projects-message">Vous n'avez pas encore de projet. Cliquez sur "Nouveau projet" pour
                            commencer !</p>
                    <?php else: ?>
                        <?php foreach ($projets as $projet):
                            // Récupère le nombre de membres calculé
                            $member_count = $project_member_counts[$projet['id']] ?? 1;
                            $user_role_display = $projet['user_role'];
                            // Formatage pour les rôles de membres (ex: developpeur -> Développeur)
                            if ($user_role_display !== 'Chef de projet') {
                                $user_role_display = ucfirst(str_replace('_', ' ', $user_role_display));
                            }
                            ?>
                            <div class="project-card">
                                <div class="card-header">
                                    <span class="project-icon">📁</span>
                                    <span
                                        class="tag <?php echo $projet['user_role'] === 'Chef de projet' ? 'proprietary' : 'member-role'; ?>">
                                        <?php echo htmlspecialchars($user_role_display); ?>
                                    </span>
                                </div>
                                <h2 class="project-title"><?php echo htmlspecialchars($projet['nom_projet']); ?></h2>
                                <p class="project-description">
                                    <?php
                                    // Affiche 'Aucune description' si le champ est vide ou NULL
                                    echo htmlspecialchars($projet['description'] ?: 'Aucune description');
                                    ?>
                                </p>
                                <div class="card-footer">
                                    <span class="members">👤 <?php echo $member_count; ?>
                                        membre<?php echo $member_count > 1 ? 's' : ''; ?></span>
                                    <a href="projet.php?id=<?php echo htmlspecialchars($projet['id']); ?>"
                                        class="open-button">Ouvrir</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section id="parametres"
                class="dashboard-section <?php echo $initial_section === 'parametres' ? 'active' : ''; ?>">
                <header class="main-header">
                    <div>
                        <h1>Paramètres</h1>
                        <p class="subtitle">Gérez vos informations personnelles et préférences</p>
                    </div>
                </header>

                <?php if ($alert_message && $initial_section === 'parametres'): ?>
                    <div class="message-alert <?php echo $alert_status; ?>"><?php echo htmlspecialchars($alert_message); ?>
                    </div>
                <?php endif; ?>

                <div class="settings-block">
                    <div class="block-header">
                        <span class="block-icon">👤</span>
                        <div>
                            <h2>Informations personnelles</h2>
                            <p>Mettez à jour vos informations de profil</p>
                        </div>
                    </div>
                    <form class="settings-form" method="POST" action="assets/php/parametres.php">
                        <div class="form-row">
                            <div class="half-width"><label for="prenom">Prénom</label><input type="text" id="prenom"
                                    name="prenom" value="<?php echo htmlspecialchars($user_data['prenom'] ?? ''); ?>"
                                    required></div>
                            <div class="half-width"><label for="nom">Nom</label><input type="text" id="nom" name="nom"
                                    value="<?php echo htmlspecialchars($user_data['nom'] ?? ''); ?>" required></div>
                        </div>
                        <label for="email">Email</label>
                        <input type="email" id="email"
                            value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" disabled
                            class="disabled-field">
                        <p class="email-hint">L'email ne peut pas être modifié</p>
                        <button type="submit" class="save-button" name="update_info">Enregistrer les
                            modifications</button>
                    </form>
                </div>

                <div class="settings-block">
                    <div class="block-header">
                        <span class="block-icon">🔒</span>
                        <div>
                            <h2>Sécurité</h2>
                            <p>Changez votre mot de passe</p>
                        </div>
                    </div>
                    <form class="settings-form" method="POST" action="assets/php/parametres.php">
                        <label for="new-pass">Nouveau mot de passe</label>
                        <input type="password" id="new-pass" name="new_password" required>
                        <label for="confirm-pass">Confirmer le nouveau mot de passe</label>
                        <input type="password" id="confirm-pass" name="confirm_password" required>
                        <button type="submit" class="save-button" name="change_password">Changer le mot de
                            passe</button>
                    </form>
                </div>

                <div class="settings-block danger-zone">
                    <div class="block-header danger-header">
                        <span class="block-icon danger-icon">🛑</span>
                        <div>
                            <h2>Zone de danger</h2>
                            <p>Supprimez définitivement votre compte et toutes vos données</p>
                        </div>
                    </div>
                    <p class="danger-warning">Cette action supprimera définitivement votre compte, tous vos projets,
                        tâches, tickets, documents et messages. Cette action ne peut pas être annulée.</p>
                    <button type="button" class="danger-button" id="delete-account-button">Supprimer mon compte</button>
                </div>

            </section>

            <footer>
                <div class="footer-content"
                    style="text-align:center;margin-top:20px;color:#64748b;font-size:13px;justify-content:center">
                    <p>© <?php echo date('Y'); ?> Gestion Projets. Tous droits réservés. · <a
                            href="privacy.php">Politique
                            de confidentialité</a></p>
                </div>
            </footer>
        </main>
    </div>

    <div id="delete-modal" class="modal-overlay hidden">
        <div class="modal-content">
            <h2 class="modal-title-danger">Confirmer la suppression</h2>
            <p class="modal-body-danger">Êtes-vous absolument sûr de vouloir supprimer votre compte ? Cette action est
                <strong>irréversible</strong> et toutes vos données seront perdues.
            </p>

            <div class="modal-actions">
                <button type="button" id="cancel-delete" class="button-secondary">Annuler</button>
                <form id="confirm-delete-form" method="POST" action="assets/php/parametres.php"
                    style="display: inline;">
                    <input type="hidden" name="delete_account" value="1">
                    <button type="submit" class="button-danger">Supprimer définitivement</button>
                </form>
            </div>
        </div>
    </div>

    <div id="new-project-modal" class="modal-overlay hidden">
        <div class="modal-content project-modal-content">
            <button type="button" class="close-modal-button" id="close-new-project-modal">×</button>
            <h2 class="project-modal-title">Créer un nouveau projet</h2>
            <p class="project-modal-subtitle">Créez un nouveau projet pour votre équipe</p>

            <form method="POST" action="assets/php/projets.php">
                <div class="form-group">
                    <label for="project-name">Nom du projet <span style="color: #e74c3c;">*</span></label>
                    <input type="text" id="project-name" name="nom_projet" required>
                </div>

                <div class="form-group">
                    <label for="project-description">Description</label>
                    <textarea id="project-description" name="description" rows="4"></textarea>
                </div>

                <button type="submit" class="project-modal-button" name="create_project">Créer le projet</button>
            </form>
        </div>
    </div>

    <script src="assets/javascript/index.js"></script>
</body>

</html>