<?php
session_start();
require_once('../vendor/autoload.php'); // Use Composer autoloader instead

date_default_timezone_set('Europe/Paris');

if (!isset($_SESSION['reason_data'])) {
    die('Aucune donnée de justificatif trouvée.');
}

$reason_data = $_SESSION['reason_data'];

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Gestion d\'absences');
$pdf->SetAuthor('Gestion d\'absences');
$pdf->SetTitle('PDF with Text and Images');
$pdf->SetSubject('Example PDF');

// Set default header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

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
$html_content .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;">';

// Créer des objets DateTime avec le fuseau horaire de Paris pour les dates de début et fin
$datetime_start = new DateTime($reason_data['datetime_start']);
$datetime_start->setTimezone(new DateTimeZone('Europe/Paris'));
$html_content .= '<tr><td><strong>Date et heure de début :</strong></td><td>' . $datetime_start->format('d/m/Y à H:i:s') . '</td></tr>';

$datetime_end = new DateTime($reason_data['datetime_end']);
$datetime_end->setTimezone(new DateTimeZone('Europe/Paris'));
$html_content .= '<tr><td><strong>Date et heure de fin :</strong></td><td>' . $datetime_end->format('d/m/Y à H:i:s') . '</td></tr>';

// Gestion des cours concernés
$cours = $reason_data['class_involved'];
if (is_array($cours)) {
    $cours_text = htmlspecialchars(implode(', ', $cours));
} else {
    $cours_text = htmlspecialchars($cours);
}
$html_content .= '<tr><td><strong>Cours concerné(s) :</strong></td><td>' . $cours_text . '</td></tr>';

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

$pdf->writeHTML($html_content);

// Ajouter une nouvelle page pour le justificatif si un fichier a été uploadé
if (!empty($reason_data['proof_file']) && !empty($reason_data['saved_file_name'])) {
    $pdf->AddPage();
    
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Justificatif fourni', 0, 1, 'C');
    $pdf->Ln(5);
    
    // File name
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Nom du fichier : ' . htmlspecialchars($reason_data['proof_file']), 0, 1, 'L');
    $pdf->Ln(5);

    // Construct absolute path to uploads directory
    $upload_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $reason_data['saved_file_name'];
    $extension = strtolower(pathinfo($reason_data['proof_file'], PATHINFO_EXTENSION));

    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
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
                $pdf->Cell(0, 10, 'Nom du fichier : ' . htmlspecialchars($reason_data['proof_file']), 0, 1, 'L');
                $pdf->Ln(5);
                
                // Use of Imagick to convert the first page to an image
                if (class_exists('Imagick')) {
                    try {
                        // Check if file exists before processing
                        if (!file_exists($upload_path)) {
                            throw new Exception("Le fichier PDF n'existe pas : " . $upload_path);
                        }
                        
                        // Check if file is readable
                        if (!is_readable($upload_path)) {
                            throw new Exception("Le fichier PDF n'est pas lisible : " . $upload_path);
                        }
                        
                        $imagick = new Imagick();
                        $imagick->setResolution(150, 150);
                        $imagick->readImage($upload_path . '[0]'); // Première page
                        $imagick->setImageFormat('jpeg');
                        $imagick->setImageCompressionQuality(85);
                        
                        $temp_image = sys_get_temp_dir() . '/pdf_preview_' . uniqid() . '.jpg';
                        $imagick->writeImage($temp_image);
                        
                        $pdf->Image($temp_image, 15, $pdf->GetY(), 180, 0, 'JPEG');
                        
                        unlink($temp_image);
                        $imagick->destroy();
                        
                    } catch (Exception $e) {
                        $pdf->SetTextColor(255, 140, 0);
                        $pdf->MultiCell(0, 10, 'Prévisualisation non disponible. Erreur : ' . $e->getMessage(), 0, 'L');
                        $pdf->MultiCell(0, 10, 'Chemin du fichier : ' . $upload_path, 0, 'L');
                        $pdf->MultiCell(0, 10, 'Le fichier a été joint avec succès à votre demande.', 0, 'L');
                        $pdf->SetTextColor(0, 0, 0);
                    }
                } else {
                    $pdf->SetTextColor(255, 140, 0);
                    $pdf->MultiCell(0, 10, 'Prévisualisation non disponible : Extension Imagick requise pour afficher le contenu des PDFs. Le fichier a été joint avec succès à votre demande.', 0, 'L');
                    $pdf->SetTextColor(0, 0, 0);
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
}

// Download the PDF
// $pdf->Output('Justificatif_recapitulatif_' . date('Y-m-d_H-i-s') . '.pdf', 'D');
$pdf->Output('Justificatif_recapitulatif_' . date('Y-m-d_H-i-s') . '.pdf', 'I');