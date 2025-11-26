/**
 * view_proof.js
 * 
 * Script JavaScript pour l'interface de validation des justificatifs.
 * 
 * Fonctionnalités principales :
 * 1. Affichage conditionnel des champs de saisie personnalisés
 *    - Affiche le champ "Autre raison" quand l'utilisateur sélectionne "Autre"
 *    - Gère à la fois les raisons de rejet et de validation
 * 
 * 2. Génération dynamique du formulaire de scission
 *    - Permet de créer 2 à 5 périodes de scission
 *    - Chaque période inclut : date de début, heure de début, date de fin, heure de fin
 *    - Option de validation directe pour chaque période (checkbox)
 * 
 * 3. Pré-remplissage intelligent avec les dates de début et de fin du justificatif d'origine
 * 
 * @package View/assets/js
 * @author Équipe de développement S3.01
 * @version 2.0
 */

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

// ============================================
// GESTION DYNAMIQUE DES PÉRIODES DE SCISSION
// ============================================

// Couleurs utilisées pour différencier visuellement les périodes
const colors = ['#4CAF50', '#2196F3', '#FF9800', '#9C27B0', '#F44336'];

/**
 * Génère dynamiquement les champs de formulaire pour les périodes de scission
 * 
 * Cette fonction crée entre 2 et 5 périodes selon le choix de l'utilisateur.
 * Chaque période contient :
 * - Date de début et heure de début
 * - Date de fin et heure de fin
 * - Checkbox pour validation directe
 * 
 * Le layout s'adapte au nombre de périodes sélectionnées.
 * 
 * @param {string} startDate - Date de début par défaut (format YYYY-MM-DD) pour la première période
 * @param {string} endDate - Date de fin par défaut (format YYYY-MM-DD) pour la dernière période
 */
function updatePeriodFields(startDate, endDate) {
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
                       value="08:00" required>
            </div>
            <div class="form-group">
                <label for="period${i}_end_date">Date de fin :</label>
                <input type="date" name="period${i}_end_date" id="period${i}_end_date" 
                       value="${defaultEndDate}" required>
            </div>
            <div class="form-group">
                <label for="period${i}_end_time">Heure de fin :</label>
                <input type="time" name="period${i}_end_time" id="period${i}_end_time" 
                       value="18:00" required>
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
