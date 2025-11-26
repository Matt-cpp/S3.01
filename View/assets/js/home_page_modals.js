// Gestion des modals pour la page d'accueil (absences et justificatifs)
document.addEventListener("DOMContentLoaded", function () {
  // Éléments pour le modal des absences
  const absenceModal = document.getElementById("absenceModal");
  const absenceModalContent = document.getElementById("absenceModalContent");
  const closeAbsenceModalBtn = document.getElementById("closeAbsenceModal");
  const absenceRows = document.querySelectorAll(".absence-row");

  // Éléments pour le modal des justificatifs
  const proofModal = document.getElementById("proofModal");
  const proofModalContent = document.getElementById("proofModalContent");
  const closeProofModalBtn = document.getElementById("closeProofModal");
  const proofRows = document.querySelectorAll(".proof-row");

  // Couleurs de bordure selon le statut
  const statusBorderColors = {
    accepted: "#28a745", // Vert
    rejected: "#dc3545", // Rouge
    under_review: "#ffc107", // Jaune/Orange
    pending: "#17a2b8", // Bleu
    none: "#6c757d", // Gris
  };

  // Gestion du modal des absences
  absenceRows.forEach((row) => {
    row.addEventListener("click", function () {
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
      const statusText = this.dataset.statusText;
      const statusIcon = this.dataset.statusIcon;
      const statusClass = this.dataset.statusClass;

      // Remplir le modal avec les données
      document.getElementById("absenceModalDate").textContent = date;
      document.getElementById("absenceModalTime").textContent = time;
      document.getElementById("absenceModalCourse").textContent = course;
      document.getElementById("absenceModalTeacher").textContent = teacher;
      document.getElementById("absenceModalRoom").textContent = room;
      document.getElementById("absenceModalDuration").textContent =
        duration + "h";
      document.getElementById("absenceModalEvaluation").textContent =
        evaluation;

      // Gérer le code du cours
      if (courseCode && courseCode.trim() !== "") {
        document.getElementById("absenceCourseCodeItem").style.display = "flex";
        document.getElementById("absenceModalCourseCode").textContent =
          courseCode;
      } else {
        document.getElementById("absenceCourseCodeItem").style.display = "none";
      }

      // Afficher le type avec le badge approprié
      const typeBadgeElement = document.getElementById("absenceModalType");
      typeBadgeElement.textContent = type;
      typeBadgeElement.className = "badge " + typeBadge;

      // Afficher le statut avec le badge approprié
      const statusBadge = document.getElementById("absenceModalStatus");
      statusBadge.textContent = statusIcon + " " + statusText;
      statusBadge.className = "badge " + statusClass;

      // Appliquer la couleur de bordure selon le statut
      const borderColor = statusBorderColors[modalStatus] || "#6c757d";
      absenceModalContent.style.borderColor = borderColor;
      absenceModalContent.style.borderWidth = "4px";
      absenceModalContent.style.borderStyle = "solid";

      // Afficher le modal
      absenceModal.classList.add("show");
      document.body.style.overflow = "hidden";
    });
  });

  // Gestion du modal des justificatifs
  proofRows.forEach((row) => {
    row.addEventListener("click", function () {
      const status = this.dataset.status;
      const proofId = this.dataset.proofId;
      const period = this.dataset.period;
      const startDatetime = this.dataset.startDatetime;
      const endDatetime = this.dataset.endDatetime;
      const reason = this.dataset.reason;
      const customReason = this.dataset.customReason;
      const studentComment = this.dataset.studentComment;
      const hours = this.dataset.hours;
      const absences = this.dataset.absences;
      const halfDays = this.dataset.halfDays;
      const submission = this.dataset.submission;
      const processing = this.dataset.processing;
      const statusText = this.dataset.statusText;
      const statusIcon = this.dataset.statusIcon;
      const statusClass = this.dataset.statusClass;
      const exam = this.dataset.exam;
      const comment = this.dataset.comment;

      // Fonction pour formater la date et l'heure
      function formatDateTime(datetime) {
        if (!datetime) return '-';
        const date = new Date(datetime);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${day}/${month}/${year} à ${hours}h${minutes}`;
      }

      // Remplir le modal avec les données
      document.getElementById("proofModalStartDate").textContent = formatDateTime(startDatetime);
      document.getElementById("proofModalEndDate").textContent = formatDateTime(endDatetime);
      document.getElementById("proofModalReason").textContent = reason;
      document.getElementById("proofModalHours").textContent = hours + "h";
      document.getElementById("proofModalAbsences").textContent =
        absences + " absence" + (absences > 1 ? "s" : "");
      document.getElementById("proofModalHalfDays").textContent =
        halfDays + " demi-journ\u00e9e" + (halfDays > 1 ? "s" : "");
      document.getElementById("proofModalSubmission").textContent = submission;
      document.getElementById("proofModalProcessing").textContent = processing;
      document.getElementById("proofModalExam").textContent = exam;

      // Gérer la raison personnalisée
      if (customReason && customReason.trim() !== "") {
        document.getElementById("proofCustomReasonItem").style.display = "flex";
        document.getElementById("proofModalCustomReason").textContent =
          customReason;
      } else {
        document.getElementById("proofCustomReasonItem").style.display = "none";
      }

      // Gérer le commentaire de l'étudiant
      if (studentComment && studentComment.trim() !== "") {
        document.getElementById("proofStudentCommentItem").style.display = "flex";
        document.getElementById("proofModalStudentComment").textContent = studentComment;
      } else {
        document.getElementById("proofStudentCommentItem").style.display = "none";
      }

      // Afficher le statut avec le badge approprié
      const statusBadge = document.getElementById("proofModalStatus");
      statusBadge.textContent = statusIcon + " " + statusText;
      statusBadge.className = "badge " + statusClass;

      // Afficher le commentaire s'il existe
      if (comment && comment.trim() !== "") {
        document.getElementById("proofCommentSection").style.display = "block";
        document.getElementById("proofModalComment").textContent = comment;
      } else {
        document.getElementById("proofCommentSection").style.display = "none";
      }

      // Afficher le bouton "Compléter" uniquement pour les justificatifs en révision
      const actionSection = document.getElementById("proofActionSection");
      const completeBtn = document.getElementById("proofModalCompleteBtn");
      if (status === "under_review" && proofId) {
        actionSection.style.display = "block";
        completeBtn.href =
          "../../Presenter/get_proof_for_edit.php?proof_id=" + proofId;
      } else {
        actionSection.style.display = "none";
      }

      // Appliquer la couleur de bordure selon le statut
      const borderColor = statusBorderColors[status] || "#6c757d";
      proofModalContent.style.borderColor = borderColor;
      proofModalContent.style.borderWidth = "4px";
      proofModalContent.style.borderStyle = "solid";

      // Afficher le modal
      proofModal.classList.add("show");
      document.body.style.overflow = "hidden";
    });
  });

  // Fermer les modals
  closeAbsenceModalBtn.addEventListener("click", () =>
    closeModal(absenceModal)
  );
  closeProofModalBtn.addEventListener("click", () => closeModal(proofModal));

  // Fermer en cliquant sur les overlays
  document.querySelectorAll(".modal-overlay").forEach((overlay) => {
    overlay.addEventListener("click", function () {
      if (absenceModal.classList.contains("show")) {
        closeModal(absenceModal);
      }
      if (proofModal.classList.contains("show")) {
        closeModal(proofModal);
      }
    });
  });

  // Fermer avec la touche Échap
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      if (absenceModal.classList.contains("show")) {
        closeModal(absenceModal);
      }
      if (proofModal.classList.contains("show")) {
        closeModal(proofModal);
      }
    }
  });

  function closeModal(modal) {
    modal.classList.remove("show");
    document.body.style.overflow = "";
  }
});
