<?php
// Errors ko screen par display hone se rokein taaki JSON kharab na ho
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Hamesha JSON header bhejein taaki frontend ko hamesha JSON mile
header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = getenv("DB_HOST");
$port = (int) (getenv("DB_PORT") ?: 3306);
$dbname = getenv("DB_NAME");
$username = getenv("DB_USER");
$password = getenv("DB_PASSWORD");

try {
    $conn = mysqli_init();

    // Agar SSL ki zaroorat na ho toh MYSQLI_CLIENT_SSL hata sakte hain, par agar Render/Aiven par hai toh theek hai
    $connected = mysqli_real_connect(
        $conn,
        $host,
        $username,
        $password,
        $dbname,
        $port,
        null,
        MYSQLI_CLIENT_SSL
    );

    if (!$connected) {
        throw new Exception("Database connection failed.");
    }

    $conn->set_charset("utf8mb4");

} catch (Throwable $e) {
    http_response_code(500);

    error_log("Database error: " . $e->getMessage());

    // HTML/Text ke bajaye clean JSON error return karein
    echo json_encode([
        'error' => 'Database connection failed. Please check your settings.',
        'details' => $e->getMessage()
    ]);
    exit;
}
