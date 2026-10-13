<?php

header("Content-Type: application/json; charset=UTF-8");

header(
    "Access-Control-Allow-Origin: *"
);

header(
    "Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS"
);

header(
    "Access-Control-Allow-Headers: Content-Type, Authorization"
);

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit();
}

/*
|--------------------------------------------------------------------------
| Database Configuration
|--------------------------------------------------------------------------
| Credentials Render ke Environment Variables se milenge.
| Password ko code mein hard-code nahi karna hai.
|--------------------------------------------------------------------------
*/

$host = getenv("DB_HOST");
$port = (int) (getenv("DB_PORT") ?: 3306);
$dbname = getenv("DB_NAME") ?: "defaultdb";
$username = getenv("DB_USER");
$password = getenv("DB_PASSWORD");

if (!$host || !$username || !$password) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database configuration is incomplete."
    ]);

    exit();
}

/*
|--------------------------------------------------------------------------
| MySQL Connection
|--------------------------------------------------------------------------
*/

mysqli_report(MYSQLI_REPORT_OFF);

$conn = mysqli_init();

if (!$conn) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Could not initialize database connection."
    ]);

    exit();
}

/*
|--------------------------------------------------------------------------
| SSL Configuration
|--------------------------------------------------------------------------
| Aiven ke CA certificate ka path DB_SSL_CA environment variable
| mein set kiya jayega.
|--------------------------------------------------------------------------
*/

$caPath = getenv("DB_SSL_CA");

if (!$caPath || !is_readable($caPath)) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database SSL certificate is not configured."
    ]);

    exit();
}

mysqli_ssl_set(
    $conn,
    null,
    null,
    $caPath,
    null,
    null
);

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
    error_log(
        "Database connection failed: " . mysqli_connect_error()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database connection failed. Check server configuration."
    ]);

    exit();
}

$conn->set_charset("utf8mb4");

?>
