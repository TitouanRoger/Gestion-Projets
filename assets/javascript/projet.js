// ============================================================================
// MODALES PERSONNALISÉES
// ============================================================================

/**
 * Affiche une modale de saisie personnalisée (remplace prompt())
 * @param {Object} options - Configuration de la modale
 * @returns {Promise<string|null>} Valeur saisie ou null si annulé
 */
function showModalPrompt(options) {
    const { title = 'Saisie requise', label = 'Entrer une valeur:', placeholder = '', okText = 'OK', cancelText = 'Annuler', variant = 'info' } = options || {};
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';

    const modal = document.createElement('div');
    modal.className = 'modal-box ' + `variant-${variant}`;

    const h = document.createElement('div');
    h.textContent = title || '';
    h.className = 'modal-title';

    const lab = document.createElement('label');
    lab.textContent = label;
    lab.className = 'modal-label';

    const input = document.createElement('input');
    input.type = 'text';
    input.placeholder = placeholder;
    input.className = 'modal-input';
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') ok.click();
    });

    const actions = document.createElement('div');
    actions.className = 'modal-actions';

    const ok = document.createElement('button');
    ok.textContent = okText;
    ok.className = 'modal-btn primary';
    ok.addEventListener('click', () => {
        const val = input.value.trim();
        cleanup();
        // Retourner la chaîne même vide; null uniquement si Annuler
        resolve(val);
    });

    const cancel = document.createElement('button');
    cancel.textContent = cancelText;
    cancel.className = 'modal-btn';
    cancel.addEventListener('click', () => {
        cleanup();
        resolve(null);
    });

    actions.appendChild(ok);
    actions.appendChild(cancel);

    modal.appendChild(h);
    modal.appendChild(lab);
    modal.appendChild(input);
    modal.appendChild(actions);
    overlay.appendChild(modal);

    let resolve;
    function cleanup() {
        document.body.removeChild(overlay);
    }
    document.body.appendChild(overlay);
    setTimeout(() => input.focus(), 0);
    return new Promise((res) => { resolve = res; });
}

/**
 * Affiche une modale de confirmation personnalisée (remplace confirm())
 * @param {Object} options - Configuration de la modale
 * @returns {Promise<boolean>} true si confirmé, false si annulé
 */
function showModalConfirm(options) {
    const { title = 'Confirmation', message = 'Êtes-vous sûr ?', okText = 'OK', cancelText = 'Annuler', variant = 'info' } = options || {};
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';

    const modal = document.createElement('div');
    modal.className = 'modal-box ' + `variant-${variant}`;

    const h = document.createElement('div');
    h.textContent = title || '';
    h.className = 'modal-title';

    const msg = document.createElement('div');
    msg.textContent = message;
    msg.className = 'modal-message';

    const actions = document.createElement('div');
    actions.className = 'modal-actions';

    const ok = document.createElement('button');
    ok.textContent = okText;
    ok.className = 'modal-btn primary';

    const cancel = document.createElement('button');
    cancel.textContent = cancelText;
    cancel.className = 'modal-btn';

    actions.appendChild(ok);
    actions.appendChild(cancel);

    modal.appendChild(h);
    modal.appendChild(msg);
    modal.appendChild(actions);
    overlay.appendChild(modal);

    let resolve;
    function cleanup() { document.body.removeChild(overlay); }
    document.body.appendChild(overlay);
    ok.addEventListener('click', () => { cleanup(); resolve(true); });
    cancel.addEventListener('click', () => { cleanup(); resolve(false); });
    return new Promise((res) => { resolve = res; });
}

// ============================================================================
// INITIALISATION AU CHARGEMENT DU DOM
// ============================================================================

document.addEventListener('DOMContentLoaded', function () {

    // ========================================================================
    // SYSTÈME DE DÉTECTION D'INACTIVITÉ (15 minutes)
    // ========================================================================
    // Déconnecte automatiquement l'utilisateur après 15 min d'inactivité
    // avec avertissement 1 minute avant la déconnexion
    (function initInactivityGuard() {
        const LIMIT_MS = 15 * 60 * 1000;
        const WARNING_MS = 60 * 1000;
        let lastActivity = Date.now();
        let warned = false;

        // Met à jour le timestamp de dernière activité
        function softActivity() { lastActivity = Date.now(); }

        // Réinitialise complètement l'état d'inactivité
        function hardActivity() {
            lastActivity = Date.now();
            warned = false;
            const b = document.getElementById('idle-warning');
            if (b) b.remove();
        }

        // Écoute tous les types d'activité utilisateur
        ['mousemove', 'keydown', 'scroll', 'touchstart', 'focus'].forEach(ev =>
            window.addEventListener(ev, softActivity, { passive: true })
        );

        // Affiche une bannière d'avertissement avec bouton "Rester connecté"
        function showIdleBanner(text) {
            if (document.getElementById('idle-warning')) return;
            const bar = document.createElement('div');
            bar.id = 'idle-warning';
            bar.style.position = 'fixed';
            bar.style.bottom = '10px';
            bar.style.right = '10px';
            bar.style.background = '#1e293b';
            bar.style.color = '#fff';
            bar.style.padding = '10px 12px';
            bar.style.borderRadius = '6px';
            bar.style.boxShadow = '0 2px 6px rgba(0,0,0,.3)';
            bar.style.zIndex = '99999';
            bar.style.display = 'flex';
            bar.style.gap = '10px';
            bar.style.alignItems = 'center';

            const msg = document.createElement('span');
            msg.textContent = text;

            const btn = document.createElement('button');
            btn.textContent = 'Rester connecté';
            btn.style.background = '#10b981';
            btn.style.color = '#fff';
            btn.style.border = 'none';
            btn.style.padding = '6px 10px';
            btn.style.borderRadius = '4px';
            btn.style.cursor = 'pointer';
            btn.addEventListener('click', () => {
                hardActivity();
                fetch('assets/php/ping.php', { method: 'HEAD' }).catch(() => { });
            });

            bar.appendChild(msg);
            bar.appendChild(btn);
            document.body.appendChild(bar);
        }

        // Vérifie l'inactivité toutes les secondes
        setInterval(() => {
            const idle = Date.now() - lastActivity;

            // Affiche l'avertissement 1 minute avant déconnexion
            if (!warned && idle >= (LIMIT_MS - WARNING_MS)) {
                warned = true;
                showIdleBanner('Inactivité détectée : déconnexion dans 1 minute…');
            }

            // Déconnexion automatique après 15 minutes
            if (idle >= LIMIT_MS) {
                window.location.href = 'assets/php/logout.php?reason=timeout';
            }
        }, 1000);
    })();

    // ========================================================================
    // GESTION DES MODALES DE TICKETS
    // ========================================================================

    // Modale de création de nouveau ticket
    const newModal = document.getElementById('new-ticket-modal');
    const openNewButton = document.getElementById('open-new-ticket-modal');
    const closeNewButton = document.getElementById('close-new-ticket-modal');
    const cancelNewButton = document.getElementById('cancel-ticket');

    // Ouverture de la modale
    if (openNewButton && newModal) {
        openNewButton.addEventListener('click', () => {
            newModal.classList.remove('hidden');
        });
    }

    // Fermeture de la modale de création de ticket
    [closeNewButton, cancelNewButton].forEach(button => {
        if (button) {
            button.addEventListener('click', () => {
                newModal.classList.add('hidden');
            });
        }
    });

    // Clic en dehors pour fermer (Modale de création de ticket)
    if (newModal) {
        newModal.addEventListener('click', (e) => {
            if (e.target === newModal) {
                newModal.classList.add('hidden');
            }
        });
    }

    // --- 3. Logique de Filtrage des Tickets ---

    // ========================================================================
    // MODALE DE CRÉATION DE COMITÉ (séparateur de tickets)
    // ========================================================================
    const committeeModal = document.getElementById('committee-modal');
    const openCommitteeButton = document.getElementById('open-committee-modal');
    const closeCommitteeButton = document.getElementById('close-committee-modal');
    const cancelCommitteeButton = document.getElementById('cancel-committee');

    if (openCommitteeButton && committeeModal) {
        openCommitteeButton.addEventListener('click', () => {
            committeeModal.classList.remove('hidden');
        });
    }

    [closeCommitteeButton, cancelCommitteeButton].forEach(button => {
        if (button) {
            button.addEventListener('click', () => {
                committeeModal.classList.add('hidden');
            });
        }
    });

    if (committeeModal) {
        committeeModal.addEventListener('click', (e) => {
            if (e.target === committeeModal) {
                committeeModal.classList.add('hidden');
            }
        });
    }

    // ========================================================================
    // SYSTÈME DE FILTRAGE DES TICKETS
    // ========================================================================
    const statutFilter = document.getElementById('statut_filter');
    const typeFilter = document.getElementById('type_filter');

    // Applique les filtres sélectionnés en reconstruisant l'URL
    function applyTicketFilters() {
        const currentUrl = new URL(window.location.href);

        currentUrl.searchParams.delete('success');
        currentUrl.searchParams.delete('error');
        currentUrl.searchParams.set('tab', 'tickets'); // S'assurer que l'onglet est bien 'tickets'

        // 1. Statut
        const statut = statutFilter.value;
        if (statut && statut !== 'all') {
            currentUrl.searchParams.set('filter_statut', statut);
        } else {
            currentUrl.searchParams.delete('filter_statut');
        }

        // 2. Type
        const type = typeFilter.value;
        if (type && type !== 'all') {
            currentUrl.searchParams.set('filter_type', type);
        } else {
            currentUrl.searchParams.delete('filter_type');
        }

        window.location.href = currentUrl.toString();
    }

    // Attacher les écouteurs d'événements de changement pour les tickets
    if (statutFilter) {
        statutFilter.addEventListener('change', applyTicketFilters);
    }
    if (typeFilter) {
        typeFilter.addEventListener('change', applyTicketFilters);
    }

    // Intercepte tous les formulaires nécessitant une confirmation
    // Remplace confirm() natif par modale personnalisée
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = form.getAttribute('data-confirm') || 'Confirmer ?';
            const ok = await showModalConfirm({ message: msg });
            if (ok) form.submit();
        });
    });


    // ========================================================================
    // GESTION DES TÂCHES (TASKS)
    // ========================================================================

    // Éléments des modales de tâches
    const newTaskModal = document.getElementById('new-task-modal');
    const openNewTaskModalBtn = document.getElementById('open-new-task-modal');
    const closeNewTaskModalBtn = document.getElementById('close-new-task-modal');
    const cancelTaskBtn = document.getElementById('cancel-task');

    const editTaskModal = document.getElementById('edit-task-modal');
    const closeEditTaskModalBtn = document.getElementById('close-edit-task-modal');
    const cancelEditTaskBtn = document.getElementById('cancel-edit-task');
    const editTaskIcons = document.querySelectorAll('.open-edit-task-modal');

    // Modale de création de tâche
    if (openNewTaskModalBtn) {
        openNewTaskModalBtn.addEventListener('click', () => newTaskModal.classList.remove('hidden'));
    }
    [closeNewTaskModalBtn, cancelTaskBtn].forEach(button => {
        if (button) button.addEventListener('click', () => newTaskModal.classList.add('hidden'));
    });
    if (newTaskModal) {
        newTaskModal.addEventListener('click', (e) => {
            if (e.target === newTaskModal) newTaskModal.classList.add('hidden');
        });
    }

    // Modale d'édition de tâche
    if (closeEditTaskModalBtn) {
        closeEditTaskModalBtn.addEventListener('click', () => editTaskModal.classList.add('hidden'));
    }
    if (cancelEditTaskBtn) {
        cancelEditTaskBtn.addEventListener('click', () => editTaskModal.classList.add('hidden'));
    }
    if (editTaskModal) {
        editTaskModal.addEventListener('click', (e) => {
            if (e.target === editTaskModal) editTaskModal.classList.add('hidden');
        });
    }

    // Remplit la modale d'édition avec les données de la tâche sélectionnée
    editTaskIcons.forEach(icon => {
        icon.addEventListener('click', function (e) {
            e.preventDefault();

            // Récupération des données simples
            document.getElementById('edit-task-id').value = this.dataset.id;
            document.getElementById('edit-task-titre').value = this.dataset.titre;
            document.getElementById('edit-task-description').value = this.dataset.description;
            document.getElementById('edit-task-type').value = this.dataset.type;
            document.getElementById('edit-task-priorite').value = this.dataset.priorite;
            document.getElementById('edit-task-statut').value = this.dataset.statut;

            // --- NOUVEAU: GESTION DES CASES À COCHER D'ASSIGNATION MULTIPLE ---
            const assignedIdsString = this.dataset.assignedIds || ''; // Assurez-vous d'avoir cet attribut sur l'icône (voir point 4)
            const assignedIds = assignedIdsString.split(',').map(id => id.trim()).filter(id => id !== ''); // Diviser et nettoyer

            // Récupérer toutes les cases à cocher d'assignation
            const assignCheckboxes = document.querySelectorAll('#edit-task-assigne input[type="checkbox"]');

            assignCheckboxes.forEach(checkbox => {
                const userId = checkbox.value;
                // Cocher la case si l'ID utilisateur est dans la liste des IDs assignés
                checkbox.checked = assignedIds.includes(userId);
            });
            // -----------------------------------------------------------------

            editTaskModal.classList.remove('hidden');
        });
    });

    // ========================================================================
    // FILTRAGE DES TÂCHES
    // ========================================================================
    const taskFilterSelects = document.querySelectorAll('.tasks-filter-bar select');
    const taskFilterForm = document.getElementById('task-filter-form');

    // Soumet le formulaire automatiquement lors du changement de filtre
    if (taskFilterForm) {
        taskFilterSelects.forEach(select => {
            select.addEventListener('change', () => {
                // Le formulaire est déjà configuré dans le HTML pour l'onglet 'tasks'
                taskFilterForm.submit();
            });
        });
    }


    // ========================================================================
    // ACTIONS AJAX SUR LES TÂCHES (assignation, changement statut)
    // ========================================================================

    /**
     * Exécute une action sur une tâche via AJAX
     * @param {number} taskId - ID de la tâche
     * @param {string} action - Type d'action (toggle_assign, toggle_status, etc.)
     * @param {Object} data - Données additionnelles
     */
    async function updateTaskAction(taskId, action, data = {}) {
        const projectId = document.querySelector('input[name="project_id"]').value;
        try {
            const formData = new FormData();
            formData.append('task_id', taskId);
            formData.append('action', action);
            formData.append('project_id', projectId);

            for (const key in data) {
                formData.append(key, data[key]);
            }

            const response = await fetch('assets/php/tasks.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Ajouter un message de succès à l'URL avant de recharger
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('success', result.message);
                window.location.href = currentUrl.toString();
            } else {
                alert('Erreur: ' + result.message);
            }
        } catch (error) {
            console.error('Erreur de requête AJAX:', error);
            alert('Une erreur technique est survenue.');
        }
    }

    // Boutons d'auto-assignation/désassignation des tâches
    const assignTaskButtons = document.querySelectorAll('.assign-task-button');
    assignTaskButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const taskId = btn.getAttribute('data-task-id');
            updateTaskAction(taskId, 'toggle_assign');
        });
    });
    // Cases à cocher pour marquer les tâches comme terminées
    const taskDoneCheckboxes = document.querySelectorAll('.task-done-checkbox');
    taskDoneCheckboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            const taskId = cb.getAttribute('data-id');
            const newStatus = cb.checked ? 'terminée' : 'a faire';
            updateTaskAction(taskId, 'toggle_status', { statut: newStatus });
        });
    });

    // ========================================================================
    // SYSTÈME DE CHAT TEMPS RÉEL (messages privés)
    // ========================================================================
    // Configuration globale du chat (disponible sur toute la page projet)
    const projectId = window.PROJECT_ID || new URL(window.location.href).searchParams.get('id');
    const currentUserId = window.CURRENT_USER_ID;

    // Fonction beep globale (Web Audio API)
    function playBeep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator(); const gain = ctx.createGain();
            osc.type = 'sine'; osc.frequency.value = 880; // La
            osc.connect(gain); gain.connect(ctx.destination);
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
            osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.15);
        } catch (e) { }
    }

    // Toast host global pour notifications
    const toastHost = document.createElement('div');
    toastHost.className = 'toast-host';
    document.body.appendChild(toastHost);

    function showToast(text) {
        const t = document.createElement('div');
        t.className = 'toast-item';
        t.textContent = text;
        toastHost.appendChild(t);
        setTimeout(() => { t.classList.add('fade'); setTimeout(() => t.remove(), 400); }, 3000);
    }

    // Polling global des nouveaux messages (fonctionne sur tous les onglets)
    // Utiliser sessionStorage pour persister les IDs entre les changements d'onglet
    const storageKey = `project_${projectId}_lastMessageIds`;

    // Charger les derniers IDs depuis le sessionStorage
    let lastMessageIds = {};
    try {
        const stored = sessionStorage.getItem(storageKey);
        if (stored) lastMessageIds = JSON.parse(stored);
    } catch (e) { }

    async function pollNewMessages() {
        try {
            const res = await fetch(`assets/php/messages.php?action=list&project_id=${encodeURIComponent(projectId)}`);
            if (!res.ok) return;
            const data = await res.json();

            // Calculer le total de messages non lus
            let totalUnread = 0;
            data.forEach(t => {
                if (t.unread > 0) totalUnread += t.unread;
            });

            // Mettre à jour le badge sur l'onglet Messages
            const messagesTab = document.querySelector('a[href*="tab=messages"]');
            if (messagesTab) {
                let badge = messagesTab.querySelector('.badge');
                if (totalUnread > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'badge';
                        messagesTab.appendChild(badge);
                    }
                    badge.textContent = totalUnread;
                } else if (badge) {
                    badge.remove();
                }
            }

            // Vérifier les nouveaux messages
            data.forEach(t => {
                const prevLast = lastMessageIds[t.other_id] || 0;
                // Ne notifier que si : nouveau message ET envoyé par l'autre ET pas encore vu
                if (t.last_message_id > prevLast && t.last_sender_id != currentUserId) {
                    playBeep();
                    const senderName = t.other_name || `Utilisateur ${t.other_id}`;
                    showToast(`Nouveau message de ${senderName}`);
                }
                // Toujours mettre à jour le dernier ID connu
                lastMessageIds[t.other_id] = t.last_message_id;
            });

            // Sauvegarder dans sessionStorage
            sessionStorage.setItem(storageKey, JSON.stringify(lastMessageIds));
        } catch (e) { }
    }

    // Lancer le polling global toutes les 5 secondes
    setInterval(pollNewMessages, 5000);
    pollNewMessages(); // Premier appel immédiat

    // ========================================================================
    // UI CHAT (uniquement si dans l'onglet Messages)
    // ========================================================================
    const messagesContainer = document.querySelector('.messages-view-container');
    if (messagesContainer) {
        let selectedOtherId = window.INITIAL_OTHER_ID || null;

        // Éléments DOM du chat
        const membersList = document.getElementById('chat-members');
        const messagesEl = document.getElementById('chat-messages');
        const threadTitleEl = document.getElementById('chat-thread-title');
        const typingEl = document.getElementById('typing-indicator');
        const sendForm = document.getElementById('chat-send-form');
        const textarea = document.getElementById('chat-message');
        const sendBtn = document.getElementById('chat-send-btn');
        const recipientInput = document.getElementById('chat-recipient-id');
        const emptyHint = document.getElementById('empty-thread-hint');
        const composeBox = document.getElementById('chat-compose');
        const loadedMessageIds = {}; // Track per conversation message ids
        let lastTypingSent = 0;

        function escapeHtml(s) { return s.replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[c])); }

        function buildMsgElement(m, otherLastRead) {
            const row = document.createElement('div');
            row.className = 'msg-row' + (m.from == currentUserId ? ' mine' : '');
            const bubble = document.createElement('div');
            bubble.className = 'msg-bubble';
            const p = document.createElement('p');
            p.innerHTML = escapeHtml(m.text).replace(/\n/g, '<br>');
            bubble.appendChild(p);
            // Footer (date + statut lecture éventuel)
            const footer = document.createElement('div');
            footer.className = 'msg-footer';
            const time = document.createElement('span');
            time.className = 'msg-time';
            time.textContent = m.at;
            footer.appendChild(time);
            if (m.from == currentUserId) {
                const st = document.createElement('span');
                st.className = 'msg-status';
                st.textContent = (otherLastRead && m.id <= otherLastRead) ? '✓✓' : '✓';
                footer.appendChild(st);
            }
            bubble.appendChild(footer);
            if (m.files && m.files.length) {
                const ul = document.createElement('ul');
                ul.className = 'msg-files';
                m.files.forEach(f => {
                    const li = document.createElement('li');
                    const a = document.createElement('a');
                    a.href = `assets/php/download_message_file.php?project_id=${encodeURIComponent(projectId)}&message_id=${m.id}&file_id=${f.file_id}`;
                    a.target = '_blank'; a.rel = 'noopener';
                    a.textContent = `📎 ${f.name} (${Math.round(f.size / 1024)} Ko)`;
                    li.appendChild(a); ul.appendChild(li);
                });
                bubble.appendChild(ul);
            }
            row.appendChild(bubble);
            return row;
        }

        async function loadThreads() {
            try {
                const res = await fetch(`assets/php/messages.php?action=list&project_id=${encodeURIComponent(projectId)}`);
                if (!res.ok) return;
                const data = await res.json();
                // Mettre à jour uniquement les badges UI (notifications gérées globalement)
                const map = {}; data.forEach(t => map[t.other_id] = t);
                membersList.querySelectorAll('.chat-member').forEach(li => {
                    const oid = parseInt(li.getAttribute('data-user')); const info = map[oid];
                    const badge = li.querySelector('.unread-badge');
                    if (info && info.unread > 0) {
                        badge.textContent = info.unread; li.classList.add('has-unread');
                    } else { badge.textContent = '0'; li.classList.remove('has-unread'); }
                });
            } catch (e) { }
        }

        async function loadThread() {
            if (!selectedOtherId) return;
            try {
                const res = await fetch(`assets/php/messages.php?action=thread&project_id=${encodeURIComponent(projectId)}&other_id=${encodeURIComponent(selectedOtherId)}`);
                if (!res.ok) return; const json = await res.json();
                const msgs = json.messages || []; const typing = json.typing || false; const otherLastRead = json.other_last_read || 0;
                typingEl.style.display = typing ? 'block' : 'none';
                if (!loadedMessageIds[selectedOtherId]) loadedMessageIds[selectedOtherId] = new Set();
                let appended = 0; msgs.forEach(m => {
                    if (!loadedMessageIds[selectedOtherId].has(m.id)) {
                        messagesEl.appendChild(buildMsgElement(m, otherLastRead));
                        loadedMessageIds[selectedOtherId].add(m.id); appended++;
                    }
                });
                if (appended > 0) { messagesEl.scrollTop = messagesEl.scrollHeight; }
                if (emptyHint) emptyHint.style.display = msgs.length ? 'none' : 'block';
            } catch (e) { }
        }

        function selectMember(id, name) {
            selectedOtherId = id; recipientInput.value = id; threadTitleEl.textContent = name; sendBtn.disabled = false;
            messagesEl.innerHTML = ''; if (emptyHint) emptyHint.style.display = 'block'; loadedMessageIds[id] = new Set();
            membersList.querySelectorAll('.chat-member').forEach(li => li.classList.toggle('active', parseInt(li.getAttribute('data-user')) === id));
            if (composeBox) composeBox.style.display = 'block';
            loadThread();
        }

        membersList.addEventListener('click', e => {
            const li = e.target.closest('.chat-member'); if (!li) return;
            const oid = parseInt(li.getAttribute('data-user')); const nm = li.querySelector('.name').textContent.trim();
            selectMember(oid, nm);
        });

        if (selectedOtherId) { // Pré-sélection depuis URL
            const preLi = membersList.querySelector(`.chat-member[data-user='${selectedOtherId}']`);
            if (preLi) { selectMember(parseInt(selectedOtherId), preLi.querySelector('.name').textContent.trim()); }
        }

        sendForm.addEventListener('submit', async e => {
            e.preventDefault(); if (!selectedOtherId) return;
            const fd = new FormData(sendForm);
            try {
                const res = await fetch('assets/php/messages.php', { method: 'POST', body: fd }); const j = await res.json().catch(() => ({}));
                if (res.ok && j.status === 'OK') { sendForm.reset(); textarea.value = ''; sendBtn.disabled = false; loadThread(); showToast('Message envoyé'); }
                else showToast(j.error || 'Erreur envoi');
            } catch (err) { showToast('Erreur réseau'); }
        });

        textarea.addEventListener('input', () => { if (!selectedOtherId) return; const now = Date.now(); if (now - lastTypingSent > 300) { lastTypingSent = now; sendTyping(); } });

        async function sendTyping() {
            try { await fetch('assets/php/messages.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=typing&project_id=${encodeURIComponent(projectId)}&other_id=${encodeURIComponent(selectedOtherId)}` }); } catch (e) { }
        }

        // Polling threads & current thread (UI uniquement)
        setInterval(loadThreads, 5000);
        setInterval(() => { if (selectedOtherId) loadThread(); }, 4000);
        loadThreads(); if (selectedOtherId) loadThread();
    }

    // --------------------------------------------------
    // --- Section Code Repository ---
    // --------------------------------------------------
    // --------------------------------------------------
    // --- Section Documents (listing/upload/suppression)
    // --------------------------------------------------
    const docsView = document.querySelector('.documents-view-container');
    if (docsView) {
        const docLists = Array.from(document.querySelectorAll('.documents-list'));
        const anyList = docLists[0];
        const projectId = (anyList && anyList.getAttribute('data-project-id')) || new URL(window.location.href).searchParams.get('id');
        const userRole = (document.getElementById('documents-user-role')?.value || '');
        const sortKeySel = document.getElementById('docs-sort-key');
        const sortDirSel = document.getElementById('docs-sort-dir');

        // Permissions par catégorie selon le rôle (doit correspondre au backend)
        const rolePermissions = {
            'cahier_des_charges': ['proprietaire', 'redacteur', 'redacteur_technique'],
            'comites_projet': ['proprietaire', 'redacteur', 'redacteur_technique'],
            'guide_utilisateur': ['proprietaire', 'redacteur', 'redacteur_technique'],
            'rapport_activites': ['proprietaire', 'recetteur', 'recetteur_technique'],
            'rapports_propositions': ['proprietaire', 'redacteur', 'redacteur_technique'],
            'recettes': ['proprietaire', 'recetteur', 'recetteur_technique'],
            'reponses_techniques': ['proprietaire', 'redacteur', 'redacteur_technique'],
        };

        function canManageCategory(role, category) {
            if (role === 'proprietaire') return true;
            const allowed = rolePermissions[category] || [];
            return allowed.includes(role);
        }

        function humanSize(bytes) {
            if (bytes === 0 || bytes === undefined || bytes === null) return '0 o';
            const units = ['o', 'Ko', 'Mo', 'Go'];
            let i = 0, v = bytes;
            while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
            return (Math.round(v * 10) / 10) + ' ' + units[i];
        }

        function formatDate(ts) {
            try {
                const d = new Date(ts * 1000);
                return d.toLocaleString('fr-FR');
            } catch (_) { return ''; }
        }

        function getSortKey() {
            const v = sortKeySel ? sortKeySel.value : 'mtime';
            return (v === 'name' || v === 'size' || v === 'mtime') ? v : 'mtime';
        }

        function getSortDir() {
            const v = sortDirSel ? sortDirSel.value : 'desc';
            return (v === 'asc' || v === 'desc') ? v : 'desc';
        }

        function sortFiles(files) {
            const key = getSortKey();
            const dir = getSortDir();
            const mul = dir === 'asc' ? 1 : -1;
            return files.sort((a, b) => {
                if (key === 'name') {
                    return a.name.localeCompare(b.name) * mul;
                }
                if (key === 'size') {
                    const as = a.size || 0, bs = b.size || 0;
                    return (as - bs) * mul;
                }
                // mtime par défaut
                const am = a.mtime || 0, bm = b.mtime || 0;
                return (am - bm) * mul;
            });
        }

        function renderCategory(container, files, category) {
            container.innerHTML = '';
            if (!files || !files.length) {
                const em = document.createElement('em');
                em.textContent = 'Aucun document';
                container.appendChild(em);
                return;
            }
            const ul = document.createElement('ul');
            ul.className = 'documents-files';
            const sorted = sortFiles(files.slice());
            const canEdit = canManageCategory(userRole, category);
            sorted.forEach(f => {
                const li = document.createElement('li');
                li.className = 'document-item';
                const name = f.name;

                const left = document.createElement('div');
                left.className = 'doc-left';
                const a = document.createElement('a');
                a.className = 'doc-link';
                a.href = '#';
                a.textContent = name;
                a.addEventListener('click', (ev) => { ev.preventDefault(); openDocPreview(category, name); });
                const meta = document.createElement('span');
                meta.className = 'doc-meta';
                meta.textContent = ` — ${humanSize(f.size)} • ${formatDate(f.mtime)}`;
                left.appendChild(a);
                left.appendChild(meta);

                const right = document.createElement('div');
                right.className = 'doc-right';
                const dl = document.createElement('a');
                dl.className = 'button-small';
                dl.textContent = 'Télécharger';
                dl.href = `assets/php/documents.php?action=download&project_id=${encodeURIComponent(projectId)}&category=${encodeURIComponent(category)}&name=${encodeURIComponent(name)}`;
                dl.target = '_blank'; dl.rel = 'noopener';
                right.appendChild(dl);
                if (canEdit) {
                    const del = document.createElement('button');
                    del.type = 'button';
                    del.className = 'button-small button-secondary';
                    del.textContent = 'Supprimer';
                    del.addEventListener('click', async () => {
                        const ok = await showModalConfirm({ message: `Supprimer “${name}” ?`, variant: 'error' });
                        if (!ok) return;
                        try {
                            const fd = new FormData();
                            fd.append('action', 'delete');
                            fd.append('project_id', projectId);
                            fd.append('category', category);
                            fd.append('name', name);
                            const res = await fetch('assets/php/documents.php', { method: 'POST', body: fd });
                            const j = await res.json().catch(() => ({}));
                            if (res.ok && j.status === 'OK') {
                                await loadAll();
                                showModalAlert('Document supprimé', { variant: 'success' });
                            } else {
                                showModalAlert(j.error || 'Suppression refusée', { variant: 'error' });
                            }
                        } catch (_) {
                            showModalAlert('Erreur réseau', { variant: 'error' });
                        }
                    });
                    right.appendChild(del);
                }

                li.appendChild(left);
                li.appendChild(right);
                ul.appendChild(li);
            });
            container.appendChild(ul);
        }

        async function loadAll() {
            // Afficher un état de chargement
            docLists.forEach(c => c.innerHTML = '<div class="small-muted">Chargement…</div>');
            try {
                const url = `assets/php/documents.php?action=list&project_id=${encodeURIComponent(projectId)}`;
                const res = await fetch(url);
                const json = await res.json();
                if (!res.ok || json.error) throw new Error(json.error || 'Erreur');
                const filesMap = json.files || {};
                docLists.forEach(c => {
                    const cat = c.getAttribute('data-category');
                    renderCategory(c, filesMap[cat] || [], cat);
                });
            } catch (e) {
                docLists.forEach(c => c.innerHTML = '<div class="small-error">Impossible de charger la liste</div>');
            }
        }

        // Masquer les formulaires d'upload pour les catégories où l'utilisateur n'a pas la permission
        document.querySelectorAll('.document-category-block').forEach(block => {
            const form = block.querySelector('.category-actions .inline-form');
            if (!form) return;
            const categoryInput = form.querySelector('input[name="category"]');
            const category = categoryInput ? categoryInput.value : '';
            if (!canManageCategory(userRole, category)) {
                form.style.display = 'none';
            }
        });
        // Gérer les formulaires d'upload par catégorie (si autorisé)
        document.querySelectorAll('.document-category-block form[action*="documents.php"]').forEach(form => {
            const categoryInput = form.querySelector('input[name="category"]');
            const category = categoryInput ? categoryInput.value : '';
            if (!canManageCategory(userRole, category)) return; // ne rien attacher si non autorisé
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const fd = new FormData(form);
                // Assurer project_id présent (au cas où)
                if (!fd.get('project_id')) fd.append('project_id', projectId);
                try {
                    const res = await fetch('assets/php/documents.php', { method: 'POST', body: fd });
                    const j = await res.json().catch(() => ({}));
                    if (res.ok && j.status === 'OK') {
                        form.reset();
                        await loadAll();
                        showModalAlert('Fichier téléversé', { variant: 'success' });
                    } else {
                        showModalAlert(j.error || 'Upload refusé', { variant: 'error' });
                    }
                } catch (_) {
                    showModalAlert('Erreur réseau', { variant: 'error' });
                }
            });
        });

        function openDocPreview(category, name) {
            const ext = (name.split('.').pop() || '').toLowerCase();
            const url = `assets/php/documents.php?action=view&project_id=${encodeURIComponent(projectId)}&category=${encodeURIComponent(category)}&name=${encodeURIComponent(name)}`;
            const overlay = document.createElement('div');
            overlay.className = 'doc-preview-overlay';
            const modal = document.createElement('div');
            modal.className = 'doc-preview-modal';
            const header = document.createElement('div');
            header.className = 'doc-preview-header';
            const title = document.createElement('div');
            title.className = 'doc-preview-title';
            title.textContent = name;
            const close = document.createElement('button');
            close.className = 'doc-preview-close';
            close.textContent = '×';
            close.addEventListener('click', () => document.body.removeChild(overlay));
            header.appendChild(title);
            header.appendChild(close);
            const body = document.createElement('div');
            body.className = 'doc-preview-body';
            let contentEl;
            if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext)) {
                const img = document.createElement('img');
                img.src = url;
                img.alt = name;
                img.className = 'doc-preview-image';
                contentEl = img;
            } else {
                const iframe = document.createElement('iframe');
                iframe.src = url;
                iframe.className = 'doc-preview-frame';
                iframe.setAttribute('title', name);
                contentEl = iframe;
            }
            body.appendChild(contentEl);
            modal.appendChild(header);
            modal.appendChild(body);
            overlay.appendChild(modal);
            overlay.addEventListener('click', (e) => { if (e.target === overlay) document.body.removeChild(overlay); });
            document.body.appendChild(overlay);
        }

        if (sortKeySel) sortKeySel.addEventListener('change', loadAll);
        if (sortDirSel) sortDirSel.addEventListener('change', loadAll);
        loadAll();
    }

    const codeContainer = document.querySelector('.code-repo-container');
    if (codeContainer) {
        const projectId = document.getElementById('code-project-id').value;
        const isOwner = document.getElementById('code-is-owner').value === '1';
        const treeEl = document.getElementById('code-tree');
        const rootDropEl = document.getElementById('code-root-drop');
        const previewEl = document.getElementById('code-preview');
        const uploadInput = document.getElementById('code-upload-input');
        const uploadFolderInput = document.getElementById('code-upload-folder-input');
        const btnCreateFolder = document.getElementById('code-create-folder-btn');
        const btnCreateFile = document.getElementById('code-create-file-btn');
        const btnUpload = document.getElementById('code-upload-files-btn');
        const btnUploadFolder = document.getElementById('code-upload-folder-btn');
        const btnDownloadAll = document.getElementById('code-download-all-btn');
        const btnDownloadSelection = document.getElementById('code-download-selection-btn');
        const btnRename = document.getElementById('code-rename-btn');
        const btnDelete = document.getElementById('code-delete-btn');
        let selectedPaths = new Set();
        let singleSelected = null; // Dernier élément cliqué (pour rename/delete/move)
        let lastSelectedIndex = null; // Pour SHIFT+CLICK
        const flatIndexMap = new Map(); // path -> index selon l'ordre d'affichage
        const collapsedState = new Map(); // path -> boolean (true = collapsed)
        let currentUploadParent = ''; // Dossier parent pour upload

        function getUploadParent() {
            // Priorité au dossier courant mémorisé
            if (currentUploadParent) return currentUploadParent;
            // Sinon, si un dossier est sélectionné, l'utiliser
            if (singleSelected && !singleSelected.includes('.')) return singleSelected;
            // Sinon racine
            return '';
        }
        // Réutilise le système de toasts global (chat) si déjà créé, sinon en crée un
        let codeToastHost = document.querySelector('.toast-host');
        if (!codeToastHost) {
            codeToastHost = document.createElement('div');
            codeToastHost.className = 'toast-host';
            document.body.appendChild(codeToastHost);
        }
        function showCodeToast(msg) {
            const t = document.createElement('div');
            t.className = 'toast-item';
            t.textContent = msg;
            codeToastHost.appendChild(t);
            setTimeout(() => { t.classList.add('fade'); setTimeout(() => t.remove(), 400); }, 2500);
        }

        function api(action, params = {}, method = 'GET') {
            const base = `assets/php/code_repo.php?action=${encodeURIComponent(action)}&project_id=${encodeURIComponent(projectId)}`;
            if (method === 'GET') {
                const extra = Object.entries(params).map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
                return fetch(extra ? base + '&' + extra : base).then(r => r.json());
            } else {
                const fd = new FormData();
                for (const k in params) fd.append(k, params[k]);
                return fetch(base, { method, body: fd }).then(r => r.json());
            }
        }

        // Désélectionner en cliquant en dehors de l'arborescence
        document.addEventListener('click', (e) => {
            // Ne pas désélectionner si clic dans la barre d’actions ou dans la zone de preview (boutons Éditer/Télécharger)
            if (!treeEl.contains(e.target) && !e.target.closest('.code-actions-bar') && !e.target.closest('#code-preview')) {
                document.querySelectorAll('.code-tree-row.selected').forEach(el => el.classList.remove('selected'));
                singleSelected = null;
                // Ne pas forcer currentUploadParent à vide; garder le dernier dossier utilisé si souhaité
                previewEl.innerHTML = '<em>Sélectionnez un fichier pour afficher son contenu.</em>';
            }
        });

        function renderTree(tree, parentPath = '', depth = 0) {
            const ul = document.createElement('ul');
            ul.className = 'code-tree-list';
            tree.forEach(item => {
                const li = document.createElement('li');
                li.className = 'code-tree-item ' + (item.type === 'dir' ? 'dir' : 'file');
                const relPath = parentPath ? parentPath + '/' + item.name : item.name;
                const row = document.createElement('div');
                row.className = 'code-tree-row';
                row.style.setProperty('--depth', depth);
                const icon = item.type === 'dir' ? '📁' : '📄';
                if (item.type === 'dir') {
                    const toggle = document.createElement('span');
                    toggle.className = 'toggle';
                    // Appliquer état initial (par défaut: plié)
                    const initialCollapsed = collapsedState.has(relPath) ? (collapsedState.get(relPath) === true) : true;
                    if (initialCollapsed) li.classList.add('collapsed');
                    toggle.textContent = li.classList.contains('collapsed') ? '+' : '−';
                    toggle.title = 'Plier/Déplier';
                    toggle.style.cursor = 'pointer';
                    toggle.addEventListener('click', (ev) => {
                        ev.stopPropagation();
                        const childrenUl = li.querySelector('.code-tree-children');
                        li.classList.toggle('collapsed');
                        const collapsed = li.classList.contains('collapsed');
                        if (childrenUl) childrenUl.style.display = collapsed ? 'none' : 'block';
                        toggle.textContent = collapsed ? '+' : '−';
                        collapsedState.set(relPath, collapsed);
                    });
                    row.appendChild(toggle);
                }
                const iconSpan = document.createElement('span');
                iconSpan.className = 'icon';
                iconSpan.textContent = icon;
                const nameSpan = document.createElement('span');
                nameSpan.className = 'name';
                nameSpan.textContent = item.name;
                row.appendChild(iconSpan);
                row.appendChild(nameSpan);
                // Indexation pour sélection par plage
                const currentIndex = flatIndexMap.size;
                flatIndexMap.set(relPath, currentIndex);
                // Drag & Drop (déplacement) — réservé aux propriétaires
                if (isOwner) {
                    row.setAttribute('draggable', 'true');
                    row.addEventListener('dragstart', (ev) => {
                        ev.dataTransfer.setData('text/plain', relPath);
                        ev.dataTransfer.effectAllowed = 'move';
                        row.classList.add('dragging');
                    });
                    row.addEventListener('dragend', () => {
                        row.classList.remove('dragging');
                    });
                    // Cible de drop uniquement pour les dossiers
                    if (item.type === 'dir') {
                        row.addEventListener('dragover', (ev) => {
                            // Toujours autoriser le drop sur dossiers; validation fera le reste au drop
                            ev.preventDefault();
                            ev.dataTransfer.dropEffect = 'move';
                            row.classList.add('drag-over');
                        });
                        row.addEventListener('dragleave', () => {
                            row.classList.remove('drag-over');
                        });
                        row.addEventListener('drop', async (ev) => {
                            ev.preventDefault();
                            row.classList.remove('drag-over');
                            const src = ev.dataTransfer.getData('text/plain');
                            if (!src || src === relPath) return;
                            // Empêcher déplacement dans un descendant de lui-même
                            if (relPath.startsWith(src + '/')) { showCodeToast('Déplacement invalide'); return; }
                            try {
                                const r = await api('move', { path: src, new_parent: relPath }, 'POST');
                                if (!r.error) { showCodeToast('Déplacé ✔'); loadTree(); }
                                else showModalAlert(r.error, { variant: 'error' });
                            } catch { showModalAlert('Erreur réseau', { variant: 'error' }); }
                        });
                    }

                }

                row.addEventListener('click', (event) => {
                    const idx = flatIndexMap.get(relPath);
                    if (event.shiftKey && lastSelectedIndex !== null) {
                        const start = Math.min(lastSelectedIndex, idx);
                        const end = Math.max(lastSelectedIndex, idx);
                        if (!event.ctrlKey) {
                            document.querySelectorAll('.code-tree-row.selected').forEach(el => el.classList.remove('selected'));
                            selectedPaths.clear();
                        }
                        flatIndexMap.forEach((i, p) => {
                            if (i >= start && i <= end) selectedPaths.add(p);
                        });
                        applySelectionClasses();
                    } else if (event.ctrlKey) {
                        if (selectedPaths.has(relPath)) selectedPaths.delete(relPath); else selectedPaths.add(relPath);
                        applySelectionClasses();
                        lastSelectedIndex = idx;
                    } else {
                        selectedPaths.clear();
                        selectedPaths.add(relPath);
                        applySelectionClasses();
                        lastSelectedIndex = idx;
                    }
                    singleSelected = relPath;
                    // Mettre à jour le dossier parent pour upload
                    if (item.type === 'dir') {
                        currentUploadParent = relPath;
                    } else {
                        const lastSlash = relPath.lastIndexOf('/');
                        currentUploadParent = lastSlash > 0 ? relPath.substring(0, lastSlash) : '';
                    }
                    if (item.type === 'file') {
                        previewEl.innerHTML = '<em>Chargement...</em>';
                        api('get', { path: relPath }).then(data => {
                            if (data.error) { previewEl.textContent = data.error; return; }
                            if (data.is_text) {
                                const pre = document.createElement('pre');
                                pre.className = 'code-content-block';
                                pre.textContent = data.content;
                                previewEl.innerHTML = '';
                                previewEl.appendChild(pre);
                                if (isOwner) {
                                    const editBtn = document.createElement('button');
                                    editBtn.textContent = '✏️ Éditer';
                                    editBtn.className = 'code-btn inline';
                                    previewEl.appendChild(editBtn);
                                    editBtn.addEventListener('click', (ev) => {
                                        ev.stopPropagation();
                                        // Passer en mode édition
                                        const textarea = document.createElement('textarea');
                                        textarea.value = data.content;
                                        textarea.style.width = '100%';
                                        textarea.style.minHeight = '300px';
                                        textarea.style.fontFamily = 'monospace';
                                        textarea.style.fontSize = '13px';
                                        const actions = document.createElement('div');
                                        actions.style.marginTop = '8px';
                                        const saveBtn = document.createElement('button');
                                        saveBtn.textContent = '💾 Enregistrer';
                                        saveBtn.className = 'code-btn';
                                        const cancelBtn = document.createElement('button');
                                        cancelBtn.textContent = 'Annuler';
                                        cancelBtn.className = 'code-btn secondary';
                                        previewEl.innerHTML = '';
                                        previewEl.appendChild(textarea);
                                        actions.appendChild(saveBtn);
                                        actions.appendChild(cancelBtn);
                                        previewEl.appendChild(actions);
                                        cancelBtn.addEventListener('click', (ev) => {
                                            ev.stopPropagation();
                                            // Recharger le fichier pour annuler
                                            singleSelected = relPath;
                                            api('get', { path: relPath }).then(r => {
                                                if (!r.error && r.is_text) {
                                                    previewEl.innerHTML = '';
                                                    const pre2 = document.createElement('pre');
                                                    pre2.className = 'code-content-block';
                                                    pre2.textContent = r.content;
                                                    previewEl.appendChild(pre2);
                                                    // Recréer les actions dans le même ordre que la sélection initiale
                                                    previewEl.appendChild(editBtn);
                                                    const dl2 = document.createElement('a');
                                                    dl2.href = `assets/php/code_repo.php?action=download&project_id=${encodeURIComponent(projectId)}&path=${encodeURIComponent(relPath)}`;
                                                    dl2.textContent = 'Télécharger ce fichier';
                                                    dl2.className = 'code-btn inline';
                                                    dl2.addEventListener('click', (e2) => { e2.stopPropagation(); });
                                                    previewEl.appendChild(dl2);
                                                }
                                            });
                                        });
                                        saveBtn.addEventListener('click', (ev) => {
                                            ev.stopPropagation();
                                            const newContent = textarea.value;
                                            if (newContent.length > 500000) { showModalAlert('Fichier trop volumineux (>500KB)', { variant: 'error' }); return; }
                                            const fd = new FormData();
                                            fd.append('action', 'save');
                                            fd.append('project_id', projectId);
                                            fd.append('path', relPath);
                                            fd.append('content', newContent);
                                            fetch('assets/php/code_repo.php?action=save&project_id=' + encodeURIComponent(projectId), { method: 'POST', body: fd })
                                                .then(r => r.json())
                                                .then(r => {
                                                    if (r.error) { showModalAlert(r.error, { variant: 'error' }); return; }
                                                    showCodeToast('Fichier sauvegardé ✔');
                                                    // Rafraîchir arbre + recharger contenu
                                                    loadTree();
                                                    singleSelected = relPath;
                                                    api('get', { path: relPath }).then(rr => {
                                                        if (!rr.error && rr.is_text) {
                                                            previewEl.innerHTML = '';
                                                            const pre3 = document.createElement('pre');
                                                            pre3.className = 'code-content-block';
                                                            pre3.textContent = rr.content;
                                                            previewEl.appendChild(pre3);
                                                            // Recréer les actions dans le même ordre que la sélection initiale
                                                            previewEl.appendChild(editBtn);
                                                            const dl3 = document.createElement('a');
                                                            dl3.href = `assets/php/code_repo.php?action=download&project_id=${encodeURIComponent(projectId)}&path=${encodeURIComponent(relPath)}`;
                                                            dl3.textContent = 'Télécharger ce fichier';
                                                            dl3.className = 'code-btn inline';
                                                            dl3.addEventListener('click', (e3) => { e3.stopPropagation(); });
                                                            previewEl.appendChild(dl3);
                                                        }
                                                    });
                                                })
                                                .catch(() => showModalAlert('Erreur sauvegarde', { variant: 'error' }));
                                        });
                                    });
                                }
                            } else {
                                previewEl.innerHTML = '<p><strong>' + data.name + '</strong></p><p>Type non prévisualisable. Taille: ' + data.size + ' octets.</p>';
                            }
                            const dl = document.createElement('a');
                            dl.href = `assets/php/code_repo.php?action=download&project_id=${encodeURIComponent(projectId)}&path=${encodeURIComponent(relPath)}`;
                            dl.textContent = 'Télécharger ce fichier';
                            dl.className = 'code-btn inline';
                            dl.addEventListener('click', (ev) => { ev.stopPropagation(); });
                            previewEl.appendChild(dl);
                        });
                    }
                });
                li.appendChild(row);
                if (item.type === 'dir' && item.children && item.children.length) {
                    const childrenUl = renderTree(item.children, relPath, depth + 1);
                    childrenUl.className = 'code-tree-children';
                    // Appliquer visibilité (par défaut plié)
                    const isCollapsed = li.classList.contains('collapsed');
                    if (isCollapsed) childrenUl.style.display = 'none';
                    li.appendChild(childrenUl);
                }
                ul.appendChild(li);
            });
            return ul;
        }

        function applySelectionClasses() {
            const allRows = document.querySelectorAll('.code-tree-row');
            allRows.forEach(r => r.classList.remove('selected'));
            selectedPaths.forEach(p => {
                const idx = flatIndexMap.get(p);
                if (idx !== undefined && allRows[idx]) allRows[idx].classList.add('selected');
            });
        }

        function loadTree() {
            treeEl.innerHTML = '<div class="code-loading">Chargement…</div>';
            api('list').then(data => {
                if (data.error) { treeEl.textContent = data.error; return; }
                treeEl.innerHTML = '';
                flatIndexMap.clear(); selectedPaths.clear(); lastSelectedIndex = null;
                treeEl.appendChild(renderTree(data.tree));
                singleSelected = null;
                previewEl.innerHTML = '<em>Sélectionnez un fichier pour afficher son contenu.</em>';
            });
        }
        // Zone de dépôt racine (déplacement vers racine)
        if (isOwner && rootDropEl) {
            rootDropEl.addEventListener('dragover', (ev) => { ev.preventDefault(); rootDropEl.classList.add('drag-over'); });
            rootDropEl.addEventListener('dragleave', () => { rootDropEl.classList.remove('drag-over'); });
            rootDropEl.addEventListener('drop', async (ev) => {
                ev.preventDefault(); rootDropEl.classList.remove('drag-over');
                const src = ev.dataTransfer.getData('text/plain');
                if (!src) return;
                // Déplacer vers la racine: new_parent = ''
                try {
                    const r = await api('move', { path: src, new_parent: '' }, 'POST');
                    if (!r.error) { showCodeToast('Déplacé à la racine ✔'); loadTree(); }
                    else alert(r.error);
                } catch { alert('Erreur réseau'); }
            });
        }
        loadTree();

        if (isOwner && btnCreateFolder) {
            btnCreateFolder.addEventListener('click', async () => {
                const parent = singleSelected && !singleSelected.includes('.') ? singleSelected : '';
                const name = await showModalPrompt({ label: 'Nom du nouveau dossier:', variant: 'success' });
                if (!name) return;
                api('create_folder', { parent, name }, 'POST').then(r => {
                    if (!r.error) { showCodeToast('Dossier créé ✔'); loadTree(); }
                    else showModalAlert(r.error, { variant: 'error' });
                });
            });
        }
        if (isOwner && btnCreateFile) {
            btnCreateFile.addEventListener('click', async () => {
                const parent = singleSelected && !singleSelected.includes('.') ? singleSelected : '';
                const name = await showModalPrompt({ label: 'Nom du nouveau fichier (ex: script.js):', placeholder: 'script.js', variant: 'success' });
                if (!name) return;
                api('create_file', { parent, name }, 'POST').then(r => {
                    if (!r.error) { showCodeToast('Fichier créé ✔'); loadTree(); }
                    else showModalAlert(r.error, { variant: 'error' });
                });
            });
        }
        if (isOwner && btnUpload) {
            btnUpload.addEventListener('click', () => {
                uploadInput.value = '';
                uploadInput.click();
            });
            uploadInput.addEventListener('change', async () => {
                if (!uploadInput.files.length) return;
                const parent = getUploadParent();
                const files = Array.from(uploadInput.files);
                const progressModal = showUploadProgress();
                try {
                    const count = await uploadFilesBatch(files, parent, projectId, progressModal);
                    progressModal.close();
                    showCodeToast(`${count} fichier(s) uploadé(s) ✔`);
                    loadTree();
                } catch (e) {
                    // Erreur déjà gérée dans uploadFilesBatch
                }
            });
        }
        if (isOwner && btnUploadFolder) {
            btnUploadFolder.addEventListener('click', () => {
                uploadFolderInput.value = '';
                uploadFolderInput.click();
            });
            uploadFolderInput.addEventListener('change', async () => {
                if (!uploadFolderInput.files.length) return;
                const parent = getUploadParent();
                const files = uploadFolderInput.files;
                const progressModal = showUploadProgress();
                // Rafraîchir l'état local pour éviter les incohérences après suppression/réupload
                try { loadTree(); } catch (_) { }
                await new Promise(r => setTimeout(r, 200));
                try {
                    const count = await uploadFolderBatch(files, parent, projectId, progressModal);
                    progressModal.close();
                    showCodeToast(`Dossier uploadé (${count} fichiers) ✔`);
                    loadTree();
                } catch (e) {
                    // Erreur déjà gérée dans uploadFolderBatch
                }
                // Reset input pour éviter réutilisation immédiate du même handle dossier
                try { uploadFolderInput.value = ''; } catch (_) { }
            });
        }
        if (btnDownloadAll) {
            btnDownloadAll.addEventListener('click', async () => {
                btnDownloadAll.disabled = true;
                const prevText = btnDownloadAll.textContent;
                btnDownloadAll.textContent = '⏳ Préparation…';
                const url = `assets/php/code_repo.php?action=download_all&project_id=${encodeURIComponent(projectId)}`;
                try {
                    const resp = await fetch(url);
                    if (!resp.ok) { showModalAlert('Erreur téléchargement', { variant: 'error' }); return; }
                    const blob = await resp.blob();
                    const a = document.createElement('a');
                    const dlUrl = window.URL.createObjectURL(blob);
                    a.href = dlUrl;
                    const cd = resp.headers.get('Content-Disposition') || '';
                    const match = cd.match(/filename="?([^";]+)"?/i);
                    const fname = match ? match[1] : 'code.zip';
                    a.download = fname;
                    showCodeToast('Téléchargement prêt ✔');
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    window.URL.revokeObjectURL(dlUrl);
                } catch (e) { showModalAlert('Erreur réseau', { variant: 'error' }); }
                finally {
                    btnDownloadAll.disabled = false;
                    btnDownloadAll.textContent = prevText;
                }
            });
        }
        if (btnDownloadSelection) {
            btnDownloadSelection.addEventListener('click', async () => {
                const targets = selectedPaths.size ? Array.from(selectedPaths) : (singleSelected ? [singleSelected] : []);
                if (!targets.length) { showModalAlert('Sélectionnez au moins un élément', { variant: 'error' }); return; }
                const fd = new FormData();
                fd.append('project_id', projectId);
                fd.append('action', 'download_multi');
                targets.forEach(p => fd.append('paths[]', p));
                const url = `assets/php/code_repo.php?action=download_multi&project_id=${encodeURIComponent(projectId)}`;
                // Indicateur de chargement
                btnDownloadSelection.disabled = true;
                const prevText = btnDownloadSelection.textContent;
                btnDownloadSelection.textContent = '⏳ Préparation…';
                try {
                    const resp = await fetch(url, { method: 'POST', body: fd });
                    if (!resp.ok) { showModalAlert('Erreur téléchargement', { variant: 'error' }); return; }
                    const blob = await resp.blob();
                    const a = document.createElement('a');
                    const dlUrl = window.URL.createObjectURL(blob);
                    a.href = dlUrl;
                    // Récupérer le nom depuis Content-Disposition si présent
                    const cd = resp.headers.get('Content-Disposition') || '';
                    const match = cd.match(/filename="?([^";]+)"?/i);
                    const fname = match ? match[1] : 'selection.zip';
                    a.download = fname;
                    showCodeToast('Téléchargement prêt ✔');
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    window.URL.revokeObjectURL(dlUrl);
                } catch (e) { showModalAlert('Erreur réseau', { variant: 'error' }); }
                finally {
                    btnDownloadSelection.disabled = false;
                    btnDownloadSelection.textContent = prevText;
                }
            });
        }
        // Ancien bouton de déplacement supprimé au profit du glisser-déposer
        if (isOwner && btnRename) {
            btnRename.addEventListener('click', async () => {
                const targets = selectedPaths.size ? Array.from(selectedPaths) : (singleSelected ? [singleSelected] : []);
                if (targets.length !== 1) { showModalAlert('Renommer nécessite une sélection unique', { variant: 'error' }); return; }
                const newName = await showModalPrompt({ label: 'Nouveau nom:' });
                if (!newName) return;
                api('rename', { path: targets[0], new_name: newName }, 'POST').then(r => { if (!r.error) { showCodeToast('Renommé ✔'); loadTree(); } else showModalAlert(r.error, { variant: 'error' }); });
            });
        }
        if (isOwner && btnDelete) {
            btnDelete.addEventListener('click', async () => {
                const targets = selectedPaths.size ? Array.from(selectedPaths) : (singleSelected ? [singleSelected] : []);
                if (!targets.length) { showModalAlert('Sélectionnez au moins un élément', { variant: 'error' }); return; }
                const ok = await showModalConfirm({ message: `Confirmer la suppression de ${targets.length} élément(s) ?`, variant: 'error' });
                if (!ok) return;
                let done = 0, errors = 0;
                const runNext = async () => {
                    if (targets.length === 0) {
                        if (errors === 0) showCodeToast(`Supprimé ✔ (${done})`); else showModalAlert(`Terminé avec ${errors} erreur(s)`, { variant: 'error' });
                        // Rafraîchir complètement la page pour éviter les incohérences d'état avant réupload
                        window.location.reload();
                        return;
                    }
                    const p = targets.shift();
                    try {
                        const r = await api('delete', { path: p }, 'POST');
                        if (!r.error) done++; else { errors++; }
                    } catch { errors++; }
                    runNext();
                };
                runNext();
            });
        }
    }

    // --------------------------------------------------
    // --- Section Versions (list/create/download)
    // --------------------------------------------------
    const versionsView = document.querySelector('.versions-view-container');
    if (versionsView) {
        const projectId = document.getElementById('versions-project-id').value;
        const isOwner = document.getElementById('versions-is-owner').value === '1';
        const listEl = document.getElementById('versions-list');
        const btnCreate = document.getElementById('create-version-btn');

        function formatTs(ts) {
            try { return new Date(ts * 1000).toLocaleString('fr-FR'); } catch { return ts; }
        }

        function renderVersions(arr) {
            listEl.innerHTML = '';
            if (!arr || !arr.length) {
                listEl.innerHTML = '<em>Aucune version créée.</em>';
                return;
            }
            const ul = document.createElement('ul');
            ul.className = 'versions-list-ul';
            // Tri naturel sur le nom normalisé
            arr.sort((a, b) => {
                const an = a.name || a.number || '';
                const bn = b.name || b.number || '';
                return an.localeCompare(bn, 'fr', { numeric: true, sensitivity: 'base' });
            });
            arr.forEach(v => {
                const li = document.createElement('li');
                li.className = 'version-item';
                li.style.marginBottom = '12px';
                const head = document.createElement('div');
                head.className = 'version-head';
                head.style.marginBottom = '6px';
                const label = v.name || v.number;
                head.textContent = 'Version ' + label + ' — ' + formatTs(v.created_at);
                const actions = document.createElement('div');
                actions.className = 'version-actions';
                actions.style.display = 'flex';
                actions.style.flexWrap = 'wrap';
                actions.style.gap = '12px';
                actions.style.marginTop = '4px';
                const dl = document.createElement('a');
                dl.className = 'button-small button-primary';
                dl.textContent = 'Télécharger ZIP';
                dl.href = `assets/php/versions.php?action=download_version&project_id=${encodeURIComponent(projectId)}&name=${encodeURIComponent(label)}`;
                dl.target = '_blank'; dl.rel = 'noopener';
                actions.appendChild(dl);
                const dlCode = document.createElement('a');
                dlCode.className = 'button-small button-secondary';
                dlCode.textContent = 'Télécharger Code';
                dlCode.href = `assets/php/versions.php?action=download_code&project_id=${encodeURIComponent(projectId)}&name=${encodeURIComponent(label)}`;
                dlCode.target = '_blank'; dlCode.rel = 'noopener';
                actions.appendChild(dlCode);
                const dlDocs = document.createElement('a');
                dlDocs.className = 'button-small button-secondary';
                dlDocs.textContent = 'Télécharger Documents';
                dlDocs.href = `assets/php/versions.php?action=download_documents&project_id=${encodeURIComponent(projectId)}&name=${encodeURIComponent(label)}`;
                dlDocs.target = '_blank'; dlDocs.rel = 'noopener';
                actions.appendChild(dlDocs);
                const viewDocs = document.createElement('button');
                viewDocs.className = 'button-small view-btn';
                viewDocs.textContent = 'Voir Documents';
                viewDocs.addEventListener('click', async () => {
                    const res = await fetch(`assets/php/versions.php?action=list_contents&project_id=${encodeURIComponent(projectId)}&name=${encodeURIComponent(label)}`);
                    const j = await res.json().catch(() => ({}));
                    if (!res.ok || j.error) { showModalAlert(j.error || 'Erreur chargement', { variant: 'error' }); return; }
                    const docs = j.documents || {};
                    const overlay = document.createElement('div'); overlay.className = 'modal-overlay';
                    const modal = document.createElement('div'); modal.className = 'modal-box variant-info';
                    modal.style.width = '80vw'; modal.style.maxWidth = '1200px'; modal.style.height = '80vh'; modal.style.maxHeight = '85vh';
                    modal.style.display = 'flex'; modal.style.flexDirection = 'column';
                    const title = document.createElement('div'); title.className = 'modal-title'; title.textContent = 'Documents — ' + label;
                    const body = document.createElement('div'); body.className = 'modal-message';
                    body.style.flex = '1'; body.style.display = 'flex';
                    const layout = document.createElement('div'); layout.style.display = 'grid'; layout.style.gridTemplateColumns = '0.8fr 2.2fr'; layout.style.gap = '16px'; layout.style.height = '100%';
                    const left = document.createElement('div'); left.style.height = '100%'; left.style.overflow = 'auto'; left.style.border = '1px solid #e5e7eb'; left.style.borderRadius = '6px'; left.style.padding = '8px';
                    const preview = document.createElement('div'); preview.style.height = '100%'; preview.style.border = '1px solid #e5e7eb'; preview.style.borderRadius = '6px'; preview.style.padding = '8px'; preview.style.background = '#0b1220'; preview.style.color = '#e5e7eb'; preview.innerHTML = '<em>Sélectionnez un document pour l’aperçu.</em>';
                    // Barre d'outils preview (sans agrandissement)
                    const toolbar = document.createElement('div'); toolbar.style.display = 'flex'; toolbar.style.justifyContent = 'flex-end'; toolbar.style.gap = '8px'; toolbar.style.padding = '8px';
                    let lastDocUrl = '';
                    const btnOpenTab = document.createElement('button'); btnOpenTab.className = 'button-small button-secondary'; btnOpenTab.textContent = 'Ouvrir dans un onglet';
                    btnOpenTab.addEventListener('click', () => {
                        if (lastDocUrl) { const a = document.createElement('a'); a.href = lastDocUrl; a.target = '_blank'; a.rel = 'noopener'; document.body.appendChild(a); a.click(); a.remove(); }
                    });
                    toolbar.appendChild(btnOpenTab);
                    Object.keys(docs).forEach(cat => {
                        const h = document.createElement('div'); h.style.fontWeight = '600'; h.style.margin = '8px 0 4px'; h.textContent = cat; left.appendChild(h); const ul = document.createElement('ul'); (docs[cat] || []).forEach(fname => {
                            const li = document.createElement('li'); li.style.cursor = 'pointer'; li.textContent = fname; li.addEventListener('click', async () => {
                                preview.innerHTML = '<div class="small-muted">Chargement…</div>';
                                const ext = (fname.split('.').pop() || '').toLowerCase();
                                const url = `assets/php/versions.php?action=view_document&project_id=${encodeURIComponent(projectId)}&name=${encodeURIComponent(label)}&category=${encodeURIComponent(cat)}&filename=${encodeURIComponent(fname)}`;
                                preview.innerHTML = '';
                                // Toujours afficher la barre d'outils
                                preview.appendChild(toolbar);
                                if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext)) {
                                    const img = document.createElement('img');
                                    img.src = url; img.alt = fname;
                                    img.style.maxWidth = '100%';
                                    img.style.height = '100%';
                                    img.style.flex = '1 1 auto';
                                    img.className = 'doc-preview-image';
                                    preview.appendChild(img);
                                } else if (ext === 'pdf') {
                                    const embed = document.createElement('embed');
                                    embed.src = url; embed.type = 'application/pdf';
                                    embed.style.width = '100%';
                                    embed.style.height = '100%';
                                    embed.style.flex = '1 1 auto';
                                    embed.setAttribute('title', fname);
                                    embed.className = 'doc-preview-frame';
                                    preview.appendChild(embed);
                                } else {
                                    const iframe = document.createElement('iframe');
                                    iframe.src = url;
                                    iframe.style.width = '100%';
                                    iframe.style.height = '100%';
                                    iframe.style.flex = '1 1 auto';
                                    iframe.setAttribute('title', fname);
                                    iframe.className = 'doc-preview-frame';
                                    preview.appendChild(iframe);
                                }
                                lastDocUrl = url;
                            }); ul.appendChild(li);
                        }); left.appendChild(ul);
                    });
                    layout.appendChild(left); layout.appendChild(preview);
                    body.appendChild(layout);
                    const actionsBox = document.createElement('div'); actionsBox.className = 'modal-actions'; const closeBtn = document.createElement('button'); closeBtn.className = 'modal-btn primary'; closeBtn.textContent = 'Fermer'; actionsBox.appendChild(closeBtn);
                    modal.appendChild(title); modal.appendChild(body); modal.appendChild(actionsBox); overlay.appendChild(modal); document.body.appendChild(overlay);
                    closeBtn.addEventListener('click', () => document.body.removeChild(overlay));
                    const onKey = (e) => { if (e.key === 'Escape') { document.removeEventListener('keydown', onKey); if (document.body.contains(overlay)) document.body.removeChild(overlay); } };
                    document.addEventListener('keydown', onKey);
                });
                actions.appendChild(viewDocs);
                li.appendChild(head);
                li.appendChild(actions);
                ul.appendChild(li);
            });
            listEl.appendChild(ul);
        }

        async function loadVersions() {
            listEl.innerHTML = '<div class="small-muted">Chargement…</div>';
            try {
                const res = await fetch(`assets/php/versions.php?action=list&project_id=${encodeURIComponent(projectId)}&_=${Date.now()}`);
                const j = await res.json();
                if (!res.ok || j.error) throw new Error(j.error || 'Erreur');
                renderVersions(j.versions || []);
            } catch (e) {
                listEl.innerHTML = '<div class="small-error">Impossible de charger les versions</div>';
            }
        }

        if (btnCreate && isOwner) {
            btnCreate.addEventListener('click', async () => {
                const ok = await showModalConfirm({ message: 'Créer une nouvelle version maintenant ?\nCela réinitialisera Documents et Code.', variant: 'warning' });
                if (!ok) return;
                // Saisie unique du nom: bouton "Créer", nom obligatoire
                const v = await showModalPrompt({ title: 'Nom de version', label: 'Nom (ex: 1.0.0, 1.0):', placeholder: '1.0.0', okText: 'Créer', cancelText: 'Annuler', variant: 'info' });
                if (v === null) return;
                const versionName = (v || '').trim();
                if (versionName.length === 0) {
                    await showModalAlert('Veuillez entrer un nom de version.', { variant: 'error' });
                    return;
                }
                const fd = new FormData();
                fd.append('action', 'create');
                fd.append('project_id', projectId);
                fd.append('version_name', versionName);
                try {
                    const res = await fetch('assets/php/versions.php', { method: 'POST', body: fd });
                    const j = await res.json().catch(() => ({}));
                    if (res.ok && j.status === 'OK') {
                        const createdLabel = j.created_name || j.created || versionName;
                        showModalAlert('Version ' + createdLabel + ' créée', { variant: 'success' });
                        await loadVersions();
                        const statCards = document.querySelectorAll('.stat-card .stat-label');
                        statCards.forEach(lbl => { if (lbl.textContent.trim() === 'Versions') { const valEl = lbl.parentElement.querySelector('.stat-value'); if (valEl) { valEl.textContent = (parseInt(valEl.textContent, 10) || 0) + 1; } } });
                    } else {
                        showModalAlert(j.error || 'Création refusée', { variant: 'error' });
                    }
                } catch (_) { showModalAlert('Erreur réseau', { variant: 'error' }); }
            });
        }

        loadVersions();
    }
});

/* Styles rapides pour toasts (injection si absent) */
if (!document.getElementById('toast-styles')) {
    const st = document.createElement('style');
    st.id = 'toast-styles';
    st.textContent = `.toast-host{position:fixed;bottom:12px;right:12px;display:flex;flex-direction:column;gap:8px;z-index:9999}.toast-item{background:#1e293b;color:#fff;padding:8px 12px;border-radius:4px;font-size:12px;box-shadow:0 2px 6px rgba(0,0,0,.3);transition:opacity .4s}.toast-item.fade{opacity:0}`;
    document.head.appendChild(st);
}

function showModalAlert(message, options) {
    const { title, okText = 'OK', variant = 'info' } = options || {};
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    const modal = document.createElement('div');
    modal.className = 'modal-box ' + `variant-${variant}`;
    const h = document.createElement('div');
    const defaultTitle = variant === 'error' ? 'Erreur' : (variant === 'success' ? 'Succès' : 'Information');
    h.textContent = title || defaultTitle;
    h.className = 'modal-title';
    const msg = document.createElement('div');
    msg.textContent = message;
    msg.className = 'modal-message';
    const actions = document.createElement('div');
    actions.className = 'modal-actions';
    const ok = document.createElement('button');
    ok.textContent = okText;
    ok.className = 'modal-btn primary';
    actions.appendChild(ok);
    modal.appendChild(h);
    modal.appendChild(msg);
    modal.appendChild(actions);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    return new Promise((resolve) => {
        function cleanup() { document.body.removeChild(overlay); }
        ok.addEventListener('click', () => { cleanup(); resolve(); });
    });
}

function showUploadProgress() {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    const modal = document.createElement('div');
    modal.className = 'modal-box variant-info';
    const h = document.createElement('div');
    h.textContent = 'Upload en cours';
    h.className = 'modal-title';
    const msg = document.createElement('div');
    msg.className = 'modal-message';
    msg.textContent = 'Téléchargement des fichiers...';
    const progressBar = document.createElement('div');
    progressBar.className = 'progress-bar-container';
    const progressFill = document.createElement('div');
    progressFill.className = 'progress-bar-fill';
    progressFill.style.width = '0%';
    progressBar.appendChild(progressFill);
    const percentText = document.createElement('div');
    percentText.className = 'progress-percent';
    percentText.textContent = '0%';
    modal.appendChild(h);
    modal.appendChild(msg);
    modal.appendChild(progressBar);
    modal.appendChild(percentText);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    return {
        update: (percent) => {
            progressFill.style.width = percent + '%';
            percentText.textContent = percent + '%';
        },
        close: () => {
            if (document.body.contains(overlay)) document.body.removeChild(overlay);
        }
    };
}