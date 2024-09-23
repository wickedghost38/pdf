<?php
require_once('vendor/autoload.php');

use setasign\Fpdi\Fpdi;

//calls the function to combine the pdfs
function combinePDFs($files) {
    $pdf = new FPDI();
//checks pdfs and add numbers to page
    foreach ($files as $file) {
        $pageCount = $pdf->setSourceFile($file);
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
        }
    }
    //combines the pdfs
    $pdf->Output('D', 'combined.pdf'); 
}
//size order from top to bottom
$pageSizes = [
    'A9' => [37, 52],
    'A8' => [52, 74],
    'A7' => [74, 105],
    'A6' => [105, 148],
    'A5' => [148, 210],
    'A4' => [210, 297],
    'A3' => [297, 420],
    'A2' => [420, 594],
    'A1' => [594, 841],
    'A0' => [841, 1189]
];
//saves files before deleting them
$uploadedFiles = [];
foreach ($_FILES['pdf_files']['tmp_name'] as $key => $tmpName) {
    $uploadedFiles[] = $tmpName;
}

if (!empty($uploadedFiles)) {
    combinePDFs($uploadedFiles);
    // Delete the temporary files after combining
    foreach ($uploadedFiles as $file) {
        unlink($file);
    }
} else {
    echo "No files uploaded.";
}

