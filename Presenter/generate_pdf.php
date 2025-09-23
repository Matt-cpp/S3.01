<?php
require_once('../vendor/autoload.php'); // Use Composer autoloader instead

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

$pdf->SetHeaderData('', 0, 'Document Title', 'Generated on ' . date('Y-m-d H:i:s'));

// Set footer
$pdf->setFooterData();

// Add a page
$pdf->AddPage();

// Set font for title
$pdf->SetFont('helvetica', 'B', 20);
$pdf->Cell(0, 15, 'My PDF Document', 0, 1, 'C');

// Add some space
$pdf->Ln(10);

// Set font for normal text
$pdf->SetFont('helvetica', '', 12);

// Add paragraph
$pdf->writeHTML('<h2>Introduction</h2>');
$pdf->writeHTML('<p>This is a paragraph with <strong>bold text</strong> and <em>italic text</em>.</p>');

// Or use Cell method for simple text
$pdf->Cell(0, 10, 'This is a simple text line', 0, 1);

// Add image from file
// Image(file, x, y, width, height, type, link, align, resize, dpi, palign, ismask, imgmask, border)


// Add space after image
$pdf->Ln(60);

// Or add image with HTML
$pdf->writeHTML('<img src="../View/img/UPHF.png" width="200" height="150" />');

// Create a table
$html = '
<table border="1" cellpadding="5">
    <tr style="background-color:#cccccc;">
        <th>Name</th>
        <th>Age</th>
        <th>City</th>
    </tr>
    <tr>
        <td>John Doe</td>
        <td>30</td>
        <td>New York</td>
    </tr>
    <tr>
        <td>Jane Smith</td>
        <td>25</td>
        <td>London</td>
    </tr>
</table>';

$pdf->writeHTML($html);

// Close and output PDF document
// I = send to browser inline
// D = download
// F = save to file
// S = return as string

// Download the PDF
$pdf->Output('document.pdf', 'D');

// Or save to file
// $pdf->Output('/path/to/save/document.pdf', 'F');

// Or display in browser
// $pdf->Output('document.pdf', 'I');