document.addEventListener('DOMContentLoaded', function () {
// ============================================================
// RESET_PASSWORD.JS - RÉINITIALISATION DU MOT DE PASSE
// ============================================================
// Gère l'affichage/masquage des mots de passe dans le
// formulaire de réinitialisation (nouveau mot de passe et confirmation)
// ============================================================

    // ============================================================
    // AFFICHAGE/MASQUAGE DES MOTS DE PASSE
    // ============================================================
    // Toggle entre input type='password' et type='text'
    // Change l'icône: 👁️ (afficher) ↔ 🙈 (masquer)

    const passwordToggles = document.querySelectorAll('.password-toggle');

    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);

            if (targetInput.type === 'password') {
                targetInput.type = 'text';
                this.textContent = '🙈'; // Icône pour Masquer
            } else {
                targetInput.type = 'password';
                this.textContent = '👁️'; // Icône pour Afficher
            }
        });
    });
});