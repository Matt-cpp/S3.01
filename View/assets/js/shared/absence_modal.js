// Modal management for displaying absence details
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('absenceModal');
    const modalContent = document.getElementById('modalContent');
    const closeModalBtn = document.getElementById('closeModal');
    const absenceRows = document.querySelectorAll('.absence-row');

    // Border colors based on status
    const statusBorderColors = {
        'accepted': '#28a745',      // Green
        'rejected': '#dc3545',      // Red - Proof rejected
        'under_review': '#ffc107',  // Yellow/Orange
        'pending': '#17a2b8',       // Blue
        'none': '#dc3545'           // Red - Unjustified absence
    };

    // Open modal when clicking on a row
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

            // Raw data for the justify link
            const courseDateRaw = this.dataset.courseDateRaw;
            const startTimeRaw = this.dataset.startTimeRaw;
            const endTimeRaw = this.dataset.endTimeRaw;

            // Evaluation and makeup data
            const isEvaluation = this.dataset.isEvaluation === '1';
            const hasMakeup = this.dataset.hasMakeup === '1';
            const makeupScheduled = this.dataset.makeupScheduled === '1';
            const makeupDate = this.dataset.makeupDate;
            const makeupTime = this.dataset.makeupTime;
            const makeupDuration = this.dataset.makeupDuration;
            const makeupRoom = this.dataset.makeupRoom;
            const makeupResource = this.dataset.makeupResource;
            const makeupComment = this.dataset.makeupComment;

            // Fill the modal with data
            document.getElementById('modalDate').textContent = date;
            document.getElementById('modalTime').textContent = time;
            document.getElementById('modalCourse').textContent = course;
            document.getElementById('modalTeacher').textContent = teacher;
            document.getElementById('modalRoom').textContent = room;
            document.getElementById('modalDuration').textContent = duration + 'h';
            document.getElementById('modalEvaluation').textContent = evaluation;
            document.getElementById('modalMotif').textContent = motif || 'Aucun motif spécifié';

            // Display the type with the appropriate badge
            const typeBadgeElement = document.getElementById('modalType');
            typeBadgeElement.textContent = type;
            typeBadgeElement.className = 'badge ' + typeBadge;

            // Display the status with the appropriate badge
            const statusBadge = document.getElementById('modalStatus');
            statusBadge.textContent = statusIcon + ' ' + statusText;
            statusBadge.className = 'badge ' + statusClass;

            // Handle the display of the evaluation section
            const evaluationSection = document.getElementById('evaluationSection');
            if (isEvaluation) {
                evaluationSection.style.display = 'block';
                document.getElementById('evaluationCourse').textContent = course;
                document.getElementById('evaluationDate').textContent = date;
                document.getElementById('evaluationTime').textContent = time;
            } else {
                evaluationSection.style.display = 'none';
            }

            // Handle the display of the makeup section
            const makeupSection = document.getElementById('makeupSection');
            if (hasMakeup && makeupScheduled) {
                makeupSection.style.display = 'block';
                document.getElementById('makeupDate').textContent = makeupDate || '-';
                document.getElementById('makeupTime').textContent = makeupTime || '-';
                document.getElementById('makeupDuration').textContent = makeupDuration ? makeupDuration + 'h' : '-';
                document.getElementById('makeupRoom').textContent = makeupRoom || '-';
                
                // Handle the resource
                const makeupResourceItem = document.getElementById('makeupResourceItem');
                if (makeupResource && makeupResource.trim() !== '') {
                    makeupResourceItem.style.display = 'flex';
                    document.getElementById('makeupResource').textContent = makeupResource;
                } else {
                    makeupResourceItem.style.display = 'none';
                }

                // Handle the comment
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

            // Apply the border color based on status
            const borderColor = statusBorderColors[modalStatus] || '#6c757d';
            modalContent.style.borderColor = borderColor;
            modalContent.style.borderWidth = '4px';
            modalContent.style.borderStyle = 'solid';

            // Handle the "Justify" button
            const justifySection = document.getElementById('justifySection');
            const justifyButton = document.getElementById('justifyButton');
            if (justifySection && justifyButton) {
                // Show only if the absence is not already justified (accepted)
                if (modalStatus !== 'accepted') {
                    // Build datetime-local from raw data
                    const startDateTime = courseDateRaw + 'T' + startTimeRaw.substring(0, 5);
                    const endDateTime = courseDateRaw + 'T' + endTimeRaw.substring(0, 5);
                    justifyButton.href = 'proof_submit.php?prefill_start=' + encodeURIComponent(startDateTime) + '&prefill_end=' + encodeURIComponent(endDateTime);
                    justifySection.style.display = 'block';
                } else {
                    justifySection.style.display = 'none';
                }
            }

            // Show the modal
            modal.classList.add('show');
            document.body.style.overflow = 'hidden'; // Prevent body scrolling
        });
    });

    // Close the modal with the X button
    closeModalBtn.addEventListener('click', closeModal);

    // Close the modal by clicking on the overlay
    document.querySelector('.modal-overlay').addEventListener('click', closeModal);

    // Close the modal with the Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modal.classList.contains('show')) {
            closeModal();
        }
    });

    function closeModal() {
        modal.classList.remove('show');
        document.body.style.overflow = ''; // Re-enable body scrolling
    }
});
