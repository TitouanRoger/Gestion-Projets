<?php
// ============================================================
// CONTACT.PHP - PAGE DE CONTACT
// ============================================================
// Formulaire de contact permettant aux utilisateurs
// (connectés ou non) d'envoyer un message aux administrateurs
// Pré-remplit nom/prénom/email si utilisateur connecté
// ============================================================

session_start();
require __DIR__ . '/assets/php/auth_messages.php';

$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="stylesheet" href="assets/css/contact.css">
    <link rel="stylesheet" href="assets/css/footer.css">
</head>

<body>
    <!-- ============================ -->
    <!-- CONTENEUR PRINCIPAL -->
    <!-- ============================ -->
    <div class="app-container">
        <main class="main-content">
            <!-- En-tête avec bouton retour -->
            <section class="content-header">
                <div style="display:flex; align-items:center; gap:12px;">
                    <button type="button" class="btn" onclick="history.back()"
                        style="padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; background:#fff; cursor:pointer;">←
                        Retour</button>
                    <div>
                        <h1>Contact</h1>
                        <p>Une question, une remarque ou un bug ? Envoyez-nous un message.</p>
                    </div>
                </div>
            </section>


            <!-- Messages d'alerte (succès ou erreur) -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert success"><?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="alert error"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>

            <section class="card">
                <form action="assets/php/contact_submit.php" method="post" class="form-grid">
                    <div class="form-row">
                        <label for="prenom">Prénom</label>
                        <input type="text" id="prenom" name="prenom" required
                            value="<?php echo isset($_SESSION['user_prenom']) ? htmlspecialchars($_SESSION['user_prenom']) : ''; ?>">
                    </div>
                    <div class="form-row">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" required
                            value="<?php echo isset($_SESSION['user_nom']) ? htmlspecialchars($_SESSION['user_nom']) : ''; ?>">
                    </div>
                    <div class="form-row">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required
                            value="<?php echo isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : ''; ?>">
                    </div>
                    <div class="form-row">
                        <label for="sujet">Sujet</label>
                        <input type="text" id="sujet" name="sujet" required maxlength="150">
                    </div>
                    <div class="form-row">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" rows="6" required maxlength="2000"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="submit_contact" class="btn primary">Envoyer</button>
                    </div>
                </form>
            </section>


            <!-- ============================ -->
            <!-- FOOTER -->
            <!-- ============================ -->
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
</body>

</html>