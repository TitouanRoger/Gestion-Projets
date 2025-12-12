document.addEventListener('DOMContentLoaded', function () {
// ============================================================
// INDEX.JS - DASHBOARD PRINCIPAL
// ============================================================
// Gestion de la page d'accueil: système d'inactivité,
// navigation entre sections (Projets, Paramètres),
// modales de création de projet et suppression de compte
// ============================================================

    // ============================================================
    // 1. SYSTÈME D'INACTIVITÉ
    // ============================================================
    // Détecte l'inactivité utilisateur (15 min) et affiche
    // un avertissement 1 minute avant la déconnexion automatique.
    // Distingue activité "douce" (mouvements) et "explicite" (clics)
    (function initInactivityGuard() {
        const LIMIT_MS = 15 * 60 * 1000; // 15 minutes
        const WARNING_MS = 60 * 1000; // 1 min avant
        let lastActivity = Date.now();
        let warned = false;
        // Séparer l'activité "douce" (mouvements) de l'activité "explicite" (clic bouton)
        function softActivity() { lastActivity = Date.now(); }
        function hardActivity() { lastActivity = Date.now(); warned = false; const b = document.getElementById('idle-warning'); if (b) b.remove(); }
        ['mousemove', 'keydown', 'scroll', 'touchstart', 'focus'].forEach(ev => window.addEventListener(ev, softActivity, { passive: true }));
        // Les clics généraux ne ferment plus la bannière; seul le bouton dédié le fait
        function showIdleBanner(text) {
            if (document.getElementById('idle-warning')) return;
            const bar = document.createElement('div');
            bar.id = 'idle-warning';
            Object.assign(bar.style, { position: 'fixed', bottom: '10px', right: '10px', background: '#1e293b', color: '#fff', padding: '10px 12px', borderRadius: '6px', boxShadow: '0 2px 6px rgba(0,0,0,.3)', zIndex: '99999', display: 'flex', gap: '10px', alignItems: 'center' });
            const msg = document.createElement('span'); msg.textContent = text;
            const btn = document.createElement('button'); btn.textContent = 'Rester connecté'; btn.style.background = '#10b981'; btn.style.color = '#fff'; btn.style.border = 'none'; btn.style.padding = '6px 10px'; btn.style.borderRadius = '4px'; btn.style.cursor = 'pointer';
            btn.addEventListener('click', () => {
                hardActivity();
                // Ping serveur pour rafraîchir last_activity côté serveur
                fetch('assets/php/ping.php', { method: 'HEAD' }).catch(() => { });
            });
            bar.appendChild(msg); bar.appendChild(btn);
            document.body.appendChild(bar);
        }
        setInterval(() => { const idle = Date.now() - lastActivity; if (!warned && idle >= (LIMIT_MS - WARNING_MS)) { warned = true; showIdleBanner('Inactivité détectée : déconnexion dans 1 minute…'); } if (idle >= LIMIT_MS) { window.location.href = 'assets/php/logout.php?reason=timeout'; } }, 1000);
    })();
    const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');
    const sections = document.querySelectorAll('.dashboard-section');
    const navItems = document.querySelectorAll('.sidebar-nav .nav-item');

    // ... (Déclaration des éléments des modales) ...

    // Éléments de la Modale de SUPPRESSION
    const deleteButton = document.getElementById('delete-account-button');
    const deleteModal = document.getElementById('delete-modal');
    const cancelDeleteButton = document.getElementById('cancel-delete');

    // Éléments de la Modale de PROJET
    const openNewProjectButton = document.getElementById('open-new-project-modal');
    const newProjectModal = document.getElementById('new-project-modal');
    const closeNewProjectButton = document.getElementById('close-new-project-modal');


    // ============================================================
    // 2. NAVIGATION ENTRE SECTIONS (ONGLETS)
    // ============================================================
    // Permet de basculer entre "Mes Projets" et "Paramètres"
    // en ajoutant/retirant la classe 'active' et en mettant
    // à jour l'URL (paramètre ?section=...) sans rechargement

    // --- Fonction de bascule de section (onglets) ---
    navLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const targetSectionId = this.getAttribute('data-section');

            // 1. Gérer les classes active (Visuel)
            navItems.forEach(item => item.classList.remove('active'));
            sections.forEach(section => section.classList.remove('active'));

            this.closest('.nav-item').classList.add('active');
            const targetSection = document.getElementById(targetSectionId);
            if (targetSection) {
                targetSection.classList.add('active');
            }

            // 2. 🚀 NOUVEAU : Mettre à jour l'URL pour refléter la section active 🚀
            const url = new URL(window.location.href);

            // Supprimer TOUS les paramètres d'alerte ou de provenance
            url.searchParams.delete('success');
            url.searchParams.delete('error');
            url.searchParams.delete('from');

            // Définir la nouvelle section active
            url.searchParams.set('section', targetSectionId);

            // Mettre à jour l'URL dans le navigateur sans recharger la page
            window.history.replaceState({ path: url.href }, '', url.href);
        });
    });


    // ============================================================
    // 3. GESTION DES MODALES
    // ============================================================
    // - Modale de suppression de compte (confirmation avant suppression)
    // - Modale de création de nouveau projet (formulaire)
    // Fermeture: clic overlay, Escape, ou bouton annuler

    // --- GESTION DE LA MODALE DE SUPPRESSION DE COMPTE ---
    if (deleteButton && deleteModal) {
        deleteButton.addEventListener('click', function () {
            deleteModal.classList.remove('hidden');
        });

        cancelDeleteButton.addEventListener('click', function () {
            deleteModal.classList.add('hidden');
        });

        deleteModal.addEventListener('click', function (e) {
            if (e.target === deleteModal) {
                deleteModal.classList.add('hidden');
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !deleteModal.classList.contains('hidden')) {
                deleteModal.classList.add('hidden');
            }
        });
    }

    // --- GESTION DE LA MODALE DE CRÉATION DE PROJET ---
    if (openNewProjectButton && newProjectModal) {
        openNewProjectButton.addEventListener('click', function () {
            newProjectModal.classList.remove('hidden');
        });

        closeNewProjectButton.addEventListener('click', function () {
            newProjectModal.classList.add('hidden');
        });

        newProjectModal.addEventListener('click', function (e) {
            if (e.target === newProjectModal) {
                newProjectModal.classList.add('hidden');
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !newProjectModal.classList.contains('hidden')) {
                newProjectModal.classList.add('hidden');
            }
        });
    }
});