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

function validateDates() {
  var dateStart = document.getElementById("datetime_start").value;
  var dateEnd = document.getElementById("datetime_end").value;
  var currentDate = new Date();

  // Validation of the end date not being more than 48 hours in the past
  if (dateEnd) {
    var fin = new Date(dateEnd);
    var minDate = new Date(currentDate.getTime() - 48 * 60 * 60 * 1000);

    if (fin < minDate) {
      alert("La date de fin ne peut pas être antérieure à plus de 48h.");
      document.getElementById("datetime_end").value = "";
      return false;
    }
  }

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
  }

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
    "../../Presenter/get_absences.php?datetime_start=" +
    encodeURIComponent(dateStart) +
    "&datetime_end=" +
    encodeURIComponent(dateEnd) +
    "&student_id=30";

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
  document.getElementById("class_involved_hidden").value = "";
}

// Function to show/hide loading indicator
function showCoursesLoading(show) {
  document.getElementById("courses_loading").style.display = show
    ? "block"
    : "none";
  if (show) {
    document.getElementById("courses_placeholder").style.display = "none";
    document.getElementById("courses_list").style.display = "none";
  }
}

// Function to display courses
function displayCourses(courses) {
  var placeholderEl = document.getElementById("courses_placeholder");
  var listEl = document.getElementById("courses_list");
  var hiddenEl = document.getElementById("class_involved_hidden");

  if (courses.length === 0) {
    placeholderEl.innerHTML =
      "Aucune absence non justifiée trouvée pour cette période";
    placeholderEl.style.display = "block";
    placeholderEl.style.color = "#28a745";
    listEl.style.display = "none";
    hiddenEl.value = "";
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
      '" data-course-id="' +
      index +
      '">';
    coursesHtml +=
      '<input type="checkbox" id="course_' +
      index +
      '" checked style="display: none;">';
    coursesHtml +=
      '<div class="course-button" onclick="toggleCourse(' + index + ')">';

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
    coursesHtml += '<div class="selection-indicator">✓ Sélectionné</div>';
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
  }, 10);
}

// Function to toggle course selection
function toggleCourse(index) {
  var checkbox = document.getElementById("course_" + index);
  var courseItem = document.querySelector('[data-course-id="' + index + '"]');

  checkbox.checked = !checkbox.checked;

  if (checkbox.checked) {
    courseItem.classList.add("selected");
    courseItem.classList.remove("unselected");
  } else {
    courseItem.classList.add("unselected");
    courseItem.classList.remove("selected");
  }

  // Update hidden field with selected courses
  updateSelectedCoursesFromButtons();
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

// Function to show error message
function showCoursesError(message) {
  var placeholderEl = document.getElementById("courses_placeholder");
  placeholderEl.innerHTML = message;
  placeholderEl.style.display = "block";
  placeholderEl.style.color = "#dc3545";
  document.getElementById("courses_list").style.display = "none";
  document.getElementById("class_involved_hidden").value = "";
}

window.addEventListener("DOMContentLoaded", function () {
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

  // Validate dates on form submission
  document.querySelector("form").addEventListener("submit", function (e) {
    if (!validateDates()) {
      e.preventDefault();
    }
  });
});
