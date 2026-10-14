<?php

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

try {

    // GET: Display and search students
    if ($method === 'GET') {

        $search = trim($_GET['search'] ?? '');

        if ($search !== '') {

            $stmt = $conn->prepare(
                "SELECT id, name, roll_no, email, phone, created_at
                 FROM students
                 WHERE name LIKE ? OR roll_no LIKE ?
                 ORDER BY id DESC"
            );

            $term = "%{$search}%";
            $stmt->bind_param("ss", $term, $term);
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

        echo json_encode($students);
        exit;
    }

    $data = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($data)) {
        http_response_code(400);

        echo json_encode([
            'error' => 'Invalid JSON data'
        ]);

        exit;
    }

    // POST: Add student
    if ($method === 'POST') {

        $name = trim($data['name'] ?? '');
        $roll = trim($data['roll_no'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');

        if ($name === '' || $roll === '') {
            http_response_code(422);

            echo json_encode([
                'error' => 'Name and roll number are required'
            ]);

            exit;
        }

        $stmt = $conn->prepare(
            "INSERT INTO students
             (name, roll_no, email, phone)
             VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''))"
        );

        $stmt->bind_param(
            "ssss",
            $name,
            $roll,
            $email,
            $phone
        );

        $stmt->execute();

        http_response_code(201);

        echo json_encode([
            'message' => 'Student added successfully',
            'id' => $conn->insert_id
        ]);

        exit;
    }

    // PUT: Update student
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
            http_response_code(422);

            echo json_encode([
                'error' => 'Valid ID, name and roll number are required'
            ]);

            exit;
        }

        $stmt = $conn->prepare(
            "UPDATE students
             SET name = ?,
                 roll_no = ?,
                 email = NULLIF(?, ''),
                 phone = NULLIF(?, '')
             WHERE id = ?"
        );

        $stmt->bind_param(
            "ssssi",
            $name,
            $roll,
            $email,
            $phone,
            $id
        );

        $stmt->execute();

        echo json_encode([
            'message' => 'Student updated successfully'
        ]);

        exit;
    }

    // DELETE: Delete student if no book is currently issued
    if ($method === 'DELETE') {

        $id = filter_var(
            $data['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id) {
            http_response_code(422);

            echo json_encode([
                'error' => 'Valid student ID is required'
            ]);

            exit;
        }

        $stmt = $conn->prepare(
            "DELETE FROM students
             WHERE id = ?
             AND NOT EXISTS (
                 SELECT 1
                 FROM issued_books
                 WHERE student_id = ?
                 AND status = 'issued'
             )"
        );

        $stmt->bind_param("ii", $id, $id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            http_response_code(409);

            echo json_encode([
                'error' => 'Student not found or has an issued book'
            ]);

            exit;
        }

        echo json_encode([
            'message' => 'Student deleted successfully'
        ]);

        exit;
    }

    header('Allow: GET, POST, PUT, DELETE');

    http_response_code(405);

    echo json_encode([
        'error' => 'Method not allowed'
    ]);

} catch (Throwable $e) {

    error_log('Students API error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'error' => 'Server error. Check server logs.'
    ]);

}
