<?php
    require_once('vendor/autoload.php');

    $errorlogs = [
        'not pdf' => 'The uploaded file is not a PDF.',
        'loading too long' => 'The process took too long to complete.',
        'download failed' => 'No files were uploaded for processing.'
    ];

    function logError($errorType, $errorlogs) {
        $timestamp = date('Y-m-d H:i:s');
        $message = isset($errorlogs[$errorType]) ? $errorlogs[$errorType] : 'Unknown error';
        $logMessage = "{$timestamp} - {$message}\n";

        file_put_contents('errorlog.txt', $logMessage, FILE_APPEND);
    }

    function combinePDFs($files, $outputFileName) {
        $pdf = new \setasign\Fpdi\Fpdi();

        foreach ($files as $file) {
            $pageCount = $pdf->setSourceFile($file);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }
        }

        $pdf->Output('D', $outputFileName . '.pdf'); 
    }

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

    $uploadedFiles = [];
    foreach ($_FILES['pdf_files']['tmp_name'] as $key => $tmpName) {
        if (mime_content_type($tmpName) !== 'application/pdf') {
            logError('not pdf', $errorlogs);
            continue;
        }
        
        $fileHandle = fopen($tmpName, 'rb');
        $header = fread($fileHandle, 4);
        fclose($fileHandle);
        
        if ($header !== '%PDF') {
            logError('not pdf', $errorlogs);
            continue;
        }
        
        $uploadedFiles[] = $tmpName;
    }

    if (!empty($uploadedFiles)) {
        try {
            $outputFileName = isset($_POST['file_name']) ? $_POST['file_name'] : 'combined';
            combinePDFs($uploadedFiles, $outputFileName);
            foreach ($uploadedFiles as $file) {
                unlink($file);
            }
        } catch (Exception $e) {
            logError('loading too long', $errorlogs);
        }
    } else {
        echo "No files uploaded.";
        logError('download failed', $errorlogs);
    }
    