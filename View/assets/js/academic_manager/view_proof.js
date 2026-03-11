/**
 * Gestion du formulaire de validation des justificatifs
 * - Affichage conditionnel des raisons personnalisées
 * - Génération dynamique des périodes de scission
 */

;// Affiche un toast de feedback temporaire
function showToast(message, type) {
    const existing = document.querySelector('.vp-toast');
    if (existing) existing.remove();

    const colors = {
        success: { bg: '#d1fae5', text: '#065f46', border: '#10b981' },
        error:   { bg: '#fee2e2', text: '#991b1b', border: '#ef4444' },
        info:    { bg: '#dbeafe', text: '#1e40af', border: '#3b82f6' },
        warning: { bg: '#fef3c7', text: '#92400e', border: '#f59e0b' },
    };
    const c = colors[type] || colors.info;

    const toast = document.createElement('div');
    toast.className = 'vp-toast';
    toast.textContent = message;
    Object.assign(toast.style, {
        position: 'fixed', top: '20px', right: '20px',
        padding: '0.9rem 1.4rem', borderRadius: '8px',
        boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
        zIndex: '9999', fontSize: '0.875rem', fontWeight: '500',
        border: '1px solid ' + c.border,
        backgroundColor: c.bg, color: c.text,
        transition: 'opacity 0.4s ease', opacity: '1',
    });
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; }, 3500);
    setTimeout(() => { toast.remove(); }, 3900);
}

// Détection du paramètre feedback après une action
document.addEventListener('DOMContentLoaded', function () {
    const feedbackMessages = {
        validated: { msg: 'Justificatif validé avec succès.', type: 'success' },
        rejected:  { msg: 'Justificatif rejeté.', type: 'error' },
        locked:    { msg: 'Justificatif verrouillé.', type: 'info' },
        unlocked:  { msg: 'Justificatif déverrouillé.', type: 'info' },
        info:      { msg: "Demande d'informations envoyée à l'étudiant.", type: 'warning' },
    };
    const params = new URLSearchParams(window.location.search);
    const feedback = params.get('feedback');
    if (feedback && feedbackMessages[feedback]) {
        const { msg, type } = feedbackMessages[feedback];
        showToast(msg, type);
        // Supprimer le paramètre de l'URL sans recharger la page
        const cleanUrl = window.location.pathname + '?proof_id=' + (params.get('proof_id') || '');
        history.replaceState(null, '', cleanUrl);
    }

    // Feedback immédiat sur les boutons de formulaire (désactivation après soumission)
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            const btn = form.querySelector('[type="submit"]');
            if (!btn) return;
            // setTimeout 0 : le navigateur sérialise d’abord les données (nom du bouton inclus),
            // puis le callback désactive le bouton pour éviter le double-clic.
            setTimeout(function () {
                btn.disabled = true;
                btn.textContent = 'Traitement en cours\u2026';
                btn.style.opacity = '0.6';
                btn.style.cursor = 'not-allowed';
            }, 0);
        });
    });
});

(function() {
    // Gestion de l'affichage conditionnel pour le champ "Autre" dans les raisons de rejet
    const rejSel = document.getElementById('rejection_reason');
    const rejGrp = document.getElementById('new-reason-group');
    if (rejSel && rejGrp) {
        const toggle = () => rejGrp.style.display = (rejSel.value === 'Autre') ? 'block' : 'none';
        rejSel.addEventListener('change', toggle);
        toggle();
    }

    // Gestion de l'affichage conditionnel pour le champ "Autre" dans les raisons de validation
    const valSel = document.getElementById('validation_reason');
    const valGrp = document.getElementById('new-validation-reason-group');
    if (valSel && valGrp) {
        const toggleV = () => valGrp.style.display = (valSel.value === 'Autre') ? 'block' : 'none';
        valSel.addEventListener('change', toggleV);
        toggleV();
    }
})();

// Gestion dynamique des périodes de scission
const colors = ['#4CAF50', '#2196F3', '#FF9800', '#9C27B0', '#F44336'];

/**
 * Met à jour l'affichage des champs de périodes en fonction du nombre sélectionné
 * @param {string} startDate - Date de début par défaut (format YYYY-MM-DD)
 * @param {string} endDate - Date de fin par défaut (format YYYY-MM-DD)
 * @param {string} startTime - Heure de début du justificatif original (format HH:MM)
 * @param {string} endTime - Heure de fin du justificatif original (format HH:MM)
 */
function updatePeriodFields(startDate, endDate, startTime = '08:00', endTime = '18:00') {
    const numPeriods = parseInt(document.getElementById('num_periods').value);
    const container = document.getElementById('periodsContainer');
    container.innerHTML = '';
    
    // Configuration de la grille en fonction du nombre de périodes
    const gridCols = numPeriods <= 2 ? '1fr 1fr' : numPeriods === 3 ? '1fr 1fr 1fr' : '1fr 1fr';
    container.style.gridTemplateColumns = gridCols;
    
    // Génération des champs pour chaque période
    for (let i = 1; i <= numPeriods; i++) {
        const periodDiv = document.createElement('div');
        periodDiv.style.border = `2px solid ${colors[(i-1) % colors.length]}`;
        periodDiv.style.padding = '15px';
        periodDiv.style.borderRadius = '8px';
        
        const defaultStartDate = i === 1 ? startDate : '';
        const defaultEndDate = i === numPeriods ? endDate : '';
        const defaultStartTime = i === 1 ? startTime : '08:00';
        const defaultEndTime = i === numPeriods ? endTime : '18:00';
        
        periodDiv.innerHTML = `
            <h4 style="color: ${colors[(i-1) % colors.length]}; margin-top: 0;">Période ${i}</h4>
            <div class="form-group">
                <label for="period${i}_start_date">Date de début :</label>
                <input type="date" name="period${i}_start_date" id="period${i}_start_date" 
                       value="${defaultStartDate}" required>
            </div>
            <div class="form-group">
                <label for="period${i}_start_time">Heure de début :</label>
                <input type="time" name="period${i}_start_time" id="period${i}_start_time" 
                       value="${defaultStartTime}" required>
            </div>
            <div class="form-group">
                <label for="period${i}_end_date">Date de fin :</label>
                <input type="date" name="period${i}_end_date" id="period${i}_end_date" 
                       value="${defaultEndDate}" required>
            </div>
            <div class="form-group">
                <label for="period${i}_end_time">Heure de fin :</label>
                <input type="time" name="period${i}_end_time" id="period${i}_end_time" 
                       value="${defaultEndTime}" required>
            </div>
            <div class="form-group" style="border-top: 1px solid #ddd; padding-top: 10px; margin-top: 10px;">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" name="period${i}_validate" id="period${i}_validate" 
                           value="1" style="margin-right: 8px; width: 18px; height: 18px;">
                    <span style="font-weight: 500;">Valider directement cette période</span>
                </label>
                <small style="color: #666; display: block; margin-top: 5px; margin-left: 26px;">
                    Si coché, cette période sera automatiquement validée (sinon : en attente)
                </small>
            </div>
        `;
        container.appendChild(periodDiv);
    }
}

