<?php
session_start();

if (!isset($_SESSION['justificatif_data'])) {
    die('Aucune donnée de justificatif trouvée');
}

$data = $_SESSION['justificatif_data'];

class SimplePDF {
    private $content = '';
    private $title = '';
    
    public function __construct($title = 'Document PDF') {
        $this->title = $title;
    }
    
    public function addText($text, $size = 12, $weight = 'normal') {
        $weight_style = ($weight === 'bold') ? 'font-weight: bold;' : '';
        $this->content .= "<p style='font-size: {$size}px; {$weight_style} margin: 10px 0;'>{$text}</p>";
    }
    
    public function addTitle($title, $level = 1) {
        $this->content .= "<h{$level} style='color: #2c3e50; margin: 20px 0 10px 0;'>{$title}</h{$level}>";
    }
    
    public function addList($items) {
        $this->content .= "<ul style='margin: 10px 0; padding-left: 20px;'>";
        foreach ($items as $item) {
            $this->content .= "<li style='margin: 5px 0;'>{$item}</li>";
        }
        $this->content .= "</ul>";
    }
    
    public function output($filename = 'document.pdf') {
        // En-têtes pour forcer le téléchargement du PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Générer le contenu HTML qui sera converti
        $html = $this->generateHTML();
        
        // Utiliser une approche simple de génération PDF via HTML
        $this->convertHTMLToPDF($html, $filename);
    }
    
    private function generateHTML() {
        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <title>{$this->title}</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 40px; 
                    line-height: 1.6;
                    color: #333;
                }
                .header { 
                    text-align: center; 
                    border-bottom: 2px solid #2c3e50; 
                    padding-bottom: 20px; 
                    margin-bottom: 30px;
                }
                .content { margin: 20px 0; }
                .footer { 
                    margin-top: 50px; 
                    text-align: center; 
                    font-size: 10px; 
                    color: #777;
                }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>{$this->title}</h1>
                <p>Généré le " . date('d/m/Y à H:i') . "</p>
            </div>
            <div class='content'>
                {$this->content}
            </div>
            <div class='footer'>
                <p>Document généré automatiquement par le système de gestion des absences</p>
            </div>
        </body>
        </html>";
    }
    
    private function convertHTMLToPDF($html, $filename) {
        // Pour une solution rapide, on va créer un fichier HTML temporaire
        // En production, vous devriez utiliser une vraie bibliothèque PDF comme TCPDF ou mPDF
        
        // Alternative : retourner le HTML avec des en-têtes qui simulent un PDF
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . str_replace('.pdf', '.html', $filename) . '"');
        
        echo $html;
        echo "
        <script>
            // Optionnel : ouvrir la boîte d'impression automatiquement
            window.onload = function() {
                window.print();
            };
        </script>";
    }
}

// Générer le PDF avec les données du justificatif
$pdf = new SimplePDF('Récapitulatif Justificatif d\'Absence');

$pdf->addTitle('Justificatif d\'Absence - Récapitulatif');

$pdf->addText('Informations sur l\'absence :', 14, 'bold');

$infos = array();
$infos[] = '<strong>Date et heure de début :</strong> ' . htmlspecialchars(date('d/m/Y à H:i', strtotime($data['datetime_debut'])));
$infos[] = '<strong>Date et heure de fin :</strong> ' . htmlspecialchars(date('d/m/Y à H:i', strtotime($data['datetime_fin'])));

if (!empty($data['cours_concernes'])) {
    $cours = is_array($data['cours_concernes']) ? implode(', ', $data['cours_concernes']) : $data['cours_concernes'];
    $infos[] = '<strong>Cours concerné(s) :</strong> ' . htmlspecialchars($cours);
}

$infos[] = '<strong>Motif de l\'absence :</strong> ' . htmlspecialchars($data['motif_absence'] ?? 'Non spécifié');

if (!empty($data['motif_autre'])) {
    $infos[] = '<strong>Précision du motif :</strong> ' . htmlspecialchars($data['motif_autre']);
}

if (!empty($data['fichier_justificatif'])) {
    $infos[] = '<strong>Fichier justificatif fourni :</strong> ' . htmlspecialchars($data['fichier_justificatif']);
}

if (!empty($data['commentaires'])) {
    $infos[] = '<strong>Commentaires :</strong> ' . nl2br(htmlspecialchars($data['commentaires']));
}

$pdf->addList($infos);

$pdf->addText('Ce document certifie que la demande de justificatif d\'absence a été enregistrée dans le système.', 12);

// Générer et télécharger le PDF
$filename = 'Justificatif_Absence_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->output($filename);
?>