<?php

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['error' => 'POST method required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$action = $data['action'] ?? '';

try {

    if ($action === 'register') {

        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (
            $name === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($password) < 8
        ) {
            http_response_code(422);
            echo json_encode([
                'error' => 'Enter a valid name, email and password of at least 8 characters'
            ]);
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users (name, email, password)
             VALUES (?, ?, ?)"
        );

        $stmt->bind_param("sss", $name, $email, $hash);
        $stmt->execute();

        http_response_code(201);
        echo json_encode([
            'message' => 'Registration successful'
        ]);
        exit;
    }

    if ($action === 'login') {

        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        $stmt = $conn->prepare(
            "SELECT id, name, email, password
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode([
                'error' => 'Invalid email or password'
            ]);
            exit;
        }

        // Do not return the password hash.
        unset($user['password']);

        echo json_encode([
            'message' => 'Login successful',
            'user' => $user
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode([
        'error' => 'Action must be register or login'
    ]);

} catch (Throwable $e) {

    error_log('Authentication error: ' . $e->getMessage());

    if ($e instanceof mysqli_sql_exception && $e->getCode() === 1062) {
        http_response_code(409);
        echo json_encode([
            'error' => 'This email is already registered'
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Server error. Check server logs.'
    ]);
}
