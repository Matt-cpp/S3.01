// Modal management for the home page (absences and proofs)
document.addEventListener("DOMContentLoaded", function () {
  // Elements for the absence modal
  const absenceModal = document.getElementById("absenceModal");
  const absenceModalContent = document.getElementById("absenceModalContent");
  const closeAbsenceModalBtn = document.getElementById("closeAbsenceModal");
  const absenceRows = document.querySelectorAll(".absence-row");

  // Elements for the proof modal
  const proofModal = document.getElementById("proofModal");
  const proofModalContent = document.getElementById("proofModalContent");
  const closeProofModalBtn = document.getElementById("closeProofModal");
  const proofRows = document.querySelectorAll(".proof-row");

  // Border colors based on status
  const statusBorderColors = {
    accepted: "#28a745", // Green
    rejected: "#dc3545", // Red
    under_review: "#ffc107", // Yellow/Orange
    pending: "#17a2b8", // Blue
    none: "#6c757d", // Grey
  };

  // Absence modal management
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

      // Evaluation and makeup data
      const isEvaluation = this.dataset.isEvaluation === "1";
      const hasMakeup = this.dataset.hasMakeup === "1";
      const makeupScheduled = this.dataset.makeupScheduled === "1";
      const makeupDate = this.dataset.makeupDate;
      const makeupTime = this.dataset.makeupTime;
      const makeupDuration = this.dataset.makeupDuration;
      const makeupRoom = this.dataset.makeupRoom;
      const makeupResource = this.dataset.makeupResource;
      const makeupComment = this.dataset.makeupComment;

      // Fill the modal with data
      document.getElementById("absenceModalDate").textContent = date;
      document.getElementById("absenceModalTime").textContent = time;
      document.getElementById("absenceModalCourse").textContent = course;
      document.getElementById("absenceModalTeacher").textContent = teacher;
      document.getElementById("absenceModalRoom").textContent = room;
      document.getElementById("absenceModalDuration").textContent =
        duration + "h";
      document.getElementById("absenceModalEvaluation").textContent =
        evaluation;

      // Display the type with the appropriate badge
      const typeBadgeElement = document.getElementById("absenceModalType");
      typeBadgeElement.textContent = type;
      typeBadgeElement.className = "badge " + typeBadge;

      // Display the status with the appropriate badge
      const statusBadge = document.getElementById("absenceModalStatus");
      statusBadge.textContent = statusIcon + " " + statusText;
      statusBadge.className = "badge " + statusClass;

      // Handle the display of the evaluation section
      const evaluationSection = document.getElementById("evaluationSection");
      if (isEvaluation) {
        evaluationSection.style.display = "block";
        document.getElementById("evaluationCourse").textContent = course;
        document.getElementById("evaluationDate").textContent = date;
        document.getElementById("evaluationTime").textContent = time;
      } else {
        evaluationSection.style.display = "none";
      }

      // Handle the display of the makeup section
      const makeupSection = document.getElementById("makeupSection");
      if (hasMakeup && makeupScheduled) {
        makeupSection.style.display = "block";
        document.getElementById("makeupDate").textContent = makeupDate || "-";
        document.getElementById("makeupTime").textContent = makeupTime || "-";
        document.getElementById("makeupDuration").textContent = makeupDuration
          ? makeupDuration + "h"
          : "-";
        document.getElementById("makeupRoom").textContent = makeupRoom || "-";

        // Handle the resource
        const makeupResourceItem =
          document.getElementById("makeupResourceItem");
        if (makeupResource && makeupResource.trim() !== "") {
          makeupResourceItem.style.display = "flex";
          document.getElementById("makeupResource").textContent =
            makeupResource;
        } else {
          makeupResourceItem.style.display = "none";
        }

        // Handle the comment
        const makeupCommentItem = document.getElementById("makeupCommentItem");
        if (makeupComment && makeupComment.trim() !== "") {
          makeupCommentItem.style.display = "flex";
          document.getElementById("makeupComment").textContent = makeupComment;
        } else {
          makeupCommentItem.style.display = "none";
        }
      } else {
        makeupSection.style.display = "none";
      }

      // Apply the border color based on status
      const borderColor = statusBorderColors[modalStatus] || "#6c757d";
      absenceModalContent.style.borderColor = borderColor;
      absenceModalContent.style.borderWidth = "4px";
      absenceModalContent.style.borderStyle = "solid";

      // Show the modal
      absenceModal.classList.add("show");
      document.body.style.overflow = "hidden";
    });
  });

  // Proof modal management
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
      const filesJson = this.dataset.files;

      // Function to format date and time
      function formatDateTime(datetime) {
        if (!datetime) return "-";
        const date = new Date(datetime);
        const day = String(date.getDate()).padStart(2, "0");
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const year = date.getFullYear();
        const hours = String(date.getHours()).padStart(2, "0");
        const minutes = String(date.getMinutes()).padStart(2, "0");
        return `${day}/${month}/${year} à ${hours}h${minutes}`;
      }

      // Fill the modal with data
      document.getElementById("proofModalStartDate").textContent =
        formatDateTime(startDatetime);
      document.getElementById("proofModalEndDate").textContent =
        formatDateTime(endDatetime);
      document.getElementById("proofModalReason").textContent = reason;
      document.getElementById("proofModalHours").textContent = hours + "h";
      document.getElementById("proofModalAbsences").textContent =
        absences + " absence" + (absences > 1 ? "s" : "");
      document.getElementById("proofModalHalfDays").textContent =
        halfDays + " demi-journ\u00e9e" + (halfDays > 1 ? "s" : "");
      document.getElementById("proofModalSubmission").textContent = submission;
      document.getElementById("proofModalProcessing").textContent = processing;
      document.getElementById("proofModalExam").textContent = exam;

      // Display files
      let files = [];
      try {
        files = filesJson ? JSON.parse(filesJson) : [];
      } catch (e) {
        console.error("Error parsing files JSON:", e);
        files = [];
      }

      const filesSection = document.getElementById("proofFilesSection");
      const modalFiles = document.getElementById("proofModalFiles");

      if (files && files.length > 0) {
        filesSection.style.display = "block";
        modalFiles.innerHTML = "";
        files.forEach((file, index) => {
          const fileName =
            file.original_name || file.saved_name || "Fichier " + (index + 1);
          const fileSize = file.file_size
            ? " (" + (file.file_size / 1024).toFixed(1) + " Ko)"
            : "";

          const fileLink = document.createElement("a");
          fileLink.href =
            "../../../../Presenter/student/view_upload_proof.php?proof_id=" +
            proofId +
            "&file_index=" +
            index;
          fileLink.target = "_blank";
          fileLink.style.cssText =
            "display: inline-block; padding: 8px 12px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;";
          fileLink.textContent = "📄 " + fileName + fileSize;

          modalFiles.appendChild(fileLink);
        });
      } else {
        filesSection.style.display = "none";
      }

      // Handle the custom reason
      if (customReason && customReason.trim() !== "") {
        document.getElementById("proofCustomReasonItem").style.display = "flex";
        document.getElementById("proofModalCustomReason").textContent =
          customReason;
      } else {
        document.getElementById("proofCustomReasonItem").style.display = "none";
      }

      // Handle the student comment
      if (studentComment && studentComment.trim() !== "") {
        document.getElementById("proofStudentCommentItem").style.display =
          "flex";
        document.getElementById("proofModalStudentComment").textContent =
          studentComment;
      } else {
        document.getElementById("proofStudentCommentItem").style.display =
          "none";
      }

      // Display the status with the appropriate badge
      const statusBadge = document.getElementById("proofModalStatus");
      statusBadge.textContent = statusIcon + " " + statusText;
      statusBadge.className = "badge " + statusClass;

      // Display the comment if it exists
      if (comment && comment.trim() !== "") {
        document.getElementById("proofCommentSection").style.display = "block";
        document.getElementById("proofModalComment").textContent = comment;
      } else {
        document.getElementById("proofCommentSection").style.display = "none";
      }

      // Show the "Complete" button only for proofs under review
      const actionSection = document.getElementById("proofActionSection");
      const completeBtn = document.getElementById("proofModalCompleteBtn");
      if (status === "under_review" && proofId) {
        actionSection.style.display = "block";
        completeBtn.href =
          "../../../../Presenter/student/get_proof_for_edit.php?proof_id=" +
          proofId;
      } else {
        actionSection.style.display = "none";
      }

      // Apply the border color based on status
      const borderColor = statusBorderColors[status] || "#6c757d";
      proofModalContent.style.borderColor = borderColor;
      proofModalContent.style.borderWidth = "4px";
      proofModalContent.style.borderStyle = "solid";

      // Show the modal
      proofModal.classList.add("show");
      document.body.style.overflow = "hidden";
    });
  });

  // Close modals
  closeAbsenceModalBtn.addEventListener("click", () =>
    closeModal(absenceModal)
  );
  closeProofModalBtn.addEventListener("click", () => closeModal(proofModal));

  // Close by clicking on overlays
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

  // Close with the Escape key
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
