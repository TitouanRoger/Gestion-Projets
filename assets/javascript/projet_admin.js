document.addEventListener('DOMContentLoaded', () => {
// ============================================================
// PROJET_ADMIN.JS - PAGE D'ADMINISTRATION DU PROJET
// ============================================================
// Gestion de la page admin d'un projet: système d'inactivité,
// modales pour ajouter/modifier/retirer des membres,
// suppression du projet
// ============================================================

    // ============================================================
    // 1. SYSTÈME D'INACTIVITÉ
    // ============================================================
    // Identique à index.js: détection automatique de l'inactivité
    // avec avertissement 1 min avant déconnexion (15 min total)
    (function initInactivityGuard() {
        const LIMIT_MS = 15 * 60 * 1000; // 15 minutes
        const WARNING_MS = 60 * 1000; // 1 min avant
        let lastActivity = Date.now();
        let warned = false;
        function softActivity(){ lastActivity = Date.now(); }
        function hardActivity(){ lastActivity = Date.now(); warned = false; const b = document.getElementById('idle-warning'); if (b) b.remove(); }
        ['mousemove','keydown','scroll','touchstart','focus'].forEach(ev => window.addEventListener(ev, softActivity, { passive:true }));
        function showIdleBanner(text){
            if(document.getElementById('idle-warning')) return;
            const bar=document.createElement('div');
            bar.id='idle-warning';
            Object.assign(bar.style,{position:'fixed',bottom:'10px',right:'10px',background:'#1e293b',color:'#fff',padding:'10px 12px',borderRadius:'6px',boxShadow:'0 2px 6px rgba(0,0,0,.3)',zIndex:'99999',display:'flex',gap:'10px',alignItems:'center'});
            const msg=document.createElement('span'); msg.textContent=text;
            const btn=document.createElement('button'); btn.textContent='Rester connecté'; btn.style.background='#10b981'; btn.style.color='#fff'; btn.style.border='none'; btn.style.padding='6px 10px'; btn.style.borderRadius='4px'; btn.style.cursor='pointer';
            btn.addEventListener('click',()=>{
                hardActivity();
                fetch('assets/php/ping.php',{method:'HEAD'}).catch(()=>{});
            });
            bar.appendChild(msg); bar.appendChild(btn);
            document.body.appendChild(bar);
        }
        setInterval(()=>{ const idle=Date.now()-lastActivity; if(!warned && idle>=(LIMIT_MS-WARNING_MS)){ warned=true; showIdleBanner('Inactivité détectée : déconnexion dans 1 minute…'); } if(idle>=LIMIT_MS){ window.location.href='assets/php/logout.php?reason=timeout'; } },1000);
    })();

    // ============================================================
    // 2. GESTION DES MODALES
    // ============================================================
    // - Modale de suppression du projet (confirmation)
    // - Modale d'ajout de membre (formulaire email)
    // - Modale d'édition du rôle d'un membre (sélecteur)
    // Fermeture: clic overlay, Escape, ou bouton annuler

    // -------------------------------------
    // --- Logique Générale des Modales ---
    // -------------------------------------

    // --- Suppression Modale ---
    const openDeleteButton = document.getElementById('delete-project-button');
    const closeDeleteButton = document.getElementById('cancel-project-delete');
    const deleteModal = document.getElementById('delete-project-modal');

    if (openDeleteButton) { openDeleteButton.addEventListener('click', () => { deleteModal.classList.remove('hidden'); }); }
    if (closeDeleteButton) { closeDeleteButton.addEventListener('click', () => { deleteModal.classList.add('hidden'); }); }

    // --- Ajout Membre Modale ---
    const openAddButtons = [
        document.getElementById('add-member-button'),
        document.getElementById('add-first-member-button'),
        document.getElementById('add-footer-member-button')
    ].filter(btn => btn !== null); // Filtre les boutons qui n'existent pas (selon l'état)

    const closeAddButton = document.getElementById('close-add-member-modal');
    const addModal = document.getElementById('add-member-modal');

    openAddButtons.forEach(button => {
        button.addEventListener('click', () => { addModal.classList.remove('hidden'); });
    });
    if (closeAddButton) { closeAddButton.addEventListener('click', () => { addModal.classList.add('hidden'); }); }


    // -------------------------------------
    // ============================================================
    // 3. MODALE MODIFIER RÔLE D'UN MEMBRE
    // ============================================================
    // Permet de changer le rôle d'un membre existant
    // (Développeur, Designer, Testeur, etc.)
    // Récupère l'ID membre depuis le formulaire de retrait
    // et pré-sélectionne le rôle actuel dans le select

    // --- Logique Modale Modifier Rôle ---
    // -------------------------------------

    const editMemberButtons = document.querySelectorAll('.edit-member-button');
    const editModal = document.getElementById('edit-member-modal');
    const closeEditModal = document.getElementById('close-edit-member-modal');

    const memberIdInput = document.getElementById('member-id-to-edit');
    const memberNameDisplay = document.getElementById('member-name-to-edit');

    // Fermer la modale
    if (closeEditModal) {
        closeEditModal.addEventListener('click', () => { editModal.classList.add('hidden'); });
    }

    // Ouvrir la modale et pré-remplir les données
    editMemberButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            // Remonter jusqu'à l'élément .member-item
            const memberItem = e.target.closest('.member-item');

            // Récupérer l'ID du membre depuis l'input caché dans le formulaire 'Retirer'
            // Le formulaire de retrait doit s'appeler 'remove_member_form'
            const removeForm = memberItem.querySelector('form[name="remove_member_form"]');

            // On vérifie si l'input existe, sinon l'ID n'est pas récupérable
            const memberIdInputInRemoveForm = removeForm ? removeForm.querySelector('input[name="member_id_to_remove"]') : null;
            const memberId = memberIdInputInRemoveForm ? memberIdInputInRemoveForm.value : null;

            // Récupérer le nom affiché et le rôle actuel
            const memberName = memberItem.querySelector('.member-name').textContent;
            const memberRoleElement = memberItem.querySelector('.member-role');

            // Si on a les infos, on pré-remplit
            if (memberId) {
                memberIdInput.value = memberId;
                memberNameDisplay.textContent = memberName;
                editModal.classList.remove('hidden');

                // Récupérer le rôle actuel pour le sélectionner par défaut
                // Remplace 'Chef de projet' par 'proprietaire' si besoin (pour l'affichage seul, bien que ce bouton soit normalement masqué pour le chef de projet)
                let currentRoleText = memberRoleElement.textContent.trim();

                if (currentRoleText === 'Chef de projet') {
                    // Si c'est le chef de projet (ce cas ne devrait pas arriver mais on sécurise)
                    document.getElementById('new_member_role').value = '';
                } else {
                    // Pour les autres rôles (ex: "Développeur" -> "developpeur")
                    const currentRoleValue = currentRoleText.toLowerCase().replace(' ', '_');
                    document.getElementById('new_member_role').value = currentRoleValue;
                }
            } else {
                console.error('Impossible de récupérer l\'ID du membre pour la modification. Vérifiez la structure HTML.');
            }
        });
    });

});