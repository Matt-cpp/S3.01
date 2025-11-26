// Gestionnaire de fichiers pour l'édition de justificatifs
// Note: FILE_CONFIG is already defined in student_proof_submit.js
let existingFilesToDelete = [];
let newFilesToAdd = [];

//Marque un fichier existant pour suppression
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

//Déclenche la sélection de nouveaux fichiers
window.addNewFiles = function () {
  document.getElementById("proof_files").click();
};

//Gère l'ajout de nouveaux fichiers
function handleNewFileSelection(event) {
  const fileInput = event.target;
  const files = Array.from(fileInput.files);

  if (files.length === 0) return;

  // Ajouter les nouveaux fichiers (éviter les doublons)
  files.forEach((newFile) => {
    const isDuplicate = newFilesToAdd.some(
      (existingFile) =>
        existingFile.name === newFile.name && existingFile.size === newFile.size
    );
    if (!isDuplicate) {
      newFilesToAdd.push(newFile);
    }
  });

  // Réinitialiser l'input
  fileInput.value = "";

  // Valider et afficher
  validateAndDisplayFiles();
}

//Supprime un nouveau fichier de la liste
function removeNewFile(index) {
  newFilesToAdd.splice(index, 1);
  validateAndDisplayFiles();
}

//Valide et affiche tous les fichiers
function validateAndDisplayFiles() {
  const newFilesContainer = document.getElementById("new-files-container");
  const warningDiv = document.getElementById("file_size_warning");

  // Calculer la taille totale
  let errors = [];
  let totalSize = 0;

  // Taille des fichiers existants (non marqués pour suppression)
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

  // Valider les nouveaux fichiers
  const validNewFiles = [];
  newFilesToAdd.forEach((file) => {
    // Vérifier l'extension
    const extension = file.name.split(".").pop().toLowerCase();
    if (!FILE_CONFIG.allowedExtensions.includes(extension)) {
      errors.push(`${file.name} : format non autorisé`);
      return;
    }

    // Vérifier la taille
    if (file.size === 0) {
      errors.push(`${file.name} : fichier vide`);
      return;
    }

    if (file.size > FILE_CONFIG.maxFileSize) {
      errors.push(`${file.name} : dépasse 5MB`);
      return;
    }

    totalSize += file.size;
    validNewFiles.push(file);
  });

  // Remplacer par les fichiers valides
  newFilesToAdd = validNewFiles;

  // Vérifier la taille totale
  if (totalSize > FILE_CONFIG.maxTotalSize) {
    errors.push(`Taille totale (${formatFileSize(totalSize)}) dépasse 20MB`);
  }

  // Afficher les erreurs
  if (errors.length > 0) {
    warningDiv.innerHTML =
      "<strong>⚠️ Erreurs détectées :</strong><br>" + errors.join("<br>");
    warningDiv.style.display = "block";
  } else {
    warningDiv.style.display = "none";
  }

  // Afficher les nouveaux fichiers
  if (newFilesToAdd.length > 0) {
    let html =
      '<div style="margin-top: 15px; padding: 10px; background: #e7f3ff; border: 1px solid #0066cc; border-radius: 5px;">';
    html +=
      '<strong style="color: #0066cc;">📎 Nouveaux fichiers à ajouter :</strong>';
    html += '<div style="margin-top: 10px;">';

    newFilesToAdd.forEach((file, index) => {
      const icon = getFileIcon(file.name.split(".").pop().toLowerCase());
      html += `
                <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: white; border-radius: 4px; margin-bottom: 6px;">
                    <span style="font-size: 24px;">${icon}</span>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 500; word-break: break-all; font-size: 14px;">${escapeHtml(
                          file.name
                        )}</div>
                        <div style="font-size: 12px; color: #666;">${formatFileSize(
                          file.size
                        )}</div>
                    </div>
                    <button type="button" onclick="removeNewFile(${index})" style="padding: 6px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; white-space: nowrap; font-size: 13px;">
                        🗑️ Retirer
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

//Met à jour le résumé des fichiers
function updateFileSummary() {
  const summaryDiv = document.getElementById("files-summary");
  if (!summaryDiv) return;

  // Compter les fichiers
  const existingCount = document.querySelectorAll(".existing-file-item").length;
  const toDeleteCount = existingFilesToDelete.length;
  const toAddCount = newFilesToAdd.length;
  const finalCount = existingCount - toDeleteCount + toAddCount;

  // Calculer la taille totale
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
  newFilesToAdd.forEach((file) => {
    totalSize += file.size;
  });

  const sizePercent = (totalSize / FILE_CONFIG.maxTotalSize) * 100;
  let sizeClass = "";
  if (sizePercent > 90) {
    sizeClass = ' style="color: #dc3545; font-weight: bold;"';
  } else if (sizePercent > 70) {
    sizeClass = ' style="color: #ffc107; font-weight: bold;"';
  }

  let html =
    '<div style="margin-top: 15px; padding: 12px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px;">';
  html += "<strong>📊 Résumé :</strong><br>";
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
    totalSize
  )} / ${formatFileSize(FILE_CONFIG.maxTotalSize)} (${sizePercent.toFixed(
    1
  )}%)</span>`;
  html += "</div>";

  summaryDiv.innerHTML = html;
}

//Obtient l'icône correspondant au type de fichier
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

//Formate la taille du fichier
function formatFileSize(bytes) {
  if (bytes === 0) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
}

//Échappe le HTML pour éviter les injections XSS
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

//Synchronise le FileList de l'input avec le tableau newFilesToAdd
function syncFileInputWithNewFiles() {
  const fileInput = document.getElementById("proof_files");
  if (!fileInput) return;

  // Créer un nouveau DataTransfer pour construire le FileList
  const dataTransfer = new DataTransfer();
  newFilesToAdd.forEach((file) => {
    dataTransfer.items.add(file);
  });

  // Mettre à jour l'input
  fileInput.files = dataTransfer.files;
}

// Initialisation
document.addEventListener("DOMContentLoaded", function () {
  // Écouteur pour l'ajout de nouveaux fichiers
  const fileInput = document.getElementById("proof_files");
  if (fileInput) {
    fileInput.addEventListener("change", handleNewFileSelection);
  }

  // Synchroniser avant la soumission du formulaire
  const form = fileInput ? fileInput.closest("form") : null;
  if (form) {
    form.addEventListener("submit", function (e) {
      syncFileInputWithNewFiles();
    });
  }

  // Initialiser le résumé
  updateFileSummary();
});
