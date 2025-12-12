<?php
// ============================================================
// PROJET_ADMIN.PHP - PAGE D'ADMINISTRATION D'UN PROJET
// ============================================================
// Accessible uniquement au créateur du projet (Chef de projet)
// Permet de:
// - Ajouter/retirer des membres
// - Modifier les rôles des membres
// - Supprimer le projet entier
// ============================================================

session_start();
require 'assets/php/db_connect.php';
require_once 'assets/php/session_security.php';

enforce_inactivity_timeout(15 * 60, 'assets/php/logout.php', true);

// ============================================================
// 1. VÉRIFICATIONS DE SÉCURITÉ
// ============================================================
// - Utilisateur doit être connecté
// - ID de projet doit être valide (numérique)
// - Projet doit exister en base de données
// - Utilisateur doit être le créateur (createur_id)
// ============================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    // Redirige l'utilisateur non connecté vers la page d'authentification
    exit();
}

$user_id = $_SESSION['user_id'];
$project_id = $_GET['id'] ?? null;

if (!$project_id || !is_numeric($project_id)) {
    header("Location: index.php?error=" . urlencode("ID de projet invalide."));
    exit();
}

$projet = null;
try {
    $stmt = $pdo->prepare("SELECT id, nom_projet, createur_id FROM projets WHERE id = ?");
    $stmt->execute([$project_id]);
    $projet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$projet) {
        header("Location: index.php?error=" . urlencode("Projet non trouvé."));
        exit();
    }

    if ($user_id != $projet['createur_id']) {
        header("Location: projet.php?id=" . $project_id . "&error=" . urlencode("Vous n'êtes pas autorisé à administrer ce projet."));
        exit();
    }

} catch (\PDOException $e) {
    error_log("Erreur de BDD lors de la récupération du projet {$project_id} pour admin: " . $e->getMessage());
    header("Location: index.php?error=" . urlencode("Erreur technique lors du chargement de l'administration du projet."));
    exit();
}

$proprietaire = null;
$membres_projet = [];

// ============================================================
// 2. RÉCUPÉRATION DES DONNÉES DU PROJET
// ============================================================
// - Informations du projet (nom, créateur_id)
// - Vérifie que l'utilisateur est bien le créateur
// ============================================================
try {
    $stmt_owner = $pdo->prepare("SELECT id, prenom, nom, email FROM utilisateurs WHERE id = ?");
    $stmt_owner->execute([$projet['createur_id']]);
    $proprietaire = $stmt_owner->fetch(PDO::FETCH_ASSOC);

    $stmt_membres = $pdo->prepare("
        SELECT 
            u.id, 
            u.prenom, 
            u.nom, 
            u.email, 
            pm.role 
        FROM projet_membres pm
        JOIN utilisateurs u ON u.id = pm.utilisateur_id
        WHERE pm.projet_id = ?
        ORDER BY u.nom
    ");
    $stmt_membres->execute([$project_id]);
    $membres_projet = $stmt_membres->fetchAll(PDO::FETCH_ASSOC);

} catch (\PDOException $e) {
    error_log("Erreur de BDD lors de la récupération des membres du projet {$project_id}: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>Administration - <?php echo htmlspecialchars($projet['nom_projet']); ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/projet_admin.css">
    <link rel="stylesheet" href="assets/css/footer.css">
</head>

<body class="project-admin-page-body">
    <div class="project-admin-container">

        <?php
        // Affiche les messages d'erreur ou de succès récupérés de l'URL
        if (isset($_GET['error'])) {
            echo '<div class="alert alert-danger">' . htmlspecialchars(urldecode($_GET['error'])) . '</div>';
        }
        if (isset($_GET['success'])) {
            echo '<div class="alert alert-success">' . htmlspecialchars(urldecode($_GET['success'])) . '</div>';
        }
        ?>

        <header class="project-admin-header">
            <div class="project-admin-header-left">
                <a href="projet.php?id=<?php echo htmlspecialchars($project_id); ?>" class="back-link">← Retour au
                    projet</a>
                <h1>Administration du projet</h1>
                <p class="subtitle">Gérez les membres et les permissions de
                    "<?php echo htmlspecialchars($projet['nom_projet']); ?>"</p>
            </div>
            <button class="delete-project-button" id="delete-project-button">🗑️ Supprimer le projet</button>
        </header>

        <section class="admin-block">
            <div class="block-header">
                <h2>Membres du projet</h2>
                <button class="add-member-button" id="add-member-button">+ Ajouter un membre</button>
            </div>
            <p class="block-subtitle">Gérez qui peut accéder au projet et leurs rôles</p>

            <?php if (empty($membres_projet) && $proprietaire): ?>
                <div class="no-members-state">
                    <span class="no-members-icon">👤+</span>
                    <p class="no-members-title">Aucun membre supplémentaire</p>
                    <p class="no-members-text">Vous êtes le Chef de projet. Ajoutez des membres pour commencer à collaborer.
                    </p>
                    <button class="add-first-member-button" id="add-first-member-button">+ Ajouter le premier
                        membre</button>
                </div>
            <?php else: ?>
                <div class="members-list">
                    <?php if ($proprietaire): ?>
                        <div class="member-item member-owner">
                            <span
                                class="member-name"><?php echo htmlspecialchars($proprietaire['prenom'] . ' ' . $proprietaire['nom']); ?></span>
                            <span class="member-role member-role-owner">Chef de projet</span>
                            <div class="member-actions">
                                <span class="owner-note">(Vous)</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($membres_projet as $membre): ?>
                        <div class="member-item">
                            <span
                                class="member-name"><?php echo htmlspecialchars($membre['prenom'] . ' ' . $membre['nom']); ?></span>
                            <span
                                class="member-role"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $membre['role']))); ?></span>
                            <div class="member-actions">

                                <button class="edit-member-button">Modifier</button>

                                <form name="remove_member_form" method="POST" action="assets/php/projets_actions.php"
                                    style="display: inline;">
                                    <input type="hidden" name="remove_member" value="1">
                                    <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">
                                    <input type="hidden" name="member_id_to_remove"
                                        value="<?php echo htmlspecialchars($membre['id']); ?>">
                                    <button type="submit" class="button-remove-member">Retirer</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="block-footer">
                    <button class="add-first-member-button" id="add-footer-member-button">+ Ajouter un autre membre</button>
                </div>
            <?php endif; ?>
        </section>

    </div>

    <div id="delete-project-modal" class="modal-overlay hidden">
        <div class="modal-content">
            <h2 class="modal-title-danger">Confirmer la suppression du projet</h2>
            <p class="modal-body-danger">
                Êtes-vous absolument sûr de vouloir supprimer le projet
                <strong>"<?php echo htmlspecialchars($projet['nom_projet']); ?>"</strong> ?
                <br><br>
                Cette action est <strong>irréversible</strong> et toutes les données associées (tâches, tickets,
                documents) seront
                perdues.
            </p>

            <div class="modal-actions">
                <button type="button" id="cancel-project-delete" class="button-secondary">Annuler</button>

                <form id="confirm-project-delete-form" method="POST" action="assets/php/projets_actions.php"
                    style="display: inline;">
                    <input type="hidden" name="delete_project" value="1">
                    <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">
                    <button type="submit" class="button-danger">Supprimer définitivement le projet</button>
                </form>
            </div>
        </div>
    </div>

    <div id="add-member-modal" class="modal-overlay hidden">
        <div class="modal-content-small">
            <div class="modal-close-button" id="close-add-member-modal">&times;</div>
            <h2 class="modal-title">Ajouter un membre</h2>
            <p class="modal-subtitle">Inviter un utilisateur à rejoindre ce projet</p>

            <form method="POST" action="assets/php/projets_actions.php">
                <input type="hidden" name="add_member" value="1">
                <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">

                <label for="member_email">Email de l'utilisateur</label>
                <input type="email" id="member_email" name="member_email" placeholder="utilisateur@exemple.com"
                    required>

                <label for="member_role">Rôle</label>
                <select id="member_role" name="member_role" required>
                    <option value="" disabled selected>Sélectionner un rôle</option>
                    <option value="developpeur">Développeur</option>
                    <option value="redacteur">Rédacteur</option>
                    <option value="redacteur_technique">Rédacteur technique</option>
                    <option value="recetteur">Recetteur</option>
                    <option value="recetteur_technique">Recetteur technique</option>
                </select>

                <button type="submit" class="button-primary-dark">
                    <span class="icon-add">+</span> Ajouter le membre
                </button>
            </form>
        </div>
    </div>

    <div id="edit-member-modal" class="modal-overlay hidden">
        <div class="modal-content-small">
            <div class="modal-close-button" id="close-edit-member-modal">&times;</div>
            <h2 class="modal-title">Modifier le rôle</h2>
            <p class="modal-subtitle">Changer le rôle de <strong id="member-name-to-edit"></strong></p>

            <form id="edit-member-form" method="POST" action="assets/php/projets_actions.php">
                <input type="hidden" name="edit_member_role" value="1">
                <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">
                <input type="hidden" name="member_id_to_edit" id="member-id-to-edit">

                <label for="new_member_role">Nouveau Rôle</label>
                <select id="new_member_role" name="new_member_role" required>
                    <option value="" disabled selected>Sélectionner un nouveau rôle</option>
                    <option value="developpeur">Développeur</option>
                    <option value="redacteur">Rédacteur</option>
                    <option value="redacteur_technique">Rédacteur technique</option>
                    <option value="recetteur">Recetteur</option>
                    <option value="recetteur_technique">Recetteur technique</option>
                </select>

                <button type="submit" class="button-primary-dark">
                    Enregistrer les modifications
                </button>
            </form>
        </div>
    </div>

    <script src="assets/javascript/projet_admin.js"></script>
    <footer>
        <div class="footer-content"
            style="text-align:center;margin-top:20px;color:#64748b;font-size:13px;justify-content:center">
            <p>© <?php echo date('Y'); ?> Gestion Projets. Tous droits réservés. · <a href="privacy.php">Politique
                    de confidentialité</a></p>
        </div>
    </footer>
</body>

</html>