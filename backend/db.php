<?php
// db.php ke top par yeh add karein taaki errors screen par HTML ki tarah print na hon
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Aapka database connection code (e.g., mysqli connection) yahan hoga...
$host = "your_host";
$user = "your_user";
$pass = "your_password";
$dbname = "your_db";

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    // Agar connection fail ho toh JSON error return karein
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['error' => 'Database Connection Failed: ' . $e->getMessage()]);
    exit;
}
<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = getenv("DB_HOST");
$port = (int) (getenv("DB_PORT") ?: 3306);
$dbname = getenv("DB_NAME");
$username = getenv("DB_USER");
$password = getenv("DB_PASSWORD");

try {
    $conn = mysqli_init();

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

    die("Database connection failed. Please check your settings.");
}
