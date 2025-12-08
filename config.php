<?php

$envPath = __DIR__ . '/.env';

// تأكد أن الملف موجود وقابل للقراءة
if (!is_readable($envPath)) {
    die('.env file not found or not readable');
}

// قراءة الملف
$env = parse_ini_file($envPath);

if ($env === false) {
    die('Error parsing .env file. Please check its syntax.');
}

// تعريف الثوابت
define('DB_HOST', $env['DB_HOST'] ?? 'localhost');
define('DB_USER', $env['DB_USER'] ?? '');
define('DB_PASS', $env['DB_PASS'] ?? '');
define('DB_NAME', $env['DB_NAME'] ?? '');
