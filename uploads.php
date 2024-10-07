<?php

require_once('vendor/autoload.php');

$errorlogs = [
    'not_a_pdf' => 'The uploaded file is not a PDF.',
    'processing_error' => 'The process took too long to complete.',
    'input_error' => 'Not enough files were uploaded for processing.',
    'unknown_error' => 'Unknown error.'
];

function getIpAddress() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function logError($errorType, $errorlogs) {
    $timestamp = date('Y-m-d H:i:s');
    $ipAddress = getIpAddress();
    $message = isset($errorlogs[$errorType]) ? $errorlogs[$errorType] : $errorlogs['unknown_error'];
    $logMessage = "{$timestamp} - {$message} - IP: {$ipAddress}\n";

    file_put_contents(__DIR__ . "/logs/error" . date("Y-m-d") . ".log", $logMessage, FILE_APPEND);

    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Max-Age: 3600");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

    $output = array();
    $output["result"] = "error";
    $output["message"] = $errorType;
    $output["feedback"] = $errorlogs[$errorType];
    http_response_code(200);
    echo json_encode($output);
    exit();
}

function logSuccess($outputFileName) {
    $timestamp = date('Y-m-d H:i:s');
    $ipAddress = getIpAddress();
    $logMessage = "{$timestamp} - Success: {$outputFileName}.pdf created by IP: {$ipAddress}\n";

    file_put_contents(__DIR__ . "/logs/success_" . date("Y-m-d") . ".log", $logMessage, FILE_APPEND);
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

if (count($_FILES['pdf_files']['tmp_name']) < 2) {
    logError('input_error', $errorlogs);
}

$uploadedFiles = [];
foreach ($_FILES['pdf_files']['tmp_name'] as $key => $tmpName) {
    if (mime_content_type($tmpName) !== 'application/pdf') {
        logError('not_a_pdf', $errorlogs);
    }

    $fileHandle = fopen($tmpName, 'rb');
    $header = fread($fileHandle, 4);
    fclose($fileHandle);

    if ($header !== '%PDF') {
        logError('not_a_pdf', $errorlogs);
    }

    $uploadedFiles[] = $tmpName;
}

if (!empty($uploadedFiles) && count($uploadedFiles) >= 2) {
    try {
        $outputFileName = isset($_POST['file_name']) ? $_POST['file_name'] : 'combined';
        combinePDFs($uploadedFiles, $outputFileName);
        foreach ($uploadedFiles as $file) {
            unlink($file);
        }
        logSuccess($outputFileName); // Log the success
    } catch (Exception $e) {
        logError('processing_error', $errorlogs);
    }
} else {
    logError('input_error', $errorlogs);
}