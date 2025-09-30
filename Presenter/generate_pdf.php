<?php
session_start();
require_once('../vendor/autoload.php');
require_once('../Model/database.php');

date_default_timezone_set('Europe/Paris');

if (!isset($_SESSION['reason_data'])) {
    die('Aucune donnée de justificatif trouvée.');
}

$reason_data = $_SESSION['reason_data'];

// Retrieve student information from database
$student_info = null;
if (isset($_SESSION['id_student'])) {
    try {
        $db = Database::getInstance();
        $student_info = $db->selectOne(
            "SELECT id, identifier, last_name, first_name, middle_name, birth_date, degrees, department, email, role
             FROM users
             WHERE id = ?",
            [$_SESSION['id_student']]
        );
    } catch (Exception $e) {
        error_log("Error retrieving student information: " . $e->getMessage());
    }
}

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

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
// Image(file, x, y, width, height, type, link, align, resize, dpi, palign, ismask, imgmask, border)
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
$html_content = '<h2>Récapitulatif de votre demande :</h2>';

// Add student information warning or details
if (!$student_info) {
    $html_content .= '<div style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin-bottom: 15px; border-radius: 4px;">';
    $html_content .= '<strong style="color: #856404;">Attention :</strong> Informations de l\'étudiant non disponibles.';
    $html_content .= '</div>';
}

$html_content .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;">';

// Add student information section
if ($student_info) {
    $html_content .= '<tr><td colspan="2" style="background-color: #e9ecef; font-weight: bold; text-align: center;">INFORMATIONS DE L\'ÉTUDIANT</td></tr>';

    $html_content .= '<tr><td><strong>Nom :</strong></td><td>' . htmlspecialchars($student_info['last_name']) . '</td></tr>';
    $html_content .= '<tr><td><strong>Prénom :</strong></td><td>' . htmlspecialchars($student_info['first_name']) . '</td></tr>';

    if (!empty($student_info['middle_name'])) {
        $html_content .= '<tr><td><strong>Deuxième prénom :</strong></td><td>' . htmlspecialchars($student_info['middle_name']) . '</td></tr>';
    }

    if (!empty($student_info['department'])) {
        $html_content .= '<tr><td><strong>Département :</strong></td><td>' . htmlspecialchars($student_info['department']) . '</td></tr>';
    }

    if (!empty($student_info['degrees'])) {
        $html_content .= '<tr><td><strong>Diplôme(s) :</strong></td><td>' . htmlspecialchars($student_info['degrees']) . '</td></tr>';
    }

    if (!empty($student_info['birth_date'])) {
        $birth_date = new DateTime($student_info['birth_date']);
        $html_content .= '<tr><td><strong>Date de naissance :</strong></td><td>' . $birth_date->format('d/m/Y') . '</td></tr>';
    }

    if (!empty($student_info['email'])) {
        $html_content .= '<tr><td><strong>Email :</strong></td><td>' . htmlspecialchars($student_info['email']) . '</td></tr>';
    }

    // Add separator row for absence details
    $html_content .= '<tr><td colspan="2" style="background-color: #e9ecef; font-weight: bold; text-align: center;">DÉTAILS DE L\'ABSENCE</td></tr>';
}

// Use of the correspondind time zonz
$datetime_start = new DateTime($reason_data['datetime_start']);
$datetime_start->setTimezone(new DateTimeZone('Europe/Paris'));
$html_content .= '<tr><td><strong>Date et heure de début :</strong></td><td>' . $datetime_start->format('d/m/Y à H:i:s') . '</td></tr>';

$datetime_end = new DateTime($reason_data['datetime_end']);
$datetime_end->setTimezone(new DateTimeZone('Europe/Paris'));
$html_content .= '<tr><td><strong>Date et heure de fin :</strong></td><td>' . $datetime_end->format('d/m/Y à H:i:s') . '</td></tr>';

$html_content .= '<tr><td><strong>Motif de l\'absence :</strong></td><td>' . htmlspecialchars($reason_data['absence_reason']) . '</td></tr>';

if (!empty($reason_data['other_reason'])) {
    $html_content .= '<tr><td><strong>Précision du motif :</strong></td><td>' . htmlspecialchars($reason_data['other_reason']) . '</td></tr>';
}

$html_content .= '<tr><td><strong>Fichier justificatif :</strong></td><td>' . htmlspecialchars($reason_data['proof_file']) . '</td></tr>';

if (!empty($reason_data['comments'])) {
    $html_content .= '<tr><td><strong>Commentaires :</strong></td><td>' . nl2br(htmlspecialchars($reason_data['comments'])) . '</td></tr>';
}

$html_content .= '<tr><td><strong>Date de soumission :</strong></td><td>' . date('d/m/Y à H:i:s', strtotime($reason_data['submission_date'])) . '</td></tr>';
$html_content .= '</table>';

// Add absence statistics if available
$cours = $reason_data['class_involved'];
$stats_hours = floatval($reason_data['stats_hours'] ?? 0);
$stats_halfdays = floatval($reason_data['stats_halfdays'] ?? 0);
$stats_evaluations = intval($reason_data['stats_evaluations'] ?? 0);
$stats_course_types = json_decode($reason_data['stats_course_types'] ?? '{}', true);
$stats_evaluation_details = json_decode($reason_data['stats_evaluation_details'] ?? '[]', true);

// Show statistics section if we have hours data OR course data
if ($stats_hours > 0 || (!empty($cours) && $cours !== '')) {
    $html_content .= '<br><h3>Statistiques des absences</h3>';
    $html_content .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;">';
    $html_content .= '<tr><td colspan="2" style="background-color: #e9ecef; font-weight: bold; text-align: center;">ANALYSE DÉTAILLÉE DES ABSENCES</td></tr>';

    // Count total courses involved
    $total_courses = 0;
    if (is_array($cours)) {
        $total_courses = count($cours);
    } else {
        $courses_array = explode('; ', $cours);
        $total_courses = count(array_filter($courses_array, function($course) {
            return trim($course) !== '';
        }));
    }

    if ($total_courses > 0) {
        $html_content .= '<tr><td><strong>Total :</strong></td><td>' . $total_courses . ' cours avec absence non justifiée</td></tr>';
    }

    if ($stats_hours > 0) {
        $html_content .= '<tr><td><strong>Nombre total d\'heures :</strong></td><td>' . number_format($stats_hours, 1) . 'h</td></tr>';
    }

    if (!empty($stats_course_types)) {
        $course_types_text = '';
        $course_type_items = array();
        foreach ($stats_course_types as $type => $count) {
            $course_type_items[] = htmlspecialchars($type) . ' (' . $count . ')';
        }
        $course_types_text = implode(', ', $course_type_items);
        $html_content .= '<tr><td><strong>Types de cours :</strong></td><td>' . $course_types_text . '</td></tr>';
    }

    if ($stats_evaluations > 0) {
        $html_content .= '<tr><td><strong>Évaluations :</strong></td><td>' . $stats_evaluations . '</td></tr>';
    }

    $html_content .= '</table>';
}

$pdf->writeHTML($html_content);

$pdf->addPage();



// Add an image or PDF preview if available
if (!empty($reason_data['proof_file']) && !empty($reason_data['saved_file_name'])) {
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Justificatif fourni', 0, 1, 'C');

    // File name
    $pdf->SetFont('helvetica', '', 12);
    $pdf->MultiCell(0, 10, 'Nom du fichier : ' . htmlspecialchars($reason_data['proof_file']), 0, 'L');

    // Construct absolute path to uploads directory
    $upload_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $reason_data['saved_file_name'];
    $extension = strtolower(pathinfo($reason_data['proof_file'], PATHINFO_EXTENSION));

    if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
        $pdf->addPage();
        if (file_exists($upload_path)) {
            try {
                $pdf->Image($upload_path, 15, $pdf->GetY(), 180, 0, '', '', '', false, 300, '', false, false, 1);
            } catch (Exception $e) {
                $pdf->SetTextColor(255, 0, 0);
                $pdf->Cell(0, 10, 'Erreur lors de l\'affichage de l\'image : ' . $e->getMessage(), 0, 1, 'L');
                $pdf->SetTextColor(0, 0, 0);
            }
        } else {
            $pdf->SetTextColor(255, 0, 0);
            $pdf->Cell(0, 10, 'Fichier image non trouvé : ' . $upload_path, 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);
        }
    } elseif ($extension === 'pdf') {
        if (file_exists($upload_path)) {
            try {
                // Create a new TCPDF object to import the PDF
                $pdf->SetTextColor(0, 0, 255);
                $pdf->Cell(0, 10, 'Type de fichier : Document PDF', 0, 1, 'L');
                $pdf->SetTextColor(0, 0, 0);
                $pdf->addPage();

                if (class_exists('Imagick')) {
                    try {
                        // First, get the number of pages in the PDF
                        $imagick = new Imagick();
                        $imagick->setResolution(150, 150);
                        $imagick->readImage($upload_path);
                        $num_pages = $imagick->getNumberImages();
                        $imagick->destroy();

                        // Loop through all pages
                        for ($page = 0; $page < $num_pages; $page++) {
                            $imagick = new Imagick();
                            $imagick->setResolution(150, 150);
                            $imagick->readImage($upload_path . '[' . $page . ']');

                            $imagick->setImageFormat('jpeg');
                            $imagick->setImageCompressionQuality(90);
                            $imagick->setImageBackgroundColor('white');
                            $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                            $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

                            $temp_image = sys_get_temp_dir() . '/pdf_preview_imagick_page_' . $page . '_' . uniqid() . '.jpg';
                            $imagick->writeImage($temp_image);

                            if (file_exists($temp_image)) {
                                // Add a new page for each PDF page (except the first one which is already added)
                                if ($page > 0) {
                                    $pdf->addPage();
                                }

                                // Display the image
                                $pdf->Image($temp_image, 15, $pdf->GetY(), 180, 0, 'JPEG');
                                unlink($temp_image);
                            }
                            $imagick->destroy();
                        }

                    } catch (Exception $e) {
                        error_log('Imagick PDF conversion failed: ' . $e->getMessage());
                        $pdf->SetTextColor(255, 0, 0);
                        $pdf->Cell(0, 10, 'Erreur lors de la conversion PDF : ' . $e->getMessage(), 0, 1, 'L');
                        $pdf->SetTextColor(0, 0, 0);
                    }
                }
            } catch (Exception $e) {
                $pdf->SetTextColor(255, 0, 0);
                $pdf->Cell(0, 10, 'Erreur lors du traitement du PDF : ' . $e->getMessage(), 0, 1, 'L');
                $pdf->SetTextColor(0, 0, 0);
            }
        } else {
            $pdf->SetTextColor(255, 0, 0);
            $pdf->Cell(0, 10, 'Fichier PDF non trouvé : ' . $upload_path, 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);
        }
    } else {
        // Other file types handling
        if (file_exists($upload_path)) {
            $file_size = filesize($upload_path);
            $pdf->SetTextColor(0, 0, 255);
            $pdf->Cell(0, 10, 'Type de fichier : ' . strtoupper($extension), 0, 1, 'L');
        }
        $pdf->SetTextColor(255, 0, 0);
        $pdf->Cell(0, 10, 'Note : Ce type de fichier ne peut pas être affiché dans le PDF.', 0, 1, 'L');
        $pdf->Cell(0, 10, 'Veuillez consulter le fichier original joint à votre demande.', 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
    }
} else {
    $pdf->SetTextColor(255, 0, 0);
    $pdf->Cell(0, 10, 'Aucun fichier justificatif fourni.', 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);
}

if ($_POST['action'] === 'download_pdf_client') {
// Download the PDF
$pdf->Output('Justificatif_recapitulatif_' . date('Y-m-d_H-i-s') . '.pdf', 'D');
}
if ($_POST['action'] === 'download_pdf_server') {
    $save_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $_POST['name_file'];
    $pdf->Output($save_path, 'F');
}
// $pdf->Output('Justificatif_recapitulatif_' . date('Y-m-d_H-i-s') . '.pdf', 'I');