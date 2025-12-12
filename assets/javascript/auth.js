// ============================================================================
// PAGE D'AUTHENTIFICATION - GESTION DES ONGLETS ET MODALES
// ============================================================================

document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('.tab-button');
    const forms = document.querySelectorAll('.auth-form');
    const urlParams = new URLSearchParams(window.location.search);
    const passwordToggles = document.querySelectorAll('.password-toggle');
    const messageAlerts = document.querySelectorAll('.message-alert');

    /**
     * Active le bon onglet (Connexion ou Inscription) et masque les messages si c'est un clic manuel.
     * @param {string} targetTab - 'login' ou 'register'
     * @param {boolean} isManualClick - Vrai si le déclenchement vient d'un clic utilisateur (défaut: false)
     */
    function activateTab(targetTab, isManualClick = false) {

        // Masque les messages lors d'un clic manuel
        if (isManualClick) {
            messageAlerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }
        // --------------------------------------------------------------------------

        // 1. Désactiver tous les onglets et formulaires
        tabs.forEach(t => t.classList.remove('active'));
        forms.forEach(f => f.style.display = 'none');

        // 2. Activer l'onglet et le formulaire ciblés
        const activeTabButton = document.querySelector(`.tab-button[data-tab="${targetTab}"]`);
        const activeForm = document.getElementById(targetTab);

        if (activeTabButton && activeForm) {
            activeTabButton.classList.add('active');
            activeForm.style.display = 'block';
        }

        // La section de masquage précédente (point 3 de votre ancien code) est supprimée ou déplacée ci-dessus.
    }


    // --- 1. Gestion des Clics Manuels sur les Onglets (Déclenchement du masquage) ---
    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            activateTab(this.getAttribute('data-tab'), true);
        });
    });


    // --- 2. Activation de l'onglet après Redirection (au chargement de la page) ---
    const errorParam = urlParams.get('error');
    const tabParam = urlParams.get('tab');
    let initialTab = 'login'; // Par défaut

    if (errorParam && tabParam === 'register') {
        initialTab = 'register';
    } else if (urlParams.has('success') || !errorParam) {
        initialTab = 'login';
    }

    // Activation initiale SANS masquer les messages (false)
    activateTab(initialTab, false);


    // ========================================================================
    // AFFICHAGE/MASQUAGE DU MOT DE PASSE
    // ========================================================================
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);

            if (targetInput.type === 'password') {
                targetInput.type = 'text';
                this.textContent = '🙈';
            } else {
                targetInput.type = 'password';
                this.textContent = '👁️';
            }
        });
    });

    // --- 4. Suppression des messages de l'URL avec un délai ---
    if (window.history.replaceState && (urlParams.has('success') || urlParams.has('error') || urlParams.has('tab'))) {

        // Délai de 4 secondes pour le nettoyage de l'URL
        setTimeout(() => {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;

            // Remplacer l'historique sans recharger la page
            window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
        }, 4000);
    }

    // ========================================================================
    // MODALE "MOT DE PASSE OUBLIÉ"
    // ========================================================================
    const forgotModalOverlay = document.getElementById('forgot-modal-overlay');
    const showForgotButton = document.getElementById('show-forgot-modal');
    const closeForgotModal = document.getElementById('close-forgot-modal');

    // Montrer la modale
    if (showForgotButton) {
        showForgotButton.addEventListener('click', function (e) {
            e.preventDefault();
            forgotModalOverlay.style.display = 'flex';

            // Masquer les messages d'alerte lors de l'ouverture de la modale
            messageAlerts.forEach(alert => { alert.style.display = 'none'; });
        });
    }

    // Cacher la modale (bouton X)
    if (closeForgotModal) {
        closeForgotModal.addEventListener('click', function () {
            forgotModalOverlay.style.display = 'none';
        });
    }

    // Cacher la modale (clic en dehors)
    if (forgotModalOverlay) {
        forgotModalOverlay.addEventListener('click', function (e) {
            if (e.target === this) {
                forgotModalOverlay.style.display = 'none';
            }
        });
    }
});