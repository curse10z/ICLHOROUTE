<?php
session_start();

// Auth check
if (!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

$base    = __DIR__;
$file    = $_GET['file'] ?? '';
$download = isset($_GET['download']) && $_GET['download'] === '1';

// Sanitize: only allow paths inside /uploads/
$file    = ltrim(str_replace('\\', '/', $file), '/');
if (!preg_match('#^uploads/[^/]+$#', $file)) {
    http_response_code(400);
    exit('Invalid file path');
}

$absPath = $base . '/' . $file;
if (!file_exists($absPath) || !is_file($absPath)) {
    http_response_code(404);
    exit('File not found');
}

// MIME detection
$ext  = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'svg'  => 'image/svg+xml',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'txt'  => 'text/plain',
    'zip'  => 'application/zip',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

$fileName = basename($absPath);
$fileSize = filesize($absPath);

header('Content-Type: ' . $mime);
header('Content-Length: ' . $fileSize);
header('Cache-Control: public, max-age=3600');
header('X-Frame-Options: SAMEORIGIN');
header('Access-Control-Allow-Origin: *');

if ($download) {
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
} else {
    header('Content-Disposition: inline; filename="' . $fileName . '"');
}

readfile($absPath);
exit();
