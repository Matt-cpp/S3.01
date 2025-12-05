// Dashboard Secretary JavaScript

document.addEventListener("DOMContentLoaded", function () {
  // Initialize all components
  initializeTabs();
  initializeCSVImport();
  initializeStudentsImport();
  initializeManualEntry();
  initializeModals();
  loadImportHistory();
});

// ===== TAB FUNCTIONALITY =====
function initializeTabs() {
  const tabButtons = document.querySelectorAll(".tab-btn");
  const tabContents = document.querySelectorAll(".tab-content");

  tabButtons.forEach((btn) => {
    btn.addEventListener("click", function () {
      const targetTab = this.dataset.tab;

      // Remove active class from all tabs and contents
      tabButtons.forEach((b) => b.classList.remove("active"));
      tabContents.forEach((c) => c.classList.remove("active"));

      // Add active class to clicked tab and corresponding content
      this.classList.add("active");
      document.getElementById(targetTab + "-tab").classList.add("active");
    });
  });
}

// ===== CSV IMPORT FUNCTIONALITY =====
function initializeCSVImport() {
  const fileInput = document.getElementById("csv-file");
  const fileNameDisplay = document.getElementById("file-name");
  const importBtn = document.getElementById("import-btn");

  fileInput.addEventListener("change", function () {
    if (this.files.length > 0) {
      fileNameDisplay.textContent = this.files[0].name;
      importBtn.disabled = false;
    } else {
      fileNameDisplay.textContent = "Aucun fichier sélectionné";
      importBtn.disabled = true;
    }
  });

  importBtn.addEventListener("click", handleCSVImport);
}

async function handleCSVImport() {
  const fileInput = document.getElementById("csv-file");
  const file = fileInput.files[0];

  if (!file) {
    showNotification("Veuillez sélectionner un fichier CSV", "error");
    return;
  }

  const formData = new FormData();
  formData.append("csv_file", file);

  // Show progress container
  const progressContainer = document.getElementById("progress-container");
  const importResult = document.getElementById("import-result");
  progressContainer.style.display = "block";
  importResult.style.display = "none";

  // Reset progress
  updateProgress(0, "Démarrage de l'importation...");

  try {
    const response = await fetch("/Presenter/api/secretary/import-csv.php", {
      method: "POST",
      body: formData,
    });

    const data = await response.json();

    if (data.success) {
      // Start polling for progress
      pollImportProgress(data.import_id);
    } else {
      showImportResult(false, data.message);
    }
  } catch (error) {
    console.error("Import error:", error);
    showImportResult(false, "Erreur lors de l'importation: " + error.message);
  }
}

function pollImportProgress(importId) {
  const interval = setInterval(async () => {
    try {
      const response = await fetch(
        `/Presenter/api/secretary/import-progress.php?import_id=${importId}`
      );
      const data = await response.json();

      if (data.status === "completed") {
        clearInterval(interval);
        updateProgress(100, "Importation terminée!");
        showImportResult(
          true,
          `Import réussi! ${data.total_processed} lignes traitées.`
        );
        loadImportHistory();

        // Reset file input
        document.getElementById("csv-file").value = "";
        document.getElementById("file-name").textContent =
          "Aucun fichier sélectionné";
        document.getElementById("import-btn").disabled = true;
      } else if (data.status === "error") {
        clearInterval(interval);
        showImportResult(false, data.message);
      } else {
        // Update progress
        const percentage = (data.processed / data.total) * 100;
        updateProgress(percentage, data.message);

        // Update details
        const details = `Traité: ${data.processed}/${data.total} lignes`;
        document.getElementById("progress-details").textContent = details;
      }
    } catch (error) {
      console.error("Progress polling error:", error);
      clearInterval(interval);
    }
  }, 500); // Poll every 500ms for smoother progress updates
}

function updateProgress(percentage, statusText) {
  const progressFill = document.getElementById("progress-fill");
  const progressStatus = document.getElementById("progress-status");
  const progressPercentage = document.getElementById("progress-percentage");

  progressFill.style.width = percentage + "%";
  progressStatus.textContent = statusText;
  progressPercentage.textContent = Math.round(percentage) + "%";
}

function showImportResult(success, message) {
  const importResult = document.getElementById("import-result");
  importResult.className = "import-result " + (success ? "success" : "error");
  importResult.innerHTML = message;
  importResult.style.display = "block";

  // Hide progress container after a delay
  setTimeout(() => {
    document.getElementById("progress-container").style.display = "none";
  }, 1000);
}

// ===== STUDENTS IMPORT FUNCTIONALITY =====
function initializeStudentsImport() {
  const fileInput = document.getElementById("students-csv-file");
  const fileNameDisplay = document.getElementById("students-file-name");
  const importBtn = document.getElementById("students-import-btn");

  fileInput.addEventListener("change", function () {
    if (this.files.length > 0) {
      fileNameDisplay.textContent = this.files[0].name;
      importBtn.disabled = false;
    } else {
      fileNameDisplay.textContent = "Aucun fichier sélectionné";
      importBtn.disabled = true;
    }
  });

  importBtn.addEventListener("click", handleStudentsImport);
}

async function handleStudentsImport() {
  const fileInput = document.getElementById("students-csv-file");
  const file = fileInput.files[0];

  if (!file) {
    showNotification("Veuillez sélectionner un fichier CSV", "error");
    return;
  }

  const formData = new FormData();
  formData.append("csv_file", file);

  // Show progress container
  const progressContainer = document.getElementById(
    "students-progress-container"
  );
  const importResult = document.getElementById("students-import-result");
  progressContainer.style.display = "block";
  importResult.style.display = "none";

  // Reset progress
  updateStudentsProgress(0, "Démarrage de l'importation...");

  try {
    const response = await fetch(
      "/Presenter/api/secretary/import-students.php",
      {
        method: "POST",
        body: formData,
      }
    );

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || "Erreur lors de l'importation");
    }

    // Show success
    updateStudentsProgress(100, "Importation terminée!");
    showStudentsImportResult(
      true,
      `
      <strong>Importation réussie!</strong><br>
      ${data.created} étudiant(s) créé(s)<br>
      ${data.skipped} étudiant(s) ignoré(s) (déjà existants)
    `
    );

    // Reload history
    loadImportHistory();

    // Reset form
    fileInput.value = "";
    document.getElementById("students-file-name").textContent =
      "Aucun fichier sélectionné";
    document.getElementById("students-import-btn").disabled = true;
  } catch (error) {
    updateStudentsProgress(0, "Erreur");
    showStudentsImportResult(
      false,
      `<strong>Erreur:</strong> ${error.message}`
    );
  }
}

function updateStudentsProgress(percentage, statusText) {
  const progressFill = document.getElementById("students-progress-fill");
  const progressStatus = document.getElementById("students-progress-status");
  const progressPercentage = document.getElementById(
    "students-progress-percentage"
  );

  progressFill.style.width = percentage + "%";
  progressStatus.textContent = statusText;
  progressPercentage.textContent = Math.round(percentage) + "%";
}

function showStudentsImportResult(success, message) {
  const importResult = document.getElementById("students-import-result");
  importResult.className = "import-result " + (success ? "success" : "error");
  importResult.innerHTML = message;
  importResult.style.display = "block";

  // Hide progress container after a delay
  setTimeout(() => {
    document.getElementById("students-progress-container").style.display =
      "none";
  }, 1000);
}

// ===== MANUAL ENTRY FUNCTIONALITY =====
function initializeManualEntry() {
  // Student search
  const studentSearch = document.getElementById("student-search");
  studentSearch.addEventListener(
    "input",
    debounce(() => searchStudents(studentSearch.value), 300)
  );

  // Resource search
  const resourceSearch = document.getElementById("resource-search");
  resourceSearch.addEventListener(
    "input",
    debounce(() => searchResources(resourceSearch.value), 300)
  );

  // Room search
  const roomSearch = document.getElementById("room-search");
  roomSearch.addEventListener(
    "input",
    debounce(() => searchRooms(roomSearch.value), 300)
  );

  // Duration preset buttons
  const durationButtons = document.querySelectorAll(".duration-btn");
  durationButtons.forEach((btn) => {
    btn.addEventListener("click", handleDurationSelection);
  });

  // Start time change
  const startTimeInput = document.getElementById("start-time");
  startTimeInput.addEventListener("change", handleStartTimeChange);

  // Custom end time
  const customEndTime = document.getElementById("end-time");
  if (customEndTime) {
    customEndTime.addEventListener("change", handleCustomEndTimeChange);
  }

  // Form submission
  const form = document.getElementById("manual-absence-form");
  form.addEventListener("submit", handleManualAbsenceSubmit);

  // Hide search results when clicking outside
  document.addEventListener("click", function (e) {
    if (!e.target.closest(".search-container")) {
      document
        .querySelectorAll(".search-results")
        .forEach((el) => el.classList.remove("active"));
    }
  });
}

function handleStartTimeChange() {
  // Reset duration selection when start time changes
  document.querySelectorAll(".duration-btn").forEach((btn) => {
    btn.classList.remove("active");
  });
  document.getElementById("custom-end-time-container").style.display = "none";
  document.getElementById("selected-time-info").classList.remove("active");
  document.getElementById("end-time-value").value = "";
}

function handleDurationSelection(e) {
  const btn = e.target;
  const duration = btn.dataset.duration;
  const startTime = document.getElementById("start-time").value;

  if (!startTime) {
    showNotification(
      "Veuillez d'abord sélectionner une heure de début",
      "error"
    );
    return;
  }

  // Remove active class from all buttons
  document.querySelectorAll(".duration-btn").forEach((b) => {
    b.classList.remove("active");
  });

  // Add active class to clicked button
  btn.classList.add("active");

  if (duration === "custom") {
    // Show custom end time input
    document.getElementById("custom-end-time-container").style.display =
      "block";
    document.getElementById("selected-time-info").classList.remove("active");

    // Set minimum end time to start time
    const endTimeInput = document.getElementById("end-time");
    endTimeInput.min = startTime;
    endTimeInput.value = "";
    document.getElementById("end-time-value").value = "";
  } else {
    // Hide custom input
    document.getElementById("custom-end-time-container").style.display = "none";

    // Calculate end time
    const endTime = calculateEndTime(startTime, parseInt(duration));

    if (!endTime) {
      showNotification(
        "L'heure de fin dépasse 20:00. Veuillez choisir une durée plus courte ou utiliser l'option personnalisée.",
        "error"
      );
      btn.classList.remove("active");
      return;
    }

    // Set end time value
    document.getElementById("end-time-value").value = endTime;

    // Display selected time info
    displayTimeInfo(startTime, endTime, parseInt(duration));
  }
}

function handleCustomEndTimeChange(e) {
  const startTime = document.getElementById("start-time").value;
  const endTime = e.target.value;

  if (!startTime || !endTime) {
    return;
  }

  // Validate end time is after start time
  if (endTime <= startTime) {
    showNotification(
      "L'heure de fin doit être après l'heure de début",
      "error"
    );
    e.target.value = "";
    return;
  }

  // Calculate duration
  const duration = calculateDuration(startTime, endTime);

  // Set end time value
  document.getElementById("end-time-value").value = endTime;

  // Display selected time info
  displayTimeInfo(startTime, endTime, duration);
}

function calculateEndTime(startTime, durationMinutes) {
  // Parse start time
  const [hours, minutes] = startTime.split(":").map(Number);

  // Create date object for today with start time
  const date = new Date();
  date.setHours(hours, minutes, 0, 0);

  // Add duration
  date.setMinutes(date.getMinutes() + durationMinutes);

  // Check if end time exceeds 20:00
  if (
    date.getHours() > 20 ||
    (date.getHours() === 20 && date.getMinutes() > 0)
  ) {
    return null;
  }

  // Format as HH:MM
  const endHours = String(date.getHours()).padStart(2, "0");
  const endMinutes = String(date.getMinutes()).padStart(2, "0");

  return `${endHours}:${endMinutes}`;
}

function calculateDuration(startTime, endTime) {
  const [startHours, startMinutes] = startTime.split(":").map(Number);
  const [endHours, endMinutes] = endTime.split(":").map(Number);

  const startDate = new Date();
  startDate.setHours(startHours, startMinutes, 0, 0);

  const endDate = new Date();
  endDate.setHours(endHours, endMinutes, 0, 0);

  const diffMs = endDate - startDate;
  const diffMinutes = Math.floor(diffMs / 60000);

  return diffMinutes;
}

function displayTimeInfo(startTime, endTime, durationMinutes) {
  const hours = Math.floor(durationMinutes / 60);
  const minutes = durationMinutes % 60;

  let durationText = "";
  if (hours > 0 && minutes > 0) {
    durationText = `${hours}h${minutes}`;
  } else if (hours > 0) {
    durationText = `${hours}h`;
  } else {
    durationText = `${minutes}min`;
  }

  const info = document.getElementById("selected-time-info");
  info.innerHTML = `<strong>Créneau sélectionné:</strong> ${startTime} - ${endTime} (${durationText})`;
  info.classList.add("active");
}

async function searchStudents(query) {
  if (query.length < 2) {
    document.getElementById("student-results").classList.remove("active");
    return;
  }

  try {
    const response = await fetch(
      `/Presenter/api/secretary/search-students.php?q=${encodeURIComponent(
        query
      )}`
    );
    const data = await response.json();

    displaySearchResults(
      "student-results",
      data,
      (item) => {
        return {
          name: `${item.first_name} ${item.last_name}`,
          details: `ID: ${item.identifier}`,
          id: item.id,
        };
      },
      selectStudent
    );
  } catch (error) {
    console.error("Student search error:", error);
  }
}

async function searchResources(query) {
  if (query.length < 2) {
    document.getElementById("resource-results").classList.remove("active");
    return;
  }

  try {
    const response = await fetch(
      `/Presenter/api/secretary/search-resources.php?q=${encodeURIComponent(
        query
      )}`
    );
    const data = await response.json();

    displaySearchResults(
      "resource-results",
      data,
      (item) => {
        return {
          name: item.label,
          details: `Code: ${item.code} - ${
            item.teaching_type || "Type non défini"
          }`,
          id: item.id,
        };
      },
      selectResource
    );
  } catch (error) {
    console.error("Resource search error:", error);
  }
}

async function searchRooms(query) {
  if (query.length < 1) {
    document.getElementById("room-results").classList.remove("active");
    return;
  }

  try {
    const response = await fetch(
      `/Presenter/api/secretary/search-rooms.php?q=${encodeURIComponent(query)}`
    );
    const data = await response.json();

    displaySearchResults(
      "room-results",
      data,
      (item) => {
        return {
          name: item.code,
          details: "",
          id: item.id,
        };
      },
      selectRoom
    );
  } catch (error) {
    console.error("Room search error:", error);
  }
}

function displaySearchResults(containerId, items, formatFunc, selectFunc) {
  const container = document.getElementById(containerId);

  if (items.length === 0) {
    container.innerHTML =
      '<div class="search-result-item" style="color: #95a5a6;">Aucun résultat</div>';
    container.classList.add("active");
    return;
  }

  container.innerHTML = items
    .map((item) => {
      const formatted = formatFunc(item);
      return `
            <div class="search-result-item" data-id="${formatted.id}">
                <div class="result-name">${formatted.name}</div>
                ${
                  formatted.details
                    ? `<div class="result-details">${formatted.details}</div>`
                    : ""
                }
            </div>
        `;
    })
    .join("");

  // Add click handlers
  container.querySelectorAll(".search-result-item").forEach((el) => {
    el.addEventListener("click", () => {
      const id = el.dataset.id;
      const item = items.find((i) => i.id == id);
      selectFunc(item);
    });
  });

  container.classList.add("active");
}

function selectStudent(student) {
  document.getElementById("student-search").value = "";
  document.getElementById("selected-student-id").value = student.id;
  document.getElementById("student-results").classList.remove("active");

  const info = document.getElementById("selected-student-info");
  info.innerHTML = `<strong>Sélectionné:</strong> ${student.first_name} ${student.last_name} (${student.identifier})`;
  info.classList.add("active");
}

function selectResource(resource) {
  document.getElementById("resource-search").value = "";
  document.getElementById("selected-resource-id").value = resource.id;
  document.getElementById("resource-results").classList.remove("active");

  const info = document.getElementById("selected-resource-info");
  info.innerHTML = `<strong>Sélectionné:</strong> ${resource.label} (${resource.code})`;
  info.classList.add("active");
}

function selectRoom(room) {
  document.getElementById("room-search").value = "";
  document.getElementById("selected-room-id").value = room.id;
  document.getElementById("room-results").classList.remove("active");

  const info = document.getElementById("selected-room-info");
  info.innerHTML = `<strong>Sélectionné:</strong> Salle ${room.code}`;
  info.classList.add("active");
}

async function handleManualAbsenceSubmit(e) {
  e.preventDefault();

  const formData = new FormData(e.target);

  // Validate required fields
  if (
    !formData.get("student_id") ||
    !formData.get("resource_id") ||
    !formData.get("room_id")
  ) {
    showNotification("Veuillez remplir tous les champs obligatoires", "error");
    return;
  }

  // Validate time selection
  const endTime = formData.get("end_time");
  if (!endTime) {
    showNotification(
      "Veuillez sélectionner une durée ou une heure de fin",
      "error"
    );
    return;
  }

  try {
    const response = await fetch(
      "/Presenter/api/secretary/create-manual-absence.php",
      {
        method: "POST",
        body: formData,
      }
    );

    const data = await response.json();

    if (data.success) {
      showNotification("Absence enregistrée avec succès!", "success");
      e.target.reset();

      // Clear selected items
      document
        .querySelectorAll(".selected-info")
        .forEach((el) => el.classList.remove("active"));
      document.getElementById("selected-student-id").value = "";
      document.getElementById("selected-resource-id").value = "";
      document.getElementById("selected-room-id").value = "";
      document.getElementById("selected-time-info").classList.remove("active");
      document.getElementById("custom-end-time-container").style.display =
        "none";
      document.querySelectorAll(".duration-btn").forEach((btn) => {
        btn.classList.remove("active");
      });

      // Refresh history
      loadImportHistory();
    } else {
      showNotification("Erreur: " + data.message, "error");
    }
  } catch (error) {
    console.error("Manual absence error:", error);
    showNotification("Erreur lors de l'enregistrement", "error");
  }
}

// ===== MODAL FUNCTIONALITY =====
function initializeModals() {
  // Create resource modal
  const createResourceBtn = document.getElementById("create-resource-btn");
  const createResourceModal = document.getElementById("create-resource-modal");
  const createResourceForm = document.getElementById("create-resource-form");

  createResourceBtn.addEventListener("click", () => {
    createResourceModal.classList.add("active");
  });

  createResourceForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    await handleCreateResource(e.target);
  });

  // Create room modal
  const createRoomBtn = document.getElementById("create-room-btn");
  const createRoomModal = document.getElementById("create-room-modal");
  const createRoomForm = document.getElementById("create-room-form");

  createRoomBtn.addEventListener("click", () => {
    createRoomModal.classList.add("active");
  });

  createRoomForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    await handleCreateRoom(e.target);
  });

  // Close modal handlers
  document.querySelectorAll(".close-modal, .cancel-modal").forEach((el) => {
    el.addEventListener("click", function () {
      this.closest(".modal").classList.remove("active");
    });
  });

  // Close modal on outside click
  document.querySelectorAll(".modal").forEach((modal) => {
    modal.addEventListener("click", function (e) {
      if (e.target === this) {
        this.classList.remove("active");
      }
    });
  });
}

async function handleCreateResource(form) {
  const formData = new FormData(form);

  try {
    const response = await fetch(
      "/Presenter/api/secretary/create-resource.php",
      {
        method: "POST",
        body: formData,
      }
    );

    const data = await response.json();

    if (data.success) {
      showNotification("Matière créée avec succès!", "success");
      form.reset();
      document
        .getElementById("create-resource-modal")
        .classList.remove("active");

      // Auto-select the newly created resource
      selectResource(data.resource);
    } else {
      showNotification("Erreur: " + data.message, "error");
    }
  } catch (error) {
    console.error("Create resource error:", error);
    showNotification("Erreur lors de la création", "error");
  }
}

async function handleCreateRoom(form) {
  const formData = new FormData(form);

  try {
    const response = await fetch("/Presenter/api/secretary/create-room.php", {
      method: "POST",
      body: formData,
    });

    const data = await response.json();

    if (data.success) {
      showNotification("Salle créée avec succès!", "success");
      form.reset();
      document.getElementById("create-room-modal").classList.remove("active");

      // Auto-select the newly created room
      selectRoom(data.room);
    } else {
      showNotification("Erreur: " + data.message, "error");
    }
  } catch (error) {
    console.error("Create room error:", error);
    showNotification("Erreur lors de la création", "error");
  }
}

// ===== HISTORY FUNCTIONALITY =====
async function loadImportHistory() {
  try {
    const response = await fetch(
      "/Presenter/api/secretary/get-import-history.php"
    );
    const data = await response.json();

    const historyContainer = document.getElementById("import-history");

    if (!data || data.length === 0) {
      historyContainer.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <div class="empty-state-text">Aucun historique disponible</div>
                </div>
            `;
      return;
    }

    historyContainer.innerHTML = data
      .map((item) => {
        const statusClass =
          item.status === "success"
            ? "success"
            : item.status === "error"
            ? "error"
            : "processing";

        return `
                <div class="history-item">
                    <div class="history-date">${formatDate(
                      item.created_at
                    )}</div>
                    <div class="history-action">${item.action}</div>
                    <div class="history-details">${item.details}</div>
                    <span class="history-status ${statusClass}">${getStatusLabel(
          item.status
        )}</span>
                </div>
            `;
      })
      .join("");
  } catch (error) {
    console.error("History load error:", error);
  }
}

// ===== UTILITY FUNCTIONS =====
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

function showNotification(message, type) {
  // Create a simple notification
  const notification = document.createElement("div");
  notification.className = `notification ${type}`;
  notification.textContent = message;
  notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === "success" ? "#27ae60" : "#e74c3c"};
        color: white;
        border-radius: 4px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideIn 0.3s;
    `;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.animation = "fadeOut 0.3s";
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

function formatDate(dateString) {
  const date = new Date(dateString);
  const now = new Date();
  const diff = now - date;
  const minutes = Math.floor(diff / 60000);
  const hours = Math.floor(diff / 3600000);
  const days = Math.floor(diff / 86400000);

  if (minutes < 1) return "À l'instant";
  if (minutes < 60) return `Il y a ${minutes} minute${minutes > 1 ? "s" : ""}`;
  if (hours < 24) return `Il y a ${hours} heure${hours > 1 ? "s" : ""}`;
  if (days < 7) return `Il y a ${days} jour${days > 1 ? "s" : ""}`;

  return date.toLocaleDateString("fr-FR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function getStatusLabel(status) {
  const labels = {
    success: "Réussi",
    error: "Erreur",
    processing: "En cours",
  };
  return labels[status] || status;
}
