// Gestion du modal pour afficher les détails des absences
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('absenceModal');
    const modalContent = document.getElementById('modalContent');
    const closeModalBtn = document.getElementById('closeModal');
    const absenceRows = document.querySelectorAll('.absence-row');

    // Couleurs de bordure selon le statut
    const statusBorderColors = {
        'accepted': '#28a745',      // Vert
        'rejected': '#dc3545',      // Rouge - Justificatif refusé
        'under_review': '#ffc107',  // Jaune/Orange
        'pending': '#17a2b8',       // Bleu
        'none': '#dc3545'           // Rouge - Absence non justifiée
    };

    // Ouvrir le modal quand on clique sur une ligne
    absenceRows.forEach(row => {
        row.addEventListener('click', function() {
            const modalStatus = this.dataset.modalStatus;
            const date = this.dataset.date;
            const time = this.dataset.time;
            const course = this.dataset.course;
            const courseCode = this.dataset.courseCode;
            const teacher = this.dataset.teacher;
            const room = this.dataset.room;
            const duration = this.dataset.duration;
            const type = this.dataset.type;
            const typeBadge = this.dataset.typeBadge;
            const evaluation = this.dataset.evaluation;
            const motif = this.dataset.motif;
            const statusText = this.dataset.statusText;
            const statusIcon = this.dataset.statusIcon;
            const statusClass = this.dataset.statusClass;

            // Données évaluation et rattrapage
            const isEvaluation = this.dataset.isEvaluation === '1';
            const hasMakeup = this.dataset.hasMakeup === '1';
            const makeupScheduled = this.dataset.makeupScheduled === '1';
            const makeupDate = this.dataset.makeupDate;
            const makeupTime = this.dataset.makeupTime;
            const makeupDuration = this.dataset.makeupDuration;
            const makeupRoom = this.dataset.makeupRoom;
            const makeupResource = this.dataset.makeupResource;
            const makeupComment = this.dataset.makeupComment;

            // Remplir le modal avec les données
            document.getElementById('modalDate').textContent = date;
            document.getElementById('modalTime').textContent = time;
            document.getElementById('modalCourse').textContent = course;
            document.getElementById('modalTeacher').textContent = teacher;
            document.getElementById('modalRoom').textContent = room;
            document.getElementById('modalDuration').textContent = duration + 'h';
            document.getElementById('modalEvaluation').textContent = evaluation;
            document.getElementById('modalMotif').textContent = motif || 'Aucun motif spécifié';

            // Afficher le type avec le badge approprié
            const typeBadgeElement = document.getElementById('modalType');
            typeBadgeElement.textContent = type;
            typeBadgeElement.className = 'badge ' + typeBadge;

            // Afficher le statut avec le badge approprié
            const statusBadge = document.getElementById('modalStatus');
            statusBadge.textContent = statusIcon + ' ' + statusText;
            statusBadge.className = 'badge ' + statusClass;

            // Gérer l'affichage de la section évaluation
            const evaluationSection = document.getElementById('evaluationSection');
            if (isEvaluation) {
                evaluationSection.style.display = 'block';
                document.getElementById('evaluationCourse').textContent = course;
                document.getElementById('evaluationDate').textContent = date;
                document.getElementById('evaluationTime').textContent = time;
            } else {
                evaluationSection.style.display = 'none';
            }

            // Gérer l'affichage de la section rattrapage
            const makeupSection = document.getElementById('makeupSection');
            if (hasMakeup && makeupScheduled) {
                makeupSection.style.display = 'block';
                document.getElementById('makeupDate').textContent = makeupDate || '-';
                document.getElementById('makeupTime').textContent = makeupTime || '-';
                document.getElementById('makeupDuration').textContent = makeupDuration ? makeupDuration + 'h' : '-';
                document.getElementById('makeupRoom').textContent = makeupRoom || '-';
                
                // Gérer la ressource
                const makeupResourceItem = document.getElementById('makeupResourceItem');
                if (makeupResource && makeupResource.trim() !== '') {
                    makeupResourceItem.style.display = 'flex';
                    document.getElementById('makeupResource').textContent = makeupResource;
                } else {
                    makeupResourceItem.style.display = 'none';
                }

                // Gérer le commentaire
                const makeupCommentItem = document.getElementById('makeupCommentItem');
                if (makeupComment && makeupComment.trim() !== '') {
                    makeupCommentItem.style.display = 'flex';
                    document.getElementById('makeupComment').textContent = makeupComment;
                } else {
                    makeupCommentItem.style.display = 'none';
                }
            } else {
                makeupSection.style.display = 'none';
            }

            // Appliquer la couleur de bordure selon le statut
            const borderColor = statusBorderColors[modalStatus] || '#6c757d';
            modalContent.style.borderColor = borderColor;
            modalContent.style.borderWidth = '4px';
            modalContent.style.borderStyle = 'solid';

            // Afficher le modal
            modal.classList.add('show');
            document.body.style.overflow = 'hidden'; // Empêcher le scroll du body
        });
    });

    // Fermer le modal avec le bouton X
    closeModalBtn.addEventListener('click', closeModal);

    // Fermer le modal en cliquant sur l'overlay
    document.querySelector('.modal-overlay').addEventListener('click', closeModal);

    // Fermer le modal avec la touche Échap
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modal.classList.contains('show')) {
            closeModal();
        }
    });

    function closeModal() {
        modal.classList.remove('show');
        document.body.style.overflow = ''; // Réactiver le scroll du body
    }
});
