// Configuration pour la gestion des fichiers multiples
const FILE_CONFIG = {
    maxFileSize: 5 * 1024 * 1024,      // 5MB par fichier
    maxTotalSize: 20 * 1024 * 1024,    // 20MB au total
    allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
    mimeTypes: {
        'pdf': 'application/pdf',
        'jpg': 'image/jpeg',
        'jpeg': 'image/jpeg',
        'png': 'image/png',
        'doc': 'application/msword',
        'docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    }
};

// Variable globale pour stocker les fichiers sélectionnés
let selectedFiles = [];

function toggleCustomReason() {
  var select = document.getElementById("absence_reason");
  var customdiv = document.getElementById("custom_reason");
  var custominput = document.getElementById("other_reason");

  if (select.value === "autre") {
    customdiv.style.display = "block";
    custominput.required = true;
  } else {
    customdiv.style.display = "none";
    custominput.required = false;
    custominput.value = "";
  }
}

// Global variable to store courses data
var coursesData = [];

function getRealTime() {
  var now = new Date();
  return new Date(now.toLocaleString("en-US", { timeZone: "Europe/Paris" }));
}

function validateDates() {
  var dateStart = document.getElementById("datetime_start").value;
  var dateEnd = document.getElementById("datetime_end").value;

  var realTime = getRealTime();
  var maxEndDate = new Date(realTime);
  maxEndDate.setDate(maxEndDate.getDate() + 1);

  // Validation of the end date being after the start date
  if (dateStart && dateEnd) {
    var debut = new Date(dateStart);
    var fin = new Date(dateEnd);

    if (fin <= debut) {
      alert(
        "La date/heure de fin doit être postérieure à la date/heure de début."
      );
      document.getElementById("datetime_end").value = "";
      return false;
    }

    // Check if end date is more than 1 day after current date
    if (fin > maxEndDate) {
      alert(
        "La date/heure de fin ne peut pas être plus d'un jour après la date actuelle."
      );
      document.getElementById("datetime_end").value = "";
      return false;
    }
  }

  return true;
}

/**
 * Valide et affiche les fichiers sélectionnés
 */
function handleFileSelection(event) {
    const fileInput = event.target;
    const files = Array.from(fileInput.files);
    
    // Réinitialiser la liste des fichiers
    selectedFiles = [];
    
    // Valider chaque fichier
    let totalSize = 0;
    let errors = [];
    
    files.forEach((file, index) => {
        // Vérifier l'extension
        const extension = file.name.split('.').pop().toLowerCase();
        if (!FILE_CONFIG.allowedExtensions.includes(extension)) {
            errors.push(`${file.name} : format non autorisé`);
            return;
        }
        
        // Vérifier la taille du fichier
        if (file.size === 0) {
            errors.push(`${file.name} : fichier vide`);
            return;
        }
        
        if (file.size > FILE_CONFIG.maxFileSize) {
            errors.push(`${file.name} : dépasse 5MB (${formatFileSize(file.size)})`);
            return;
        }
        
        totalSize += file.size;
        selectedFiles.push(file);
    });
    
    // Vérifier la taille totale
    if (totalSize > FILE_CONFIG.maxTotalSize) {
        errors.push(`Taille totale (${formatFileSize(totalSize)}) dépasse 20MB`);
    }
    
    // Afficher les erreurs
    const warningDiv = document.getElementById('file_size_warning');
    if (errors.length > 0) {
        warningDiv.innerHTML = '<strong>Erreurs détectées :</strong><br>' + errors.join('<br>');
        warningDiv.style.display = 'block';
        
        // Si erreur critique, vider la sélection
        if (totalSize > FILE_CONFIG.maxTotalSize || selectedFiles.length === 0) {
            fileInput.value = '';
            selectedFiles = [];
            document.getElementById('files_preview').style.display = 'none';
            return;
        }
    } else {
        warningDiv.style.display = 'none';
    }
    
    // Afficher l'aperçu des fichiers valides
    displayFilesPreview(selectedFiles, totalSize);
}

/**
 * Affiche l'aperçu des fichiers sélectionnés
 */
function displayFilesPreview(files, totalSize) {
    const previewDiv = document.getElementById('files_preview');
    const filesListDiv = document.getElementById('files_list');
    const totalSizeDiv = document.getElementById('total_size');
    
    if (files.length === 0) {
        previewDiv.style.display = 'none';
        return;
    }
    
    // Construire la liste des fichiers
    let filesHtml = '';
    files.forEach((file, index) => {
        const extension = file.name.split('.').pop().toLowerCase();
        const icon = getFileIcon(extension);
        
        filesHtml += `
            <div class="file-item" data-file-index="${index}">
                <div class="file-info">
                    <span class="file-icon">${icon}</span>
                    <div class="file-details">
                        <span class="file-name">${escapeHtml(file.name)}</span>
                        <span class="file-size">${formatFileSize(file.size)}</span>
                    </div>
                </div>
                <button type="button" class="file-remove" onclick="removeFile(${index})">
                    🗑️ Supprimer
                </button>
            </div>
        `;
    });
    
    filesListDiv.innerHTML = filesHtml;
    
    // Afficher la taille totale
    const sizePercent = (totalSize / FILE_CONFIG.maxTotalSize) * 100;
    let sizeClass = '';
    if (sizePercent > 90) {
        sizeClass = 'error';
    } else if (sizePercent > 70) {
        sizeClass = 'warning';
    }
    
    totalSizeDiv.className = sizeClass;
    totalSizeDiv.innerHTML = `
        Taille totale : <strong>${formatFileSize(totalSize)}</strong> / ${formatFileSize(FILE_CONFIG.maxTotalSize)}
        (${sizePercent.toFixed(1)}%)
    `;
    
    previewDiv.style.display = 'block';
}

/**
 * Supprime un fichier de la sélection
 */
function removeFile(index) {
    const fileInput = document.getElementById('proof_files');
    const dt = new DataTransfer();
    
    // Recréer la FileList sans le fichier supprimé
    selectedFiles.forEach((file, i) => {
        if (i !== index) {
            dt.items.add(file);
        }
    });
    
    fileInput.files = dt.files;
    
    // Re-déclencher la validation
    handleFileSelection({ target: fileInput });
}

/**
 * Obtient l'icône correspondant au type de fichier
 */
function getFileIcon(extension) {
    const icons = {
        'pdf': '📄',
        'jpg': '🖼️',
        'jpeg': '🖼️',
        'png': '🖼️',
        'doc': '📝',
        'docx': '📝'
    };
    return icons[extension] || '📎';
}

/**
 * Formate la taille du fichier
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

/**
 * Échappe le HTML pour éviter les injections XSS
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Modifier la fonction de validation existante
function validateFileSize() {
  // Cette fonction n'est plus nécessaire car la validation
  // est faite dans handleFileSelection
  return true;
}

// Function to fetch and display absences
function fetchAbsences() {
  var dateStart = document.getElementById("datetime_start").value;
  var dateEnd = document.getElementById("datetime_end").value;

  // Check if both dates are filled
  if (!dateStart || !dateEnd) {
    showCoursesPlaceholder();
    return;
  }

  // Show loading indicator
  showCoursesLoading(true);

  // Make AJAX request to fetch absences
  var xhr = new XMLHttpRequest();
  var url =
    "../../Presenter/get_absences_of_student.php?datetime_start=" +
    encodeURIComponent(dateStart) +
    "&datetime_end=" +
    encodeURIComponent(dateEnd) +
    "&student_id=" +
    (window.studentId || 1);

  // Add proof_id if we're in editing mode
  if (window.isEditing && window.editProofId) {
    url += "&proof_id=" + window.editProofId;
  }

  xhr.open("GET", url, true);
  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4) {
      showCoursesLoading(false);

      if (xhr.status === 200) {
        try {
          var response = JSON.parse(xhr.responseText);
          displayCourses(response.courses);
        } catch (e) {
          console.error("Error parsing response:", e);
          showCoursesError("Erreur lors du traitement de la réponse");
        }
      } else {
        console.error("HTTP Error:", xhr.status);
        showCoursesError("Erreur lors du chargement des cours");
      }
    }
  };

  xhr.onerror = function () {
    showCoursesLoading(false);
    console.error("Network Error");
    showCoursesError("Erreur de connexion");
  };

  xhr.send();
}

// Function to show courses placeholder
function showCoursesPlaceholder() {
  document.getElementById("courses_placeholder").style.display = "block";
  document.getElementById("courses_list").style.display = "none";
  document.getElementById("absence_recap").style.display = "none";
  document.getElementById("class_involved_hidden").value = "";
  // Reset statistics hidden fields
  document.getElementById("absence_stats_hours").value = "0";
  document.getElementById("absence_stats_halfdays").value = "0";
  document.getElementById("absence_stats_evaluations").value = "0";
  document.getElementById("absence_stats_course_types").value = "{}";
  document.getElementById("absence_stats_evaluation_details").value = "[]";
}

// Function to show/hide loading indicator
function showCoursesLoading(show) {
  document.getElementById("courses_loading").style.display = show
    ? "block"
    : "none";
  if (show) {
    document.getElementById("courses_placeholder").style.display = "none";
    document.getElementById("courses_list").style.display = "none";
    document.getElementById("absence_recap").style.display = "none";
  }
}

// Function to display courses
function displayCourses(courses) {
  // Store courses data globally for recap calculations
  coursesData = courses;

  var placeholderEl = document.getElementById("courses_placeholder");
  var listEl = document.getElementById("courses_list");
  var hiddenEl = document.getElementById("class_involved_hidden");

  if (courses.length === 0) {
    placeholderEl.innerHTML =
      '<div style="background-color: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f1aeb5; border-radius: 4px; margin: 10px 0;">' +
      "Aucune absence non justifiée trouvée pour cette période. " +
      "Vous ne pouvez soumettre un justificatif que pour des absences déjà enregistrées dans le système." +
      "</div>";
    placeholderEl.style.display = "block";
    placeholderEl.style.color = "";
    listEl.style.display = "none";
    document.getElementById("absence_recap").style.display = "none";
    hiddenEl.value = "";
    // Reset statistics hidden fields
    document.getElementById("absence_stats_hours").value = "0";
    document.getElementById("absence_stats_halfdays").value = "0";
    document.getElementById("absence_stats_evaluations").value = "0";
    document.getElementById("absence_stats_course_types").value = "{}";
    document.getElementById("absence_stats_evaluation_details").value = "[]";

    // Disable the submit button when no absences are found
    var submitButton = document.querySelector(".submit-btn");
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.style.opacity = "0.5";
      submitButton.style.cursor = "not-allowed";
      submitButton.title = "Aucune absence trouvée pour cette période";
    }
    return;
  }

  // Build courses list with button-style display
  var coursesHtml = '<div class="courses-container">';
  var courseDescriptions = [];

  courses.forEach(function (course, index) {
    var isEvaluation = course.is_evaluation || false;
    var evaluationClass = isEvaluation ? " evaluation" : "";
    var evaluationIcon = isEvaluation
      ? '<span class="evaluation-badge">ÉVALUATION</span>'
      : "";

    coursesHtml +=
      '<div class="course-item' +
      evaluationClass +
      ' selected locked" data-course-id="' +
      index +
      '">';
    coursesHtml +=
      '<input type="checkbox" id="course_' +
      index +
      '" checked style="display: none;">';
    coursesHtml +=
      '<div class="course-button selected" style="cursor: default; pointer-events: none;">';

    // Course header with title and evaluation badge
    coursesHtml += '<div class="course-header">';
    coursesHtml += '<h4 class="course-title">';
    if (course.resource_label) {
      coursesHtml += course.resource_label;
      if (course.resource_code) {
        coursesHtml +=
          ' <span class="course-code">(' + course.resource_code + ")</span>";
      }
    } else {
      coursesHtml += "Cours non spécifié";
    }
    coursesHtml += "</h4>";
    coursesHtml += evaluationIcon;
    coursesHtml += "</div>";

    // Course details
    coursesHtml += '<div class="course-details">';

    // Date and time
    coursesHtml += '<div class="course-info">';
    coursesHtml += '<span class="info-label">📅 Date:</span> ';
    coursesHtml +=
      '<span class="info-value">' +
      course.course_date +
      " (" +
      course.start_time +
      "-" +
      course.end_time +
      ")</span>";
    coursesHtml += "</div>";

    // Course type
    if (course.course_type) {
      coursesHtml += '<div class="course-info">';
      coursesHtml += '<span class="info-label">📚 Type:</span> ';
      coursesHtml +=
        '<span class="info-value">' +
        course.course_type.toUpperCase() +
        "</span>";
      coursesHtml += "</div>";
    }

    // Teacher
    if (course.teacher) {
      coursesHtml += '<div class="course-info">';
      coursesHtml += '<span class="info-label">👨‍🏫 Enseignant:</span> ';
      coursesHtml += '<span class="info-value">' + course.teacher + "</span>";
      coursesHtml += "</div>";
    }

    // Room
    if (course.room) {
      coursesHtml += '<div class="course-info">';
      coursesHtml += '<span class="info-label">🏠 Salle:</span> ';
      coursesHtml += '<span class="info-value">' + course.room + "</span>";
      coursesHtml += "</div>";
    }

    coursesHtml += "</div>"; // Close course-details
    coursesHtml += "</div>"; // Close course-button
    coursesHtml += "</div>"; // Close course-item

    courseDescriptions.push(course.description);
  });

  coursesHtml += "</div>"; // Close courses-container
  coursesHtml += '<div class="courses-summary">';
  coursesHtml +=
    "Total: <strong>" +
    courses.length +
    "</strong> cours avec absence non justifiée";
  coursesHtml += "</div>";

  listEl.innerHTML = coursesHtml;
  listEl.style.display = "block";
  placeholderEl.style.display = "none";

  // Show recap section
  document.getElementById("absence_recap").style.display = "block";

  // Re-enable the submit button when courses are found
  var submitButton = document.querySelector(".submit-btn");
  if (submitButton) {
    submitButton.disabled = false;
    submitButton.style.opacity = "1";
    submitButton.style.cursor = "pointer";
    submitButton.title = "";
  }

  // Set the hidden field value
  hiddenEl.value = courseDescriptions.join("; ");

  // Initialize all course items as selected
  setTimeout(function () {
    courses.forEach(function (course, index) {
      var courseItem = document.querySelector(
        '[data-course-id="' + index + '"]'
      );
      if (courseItem) {
        courseItem.classList.add("selected");
      }
    });

    // Update recap after initialization
    updateAbsenceRecap();
  }, 10);
}

// Function to toggle course selection
// Function to toggle course selection - DISABLED (courses are auto-selected and cannot be unselected)
function toggleCourse(index) {
  // Courses are automatically selected and cannot be unselected
  // This function is disabled to prevent deselection of absences
  return false;
}

// Function to update selected courses from button states
function updateSelectedCoursesFromButtons() {
  var selectedCourses = [];
  var checkboxes = document.querySelectorAll('[id^="course_"]');

  checkboxes.forEach(function (checkbox) {
    if (checkbox.checked) {
      var index = checkbox.id.replace("course_", "");
      var courseItem = document.querySelector(
        '[data-course-id="' + index + '"]'
      );
      if (courseItem) {
        var courseTitle = courseItem
          .querySelector(".course-title")
          .textContent.trim();
        var courseDate = courseItem
          .querySelector(".course-details .info-value")
          .textContent.trim();
        selectedCourses.push(courseTitle + " - " + courseDate);
      }
    }
  });

  document.getElementById("class_involved_hidden").value =
    selectedCourses.join("; ");

  // Update statistics in hidden fields
  updateStatisticsFields();
}

// Function to update statistics in hidden form fields
function updateStatisticsFields() {
  var stats = calculateAbsenceStats();

  // Create or update hidden fields for statistics
  updateHiddenField("absence_stats_hours", stats.totalHours.toFixed(1));
  updateHiddenField("absence_stats_halfdays", stats.halfDays.toFixed(1));
  updateHiddenField("absence_stats_evaluations", stats.evaluations.toString());
  updateHiddenField(
    "absence_stats_course_types",
    JSON.stringify(stats.courseTypes)
  );
  updateHiddenField(
    "absence_stats_evaluation_details",
    JSON.stringify(stats.evaluationDetails)
  );
}

// Helper function to create or update hidden form fields
function updateHiddenField(name, value) {
  var field = document.getElementById(name);
  if (!field) {
    field = document.createElement("input");
    field.type = "hidden";
    field.id = name;
    field.name = name;
    document.querySelector("form").appendChild(field);
  }
  field.value = value;
}

// Function to update selected courses in hidden field
function updateSelectedCourses(courses) {
  var selectedCourses = [];

  courses.forEach(function (course, index) {
    var checkbox = document.getElementById("course_" + index);
    if (checkbox && checkbox.checked) {
      selectedCourses.push(course.description);
    }
  });

  document.getElementById("class_involved_hidden").value =
    selectedCourses.join("; ");
}

// Function to calculate time difference in hours
function calculateHoursBetween(startTime, endTime) {
  var start = new Date("1970-01-01T" + startTime + ":00");
  var end = new Date("1970-01-01T" + endTime + ":00");
  return (end - start) / (1000 * 60 * 60); // Convert milliseconds to hours
}

// Function to calculate absence recap statistics
function calculateAbsenceStats() {
  var stats = {
    totalHours: 0,
    halfDays: 0,
    evaluations: 0,
    courseTypes: {},
    evaluationDetails: [],
  };

  var selectedCourses = [];
  var checkboxes = document.querySelectorAll('[id^="course_"]');

  checkboxes.forEach(function (checkbox) {
    if (checkbox.checked) {
      var index = parseInt(checkbox.id.replace("course_", ""));
      if (coursesData[index]) {
        var course = coursesData[index];
        selectedCourses.push(course);

        // Calculate hours
        var hours = calculateHoursBetween(course.start_time, course.end_time);
        stats.totalHours += hours;

        // Count half-days (assuming 4+ hours = half day)
        if (hours >= 4) {
          stats.halfDays += 0.5;
        } else if (hours >= 2) {
          stats.halfDays += 0.25;
        }

        // Count course types
        var courseType = course.course_type || "Non spécifié";
        if (stats.courseTypes[courseType]) {
          stats.courseTypes[courseType]++;
        } else {
          stats.courseTypes[courseType] = 1;
        }

        // Count evaluations and store details
        if (course.is_evaluation) {
          stats.evaluations++;
          stats.evaluationDetails.push({
            resource_label: course.resource_label || "Cours non spécifié",
            resource_code: course.resource_code,
            course_type: courseType,
            course_date: course.course_date,
            start_time: course.start_time,
            end_time: course.end_time,
            teacher: course.teacher,
            room: course.room,
          });
        }
      }
    }
  });

  return stats;
}

// Function to update absence recap display
function updateAbsenceRecap() {
  var recapElement = document.getElementById("absence_recap");
  if (!recapElement) return;

  var stats = calculateAbsenceStats();

  // Always update hidden fields with statistics data, even if 0
  document.getElementById("absence_stats_hours").value =
    stats.totalHours.toFixed(1);
  document.getElementById("absence_stats_halfdays").value =
    stats.halfDays.toFixed(1);
  document.getElementById("absence_stats_evaluations").value =
    stats.evaluations;
  document.getElementById("absence_stats_course_types").value = JSON.stringify(
    stats.courseTypes
  );
  document.getElementById("absence_stats_evaluation_details").value =
    JSON.stringify(stats.evaluationDetails);

  var recapHtml =
    '<div class="recap-title">📊 Récapitulatif des absences sélectionnées</div>';

  if (stats.totalHours === 0) {
    recapHtml += '<div class="recap-empty">Aucune absence sélectionnée</div>';
  } else {
    recapHtml += '<div class="recap-stats">';

    // Total hours
    recapHtml += '<div class="recap-item">';
    recapHtml +=
      '<span class="recap-label">⏱️ Nombre total d\'heures :</span> ';
    recapHtml +=
      '<span class="recap-value">' + stats.totalHours.toFixed(1) + "h</span>";
    recapHtml += "</div>";

    // Half days
    if (stats.halfDays > 0) {
      recapHtml += '<div class="recap-item">';
      recapHtml += '<span class="recap-label">📅 Demi-journées :</span> ';
      recapHtml +=
        '<span class="recap-value">' + stats.halfDays.toFixed(1) + "</span>";
      recapHtml += "</div>";
    }

    // Course types
    recapHtml += '<div class="recap-item">';
    recapHtml += '<span class="recap-label">📚 Types de cours :</span>';
    recapHtml += '<div class="course-types-list">';
    Object.keys(stats.courseTypes).forEach(function (type) {
      recapHtml +=
        '<span class="course-type-badge">' +
        type +
        " (" +
        stats.courseTypes[type] +
        ")</span>";
    });
    recapHtml += "</div>";
    recapHtml += "</div>";

    // Evaluations
    if (stats.evaluations > 0) {
      recapHtml += '<div class="recap-item evaluation-alert">';
      recapHtml += '<span class="recap-label">⚠️ Évaluations :</span> ';
      recapHtml += '<span class="recap-value">' + stats.evaluations + "</span>";
      recapHtml += "</div>";
    }

    recapHtml += "</div>";
  }

  recapElement.innerHTML = recapHtml;
}

// Function to show error message
function showCoursesError(message) {
  var placeholderEl = document.getElementById("courses_placeholder");
  placeholderEl.innerHTML = message;
  placeholderEl.style.display = "block";
  placeholderEl.style.color = "#dc3545";
  document.getElementById("courses_list").style.display = "none";
  document.getElementById("absence_recap").style.display = "none";
  document.getElementById("class_involved_hidden").value = "";
  // Reset statistics hidden fields
  document.getElementById("absence_stats_hours").value = "0";
  document.getElementById("absence_stats_halfdays").value = "0";
  document.getElementById("absence_stats_evaluations").value = "0";
  document.getElementById("absence_stats_course_types").value = "{}";
  document.getElementById("absence_stats_evaluation_details").value = "[]";
}

// Function to check 48h delay warning
function checkSubmissionDelay() {
  var warningContainer = document.getElementById("delay_warning_container");
  if (!warningContainer) {
    // Create the warning container if it doesn't exist
    var main = document.querySelector("main");
    if (main) {
      warningContainer = document.createElement("div");
      warningContainer.id = "delay_warning_container";
      warningContainer.style.marginBottom = "20px";
      // Insert after the page title
      var pageTitle = document.querySelector(".page-title");
      if (pageTitle && pageTitle.nextSibling) {
        main.insertBefore(warningContainer, pageTitle.nextSibling);
      } else {
        main.insertBefore(warningContainer, main.firstChild);
      }
    }
  }

  // Make AJAX request to check delay
  var xhr = new XMLHttpRequest();
  var url =
    "../../Presenter/check_proof_submission_delay.php?student_id=" +
    (window.studentId || 1);

  xhr.open("GET", url, true);
  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4 && xhr.status === 200) {
      try {
        var response = JSON.parse(xhr.responseText);

        if (
          response.success &&
          response.show_warning &&
          response.warning_message
        ) {
          displayDelayWarning(response.warning_message);
        } else {
          // Hide warning if not needed
          if (warningContainer) {
            warningContainer.style.display = "none";
          }
        }
      } catch (e) {
        console.error("Error parsing delay check response:", e);
      }
    }
  };

  xhr.onerror = function () {
    console.error("Network error while checking submission delay");
  };

  xhr.send();
}

// Function to display delay warning
function displayDelayWarning(message) {
  var warningContainer = document.getElementById("delay_warning_container");
  if (!warningContainer) return;

  warningContainer.innerHTML =
    '<div style="background-color: #fff3cd; color: #856404; padding: 20px; border: 2px solid #ffc107; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">' +
    '<div style="display: flex; align-items: start; gap: 15px;">' +
    '<div style="font-size: 32px; flex-shrink: 0;">⚠️</div>' +
    '<div style="flex-grow: 1;">' +
    message +
    "</div>" +
    "</div>" +
    "</div>";

  warningContainer.style.display = "block";
}

window.addEventListener("DOMContentLoaded", function () {
  // Check submission delay on page load
  checkSubmissionDelay();

  // Si nous sommes en mode édition et que les dates sont déjà remplies, charger les cours automatiquement
  if (window.isEditing) {
    var dateStart = document.getElementById("datetime_start").value;
    var dateEnd = document.getElementById("datetime_end").value;
    if (dateStart && dateEnd) {
      fetchAbsences();
    }
  }

  document
    .getElementById("datetime_start")
    .addEventListener("change", function () {
      var dateEnd = document.getElementById("datetime_end");
      if (dateEnd.value) {
        if (validateDates()) {
          fetchAbsences();
        }
      }
      dateEnd.min = this.value;
    });

  document
    .getElementById("datetime_end")
    .addEventListener("change", function () {
      if (this.value) {
        if (validateDates()) {
          fetchAbsences();
        }
      }
    });

  // Remplacer l'ancien écouteur de fichier par le nouveau pour fichiers multiples
  const fileInput = document.getElementById('proof_files');
  if (fileInput) {
      fileInput.addEventListener('change', handleFileSelection);
  }

  // Validate dates, file size, and selected courses on form submission
  document.querySelector("form").addEventListener("submit", function (e) {
    var classInvolvedValue = document.getElementById(
      "class_involved_hidden"
    ).value;

    if (!validateDates()) {
      e.preventDefault();
      return;
    }

    // Vérifier qu'au moins un fichier est sélectionné si désiré
    // (ou permettre 0 fichier si c'est acceptable)
    if (selectedFiles.length === 0) {
        const confirmSubmit = confirm(
            "Aucun fichier justificatif n'a été sélectionné. " +
            "Voulez-vous quand même soumettre votre demande ?"
        );
        if (!confirmSubmit) {
            e.preventDefault();
            return;
        }
    }

    // Check if any courses are selected
    if (!classInvolvedValue || classInvolvedValue.trim() === "") {
      e.preventDefault();
      alert(
        "Veuillez sélectionner les dates pour voir les absences concernées avant de soumettre le formulaire."
      );
      return;
    }
  });
});
