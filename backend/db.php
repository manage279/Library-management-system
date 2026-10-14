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
