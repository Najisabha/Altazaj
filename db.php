<?php
// DB_HOST="localhost"
// DB_USER="root"
// DB_PASS=""
// DB_NAME="u970108170_al_tazaj"

$conn = new mysqli('localhost', 'root', '', 'u970108170_al_tazaj');

if ($conn->connect_errno) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
