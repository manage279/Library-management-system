
<?php

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function respond($status, $data)
{
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

try {

    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException(
            'MySQL connection variable $conn is not available.'
        );
    }

    $conn->set_charset('utf8mb4');

    $method = $_SERVER['REQUEST_METHOD'];

    // GET: List and search students
    if ($method === 'GET') {

        $search = trim($_GET['search'] ?? '');

        if ($search !== '') {

            $stmt = $conn->prepare(
                "SELECT id, name, roll_no, email, phone, created_at
                 FROM students
                 WHERE name LIKE ? OR roll_no LIKE ?
                 ORDER BY id DESC"
            );

            $term = '%' . $search . '%';
            $stmt->bind_param('ss', $term, $term);
            $stmt->execute();

            $result = $stmt->get_result();

        } else {

            $result = $conn->query(
                "SELECT id, name, roll_no, email, phone, created_at
                 FROM students
                 ORDER BY id DESC"
            );
        }

        $students = [];

        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }

        respond(200, $students);
    }

    // Read JSON request body for write operations
    if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        header('Allow: GET, POST, PUT, DELETE');
        respond(405, ['error' => 'Method not allowed']);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        respond(400, ['error' => 'Invalid JSON data']);
    }

    // POST: Register a student/member
    if ($method === 'POST') {

        $name = trim($data['name'] ?? '');
        $roll = trim($data['roll_no'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');

        if ($name === '' || $roll === '') {
            respond(422, [
                'error' => 'Name and roll number are required'
            ]);
        }

        if (
            ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) ||
            ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone))
        ) {
            respond(422, [
                'error' => 'Please check the email address or phone number'
            ]);
        }

        $stmt = $conn->prepare(
            "INSERT INTO students (name, roll_no, email, phone)
             VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''))"
        );

        $stmt->bind_param('ssss', $name, $roll, $email, $phone);
        $stmt->execute();

        respond(201, [
            'success' => true,
            'message' => 'Student registered successfully',
            'id' => $conn->insert_id
        ]);
    }

    // PUT: Update a student/member
    if ($method === 'PUT') {

        $id = filter_var(
            $data['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $name = trim($data['name'] ?? '');
        $roll = trim($data['roll_no'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');

        if (!$id || $name === '' || $roll === '') {
            respond(422, [
                'error' => 'Valid ID, name and roll number are required'
            ]);
        }

        if (
            ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) ||
            ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone))
        ) {
            respond(422, [
                'error' => 'Please check the email address or phone number'
            ]);
        }

        $stmt = $conn->prepare(
            "UPDATE students
             SET name = ?, roll_no = ?,
                 email = NULLIF(?, ''),
                 phone = NULLIF(?, '')
             WHERE id = ?"
        );

        $stmt->bind_param(
            'ssssi',
            $name,
            $roll,
            $email,
            $phone,
            $id
        );

        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            $check = $conn->prepare(
                "SELECT id FROM students WHERE id = ?"
            );
            $check->bind_param('i', $id);
            $check->execute();

            if (!$check->get_result()->fetch_assoc()) {
                respond(404, ['error' => 'Student not found']);
            }
        }

        respond(200, [
            'success' => true,
            'message' => 'Student updated successfully'
        ]);
    }

    // DELETE: Prevent deletion while a book is issued
    if ($method === 'DELETE') {

        $id = filter_var(
            $data['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id) {
            respond(422, [
                'error' => 'Valid student ID is required'
            ]);
        }

        $stmt = $conn->prepare(
            "DELETE FROM students
             WHERE id = ?
             AND NOT EXISTS (
                 SELECT 1 FROM issued_books
                 WHERE student_id = ?
                 AND status = 'issued'
             )"
        );

        $stmt->bind_param('ii', $id, $id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            respond(409, [
                'error' => 'Student not found or has an issued book'
            ]);
        }

        respond(200, [
            'success' => true,
            'message' => 'Student deleted successfully'
        ]);
    }

} catch (Throwable $e) {

    error_log('Students API error: ' . $e->getMessage());

    respond(500, [
        'error' => 'Server error. Check Render logs and database configuration.'
    ]);
}
