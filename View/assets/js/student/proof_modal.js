// Modal management for displaying proof details
document.addEventListener("DOMContentLoaded", function () {
  const modal = document.getElementById("proofModal");
  const modalContent = document.getElementById("modalContent");
  const closeModalBtn = document.getElementById("closeModal");
  const proofRows = document.querySelectorAll(".proof-row");

  // Border colors based on status
  const statusBorderColors = {
    accepted: "#28a745", // Green
    rejected: "#dc3545", // Red
    under_review: "#ffc107", // Yellow/Orange
    pending: "#17a2b8", // Blue
  };

  // Open modal when clicking on a row
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
      document.getElementById("modalStartDate").textContent =
        formatDateTime(startDatetime);
      document.getElementById("modalEndDate").textContent =
        formatDateTime(endDatetime);
      document.getElementById("modalReason").textContent = reason;
      document.getElementById("modalHours").textContent = hours + "h";
      document.getElementById("modalAbsences").textContent =
        absences + " absence" + (absences > 1 ? "s" : "");
      document.getElementById("modalHalfDays").textContent =
        halfDays + " demi-journ\u00e9e" + (halfDays > 1 ? "s" : "");
      document.getElementById("modalSubmission").textContent = submission;
      document.getElementById("modalProcessing").textContent = processing;
      document.getElementById("modalExam").textContent = exam;

      // Display files
      let files = [];
      try {
        files = filesJson ? JSON.parse(filesJson) : [];
      } catch (e) {
        console.error("Error parsing files JSON:", e);
        files = [];
      }

      const filesSection = document.getElementById("filesSection");
      const modalFiles = document.getElementById("modalFiles");

      if (files && files.length > 0) {
        filesSection.style.display = "block";
        modalFiles.innerHTML = "";
        files.forEach((file, index) => {
          const fileName =
            file.original_name || file.saved_name || "Fichier " + (index + 1);
          const fileSize = file.size
            ? " (" + (file.size / 1024).toFixed(1) + " Ko)"
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
        document.getElementById("customReasonItem").style.display = "flex";
        document.getElementById("modalCustomReason").textContent = customReason;
      } else {
        document.getElementById("customReasonItem").style.display = "none";
      }

      // Handle the student comment
      if (studentComment && studentComment.trim() !== "") {
        document.getElementById("studentCommentItem").style.display = "flex";
        document.getElementById("modalStudentComment").textContent =
          studentComment;
      } else {
        document.getElementById("studentCommentItem").style.display = "none";
      }

      // Display the status with the appropriate badge
      const statusBadge = document.getElementById("modalStatus");
      statusBadge.textContent = statusIcon + " " + statusText;
      statusBadge.className = "badge " + statusClass;

      // Display the comment if it exists
      if (comment && comment.trim() !== "") {
        document.getElementById("commentSection").style.display = "block";
        document.getElementById("modalComment").textContent = comment;
      } else {
        document.getElementById("commentSection").style.display = "none";
      }

      // Show the "Edit" button only for proofs under review
      const actionSection = document.getElementById("actionSection");
      const editBtn = document.getElementById("modalEditBtn");
      if (status === "under_review" && proofId) {
        actionSection.style.display = "block";
        editBtn.href =
          "../../../../Presenter/student/get_proof_for_edit.php?proof_id=" +
          proofId;
      } else {
        actionSection.style.display = "none";
      }

      // Apply the border color based on status
      const borderColor = statusBorderColors[status] || "#6c757d";
      modalContent.style.borderColor = borderColor;
      modalContent.style.borderWidth = "4px";
      modalContent.style.borderStyle = "solid";

      // Show the modal
      modal.classList.add("show");
      document.body.style.overflow = "hidden"; // Prevent body scrolling
    });
  });

  // Close the modal with the X button
  closeModalBtn.addEventListener("click", closeModal);

  // Close the modal by clicking on the overlay
  document
    .querySelector(".modal-overlay")
    .addEventListener("click", closeModal);

  // Close the modal with the Escape key
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && modal.classList.contains("show")) {
      closeModal();
    }
  });

  function closeModal() {
    modal.classList.remove("show");
    document.body.style.overflow = ""; // Re-enable body scrolling
  }
});
