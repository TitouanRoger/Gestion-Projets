<?php
// ============================================================================
// PAGE PROJET - VUE DÉTAILLÉE D'UN PROJET
// ============================================================================
// Affiche les onglets : Vue d'ensemble, Tâches, Tickets, Documents, Code, 
// Versions, Messages privés
// Accessible aux membres du projet uniquement

session_start();
require 'assets/php/db_connect.php';
require_once 'assets/php/session_security.php';

enforce_inactivity_timeout(15 * 60, 'assets/php/logout.php', true);

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$project_id = $_GET['id'] ?? null;

// Vérification de la validité de l'ID du projet
if (!$project_id || !is_numeric($project_id)) {
    header("Location: index.php?error=" . urlencode("ID de projet invalide."));
    exit();
}

// ============================================================================
// RÉCUPÉRATION DES DONNÉES DU PROJET
// ============================================================================
$projet = null;
$chef_projet_nom = 'Inconnu';
$is_proprietaire = false;
$nombre_membres = 0;
$nombre_tickets = 0;
$nombre_tasks = 0;
$nombre_versions = 0; // Compteur des versions

try {
    // Récupère le projet avec jointure sur le créateur
    $stmt = $pdo->prepare("SELECT p.id, p.nom_projet, p.description, p.createur_id, u.prenom, u.nom 
                          FROM projets p
                          JOIN utilisateurs u ON p.createur_id = u.id
                          WHERE p.id = ?");
    $stmt->execute([$project_id]);
    $projet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$projet) {
        header("Location: index.php?error=" . urlencode("Projet non trouvé."));
        exit();
    }

    $chef_projet_nom = $projet['prenom'] . ' ' . $projet['nom'];

    // Vérifie si l'utilisateur est le propriétaire (créateur)
    $is_proprietaire = ($user_id == $projet['createur_id']);
    $is_member = $is_proprietaire;

    // Récupérer le rôle de l'utilisateur courant
    $user_role = $is_proprietaire ? 'proprietaire' : '';
    if (!$is_proprietaire) {
        $stmt_role = $pdo->prepare("SELECT role FROM projet_membres WHERE projet_id = ? AND utilisateur_id = ?");
        $stmt_role->execute([$project_id, $user_id]);
        $user_role = $stmt_role->fetchColumn() ?: '';
    }

    // ========================================================================
    // COMPTAGES : MEMBRES, TICKETS, TÂCHES
    // ========================================================================

    // Nombre de membres (créateur + membres ajoutés)
    $stmt_membres = $pdo->prepare("
        SELECT COUNT(utilisateur_id) 
        FROM projet_membres 
        WHERE projet_id = ?
    ");
    $stmt_membres->execute([$project_id]);
    $membres_non_createurs = $stmt_membres->fetchColumn();

    $nombre_membres = 1 + $membres_non_createurs;

    // Nombre de tickets
    $stmt_tickets_count = $pdo->prepare("
        SELECT COUNT(id) 
        FROM tickets 
        WHERE projet_id = ?
    ");
    $stmt_tickets_count->execute([$project_id]);
    $nombre_tickets = $stmt_tickets_count->fetchColumn();

    // Nombre de tâches
    $stmt_tasks_count = $pdo->prepare("
        SELECT COUNT(id) 
        FROM tâches 
        WHERE projet_id = ?
    ");
    $stmt_tasks_count->execute([$project_id]);
    $nombre_tasks = $stmt_tasks_count->fetchColumn();
    // Nombre de versions (lecture manifeste JSON si présent)
    $versions_manifest_path = __DIR__ . '/assets/uploads/projects/' . $project_id . '/versions/manifest.json';
    if (is_file($versions_manifest_path)) {
        $vm = json_decode(@file_get_contents($versions_manifest_path), true);
        if (is_array($vm))
            $nombre_versions = count($vm);
    }

    // Nombre de messages non lus
    $nombre_messages_non_lus = 0;
    $stmt_unread = $pdo->prepare("
        SELECT COUNT(*) 
        FROM messages_prives 
        WHERE projet_id = ? 
        AND recipient_id = ? 
        AND id > COALESCE((
            SELECT last_read_message_id 
            FROM messages_reads 
            WHERE projet_id = ? 
            AND user_id = ? 
            AND other_user_id = sender_id
        ), 0)
    ");
    $stmt_unread->execute([$project_id, $user_id, $project_id, $user_id]);
    $nombre_messages_non_lus = $stmt_unread->fetchColumn();

    // Liste de tous les membres du projet (créateur + membres ajoutés)
    $stmt_membres_list = $pdo->prepare("
        SELECT u.id, u.prenom, u.nom 
        FROM utilisateurs u
        WHERE u.id = ? OR u.id IN (
            SELECT utilisateur_id FROM projet_membres WHERE projet_id = ?
        )
        ORDER BY u.nom, u.prenom
    ");
    $stmt_membres_list->execute([$projet['createur_id'], $project_id]);
    $membres_projet = $stmt_membres_list->fetchAll(PDO::FETCH_ASSOC);

    // ========================================================================
    // RÉCUPÉRATION DES TICKETS (si onglet tickets actif)
    // ========================================================================
    $tickets = [];
    $filter_statut = $_GET['filter_statut'] ?? 'all';
    $filter_type = $_GET['filter_type'] ?? 'all';

    if (isset($_GET['tab']) && $_GET['tab'] == 'tickets') {

        // Requête de base avec jointure sur le créateur du ticket
        $sql_tickets = "
            SELECT t.*, u.prenom, u.nom
            FROM tickets t
            JOIN utilisateurs u ON t.createur_id = u.id
            WHERE t.projet_id = ?
        ";
        $params = [$project_id];

        // Application des filtres si spécifiés
        if ($filter_statut !== 'all') {
            $sql_tickets .= " AND t.statut = ?";
            $params[] = $filter_statut;
        }

        if ($filter_type !== 'all') {
            $sql_tickets .= " AND t.type = ?";
            $params[] = $filter_type;
        }

        $sql_tickets .= " ORDER BY t.date_creation DESC";

        $stmt_tickets = $pdo->prepare($sql_tickets);
        $stmt_tickets->execute($params);
        $tickets = $stmt_tickets->fetchAll(PDO::FETCH_ASSOC);

        // Récupère les marqueurs de comité (séparateurs de tickets)
        $stmt_committees = $pdo->prepare("SELECT id, created_at FROM tickets_commites WHERE projet_id = ? ORDER BY created_at DESC");
        try {
            $stmt_committees->execute([$project_id]);
            $committees = $stmt_committees->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $committees = [];
        }
    }

    // ========================================================================
    // RÉCUPÉRATION DES TÂCHES (si onglet tasks actif)
    // ========================================================================
    $tasks = [];
    $task_assignments = [];
    $filter_task_statut = $_GET['filter_task_statut'] ?? 'all';
    $filter_task_type = $_GET['filter_task_type'] ?? 'all';
    $filter_task_priorite = $_GET['filter_task_priorite'] ?? 'all';

    if (isset($_GET['tab']) && $_GET['tab'] == 'tasks') {

        $sql_tasks = "
        SELECT t.*
        FROM tâches t
        WHERE t.projet_id = ?
    ";
        $params_tasks = [$project_id];

        if ($filter_task_statut !== 'all') {
            $sql_tasks .= " AND t.statut = ?";
            $params_tasks[] = $filter_task_statut;
        }

        if ($filter_task_type !== 'all') {
            $sql_tasks .= " AND t.type = ?";
            $params_tasks[] = $filter_task_type;
        }

        if ($filter_task_priorite !== 'all') {
            $sql_tasks .= " AND t.priorite = ?";
            $params_tasks[] = $filter_task_priorite;
        }

        $sql_tasks .= " ORDER BY t.priorite DESC, t.date_creation DESC";

        $stmt_tasks = $pdo->prepare($sql_tasks);
        $stmt_tasks->execute($params_tasks);
        $tasks = $stmt_tasks->fetchAll(PDO::FETCH_ASSOC);

        $task_ids = array_column($tasks, 'id');

        if (!empty($task_ids)) {
            $placeholders = implode(',', array_fill(0, count($task_ids), '?'));
            $sql_assignments = "
            SELECT 
                ta.tâche_id, 
                ta.utilisateur_id, 
                u.prenom, 
                u.nom 
            FROM tâches_assignations ta
            JOIN utilisateurs u ON u.id = ta.utilisateur_id
            WHERE ta.tâche_id IN ({$placeholders})
        ";
            $stmt_assignments = $pdo->prepare($sql_assignments);
            $stmt_assignments->execute($task_ids);

            while ($assignment = $stmt_assignments->fetch(PDO::FETCH_ASSOC)) {
                $task_id = $assignment['tâche_id'];
                if (!isset($task_assignments[$task_id])) {
                    $task_assignments[$task_id] = [
                        'ids' => [],
                        'noms' => []
                    ];
                }
                // Stocker les IDs (pour la data-attribute) et les noms (pour l'affichage)
                $task_assignments[$task_id]['ids'][] = $assignment['utilisateur_id'];
                $task_assignments[$task_id]['noms'][] = htmlspecialchars($assignment['prenom'] . ' ' . $assignment['nom']);
            }
        }
    }


} catch (\PDOException $e) {
    error_log("Erreur de BDD lors de la récupération du projet {$project_id}: " . $e->getMessage());
    header("Location: index.php?error=" . urlencode("Erreur technique lors du chargement du projet."));
    exit();
}

// Récupération des messages d'alerte
$alert_message = $_GET['success'] ?? $_GET['error'] ?? null;
$alert_status = isset($_GET['success']) ? 'success' : (isset($_GET['error']) ? 'error' : null);

// Section de contenu active par défaut
$active_tab = $_GET['tab'] ?? 'overview';

// Configuration des locales pour l'affichage des dates en français
setlocale(LC_TIME, 'fr_FR.utf8', 'fra'); // 'fra' pour compatibilité Windows

// Fonctions d'aide pour l'affichage des tags de tickets
function get_type_tag_class($type)
{
    switch ($type) {
        case 'bug critique':
            return 'tag-critique';
        case 'bug':
            return 'tag-bug';
        case 'amélioration':
            return 'tag-amelioration';
        case 'idée':
            return 'tag-idee';
        default:
            return 'tag-default';
    }
}
function get_statut_tag_class($statut)
{
    switch ($statut) {
        case 'nouveau':
            return 'tag-nouveau';
        case 'approuvé':
            return 'tag-approuve';
        case 'refusé':
            return 'tag-refuse';
        case 'en cours':
            return 'tag-en-cours';
        case 'terminé':
            return 'tag-termine';
        default:
            return 'tag-default';
    }
}

// Fonctions d'aide pour l'affichage des tags de tâches
function get_task_statut_tag_class($statut)
{
    switch ($statut) {
        case 'a faire':
            return 'tag-todo';
        case 'en cours':
            return 'tag-in-progress';
        case 'terminée': // Harmonisation
        case 'validée':
            return 'tag-done';
        default:
            return 'tag-default';
    }
}
function get_task_priorite_tag_class($priorite)
{
    switch ($priorite) {
        case 'urgent': // Harmonisation
        case 'haute':
            return 'tag-high-priority';
        case 'moyenne':
            return 'tag-medium-priority';
        case 'basse':
            return 'tag-low-priority';
        default:
            return 'tag-default';
    }
}
function get_task_type_tag_class($type)
{
    switch ($type) {
        case 'développement':
            return 'tag-dev';
        case 'design': // Harmonisation
        case 'conception':
            return 'tag-design';
        case 'test':
            return 'tag-test';
        case 'documentation':
            return 'tag-docs';
        default:
            return 'tag-default';
    }
}
// Helper moderne pour formatage date en français (remplace strftime + utf8_encode dépréciés)
if (!function_exists('format_date_fr')) {
    function format_date_fr(string $dateString): string
    {
        try {
            $dt = new DateTime($dateString);
        } catch (Exception $e) {
            return $dateString; // Fallback brut si invalide
        }
        // Utilise IntlDateFormatter si disponible pour noms de mois corrects
        if (class_exists('IntlDateFormatter')) {
            $fmt = new IntlDateFormatter('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE);
            // Pattern : jour (sans zéro), mois complet, année
            $fmt->setPattern('d MMMM yyyy');
            $formatted = $fmt->format($dt);
            if ($formatted !== false) {
                return $formatted;
            }
        }
        // Fallback simple si intl non disponible
        return $dt->format('d/m/Y');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title><?php echo htmlspecialchars($projet['nom_projet']); ?> - Projet</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/projet.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <script>
        window.CURRENT_USER_ID = <?php echo (int) $user_id; ?>;
        window.PROJECT_ID = <?php echo (int) $project_id; ?>;
        window.INITIAL_OTHER_ID = <?php echo isset($_GET['other_id']) ? (int) $_GET['other_id'] : 0; ?>;
    </script>
</head>

<body class="project-page-body">
    <div class="project-view-container">

        <header class="project-header">
            <div class="project-header-top">
                <div class="project-title-area">
                    <a href="index.php" class="back-link">← Retour</a>
                    <h1>
                        <?php echo htmlspecialchars($projet['nom_projet']); ?>
                        <?php if ($is_proprietaire): ?>
                            <span class="tag proprietary">Chef de projet</span>
                        <?php endif; ?>
                    </h1>
                </div>
                <?php if ($is_proprietaire): ?>
                    <a href="projet_admin.php?id=<?php echo htmlspecialchars($projet['id']); ?>"
                        class="admin-button">Administration</a>
                <?php endif; ?>
            </div>
            <p class="project-subtitle"><?php echo htmlspecialchars($projet['description'] ?: 'Aucune description'); ?>
            </p>
        </header>

        <?php if ($alert_message): ?>
            <div class="message-alert <?php echo $alert_status; ?>"><?php echo htmlspecialchars($alert_message); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">👥</span>
                <span class="stat-value"><?php echo $nombre_membres; ?></span>
                <span class="stat-label">Membres</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">✅</span>
                <span class="stat-value"><?php echo $nombre_tasks; ?></span>
                <span class="stat-label">Tâches</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🎟️</span>
                <span class="stat-value"><?php echo $nombre_tickets; ?></span>
                <span class="stat-label">Tickets</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📦</span>
                <span class="stat-value"><?php echo (int) $nombre_versions; ?></span>
                <span class="stat-label">Versions</span>
            </div>
        </div>

        <nav class="project-nav">
            <a href="?id=<?php echo $project_id; ?>&tab=overview"
                class="nav-tab <?php echo $active_tab == 'overview' ? 'active' : ''; ?>">Vue d'ensemble</a>
            <a href="?id=<?php echo $project_id; ?>&tab=tasks"
                class="nav-tab <?php echo $active_tab == 'tasks' ? 'active' : ''; ?>">Tâches</a>
            <a href="?id=<?php echo $project_id; ?>&tab=tickets"
                class="nav-tab <?php echo $active_tab == 'tickets' ? 'active' : ''; ?>">Tickets</a>
            <a href="?id=<?php echo $project_id; ?>&tab=documents"
                class="nav-tab <?php echo $active_tab == 'documents' ? 'active' : ''; ?>">Documents</a>
            <a href="?id=<?php echo $project_id; ?>&tab=code"
                class="nav-tab <?php echo $active_tab == 'code' ? 'active' : ''; ?>">Code</a>
            <a href="?id=<?php echo $project_id; ?>&tab=versions"
                class="nav-tab <?php echo $active_tab == 'versions' ? 'active' : ''; ?>">Versions</a>
            <a href="?id=<?php echo $project_id; ?>&tab=messages"
                class="nav-tab <?php echo $active_tab == 'messages' ? 'active' : ''; ?>">
                Messages
                <?php if ($nombre_messages_non_lus > 0): ?>
                    <span class="badge"><?php echo $nombre_messages_non_lus; ?></span>
                <?php endif; ?>
            </a>
        </nav>

        <div class="project-content">
            <?php if ($active_tab == 'overview'): ?>
                <div class="content-block">
                    <h2>Informations du projet</h2>
                    <p><strong>Nom :</strong> <?php echo htmlspecialchars($projet['nom_projet']); ?></p>
                    <p><strong>Description :</strong>
                        <?php echo htmlspecialchars($projet['description'] ?: 'Aucune description'); ?></p>
                    <p><strong>Chef de projet :</strong> <?php echo htmlspecialchars($chef_projet_nom); ?></p>
                </div>
            <?php elseif ($active_tab == 'documents'): ?>
                <div class="content-block documents-view-container">
                    <h2>Documents du projet</h2>
                    <p class="subtitle">Consultez, téléchargez et gérez les documents par catégorie. Tout le monde peut
                        consulter et télécharger. Les ajouts/suppressions sont soumis aux permissions côté serveur.</p>

                    <div class="documents-sort-bar">
                        <label for="docs-sort-key">Trier par</label>
                        <select id="docs-sort-key">
                            <option value="mtime" selected>Date</option>
                            <option value="name">Nom</option>
                            <option value="size">Taille</option>
                        </select>
                        <label for="docs-sort-dir">Ordre</label>
                        <select id="docs-sort-dir">
                            <option value="desc" selected>Descendant</option>
                            <option value="asc">Ascendant</option>
                        </select>
                    </div>

                    <?php
                    // Définition des catégories visibles côté UI (doit correspondre au backend)
                    $doc_categories = [
                        'cahier_des_charges' => 'Cahier des charges',
                        'comites_projet' => 'Comités de projet',
                        'guide_utilisateur' => 'Guide utilisateur',
                        'rapport_activites' => 'Rapport d’activités',
                        'rapports_propositions' => 'Rapports & propositions',
                        'recettes' => 'Recettes',
                        'reponses_techniques' => 'Réponses techniques'
                    ];
                    ?>

                    <?php foreach ($doc_categories as $cat_key => $cat_label): ?>
                        <section class="document-category-block">
                            <header class="category-header">
                                <h3><?php echo htmlspecialchars($cat_label); ?></h3>
                                <div class="category-actions">
                                    <form class="inline-form" method="POST" action="assets/php/documents.php"
                                        enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="upload">
                                        <input type="hidden" name="project_id" value="<?php echo (int) $project_id; ?>">
                                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($cat_key); ?>">
                                        <input type="file" name="file" required>
                                        <button type="submit" class="button-small">Téléverser</button>
                                    </form>
                                    <a class="button-small button-secondary"
                                        href="assets/php/documents.php?action=download_category&project_id=<?php echo (int) $project_id; ?>&category=<?php echo urlencode($cat_key); ?>">Télécharger
                                        tout (ZIP)</a>
                                </div>
                            </header>
                            <div class="documents-list" data-project-id="<?php echo (int) $project_id; ?>"
                                data-category="<?php echo htmlspecialchars($cat_key); ?>">
                                <em>Liste des documents chargée dynamiquement.</em>
                                <p>
                                    <a href="assets/php/documents.php?action=list&project_id=<?php echo (int) $project_id; ?>&category=<?php echo urlencode($cat_key); ?>"
                                        target="_blank">Voir la liste JSON</a>
                                </p>
                            </div>
                        </section>
                    <?php endforeach; ?>
                    <input type="hidden" id="documents-user-role" value="<?php echo htmlspecialchars($user_role); ?>">
                </div>
            <?php elseif ($active_tab == 'tasks'): ?>
                <div class="content-block tasks-view-container">

                    <header class="tasks-header">
                        <h2>Tâches du projet</h2>
                        <p class="subtitle">Gérez les tâches à réaliser pour ce projet.</p>
                    </header>

                    <div class="tasks-controls-bar">
                        <div class="tasks-filter-bar">
                            <form id="task-filter-form" method="GET" action="">
                                <input type="hidden" name="id" value="<?php echo $project_id; ?>">
                                <input type="hidden" name="tab" value="tasks">

                                <select name="filter_task_statut" id="filter_task_statut">
                                    <option value="all">Tous les statuts</option>
                                    <option value="a faire" <?php echo ($filter_task_statut == 'a faire') ? 'selected' : ''; ?>>📝 À faire</option>
                                    <option value="en cours" <?php echo ($filter_task_statut == 'en cours') ? 'selected' : ''; ?>>⏳ En cours</option>
                                    <option value="terminée" <?php echo ($filter_task_statut == 'terminée') ? 'selected' : ''; ?>>✅ Terminée</option>
                                    <option value="validée" <?php echo ($filter_task_statut == 'validée') ? 'selected' : ''; ?>>🏁 Validée</option>
                                </select>

                                <select name="filter_task_type" id="filter_task_type">
                                    <option value="all">Tous les types</option>
                                    <option value="développement" <?php echo ($filter_task_type == 'développement') ? 'selected' : ''; ?>>💻 Développement</option>
                                    <option value="design" <?php echo ($filter_task_type == 'design') ? 'selected' : ''; ?>>
                                        🎨 Design</option>
                                    <option value="test" <?php echo ($filter_task_type == 'test') ? 'selected' : ''; ?>>🧪
                                        Test
                                    </option>
                                    <option value="documentation" <?php echo ($filter_task_type == 'documentation') ? 'selected' : ''; ?>>📄 Documentation</option>
                                </select>

                                <select name="filter_task_priorite" id="filter_task_priorite">
                                    <option value="all">Toutes les priorités</option>
                                    <option value="urgent" <?php echo ($filter_task_priorite == 'urgent') ? 'selected' : ''; ?>>🔥 Urgent</option>
                                    <option value="haute" <?php echo ($filter_task_priorite == 'haute') ? 'selected' : ''; ?>>
                                        ⚠️ Haute</option>
                                    <option value="moyenne" <?php echo ($filter_task_priorite == 'moyenne') ? 'selected' : ''; ?>>▶ Moyenne</option>
                                    <option value="basse" <?php echo ($filter_task_priorite == 'basse') ? 'selected' : ''; ?>>
                                        ↓ Basse</option>
                                </select>
                            </form>
                        </div>
                        <?php if ($is_proprietaire): ?>
                            <button class="new-task-button" id="open-new-task-modal">+ Nouvelle tâche</button>
                        <?php endif; ?>
                    </div>

                    <div class="tasks-grid">
                        <?php if (empty($tasks)): ?>
                            <p class="no-tasks-message">Aucune tâche n'a été créée pour ce projet ou ne correspond aux filtres.
                            </p>
                        <?php else: ?>
                            <?php
                            if (!function_exists('getFileIcon')) {
                                function getFileIcon($filename)
                                {
                                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                    $iconMap = [
                                        'pdf' => '📄',
                                        'txt' => '📝',
                                        'zip' => '📦',
                                        'png' => '🖼️',
                                        'jpg' => '🖼️',
                                        'jpeg' => '🖼️',
                                        'gif' => '🖼️'
                                    ];
                                    return $iconMap[$ext] ?? '📎';
                                }
                            }
                            foreach ($tasks as $task):
                                $priorite = strtolower($task['priorite']);
                                $type = strtolower($task['type']);
                                $statut = strtolower($task['statut']);

                                $priorite_class = get_task_priorite_tag_class($priorite);
                                $type_class = get_task_type_tag_class($type);
                                $statut_class = get_task_statut_tag_class($statut);

                                $current_assignments = $task_assignments[$task['id']] ?? ['ids' => [], 'noms' => []];
                                $assigned_ids_string = implode(',', $current_assignments['ids']);

                                $assigned_names = $current_assignments['noms'];
                                $assigne_nom = empty($assigned_names) ? 'Non assignée' : implode(', ', $assigned_names);

                                $is_user_assigned = in_array($user_id, $current_assignments['ids']);
                                $assign_button_text = $is_user_assigned ? 'Se désassigner' : 'S\'assigner';
                                ?>
                                <div class="task-card">
                                    <div class="card-title-row">
                                        <span
                                            class="icon-title <?php echo $type_class === 'tag-dev' ? 'icon-dev' : ($type_class === 'tag-design' ? 'icon-design' : ($type_class === 'tag-test' ? 'icon-test' : ($type_class === 'tag-docs' ? 'icon-docs' : 'icon-default'))); ?>">
                                            <?php echo $type_class === 'tag-dev' ? '💻' : ($type_class === 'tag-design' ? '🎨' : ($type_class === 'tag-test' ? '🧪' : ($type_class === 'tag-docs' ? '📄' : '⭐'))); ?>
                                        </span>
                                        <h3
                                            class="task-title <?php echo ($statut == 'terminée' || $statut == 'validée') ? 'task-done' : ''; ?>">
                                            <?php echo htmlspecialchars($task['titre']); ?>
                                        </h3>
                                        <?php if ($is_proprietaire): ?>
                                            <a href="#" class="edit-task-icon open-edit-task-modal" data-id="<?php echo $task['id']; ?>"
                                                data-titre="<?php echo htmlspecialchars($task['titre']); ?>"
                                                data-description="<?php echo htmlspecialchars($task['description']); ?>"
                                                data-priorite="<?php echo htmlspecialchars($priorite); ?>"
                                                data-type="<?php echo htmlspecialchars($type); ?>"
                                                data-assigned-ids="<?= htmlspecialchars($assigned_ids_string) ?>"
                                                data-statut="<?php echo htmlspecialchars($statut); ?>">
                                                ✏️
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <p class="task-description">
                                        <?php echo htmlspecialchars($task['description'] ?: 'Aucune description'); ?>
                                    </p>

                                    <div class="task-tags">
                                        <span class="tag <?php echo $priorite_class; ?>">
                                            <?php echo ucfirst(htmlspecialchars($priorite)); ?>
                                        </span>
                                        <span class="tag <?php echo $type_class; ?>">
                                            <?php echo ucfirst(htmlspecialchars($type)); ?>
                                        </span>
                                        <span class="tag <?php echo $statut_class; ?>">
                                            <?php echo ucfirst(htmlspecialchars($statut)); ?>
                                        </span>
                                    </div>

                                    <div class="task-assignee">
                                        <p>👤 Assigné(e) : <?php echo $assigne_nom; ?></p>
                                        <?php if (!in_array($statut, ['terminée', 'validée'])): ?>
                                            <button class="assign-task-button" data-task-id="<?php echo $task['id']; ?>"
                                                data-user-id="<?php echo $user_id; ?>">
                                                <?php echo $assign_button_text; ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($is_user_assigned && !in_array($statut, ['terminée', 'validée'])): ?>
                                        <form method="POST" action="assets/php/tasks.php" class="complete-task-form"
                                            enctype="multipart/form-data">
                                            <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">
                                            <input type="hidden" name="task_id" value="<?php echo htmlspecialchars($task['id']); ?>">
                                            <input type="hidden" name="complete_task" value="1">
                                            <div class="form-group" style="margin-top:10px;">
                                                <label for="attachments-<?php echo $task['id']; ?>"
                                                    style="font-size:12px; font-weight:600; color:#334155;">Fichiers (optionnel)</label>
                                                <input type="file" id="attachments-<?php echo $task['id']; ?>" name="attachments[]"
                                                    multiple style="width:100%; padding:6px; font-size:12px;">
                                            </div>
                                            <button type="submit" class="button-primary button-complete-task">Marquer terminée</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php
                                    $historyFilePath = __DIR__ . '/assets/uploads/tasks/' . $task['id'] . '/history.json';
                                    $historyEntries = [];
                                    if (file_exists($historyFilePath)) {
                                        $jsonHist = file_get_contents($historyFilePath);
                                        $historyEntries = json_decode($jsonHist, true) ?: [];
                                    }
                                    $currentAttempt = null;
                                    for ($hi = count($historyEntries) - 1; $hi >= 0; $hi--) {
                                        if (in_array($historyEntries[$hi]['action'], ['terminée', 'validée'])) {
                                            $currentAttempt = $historyEntries[$hi];
                                            break;
                                        }
                                    }
                                    if (in_array($statut, ['terminée', 'validée'])) {
                                        if ($currentAttempt && !empty($currentAttempt['files'])) {
                                            $attemptDir = htmlspecialchars($currentAttempt['attempt_dir']);
                                            $downloadAllUrl = 'assets/php/download_all_files.php?task_id=' . urlencode($task['id']) . '&attempt=' . $attemptDir;
                                            echo '<div class="task-files-list">';
                                            echo '<p class="task-files-title">📎 Fichiers : <a href="' . $downloadAllUrl . '" class="download-all-btn" target="_blank" rel="noopener">📥 Tout télécharger</a></p>';
                                            echo '<ul>';
                                            foreach ($currentAttempt['files'] as $f) {
                                                $safeF = htmlspecialchars($f);
                                                $icon = getFileIcon($f);
                                                $downloadUrl = 'assets/php/download_task_file.php?task_id=' . urlencode($task['id']) . '&attempt=' . $attemptDir . '&file=' . urlencode($f);
                                                echo '<li><a href="' . $downloadUrl . '" rel="noopener" target="_blank">' . $icon . ' ' . $safeF . '</a></li>';
                                            }
                                            echo '</ul></div>';
                                        } else {
                                            echo '<div class="task-files-list"><p class="task-files-title">📎 Aucun fichier fourni.</p></div>';
                                        }
                                    }
                                    $lastReject = null;
                                    for ($ri = count($historyEntries) - 1; $ri >= 0; $ri--) {
                                        if ($historyEntries[$ri]['action'] === 'rejetée') {
                                            $lastReject = $historyEntries[$ri];
                                            break;
                                        }
                                    }
                                    if ($lastReject && $statut !== 'validée') {
                                        echo '<div class="task-rejection-reason"><strong>Dernier rejet :</strong> ' . htmlspecialchars($lastReject['reason']) . ' <span class="history-date">(' . htmlspecialchars($lastReject['timestamp']) . ')</span></div>';
                                    }
                                    if ($is_proprietaire && $statut === 'terminée') { ?>
                                        <div class="task-validation-actions">
                                            <form method="POST" action="assets/php/tasks.php" class="inline-form">
                                                <input type="hidden" name="project_id"
                                                    value="<?php echo htmlspecialchars($project_id); ?>">
                                                <input type="hidden" name="task_id"
                                                    value="<?php echo htmlspecialchars($task['id']); ?>">
                                                <input type="hidden" name="validate_task" value="1">
                                                <button type="submit" class="button-primary button-small">Valider ✅</button>
                                            </form>
                                            <form method="POST" action="assets/php/tasks.php" class="inline-form reject-form">
                                                <input type="hidden" name="project_id"
                                                    value="<?php echo htmlspecialchars($project_id); ?>">
                                                <input type="hidden" name="task_id"
                                                    value="<?php echo htmlspecialchars($task['id']); ?>">
                                                <input type="hidden" name="reject_task" value="1">
                                                <input type="text" name="reason" placeholder="Raison du refus" required
                                                    class="reject-reason-input">
                                                <button type="submit" class="button-secondary button-small">Refuser ❌</button>
                                            </form>
                                        </div>
                                    <?php } ?>
                                    <?php if (!empty($historyEntries)) {
                                        echo '<div class="task-history"><details><summary>Historique</summary><ul>';
                                        foreach ($historyEntries as $h) {
                                            $act = htmlspecialchars($h['action']);
                                            $ts = htmlspecialchars($h['timestamp']);
                                            $reason = $h['reason'] ? ' — Raison: ' . htmlspecialchars($h['reason']) : '';
                                            $fileCount = !empty($h['files']) ? count($h['files']) : 0;
                                            $fileBadge = $fileCount > 0 ? ' <span class="file-count-badge">' . $fileCount . ' fichier' . ($fileCount > 1 ? 's' : '') . '</span>' : '';
                                            echo '<li><span class="history-action">' . $act . '</span> <span class="history-date">' . $ts . '</span>' . $fileBadge . $reason;
                                            if (!empty($h['files']) && !empty($h['attempt_dir'])) {
                                                $attemptDirSafe = htmlspecialchars($h['attempt_dir']);
                                                $downloadAllUrl = 'assets/php/download_all_files.php?task_id=' . urlencode($task['id']) . '&attempt=' . $attemptDirSafe;
                                                echo '<div class="history-files-container">';
                                                echo '<a href="' . $downloadAllUrl . '" class="download-all-btn" target="_blank" rel="noopener">📥 Tout télécharger</a>';
                                                echo '<ul class="history-files">';
                                                foreach ($h['files'] as $hf) {
                                                    $hfSafe = htmlspecialchars($hf);
                                                    $icon = getFileIcon($hf);
                                                    $downloadUrl = 'assets/php/download_task_file.php?task_id=' . urlencode($task['id']) . '&attempt=' . $attemptDirSafe . '&file=' . urlencode($hf);
                                                    echo '<li><a href="' . $downloadUrl . '" target="_blank" rel="noopener">' . $icon . ' ' . $hfSafe . '</a></li>';
                                                }
                                                echo '</ul></div>';
                                            }
                                            echo '</li>';
                                        }
                                        echo '</ul></details></div>';
                                    } ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>
            <?php elseif ($active_tab == 'tickets'): ?>
                <div class="content-block tickets-view-container">

                    <header class="tickets-header">
                        <h2>Tickets - Suggestions & Bugs</h2>
                        <p class="subtitle">Partagez vos idées d'amélioration et signalez les bugs</p>
                    </header>

                    <div class="tickets-controls-bar">
                        <div class="tickets-filter-bar">
                            <select name="statut_filter" id="statut_filter">
                                <option value="all">Tous les statuts</option>
                                <option value="nouveau" <?php echo ($filter_statut == 'nouveau') ? 'selected' : ''; ?>>🆕
                                    Nouveau
                                </option>
                                <option value="approuvé" <?php echo ($filter_statut == 'approuvé') ? 'selected' : ''; ?>>
                                    ✅ Approuvé</option>
                                <option value="refusé" <?php echo ($filter_statut == 'refusé') ? 'selected' : ''; ?>>❌ Refusé
                                </option>
                            </select>
                            <select name="type_filter" id="type_filter">
                                <option value="all">Tous les types</option>
                                <option value="bug critique" <?php echo ($filter_type == 'bug critique') ? 'selected' : ''; ?>>🚨 Bug Critique</option>
                                <option value="bug" <?php echo ($filter_type == 'bug') ? 'selected' : ''; ?>>🐞 Bug</option>
                                <option value="amélioration" <?php echo ($filter_type == 'amélioration') ? 'selected' : ''; ?>>✨ Amélioration</option>
                                <option value="idée" <?php echo ($filter_type == 'idée') ? 'selected' : ''; ?>>💡 Idée
                                </option>
                            </select>
                        </div>
                        <div style="display:flex; gap:10px; align-items:center;">
                            <?php if ($is_proprietaire): ?>
                                <button type="button" class="button-secondary" id="open-committee-modal">Comité</button>
                            <?php endif; ?>
                            <button class="new-ticket-button" id="open-new-ticket-modal">+ Nouveau ticket</button>
                        </div>
                    </div>

                    <div class="tickets-grid">
                        <?php if (empty($tickets)): ?>
                            <p class="no-tickets-message">Aucun ticket n'a encore été créé pour ce projet ou ne correspond aux
                                filtres.</p>
                        <?php else: ?>
                            <?php
                            $ci = 0;
                            $totalCommittees = isset($committees) ? count($committees) : 0;
                            $emitCommitteeIfNeeded = function ($ticketDate) use (&$ci, $totalCommittees, &$committees) {
                                while ($ci < $totalCommittees) {
                                    $markerTs = strtotime($committees[$ci]['created_at'] ?? '');
                                    if (!$markerTs) {
                                        $ci++;
                                        continue;
                                    }
                                    $ticketTs = strtotime($ticketDate);
                                    if ($ticketTs <= $markerTs) {
                                        $label = format_date_fr($committees[$ci]['created_at']);
                                        $num = max(1, $totalCommittees - $ci);
                                        echo '<div class="committee-separator">'
                                            . '<span class="committee-badge">Comité n°' . (int) $num . '</span>'
                                            . '<span class="committee-date">' . htmlspecialchars($label) . '</span>'
                                            . '</div>';
                                        $ci++;
                                    } else {
                                        break;
                                    }
                                }
                            };

                            if (!empty($tickets) && $totalCommittees > 0) {
                                $firstDate = $tickets[0]['date_creation'];
                                $emitCommitteeIfNeeded($firstDate);
                            }

                            foreach ($tickets as $ticket):
                                $type_class = get_type_tag_class($ticket['type']);
                                $statut_class = get_statut_tag_class($ticket['statut']);
                                $createur_nom = htmlspecialchars($ticket['prenom'] . ' ' . $ticket['nom']);
                                if ($totalCommittees > 0) {
                                    $emitCommitteeIfNeeded($ticket['date_creation']);
                                }
                                ?>
                                <div class="ticket-card">
                                    <div class="card-title-row">
                                        <span
                                            class="icon-title <?php echo $type_class === 'tag-idee' ? 'icon-idee' : ($type_class === 'tag-bug' || $type_class === 'tag-critique' ? 'icon-bug' : 'icon-default'); ?>">
                                            <?php echo $type_class === 'tag-idee' ? '💡' : ($type_class === 'tag-bug' || $type_class === 'tag-critique' ? '⚠️' : '⭐'); ?>
                                        </span>
                                        <h3 class="ticket-title"><?php echo htmlspecialchars($ticket['titre']); ?></h3>

                                        <?php if ($is_proprietaire && strtolower($ticket['statut']) === 'nouveau'): ?>
                                            <div class="ticket-actions-inline">
                                                <form method="POST" action="assets/php/update_ticket_status.php" class="inline-form"
                                                    onsubmit="return false;" data-confirm="Confirmer l'approbation de ce ticket ?">
                                                    <input type="hidden" name="project_id"
                                                        value="<?php echo htmlspecialchars($project_id); ?>">
                                                    <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <input type="hidden" name="statut" value="approuvé">
                                                    <button type="submit" class="button-small button-primary"
                                                        title="Approuver le ticket">Approuver ✅</button>
                                                </form>
                                                <form method="POST" action="assets/php/update_ticket_status.php" class="inline-form"
                                                    onsubmit="return false;" data-confirm="Confirmer le refus de ce ticket ?">
                                                    <input type="hidden" name="project_id"
                                                        value="<?php echo htmlspecialchars($project_id); ?>">
                                                    <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <input type="hidden" name="statut" value="refusé">
                                                    <button type="submit" class="button-small button-secondary"
                                                        title="Refuser le ticket">Refuser ❌</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <p class="ticket-description"><?php echo htmlspecialchars($ticket['description']); ?></p>

                                    <div class="ticket-tags">
                                        <span
                                            class="tag <?php echo $type_class; ?>"><?php echo ucfirst(htmlspecialchars($ticket['type'])); ?></span>
                                        <span
                                            class="tag <?php echo $statut_class; ?>"><?php echo ucfirst(htmlspecialchars($ticket['statut'])); ?></span>
                                    </div>

                                    <div class="ticket-footer">
                                        <p>Proposé par : <?php echo $createur_nom; ?></p>
                                        <p>Le : <?php echo htmlspecialchars(format_date_fr($ticket['date_creation'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>
            <?php elseif ($active_tab == 'versions'): ?>
                <div class="content-block versions-view-container">
                    <h2>Versions du projet</h2>
                    <p class="subtitle">Les versions sont des instantanés des documents et du code. Le chef de projet peut
                        créer une nouvelle version qui archive l'état actuel puis réinitialise les espaces Documents et
                        Code.</p>
                    <?php if ($is_proprietaire): ?>
                        <button type="button" class="button-primary" id="create-version-btn">+ Nouvelle version</button>
                    <?php else: ?>
                        <p><em>Seul le chef de projet peut créer des versions.</em></p>
                    <?php endif; ?>
                    <div id="versions-list" class="versions-list">
                        <em>Chargement des versions…</em>
                    </div>
                    <input type="hidden" id="versions-project-id" value="<?php echo (int) $project_id; ?>">
                    <input type="hidden" id="versions-is-owner" value="<?php echo $is_proprietaire ? '1' : '0'; ?>">
                </div>
            <?php elseif ($active_tab == 'messages'): ?>
                <div class="content-block messages-view-container">
                    <div class="chat-layout" id="chat-root">
                        <aside class="chat-sidebar">
                            <div class="chat-sidebar-header">Membres</div>
                            <ul class="chat-members" id="chat-members">
                                <?php foreach ($membres_projet as $mp):
                                    if ($mp['id'] == $user_id)
                                        continue;
                                    $initials = strtoupper(mb_substr($mp['prenom'], 0, 1) . mb_substr($mp['nom'], 0, 1)); ?>
                                    <li class="chat-member" data-user="<?php echo (int) $mp['id']; ?>">
                                        <div class="avatar" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>
                                        <div class="name"><?php echo htmlspecialchars($mp['prenom'] . ' ' . $mp['nom']); ?>
                                        </div>
                                        <span class="unread-badge" title="Non lus">0</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </aside>
                        <main class="chat-main">
                            <div class="chat-thread-header" id="chat-thread-header">
                                <span id="chat-thread-title">Sélectionnez un membre</span>
                                <div class="typing-indicator" id="typing-indicator" style="display:none">En train d'écrire
                                    <span>...</span>
                                </div>
                            </div>
                            <div class="chat-messages" id="chat-messages">
                                <div class="empty-thread-hint" id="empty-thread-hint">Aucune conversation sélectionnée.
                                </div>
                            </div>
                            <div class="chat-compose" id="chat-compose" style="display:none">
                                <form id="chat-send-form" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="send">
                                    <input type="hidden" name="project_id" value="<?php echo (int) $project_id; ?>">
                                    <input type="hidden" name="recipient_id" id="chat-recipient-id" value="">
                                    <textarea name="message" id="chat-message" placeholder="Votre message..."
                                        maxlength="1000"></textarea>
                                    <input type="file" name="files[]" multiple class="files-input">
                                    <button type="submit" class="send-btn" disabled id="chat-send-btn">Envoyer 🔐</button>
                                </form>
                            </div>
                        </main>
                    </div>
                </div>
            <?php elseif ($active_tab == 'code'): ?>
                <div class="content-block code-repo-container">
                    <h2>Répertoire du Code</h2>
                    <p class="subtitle">Parcourez et gérez les fichiers du projet.
                        <?php echo $is_proprietaire ? 'Vous pouvez créer, envoyer, renommer et supprimer.' : 'Lecture & téléchargement uniquement.'; ?>
                    </p>
                    <div class="code-flex-layout">
                        <div class="code-tree-panel">
                            <div class="code-tree-header">
                                <span>Arborescence</span>
                                <?php if ($is_proprietaire): ?>
                                    <button type="button" class="code-btn small" id="code-create-folder-btn">+ Dossier</button>
                                    <button type="button" class="code-btn small" id="code-create-file-btn">+ Fichier</button>
                                    <button type="button" class="code-btn small" id="code-upload-files-btn">↑ Fichiers</button>
                                    <button type="button" class="code-btn small" id="code-upload-folder-btn">↑ Dossier</button>
                                <?php endif; ?>
                            </div>
                            <?php if ($is_proprietaire): ?>
                                <div id="code-root-drop" class="code-root-drop" title="Déposer ici pour déplacer à la racine">
                                    Déposer ici pour déplacer à la racine</div>
                            <?php endif; ?>
                            <div id="code-tree" class="code-tree"></div>
                        </div>
                        <div class="code-detail-panel">
                            <div class="code-actions-bar">
                                <button type="button" class="code-btn" id="code-download-all-btn">📦 Tout
                                    télécharger</button>
                                <button type="button" class="code-btn" id="code-download-selection-btn">📥 Télécharger la
                                    sélection</button>
                                <?php if ($is_proprietaire): ?>
                                    <button type="button" class="code-btn" id="code-rename-btn">Renommer</button>
                                    <button type="button" class="code-btn danger" id="code-delete-btn">Supprimer</button>
                                <?php endif; ?>
                            </div>
                            <div id="code-preview" class="code-preview"><em>Sélectionnez un fichier pour afficher son
                                    contenu.</em></div>
                        </div>
                    </div>
                    <input type="hidden" id="code-project-id" value="<?php echo (int) $project_id; ?>">
                    <input type="hidden" id="code-is-owner" value="<?php echo $is_proprietaire ? '1' : '0'; ?>">
                    <input type="file" id="code-upload-input" multiple style="display:none" />
                    <input type="file" id="code-upload-folder-input" webkitdirectory multiple style="display:none" />
                </div>
            <?php else: ?>
                <div class="content-block">
                    <h2>Contenu <?php echo htmlspecialchars($active_tab); ?></h2>
                    <p>Cette section sera développée ultérieurement.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- MODALE NOUVELLE TÂCHE -->
    <div id="new-task-modal" class="modal-overlay hidden">
        <div class="modal-content task-modal-content">
            <button type="button" class="close-modal-button" id="close-new-task-modal">×</button>
            <h2 class="task-modal-title">Créer une nouvelle tâche</h2>
            <p class="task-modal-subtitle">Définissez le travail à effectuer, son type, sa priorité et ses assignés.</p>
            <form method="POST" action="assets/php/tasks.php">
                <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">
                <input type="hidden" name="create_task" value="1">
                <div class="form-group">
                    <label for="task-titre">Titre <span style="color:#e74c3c">*</span></label>
                    <input type="text" id="task-titre" name="titre" required placeholder="Titre de la tâche">
                </div>
                <div class="form-group">
                    <label for="task-description">Description <span style="color:#e74c3c">*</span></label>
                    <textarea id="task-description" name="description" rows="5" required
                        placeholder="Décrivez la tâche..."></textarea>
                </div>
                <div class="form-group">
                    <label for="task-type">Type <span style="color:#e74c3c">*</span></label>
                    <select id="task-type" name="type" required>
                        <option value="" disabled selected>Choisir…</option>
                        <option value="développement">💻 Développement</option>
                        <option value="design">🎨 Design</option>
                        <option value="test">🧪 Test</option>
                        <option value="documentation">📄 Documentation</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="task-priorite">Priorité <span style="color:#e74c3c">*</span></label>
                    <select id="task-priorite" name="priorite" required>
                        <option value="" disabled selected>Choisir…</option>
                        <option value="urgent">🔥 Urgent</option>
                        <option value="haute">⚠️ Haute</option>
                        <option value="moyenne">▶ Moyenne</option>
                        <option value="basse">↓ Basse</option>
                    </select>
                </div>
                <div class="form-group" id="new-task-assigne">
                    <label>Assigner à (multi-sélection)</label>
                    <div class="checkbox-group">
                        <?php foreach ($membres_projet as $mp): ?>
                            <label class="checkbox-item">
                                <input type="checkbox" name="assigne_ids[]" value="<?php echo (int) $mp['id']; ?>">
                                <?php echo htmlspecialchars($mp['prenom'] . ' ' . $mp['nom']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" id="cancel-task" class="button-secondary">Annuler</button>
                    <button type="submit" class="button-primary">Créer la tâche</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODALE NOUVEAU TICKET -->
    <div id="new-ticket-modal" class="modal-overlay hidden">
        <div class="modal-content ticket-modal-content">
            <button type="button" class="close-modal-button" id="close-new-ticket-modal">×</button>
            <h2 class="ticket-modal-title">Créer un nouveau ticket</h2>
            <p class="ticket-modal-subtitle">Soumettez une idée ou signalez un bug.</p>
            <form method="POST" action="assets/php/tickets.php">
                <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">
                <input type="hidden" name="create_ticket" value="1">
                <div class="form-group">
                    <label for="ticket-titre">Titre <span style="color:#e74c3c">*</span></label>
                    <input type="text" id="ticket-titre" name="titre" required
                        placeholder="Résumé de la suggestion ou du bug">
                </div>
                <div class="form-group">
                    <label for="ticket-description">Description <span style="color:#e74c3c">*</span></label>
                    <textarea id="ticket-description" name="description" rows="6" required
                        placeholder="Décrivez en détail votre idée ou le bug rencontré..."></textarea>
                </div>
                <div class="form-group">
                    <label for="ticket-type">Type <span style="color:#e74c3c">*</span></label>
                    <select id="ticket-type" name="type" required>
                        <option value="" disabled selected>Choisir…</option>
                        <option value="bug critique">🚨 Bug critique</option>
                        <option value="bug">🐞 Bug</option>
                        <option value="amélioration">✨ Amélioration</option>
                        <option value="idée">💡 Idée</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" id="cancel-ticket" class="button-secondary">Annuler</button>
                    <button type="submit" class="button-primary">Créer le ticket</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODALE ÉDITION TÂCHE -->
    <div id="edit-task-modal" class="modal-overlay hidden">
        <div class="modal-content task-modal-content">
            <button type="button" class="close-modal-button" id="close-edit-task-modal">×</button>
            <h2 class="task-modal-title">Éditer la tâche</h2>
            <p class="task-modal-subtitle">Mettez à jour les détails ou les assignations.</p>
            <form method="POST" action="assets/php/tasks.php">
                <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">
                <input type="hidden" name="edit_task" value="1">
                <input type="hidden" name="task_id" id="edit-task-id">
                <div class="form-group">
                    <label for="edit-task-titre">Titre</label>
                    <input type="text" id="edit-task-titre" name="titre" required>
                </div>
                <div class="form-group">
                    <label for="edit-task-description">Description</label>
                    <textarea id="edit-task-description" name="description" rows="5" required></textarea>
                </div>
                <div class="form-group">
                    <label for="edit-task-type">Type</label>
                    <select id="edit-task-type" name="type" required>
                        <option value="développement">Développement</option>
                        <option value="design">Design</option>
                        <option value="test">Test</option>
                        <option value="documentation">Documentation</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-task-priorite">Priorité</label>
                    <select id="edit-task-priorite" name="priorite" required>
                        <option value="urgent">Urgent</option>
                        <option value="haute">Haute</option>
                        <option value="moyenne">Moyenne</option>
                        <option value="basse">Basse</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-task-statut">Statut</label>
                    <select id="edit-task-statut" name="statut" required>
                        <option value="a faire">À faire</option>
                        <option value="en cours">En cours</option>
                        <option value="terminée">Terminée</option>
                        <option value="validée">Validée</option>
                    </select>
                </div>
                <div class="form-group" id="edit-task-assigne">
                    <label>Assignations</label>
                    <div class="checkbox-group">
                        <?php foreach ($membres_projet as $mp): ?>
                            <label class="checkbox-item">
                                <input type="checkbox" name="assigne_ids[]" value="<?php echo (int) $mp['id']; ?>">
                                <?php echo htmlspecialchars($mp['prenom'] . ' ' . $mp['nom']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" id="cancel-edit-task" class="button-secondary">Annuler</button>
                    <button type="submit" class="button-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODALE CONFIRMATION COMITÉ -->
    <div id="committee-modal" class="modal-overlay hidden">
        <div class="modal-content ticket-modal-content">
            <button type="button" class="close-modal-button" id="close-committee-modal">×</button>
            <h2 class="ticket-modal-title">Créer une séparation de comité</h2>
            <p class="ticket-modal-subtitle">Cette action va créer un marqueur daté pour séparer les tickets du prochain
                comité.</p>
            <form method="POST" action="assets/php/tickets_committee.php">
                <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">
                <input type="hidden" name="add_committee" value="1">
                <div class="modal-actions">
                    <button type="button" id="cancel-committee" class="button-secondary">Annuler</button>
                    <button type="submit" class="button-primary">Confirmer</button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <div class="footer-content"
            style="text-align:center;margin-top:20px;color:#64748b;font-size:13px;justify-content:center">
            <p>© <?php echo date('Y'); ?> Gestion Projets. Tous droits réservés. · <a href="privacy.php">Politique
                    de confidentialité</a></p>
        </div>
    </footer>
    <script src="assets/javascript/upload_batch.js"></script>
    <script src="assets/javascript/projet.js"></script>
</body>

</html>