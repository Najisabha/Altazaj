<?php
$host = "localhost";
$user = "u970108170_al_tazaj";
$pass = "Al_tazaj!@2025";
$dbname = "u970108170_al_tazaj";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
