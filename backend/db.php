<?php
// Errors ko screen par display hone se rokein taaki JSON output kharab na ho
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Hamesha JSON header bhejein
header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Environment variables se details lein, agar na milein toh yahan apne values daal sakte hain
$host = getenv("DB_HOST") ?: "localhost";
$port = (int) (getenv("DB_PORT") ?: 3306);
$dbname = getenv("DB_NAME") ?: "your_database_name";
$username = getenv("DB_USER") ?: "root";
$password = getenv("DB_PASSWORD") ?: "";

try {
    // Database connection create karein (bina SSL ke taaki error na aaye)
    $conn = new mysqli($host, $username, $password, $dbname, $port);

    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }

    $conn->set_charset("utf8mb4");

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed!',
        'details' => $e->getMessage()
    ]);
    exit;
}
