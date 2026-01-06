// Traductions FR/EN pour l'application
const translations = {
    // Page Login
    'login': {
        'fr': {
            'page_title': 'Connexion',
            'form_title': 'Se connecter',
            'email_label': 'Email:',
            'email_placeholder': 'prenom.nom@uphf.fr',
            'password_label': 'Mot de passe:',
            'password_placeholder': '********',
            'submit_btn': 'Se connecter',
            'no_account': 'Pas de compte?',
            'create_account': 'Créer un compte',
            'forgot_password': 'Mot de passe oublié?',
            'reset_password': 'Réinitialiser'
        },
        'en': {
            'page_title': 'Login',
            'form_title': 'Sign in',
            'email_label': 'Email:',
            'email_placeholder': 'firstname.lastname@uphf.fr',
            'password_label': 'Password:',
            'password_placeholder': '********',
            'submit_btn': 'Sign in',
            'no_account': 'No account?',
            'create_account': 'Create an account',
            'forgot_password': 'Forgot password?',
            'reset_password': 'Reset'
        }
    },
    
    // Page Student Home
    'student_home': {
        'fr': {
            'page_title': 'Accueil Étudiant',
            'dashboard_title': 'Tableau de Bord',
            'half_days_missed': 'Demi-journées manquées',
            'total_half_days_desc': 'Total de demi-journées d\'absence',
            'half_days_unjustified': 'Demi-journées non justifiées',
            'warning': 'ATTENTION !',
            'none_to_justify': 'Aucune à justifier',
            'half_days_justifiable': 'Demi-journées justifiables',
            'without_proof': 'Sans justificatif ou en revue',
            'half_days_justified': 'Demi-journées justifiées',
            'on_half_days': 'Sur',
            'half_days': 'demi-journées',
            'this_month': 'Ce mois-ci',
            'half_days_in': 'Demi-journées en',
            'total_absences': 'Total absences',
            'courses_missed': 'Cours manqués au total',
            'justification_rate': 'Taux de justification des demi-journées d\'absence',
            'justified_half_days': 'demi-journées justifiées',
            'on_total': 'sur',
            'total_absence_half_days': 'demi-journées d\'absence totales',
            'good': 'Bon (≥80%)',
            'medium': 'Moyen (50-79%)',
            'low': 'Faible (<50%)',
            'points_lost': 'point(s) perdu(s)',
            'in_average': 'dans la moyenne',
            'penalty_rule': '(5 demi-journées non justifiées = 0,5 point perdu)',
            'no_points_lost': 'Aucun point perdu !',
            'proofs_status': 'État de vos justificatifs',
            'accepted': 'Acceptés',
            'validated_proofs': 'Justificatifs validés',
            'pending': 'En attente',
            'under_review_desc': 'En cours d\'examen',
            'under_review': 'En révision',
            'additional_info_requested': 'Infos complémentaires demandées',
            'rejected': 'Refusés',
            'not_accepted': 'Non acceptés',
            'action_required': 'Action requise : Demi-journées non justifiées',
            'you_have': 'Vous avez',
            'unjustified_half_days_alert': 'demi-journée(s) d\'absence non justifiée(s)',
            'submit_within_48h': 'Pensez à soumettre vos justificatifs dans les 48h suivant votre retour en cours pour éviter des pénalités.',
            'submit_proof': 'Soumettre un justificatif',
            'additional_info_title': 'Informations complémentaires requises',
            'proofs_under_review': 'justificatif(s) en révision',
            'team_needs_info': 'L\'équipe pédagogique a besoin d\'informations supplémentaires.',
            'view_my_proofs': 'Consulter mes justificatifs',
            'recent_absences': 'Dernières absences',
            'recent_courses_missed': 'Derniers cours manqués',
            'date': 'Date',
            'time': 'Horaire',
            'course': 'Cours',
            'teacher': 'Enseignant',
            'room': 'Salle',
            'duration': 'Durée',
            'type': 'Type',
            'evaluation': 'Évaluation',
            'status': 'Statut',
            'justified': 'Justifiée',
            'unjustified': 'Non justifiée',
            'pending_status': 'En attente',
            'rejected_status': 'Rejeté',
            'yes': 'Oui',
            'no': 'Non',
            'makeup_scheduled': 'Rattrapage prévu',
            'more': 'Plus',
            'proofs_under_review_title': 'Justificatifs en révision',
            'proofs_needing_info': 'Justificatifs nécessitant des informations supplémentaires',
            'period': 'Période',
            'reason': 'Motif',
            'hours_missed': 'Heures ratées',
            'submission_date': 'Date soumission',
            'comment': 'Commentaire',
            'action': 'Action',
            'illness': 'Maladie',
            'death': 'Décès',
            'family_obligations': 'Obligations familiales',
            'other': 'Autre',
            'complete': 'Compléter',
            'eval': 'Éval',
            'proofs_pending_validation': 'Justificatifs en attente de validation',
            'awaiting_verification': 'En attente de vérification par le responsable pédagogique',
            'proofs_accepted_by_manager': 'Justificatifs acceptés par le responsable pédagogique',
            'validation_date': 'Date validation',
            'rejected_proofs': 'Justificatifs refusés',
            'proofs_rejected_by_manager': 'Justificatifs refusés par le responsable pédagogique',
            'rejection_date': 'Date refus',
            'absence_details': 'Détails de l\'Absence',
            'missed_evaluation': 'Évaluation ratée',
            'makeup_date': 'Date du rattrapage',
            'subject': 'Matière',
            'proof_details': 'Détails du Justificatif',
            'absence_start': 'Début d\'absence',
            'absence_end': 'Fin d\'absence',
            'specification': 'Précision',
            'student_comment': 'Commentaire de l\'étudiant',
            'affected_absences': 'Absences concernées',
            'affected_half_days': 'Demi-journées concernées',
            'processing_date': 'Date de traitement',
            'proof_files': 'Fichiers justificatifs',
            'manager_comment': 'Commentaire du responsable',
            'complete_proof': 'Compléter le justificatif'
        },
        'en': {
            'page_title': 'Student Home',
            'dashboard_title': 'Dashboard',
            'half_days_missed': 'Half-days missed',
            'total_half_days_desc': 'Total half-days of absence',
            'half_days_unjustified': 'Unjustified half-days',
            'warning': 'WARNING!',
            'none_to_justify': 'None to justify',
            'half_days_justifiable': 'Justifiable half-days',
            'without_proof': 'Without proof or under review',
            'half_days_justified': 'Justified half-days',
            'on_half_days': 'Out of',
            'half_days': 'half-days',
            'this_month': 'This month',
            'half_days_in': 'Half-days in',
            'total_absences': 'Total absences',
            'courses_missed': 'Total courses missed',
            'justification_rate': 'Justification rate of absence half-days',
            'justified_half_days': 'justified half-days',
            'on_total': 'out of',
            'total_absence_half_days': 'total absence half-days',
            'good': 'Good (≥80%)',
            'medium': 'Medium (50-79%)',
            'low': 'Low (<50%)',
            'points_lost': 'point(s) lost',
            'in_average': 'in average',
            'penalty_rule': '(5 unjustified half-days = 0.5 point lost)',
            'no_points_lost': 'No points lost!',
            'proofs_status': 'Your proofs status',
            'accepted': 'Accepted',
            'validated_proofs': 'Validated proofs',
            'pending': 'Pending',
            'under_review_desc': 'Under examination',
            'under_review': 'Under review',
            'additional_info_requested': 'Additional info requested',
            'rejected': 'Rejected',
            'not_accepted': 'Not accepted',
            'action_required': 'Action required: Unjustified half-days',
            'you_have': 'You have',
            'unjustified_half_days_alert': 'unjustified absence half-day(s)',
            'submit_within_48h': 'Please submit your proofs within 48h after returning to class to avoid penalties.',
            'submit_proof': 'Submit a proof',
            'additional_info_title': 'Additional information required',
            'proofs_under_review': 'proof(s) under review',
            'team_needs_info': 'The pedagogical team needs additional information.',
            'view_my_proofs': 'View my proofs',
            'recent_absences': 'Recent absences',
            'recent_courses_missed': 'Recent courses missed',
            'date': 'Date',
            'time': 'Time',
            'course': 'Course',
            'teacher': 'Teacher',
            'room': 'Room',
            'duration': 'Duration',
            'type': 'Type',
            'evaluation': 'Evaluation',
            'status': 'Status',
            'justified': 'Justified',
            'unjustified': 'Unjustified',
            'pending_status': 'Pending',
            'rejected_status': 'Rejected',
            'yes': 'Yes',
            'no': 'No',
            'makeup_scheduled': 'Makeup scheduled',
            'more': 'More',
            'proofs_under_review_title': 'Proofs under review',
            'proofs_needing_info': 'Proofs requiring additional information',
            'period': 'Period',
            'reason': 'Reason',
            'hours_missed': 'Hours missed',
            'submission_date': 'Submission date',
            'comment': 'Comment',
            'action': 'Action',
            'illness': 'Illness',
            'death': 'Death',
            'family_obligations': 'Family obligations',
            'other': 'Other',
            'complete': 'Complete',
            'eval': 'Eval',
            'proofs_pending_validation': 'Proofs pending validation',
            'awaiting_verification': 'Awaiting verification by the academic manager',
            'proofs_accepted_by_manager': 'Proofs accepted by the academic manager',
            'validation_date': 'Validation date',
            'rejected_proofs': 'Rejected proofs',
            'proofs_rejected_by_manager': 'Proofs rejected by the academic manager',
            'rejection_date': 'Rejection date',
            'absence_details': 'Absence Details',
            'missed_evaluation': 'Missed evaluation',
            'makeup_date': 'Makeup date',
            'subject': 'Subject',
            'proof_details': 'Proof Details',
            'absence_start': 'Absence start',
            'absence_end': 'Absence end',
            'specification': 'Specification',
            'student_comment': 'Student comment',
            'affected_absences': 'Affected absences',
            'affected_half_days': 'Affected half-days',
            'processing_date': 'Processing date',
            'proof_files': 'Proof files',
            'manager_comment': 'Manager comment',
            'complete_proof': 'Complete proof'
        }
    },
    
    // Page Teacher Home
    'teacher_home': {
        'fr': {
            'page_title': 'Tableau de bord - Enseignant',
            'dashboard_title': 'Tableau de bord - Professeur',
            'global_view': 'Vue globale des absences',
            'absent_students': 'Étudiants absents',
            'all_courses': 'Tous les cours',
            'reset': 'Réinitialiser',
            'name_firstname': 'Nom / Prénom',
            'group': 'Groupe',
            'subject': 'Matière',
            'absence_date': 'Date d\'absence',
            'status': 'Statut',
            'no_absence_found': 'Aucune absence trouvée',
            'excused': 'Excusée',
            'unjustified': 'Non justifiée',
            'justified': 'Justifiée',
            'absent': 'Absent',
            'previous': 'Précédent',
            'next': 'Suivant',
            'page': 'Page',
            'of': 'sur',
            'makeup_management': 'Gestion des rattrapages',
            'schedule_makeup': 'Planifier un rattrapage',
            'students_makeup': 'Étudiants à rattraper',
            'no_student_makeup': 'Aucun étudiant à rattraper'
        },
        'en': {
            'page_title': 'Dashboard - Teacher',
            'dashboard_title': 'Dashboard - Teacher',
            'global_view': 'Global view of absences',
            'absent_students': 'Absent students',
            'all_courses': 'All courses',
            'reset': 'Reset',
            'name_firstname': 'Name / First name',
            'group': 'Group',
            'subject': 'Subject',
            'absence_date': 'Absence date',
            'status': 'Status',
            'no_absence_found': 'No absence found',
            'excused': 'Excused',
            'unjustified': 'Unjustified',
            'justified': 'Justified',
            'absent': 'Absent',
            'previous': 'Previous',
            'next': 'Next',
            'page': 'Page',
            'of': 'of',
            'makeup_management': 'Makeup management',
            'schedule_makeup': 'Schedule a makeup',
            'students_makeup': 'Students to make up',
            'no_student_makeup': 'No student to make up'
        }
    }
};

// Fonction pour obtenir la langue actuelle
function getCurrentLanguage() {
    return localStorage.getItem('app_language') || 'fr';
}

// Fonction pour définir la langue
function setLanguage(lang) {
    localStorage.setItem('app_language', lang);
    applyTranslations();
    updateLanguageButton();
}

// Fonction pour basculer entre les langues
function toggleLanguage() {
    const currentLang = getCurrentLanguage();
    const newLang = currentLang === 'fr' ? 'en' : 'fr';
    setLanguage(newLang);
}

// Fonction pour appliquer les traductions
function applyTranslations() {
    const lang = getCurrentLanguage();
    const page = document.body.getAttribute('data-page');
    
    if (!page || !translations[page]) {
        console.warn('Page translations not found for:', page);
        return;
    }
    
    const pageTranslations = translations[page][lang];
    
    // Appliquer les traductions à tous les éléments avec data-translate
    document.querySelectorAll('[data-translate]').forEach(element => {
        const key = element.getAttribute('data-translate');
        if (pageTranslations[key]) {
            // Gérer les placeholders
            if (element.hasAttribute('placeholder')) {
                element.placeholder = pageTranslations[key];
            } else {
                element.textContent = pageTranslations[key];
            }
        }
    });
    
    // Mettre à jour le titre de la page
    if (pageTranslations['page_title']) {
        document.title = pageTranslations['page_title'];
    }

    // Formater les dates avec le mois selon la langue
    document.querySelectorAll('.current-month-year').forEach(element => {
        const dateStr = element.getAttribute('data-date');
        if (dateStr) {
            const date = new Date(dateStr);
            const options = { year: 'numeric', month: 'long' };
            const locale = lang === 'fr' ? 'fr-FR' : 'en-US';
            element.textContent = date.toLocaleDateString(locale, options);
        }
    });
}

// Fonction pour mettre à jour le bouton de langue
function updateLanguageButton() {
    const lang = getCurrentLanguage();
    const btn = document.getElementById('lang-toggle-btn');
    if (btn) {
        btn.textContent = lang === 'fr' ? 'EN' : 'FR';
        btn.title = lang === 'fr' ? 'Switch to English' : 'Passer en Français';
    }
}

// Fonction pour créer le bouton de langue
function createLanguageButton() {
    const btn = document.createElement('button');
    btn.id = 'lang-toggle-btn';
    btn.className = 'lang-toggle-btn';
    btn.onclick = toggleLanguage;
    document.body.appendChild(btn);
    updateLanguageButton();
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    createLanguageButton();
    applyTranslations();
});
