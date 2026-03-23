// File handler for proof editing
// Note: FILE_CONFIG is already defined in proof_submit.js
let existingFilesToDelete = [];
let newFilesToAdd = [];
let hiddenInputs = []; // Track hidden file inputs

//Marks an existing file for deletion
window.toggleDeleteExistingFile = function (checkbox, index) {
  if (checkbox.checked) {
    if (!existingFilesToDelete.includes(index)) {
      existingFilesToDelete.push(index);
    }
  } else {
    existingFilesToDelete = existingFilesToDelete.filter((i) => i !== index);
  }
  updateFileSummary();
};

//Triggers new file selection
window.addNewFiles = function () {
  // Create a new hidden file input for this selection
  const hiddenInput = document.createElement("input");
  hiddenInput.type = "file";
  hiddenInput.name = "proof_files[]";
  hiddenInput.multiple = true;
  hiddenInput.accept = ".pdf,.jpg,.jpeg,.png,.doc,.docx,.gif";
  hiddenInput.style.display = "none";

  // Add change event listener
  hiddenInput.addEventListener("change", function (e) {
    handleNewFileSelection(e, hiddenInput);
  });

  // Add to form
  const form = document.querySelector("form");
  form.appendChild(hiddenInput);

  // Trigger file selection
  hiddenInput.click();
};

//Handles adding new files
function handleNewFileSelection(event, hiddenInput) {
  const files = Array.from(event.target.files);

  console.log("Files selected from input:", files.length);

  if (files.length === 0) {
    // Remove the hidden input if no files selected
    if (hiddenInput.parentNode) {
      hiddenInput.parentNode.removeChild(hiddenInput);
    }
    return;
  }

  // Keep track of this hidden input
  hiddenInputs.push(hiddenInput);

  // Add new files to our display array (avoiding duplicates)
  files.forEach((newFile) => {
    const isDuplicate = newFilesToAdd.some(
      (existingFile) =>
        existingFile.name === newFile.name &&
        existingFile.size === newFile.size,
    );
    if (!isDuplicate) {
      newFilesToAdd.push({
        file: newFile,
        inputElement: hiddenInput,
      });
      console.log("Added file:", newFile.name);
    } else {
      console.log("Duplicate file skipped:", newFile.name);
    }
  });

  console.log("Total files to add:", newFilesToAdd.length);

  // Validate and display
  validateAndDisplayFiles();
}

//Removes a new file from the list
function removeNewFile(index) {
  const fileToRemove = newFilesToAdd[index];

  // Remove from array
  newFilesToAdd.splice(index, 1);

  // If this was the only file in its input, remove the input
  if (fileToRemove && fileToRemove.inputElement) {
    const inputElement = fileToRemove.inputElement;
    const filesInThisInput = newFilesToAdd.filter(
      (f) => f.inputElement === inputElement,
    );

    if (filesInThisInput.length === 0) {
      // Remove the hidden input from DOM
      if (inputElement.parentNode) {
        inputElement.parentNode.removeChild(inputElement);
      }
      // Remove from tracking array
      hiddenInputs = hiddenInputs.filter((input) => input !== inputElement);
    }
  }

  console.log("Removed file, remaining:", newFilesToAdd.length);
  validateAndDisplayFiles();
}

//Validates and displays all files
function validateAndDisplayFiles() {
  const newFilesContainer = document.getElementById("new-files-container");
  const warningDiv = document.getElementById("file_size_warning");

  // Calculate total size
  let errors = [];
  let totalSize = 0;

  // Size of existing files (not marked for deletion)
  const existingFileElements = document.querySelectorAll(".existing-file-item");
  existingFileElements.forEach((el, index) => {
    const checkbox = el.querySelector('input[type="checkbox"]');
    if (!checkbox || !checkbox.checked) {
      const sizeAttr = el.dataset.fileSize;
      if (sizeAttr) {
        totalSize += parseInt(sizeAttr);
      }
    }
  });

  // Validate new files
  const validNewFiles = [];
  newFilesToAdd.forEach((fileObj) => {
    const file = fileObj.file;

    // Check extension
    const extension = file.name.split(".").pop().toLowerCase();
    if (!FILE_CONFIG.allowedExtensions.includes(extension)) {
      errors.push(`${file.name} : format non autorisé`);
      return;
    }

    // Check size
    if (file.size === 0) {
      errors.push(`${file.name} : fichier vide`);
      return;
    }

    if (file.size > FILE_CONFIG.maxFileSize) {
      errors.push(`${file.name} : dépasse 5MB`);
      return;
    }

    totalSize += file.size;
    validNewFiles.push(fileObj);
  });

  // Replace with valid files
  newFilesToAdd = validNewFiles;

  // Check total size
  if (totalSize > FILE_CONFIG.maxTotalSize) {
    errors.push(`Taille totale (${formatFileSize(totalSize)}) dépasse 20MB`);
  }

  // Display errors
  if (errors.length > 0) {
    warningDiv.innerHTML =
      "<strong>⚠️ Erreurs détectées :</strong><br>" + errors.join("<br>");
    warningDiv.style.display = "block";
  } else {
    warningDiv.style.display = "none";
  }

  // Display new files
  if (newFilesToAdd.length > 0) {
    let html =
      '<div style="margin-top: 15px; padding: 10px; background: #e7f3ff; border: 1px solid #0066cc; border-radius: 5px;">';
    html +=
      '<strong style="color: #0066cc;">📎 Nouveaux fichiers à ajouter :</strong>';
    html += '<div style="margin-top: 10px;">';

    newFilesToAdd.forEach((fileObj, index) => {
      const file = fileObj.file;
      const icon = getFileIcon(file.name.split(".").pop().toLowerCase());
      html += `
                <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: white; border-radius: 4px; margin-bottom: 6px;">
                    <span style="font-size: 24px;">${icon}</span>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 500; word-break: break-all; font-size: 14px;">${escapeHtml(
                          file.name,
                        )}</div>
                        <div style="font-size: 12px; color: #666;">${formatFileSize(
                          file.size,
                        )}</div>
                    </div>
                    <button type="button" onclick="removeNewFile(${index})" style="padding: 6px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; white-space: nowrap; font-size: 13px;">
                        Retirer
                    </button>
                </div>
            `;
    });

    html += "</div></div>";
    newFilesContainer.innerHTML = html;
    newFilesContainer.style.display = "block";
  } else {
    newFilesContainer.style.display = "none";
  }

  updateFileSummary();
}

//Updates the file summary
function updateFileSummary() {
  const summaryDiv = document.getElementById("files-summary");
  if (!summaryDiv) return;

  // Count files
  const existingCount = document.querySelectorAll(".existing-file-item").length;
  const toDeleteCount = existingFilesToDelete.length;
  const toAddCount = newFilesToAdd.length;
  const finalCount = existingCount - toDeleteCount + toAddCount;

  // Calculate total size
  let totalSize = 0;
  document.querySelectorAll(".existing-file-item").forEach((el, index) => {
    const checkbox = el.querySelector('input[type="checkbox"]');
    if (!checkbox || !checkbox.checked) {
      const sizeAttr = el.dataset.fileSize;
      if (sizeAttr) {
        totalSize += parseInt(sizeAttr);
      }
    }
  });
  newFilesToAdd.forEach((fileObj) => {
    totalSize += fileObj.file.size;
  });

  const sizePercent = (totalSize / FILE_CONFIG.maxTotalSize) * 100;
  let sizeClass = "";
  if (sizePercent > 90) {
    sizeClass = ' style="color: #dc3545; font-weight: bold;"';
  } else if (sizePercent > 70) {
    sizeClass = ' style="color: #ffc107; font-weight: bold;"';
  }

  let html =
    '<div id="files-summary-size" style="margin-top: 15px; padding: 12px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px;">';
  html += "<strong>Résumé :</strong><br>";
  html += `Fichiers actuels : ${existingCount}`;
  if (toDeleteCount > 0) {
    html += ` <span style="color: #dc3545;">(-${toDeleteCount})</span>`;
  }
  if (toAddCount > 0) {
    html += ` <span style="color: #28a745;">(+${toAddCount})</span>`;
  }
  html += ` = <strong>${finalCount}</strong> fichier${
    finalCount > 1 ? "s" : ""
  }<br>`;
  html += `<span${sizeClass}>Taille totale : ${formatFileSize(
    totalSize,
  )} / ${formatFileSize(FILE_CONFIG.maxTotalSize)} (${sizePercent.toFixed(
    1,
  )}%)</span>`;
  html += "</div>";

  summaryDiv.innerHTML = html;
}

//Gets the icon corresponding to the file type
function getFileIcon(extension) {
  const icons = {
    pdf: "📄",
    jpg: "🖼️",
    jpeg: "🖼️",
    png: "🖼️",
    gif: "🖼️",
    doc: "📝",
    docx: "📝",
  };
  return icons[extension] || "📎";
}

//Formats the file size
function formatFileSize(bytes) {
  if (bytes === 0) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
}

//Escapes HTML to prevent XSS injections
function escapeHtml(text) {
  const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  };
  return text.replace(/[&<>"']/g, (m) => map[m]);
}

// Initialisation
document.addEventListener("DOMContentLoaded", function () {
  console.log("File edit script initialized");

  // Check before form submission
  const form = document.querySelector("form");
  if (form) {
    form.addEventListener("submit", function (e) {
      // Allow submission even with 0 files - files are optional
      console.log("=== FORM SUBMIT ===");
      console.log("Files to add:", newFilesToAdd.length);
      console.log("Hidden inputs:", hiddenInputs.length);

      // Log all hidden inputs and their files
      hiddenInputs.forEach((input, idx) => {
        console.log(`Hidden input ${idx}:`, input.files.length, "files");
        for (let i = 0; i < input.files.length; i++) {
          console.log(
            `  File: ${input.files[i].name} (${input.files[i].size} bytes)`,
          );
        }
      });

      // Count total files that will be submitted
      let totalFiles = 0;
      hiddenInputs.forEach((input) => {
        totalFiles += input.files.length;
      });
      console.log("Total files to upload:", totalFiles);
    });
  }

  // Initialize the summary
  updateFileSummary();
});
