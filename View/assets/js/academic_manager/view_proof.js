/**
 * Proof validation form management
 * - Conditional display of custom reasons
 * - Dynamic generation of split periods
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
    // Conditional display management for the "Other" field in rejection reasons
    const rejSel = document.getElementById('rejection_reason');
    const rejGrp = document.getElementById('new-reason-group');
    if (rejSel && rejGrp) {
        const toggle = () => rejGrp.style.display = (rejSel.value === 'Autre') ? 'block' : 'none';
        rejSel.addEventListener('change', toggle);
        toggle();
    }

    // Conditional display management for the "Other" field in validation reasons
    const valSel = document.getElementById('validation_reason');
    const valGrp = document.getElementById('new-validation-reason-group');
    if (valSel && valGrp) {
        const toggleV = () => valGrp.style.display = (valSel.value === 'Autre') ? 'block' : 'none';
        valSel.addEventListener('change', toggleV);
        toggleV();
    }

    // Confirmations pour les actions de validation et rejet
    const rejectionForm = document.querySelector('.rejection-form');
    const validationForm = document.querySelector('.validation-form');
    const verouillageForm = document.querySelector('.lock-form');

    if (verouillageForm) {
        verouillageForm.addEventListener('submit', function(e) {
            if (e.target.name === 'lock' || e.submitter.name === 'lock') {
                const confirmed = confirm('Êtes-vous certain de vouloir verrouiller ce justificatif ?');
                if (!confirmed) e.preventDefault();
            }
        });
    }

    if (rejectionForm) {
        rejectionForm.addEventListener('submit', function(e) {
            if (e.target.name === 'reject' || e.submitter.name === 'reject') {
                const confirmed = confirm('Êtes-vous certain de vouloir refuser ce justificatif ?');
                if (!confirmed) e.preventDefault();
            }
        });
    }

    if (validationForm) {
        validationForm.addEventListener('submit', function(e) {
            if (e.target.name === 'validate' || e.submitter.name === 'validate') {
                const confirmed = confirm('Êtes-vous certain de vouloir accepter ce justificatif ?');
                if (!confirmed) e.preventDefault();
            }
        });
    }

    // Confirmations pour les boutons d'action directs
    const actionForm = document.querySelector('.action-form');
    if (actionForm) {
        const validateBtn = actionForm.querySelector('button[name="validate"]');
        const rejectBtn = actionForm.querySelector('button[name="reject"]');
        const requestInfoBtn = actionForm.querySelector('button[name="request_info"]');

        if (validateBtn) {
            validateBtn.addEventListener('click', function(e) {
                if (!confirm('Êtes-vous certain de vouloir accepter ce justificatif ?')) {
                    e.preventDefault();
                }
            });
        }

        if (rejectBtn) {
            rejectBtn.addEventListener('click', function(e) {
                if (!confirm('Êtes-vous certain de vouloir refuser ce justificatif ?')) {
                    e.preventDefault();
                }
            });
        }

        if (requestInfoBtn) {
            requestInfoBtn.addEventListener('click', function(e) {
                if (!confirm('Êtes-vous certain de vouloir demander des informations complémentaires ?')) {
                    e.preventDefault();
                }
            });
        }
    }
})();

// Dynamic management of split periods
const colors = ['#4CAF50', '#2196F3', '#FF9800', '#9C27B0', '#F44336'];

/**
 * Updates the display of period fields based on the selected number
 * @param {string} startDate - Default start date (format YYYY-MM-DD)
 * @param {string} endDate - Default end date (format YYYY-MM-DD)
 * @param {string} startTime - Start time of the original proof (format HH:MM)
 * @param {string} endTime - End time of the original proof (format HH:MM)
 */
function updatePeriodFields(startDate, endDate, startTime = '08:00', endTime = '18:00') {
    const numPeriods = parseInt(document.getElementById('num_periods').value);
    const container = document.getElementById('periodsContainer');
    container.innerHTML = '';
    
    // Grid configuration based on the number of periods (single column on mobile)
    const isMobile = window.innerWidth <= 768;
    let gridCols;
    if (isMobile) {
        gridCols = '1fr';
    } else {
        gridCols = numPeriods <= 2 ? '1fr 1fr' : numPeriods === 3 ? '1fr 1fr 1fr' : '1fr 1fr';
    }
    container.style.gridTemplateColumns = gridCols;
    
    // Generate fields for each period
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

