<?php

// ملف .env موجود في المجلد الأب لـ public_html
$envPath = dirname(__DIR__) . '/.env';

if (!file_exists($envPath)) {
    die('.env file not found');
}

if (!is_readable($envPath)) {
    die('.env file exists but is not readable');
}

// نقرأ الملف بدون أي معالجة إضافية
$env = parse_ini_file($envPath, false, INI_SCANNER_RAW);

if ($env === false) {
    die('Error parsing .env file');
}

define('DB_HOST', $env['DB_HOST'] ?? 'localhost');
define('DB_USER', $env['DB_USER'] ?? '');
define('DB_PASS', $env['DB_PASS'] ?? '');
define('DB_NAME', $env['DB_NAME'] ?? '');
