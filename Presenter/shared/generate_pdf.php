<?php

declare(strict_types=1);

/**
 * File: generate_pdf.php
 *
 * Summary PDF generator — creates a PDF summary document for an absence proof.
 * Generates a PDF containing:
 * - Student information
 * - Absence details (dates, reason, comments)
 * - Statistics (missed hours, evaluations)
 * - Preview of the attached proof document
 * Uses TCPDF for generation and Imagick for converting PDFs to images.
 * Can be downloaded by the student or sent by email.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../../vendor/autoload.php');
require_once(__DIR__ . '/../../Model/UserModel.php');
require_once(__DIR__ . '/../../Model/format_ressource.php');

use setasign\Fpdi\Tcpdf\Fpdi;

if (!isset($_SESSION['reason_data'])) {
    die('Aucune donnée de justificatif trouvée.');
}

$reasonData = $_SESSION['reason_data'];

// Retrieve student information from database
$studentInfo = null;
if (isset($_SESSION['id_student'])) {
    try {
        $userModel = new UserModel();
        $studentInfo = $userModel->getUserById((int) $_SESSION['id_student']);
    } catch (Exception $e) {
        error_log('Error retrieving student information: ' . $e->getMessage());
    }
}

// Create new PDF document (prefer FPDI when available to import attached PDF pages)
if (class_exists(Fpdi::class)) {
    $pdf = new Fpdi(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
} else {
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
}

// Set document information
$pdf->SetCreator('Gestion d\'absences');
$pdf->SetAuthor('Gestion d\'absences');
$pdf->SetTitle('Justificatif d\'absence récapitulatif non validé');
$pdf->SetSubject('Justificatif d\'absence');

// Set default header and footer fonts
$pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

$pdf->SetHeaderData('', 0, 'Justificatif d\'absence récapitulatif', 'Généré le ' . date('d/m/Y à H:i:s'));

// Set footer
$pdf->setFooterData();

// Add a page
$pdf->AddPage();

// Add image from file
$pdf->Image('../View/img/UPHF.png', 165, 0, 30, 12, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
$pdf->Image('../View/img/logoIUT.png', 148, -1, 15, 15, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);

// Set font for title
$pdf->SetFont('helvetica', 'B', 20);
// Cell(width, height, text, border, ln, align, fill, link)
$pdf->Cell(0, 0, 'Justificatif d\'absence récapitulatif', 0, 1, 'C');
$pdf->SetTextColor(255, 0, 0);
$pdf->SetFont('helvetica', 'B', 18);
$pdf->Cell(0, 0, 'En attente de validation', 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);

// Add some space
$pdf->Ln(10);

// Set font for normal text
$pdf->SetFont('helvetica', '', 12);

// Add paragraph
$htmlContent = '<h2>Récapitulatif de votre demande :</h2>';

// Add student information warning or details
if (!$studentInfo) {
    $htmlContent .= '<div style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin-bottom: 15px; border-radius: 4px;">';
    $htmlContent .= '<strong style="color: #856404;">Attention :</strong> Informations de l\'étudiant non disponibles.';
    $htmlContent .= '</div>';
}

$htmlContent .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;">';

// Add student information section
if ($studentInfo) {
    $htmlContent .= '<tr><td colspan="2" style="background-color: #e9ecef; font-weight: bold; text-align: center;">INFORMATIONS DE L\'ÉTUDIANT</td></tr>';

    $htmlContent .= '<tr><td><strong>Nom :</strong></td><td>' . htmlspecialchars($studentInfo['last_name']) . '</td></tr>';
    $htmlContent .= '<tr><td><strong>Prénom :</strong></td><td>' . htmlspecialchars($studentInfo['first_name']) . '</td></tr>';

    if (!empty($studentInfo['middle_name'])) {
        $htmlContent .= '<tr><td><strong>Deuxième prénom :</strong></td><td>' . htmlspecialchars($studentInfo['middle_name']) . '</td></tr>';
    }

    if (!empty($studentInfo['department'])) {
        $htmlContent .= '<tr><td><strong>Département :</strong></td><td>' . htmlspecialchars($studentInfo['department']) . '</td></tr>';
    }

    if (!empty($studentInfo['degrees'])) {
        $htmlContent .= '<tr><td><strong>Diplôme(s) :</strong></td><td>' . htmlspecialchars($studentInfo['degrees']) . '</td></tr>';
    }

    if (!empty($studentInfo['birth_date'])) {
        $timezone = new DateTimeZone('Europe/Paris');
        $birthDate = new DateTime($studentInfo['birth_date'], $timezone);
        $htmlContent .= '<tr><td><strong>Date de naissance :</strong></td><td>' . $birthDate->format('d/m/Y') . '</td></tr>';
    }

    if (!empty($studentInfo['email'])) {
        $htmlContent .= '<tr><td><strong>Email :</strong></td><td>' . htmlspecialchars($studentInfo['email']) . '</td></tr>';
    }

    // Separator row for absence details
    $htmlContent .= '<tr><td colspan="2" style="background-color: #e9ecef; font-weight: bold; text-align: center;">DÉTAILS DE L\'ABSENCE</td></tr>';
}

// Use the corresponding time zone
$timezone = new DateTimeZone('Europe/Paris');
$datetimeStart = new DateTime($reasonData['datetime_start'], $timezone);
$htmlContent .= '<tr><td><strong>Date et heure de début :</strong></td><td>' . $datetimeStart->format('d/m/Y') . ' à ' . $datetimeStart->format('H:i:s') . '</td></tr>';

$datetimeEnd = new DateTime($reasonData['datetime_end'], $timezone);
$htmlContent .= '<tr><td><strong>Date et heure de fin :</strong></td><td>' . $datetimeEnd->format('d/m/Y') . ' à ' . $datetimeEnd->format('H:i:s') . '</td></tr>';

$reasonLabels = [
    'maladie' => 'Maladie',
    'deces' => 'Décès dans la famille',
    'obligations_familiales' => 'Obligations familiales',
    'rdv_medical' => 'Rendez-vous médical',
    'convocation_officielle' => 'Convocation officielle (permis, TOEIC, etc.)',
    'transport' => 'Problème de transport',
    'autre' => 'Autre',
];
$reasonDisplay = $reasonLabels[$reasonData['absence_reason'] ?? ''] ?? ($reasonData['absence_reason'] ?? 'Non précisé');

$htmlContent .= '<tr><td><strong>Motif de l\'absence :</strong></td><td>' . htmlspecialchars($reasonDisplay) . '</td></tr>';

if (!empty($reasonData['other_reason'])) {
    $htmlContent .= '<tr><td><strong>Précision du motif :</strong></td><td>' . htmlspecialchars($reasonData['other_reason']) . '</td></tr>';
}

// Display uploaded files
$proofFiles = $reasonData['proof_files'] ?? [];
if (!empty($proofFiles)) {
    $filesList = '';
    foreach ($proofFiles as $file) {
        $fileSizeKb = round($file['file_size'] / 1024, 2);
        $filesList .= htmlspecialchars($file['original_name']) . ' (' . $fileSizeKb . ' Ko)<br>';
    }
    $htmlContent .= '<tr><td><strong>Fichier(s) justificatif(s) :</strong></td><td>' . $filesList . '</td></tr>';
} else {
    $htmlContent .= '<tr><td><strong>Fichier(s) justificatif(s) :</strong></td><td>Aucun fichier fourni</td></tr>';
}

if (!empty($reasonData['comments'])) {
    $htmlContent .= '<tr><td><strong>Commentaires :</strong></td><td>' . nl2br(htmlspecialchars($reasonData['comments'])) . '</td></tr>';
}

$submissionDate = new DateTime($reasonData['submission_date'], $timezone);
$htmlContent .= '<tr><td><strong>Date de soumission :</strong></td><td>' . $submissionDate->format('d/m/Y') . ' à ' . $submissionDate->format('H:i:s') . '</td></tr>';
$htmlContent .= '</table>';

// Add absence statistics if available
$courses = $reasonData['class_involved'];
$statsHours = floatval($reasonData['stats_hours'] ?? 0);
$statsHalfdays = ceil(floatval($reasonData['stats_halfdays'] ?? 0));
$statsEvaluations = intval($reasonData['stats_evaluations'] ?? 0);
$statsCourseTypes = json_decode($reasonData['stats_course_types'] ?? '{}', true);
$statsEvaluationDetails = json_decode($reasonData['stats_evaluation_details'] ?? '[]', true);

// Show statistics section if we have hours data OR course data
if ($statsHours > 0 || (!empty($courses) && $courses !== '')) {
    $htmlContent .= '<br><h3>Statistiques des absences</h3>';
    $htmlContent .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;">';
    $htmlContent .= '<tr><td colspan="2" style="background-color: #e9ecef; font-weight: bold; text-align: center;">ANALYSE DÉTAILLÉE DES ABSENCES</td></tr>';

    // Count total courses involved
    $totalCourses = 0;
    if (is_array($courses)) {
        $totalCourses = count($courses);
    } else {
        $coursesArray = explode('; ', $courses);
        $totalCourses = count(array_filter($coursesArray, function ($course) {
            return trim($course) !== '';
        }));
    }

    if ($totalCourses > 0) {
        $htmlContent .= '<tr><td><strong>Total :</strong></td><td>' . $totalCourses . ' cours </td></tr>';
    }

    if ($statsHours > 0) {
        $htmlContent .= '<tr><td><strong>Nombre total d\'heures :</strong></td><td>' . number_format($statsHours, 1) . 'h</td></tr>';
    }

    if ($statsHalfdays > 0) {
        $htmlContent .= '<tr><td><strong>Nombre de demi-journées :</strong></td><td>' . $statsHalfdays . '</td></tr>';
    }

    if (!empty($statsCourseTypes)) {
        $courseTypesText = '';
        $courseTypeItems = array();
        foreach ($statsCourseTypes as $type => $count) {
            $courseTypeItems[] = htmlspecialchars($type) . ' (' . $count . ')';
        }
        $courseTypesText = implode(', ', $courseTypeItems);
        $htmlContent .= '<tr><td><strong>Types de cours :</strong></td><td>' . $courseTypesText . '</td></tr>';
    }

    if ($statsEvaluations > 0) {
        $htmlContent .= '<tr><td><strong>Évaluations :</strong></td><td>' . $statsEvaluations . '</td></tr>';
    }

    $htmlContent .= '</table>';

    // Add details of missed evaluations if available
    if ($statsEvaluations > 0 && !empty($statsEvaluationDetails)) {
        $htmlContent .= '<br pagebreak="true" />';
        $htmlContent .= '<h3 style="color: #dc3545;">Détails des évaluations manquées</h3>';
        $htmlContent .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-color: #f5c6cb;">';
        $htmlContent .= '<tr style="background-color: #f8d7da; color: #721c24; font-weight: bold;">
                            <th width="30%">Cours</th>
                            <th width="15%">Date</th>
                            <th width="15%">Horaire</th>
                            <th width="10%">Type</th>
                            <th width="20%">Enseignant</th>
                            <th width="10%">Salle</th>
                          </tr>';

        foreach ($statsEvaluationDetails as $eval) {
            $htmlContent .= '<tr>';
            $htmlContent .= '<td>' . htmlspecialchars(formatResourceLabel($eval['resource_label'] ?? 'Non spécifié')) . '</td>';
            $htmlContent .= '<td>' . htmlspecialchars($eval['course_date'] ?? '') . '</td>';
            $htmlContent .= '<td>' . htmlspecialchars($eval['start_time'] ?? '') . '-' . htmlspecialchars($eval['end_time'] ?? '') . '</td>';
            $htmlContent .= '<td>' . htmlspecialchars($eval['course_type'] ?? '') . '</td>';
            $htmlContent .= '<td>' . htmlspecialchars($eval['teacher'] ?? '') . '</td>';
            $htmlContent .= '<td>' . htmlspecialchars($eval['room'] ?? '') . '</td>';
            $htmlContent .= '</tr>';
        }
        $htmlContent .= '</table>';
    }
}

$pdf->writeHTML($htmlContent);

// Process all uploaded files
$proofFiles = $reasonData['proof_files'] ?? [];
if (!empty($proofFiles)) {
    // Add section for uploaded files
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Justificatifs fournis (' . count($proofFiles) . ')', 0, 1, 'C');

    foreach ($proofFiles as $index => $fileInfo) {
        if ($index > 0) {
            $pdf->AddPage();
        }
        // File header
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Fichier ' . ($index + 1) . ' / ' . count($proofFiles), 0, 1, 'L');

        // File details
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(0, 5, 'Nom : ' . htmlspecialchars($fileInfo['original_name']), 0, 'L');

        // Construct absolute path to uploads directory
        // Support both 'path' and 'saved_path' keys for compatibility
        $filePath = $fileInfo['path'] ?? $fileInfo['saved_path'] ?? '';
        $uploadPath = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . $filePath;
        $extension = strtolower(pathinfo($fileInfo['original_name'], PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
            $pdf->addPage();
            if (file_exists($uploadPath)) {
                try {
                    $pdf->Image($uploadPath, 15, $pdf->GetY(), 180, 0, '', '', '', false, 300, '', false, false, 1);
                } catch (Exception $e) {
                    $pdf->SetTextColor(255, 0, 0);
                    $pdf->Cell(0, 10, 'Erreur lors de l\'affichage de l\'image : ' . $e->getMessage(), 0, 1, 'L');
                    $pdf->SetTextColor(0, 0, 0);
                }
            } else {
                $pdf->SetTextColor(255, 0, 0);
                $pdf->Cell(0, 10, 'Fichier image non trouvé : ' . $uploadPath, 0, 1, 'L');
                $pdf->SetTextColor(0, 0, 0);
            }
        } elseif ($extension === 'pdf') {
            if (file_exists($uploadPath)) {
                try {
                    $pdf->SetTextColor(0, 0, 255);
                    $pdf->Cell(0, 10, 'Type de fichier : Document PDF', 0, 1, 'L');
                    $pdf->SetTextColor(0, 0, 0);

                    if ($pdf instanceof Fpdi) {
                        $pageCount = $pdf->setSourceFile($uploadPath);
                        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                            $templateId = $pdf->importPage($pageNo);
                            $size = $pdf->getTemplateSize($templateId);
                            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                            $pdf->AddPage($orientation);
                            $pdf->useTemplate($templateId, 10, 10, 190);
                        }
                    } elseif (class_exists('Imagick')) {
                        $pdf->addPage();
                        try {
                            // First, get the number of pages in the PDF
                            $imagick = new Imagick();
                            $imagick->setResolution(150, 150);
                            $imagick->readImage($uploadPath);
                            $numPages = $imagick->getNumberImages();
                            $imagick->destroy();

                            // Loop through all pages
                            for ($page = 0; $page < $numPages; $page++) {
                                $imagick = new Imagick();
                                $imagick->setResolution(150, 150);
                                $imagick->readImage($uploadPath . '[' . $page . ']');

                                $imagick->setImageFormat('jpeg');
                                $imagick->setImageCompressionQuality(90);
                                $imagick->setImageBackgroundColor('white');
                                $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                                $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

                                $tempImage = sys_get_temp_dir() . '/pdf_preview_imagick_page_' . $page . '_' . uniqid() . '.jpg';
                                $imagick->writeImage($tempImage);

                                if (file_exists($tempImage)) {
                                    // Add a new page for each PDF page (except the first one which is already added)
                                    if ($page > 0) {
                                        $pdf->addPage();
                                    }

                                    // Display the image
                                    $pdf->Image($tempImage, 15, $pdf->GetY(), 180, 0, 'JPEG');
                                    unlink($tempImage);
                                }
                                $imagick->destroy();
                            }
                        } catch (Exception $e) {
                            error_log('Imagick PDF conversion failed: ' . $e->getMessage());
                            $pdf->SetTextColor(255, 0, 0);
                            $pdf->Cell(0, 10, 'Erreur lors de la conversion PDF : ' . $e->getMessage(), 0, 1, 'L');
                            $pdf->SetTextColor(0, 0, 0);
                        }
                    } else {
                        $pdf->SetTextColor(255, 140, 0);
                        $pdf->Cell(0, 10, 'Aperçu PDF indisponible: aucun moteur de rendu PDF (FPDI/Imagick) n\'est disponible.', 0, 1, 'L');
                        $pdf->SetTextColor(0, 0, 0);
                    }
                } catch (Exception $e) {
                    $pdf->SetTextColor(255, 0, 0);
                    $pdf->Cell(0, 10, 'Erreur lors du traitement du PDF : ' . $e->getMessage(), 0, 1, 'L');
                    $pdf->SetTextColor(0, 0, 0);
                }
            } else {
                $pdf->SetTextColor(255, 0, 0);
                $pdf->Cell(0, 10, 'Fichier PDF non trouvé : ' . $uploadPath, 0, 1, 'L');
                $pdf->SetTextColor(0, 0, 0);
            }
        } else {
            // Other file types handling
            if (file_exists($uploadPath)) {
                $pdf->SetTextColor(0, 0, 255);
                $pdf->Cell(0, 10, 'Type de fichier : ' . strtoupper($extension), 0, 1, 'L');
            }
            $pdf->SetTextColor(255, 0, 0);
            $pdf->Cell(0, 10, 'Note : Ce type de fichier ne peut pas être affiché dans le PDF.', 0, 1, 'L');
            $pdf->Cell(0, 10, 'Veuillez consulter le fichier original joint à votre demande.', 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);
        }

        // Add spacing between files
        if ($index < count($proofFiles) - 1) {
            $pdf->Ln(10);
        }
    }
} else {
    $pdf->addPage();
    $pdf->SetTextColor(255, 0, 0);
    $pdf->Cell(0, 10, 'Aucun fichier justificatif fourni.', 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);
}

if ($_POST['action'] === 'download_pdf_client') {
    // Download the PDF
    $pdf->Output('Justificatif_recapitulatif_' . date('Y-m-d_H-i-s') . '.pdf', 'D');
}
if ($_POST['action'] === 'download_pdf_server') {
    $savePath = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $_POST['name_file'];
    $pdf->Output($savePath, 'F');
}
// $pdf->Output('Justificatif_recapitulatif_' . date('Y-m-d_H-i-s') . '.pdf', 'I');