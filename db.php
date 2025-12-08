<?php
require __DIR__ . "/config.php";

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات");
}

$conn->set_charset("utf8mb4");
