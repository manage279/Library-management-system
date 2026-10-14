<?php

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET' && $method !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {

    // GET: View issued and returned books
    if ($method === 'GET') {

        $result = $conn->query(
            "SELECT ib.id, b.title, s.name AS student_name,
                    s.roll_no, ib.issue_date, ib.return_date,
                    ib.status
             FROM issued_books ib
             JOIN books b ON b.id = ib.book_id
             JOIN students s ON s.id = ib.student_id
             ORDER BY ib.id DESC"
        );

        $records = [];

        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }

        echo json_encode($records);
        exit;
    }

    $data = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        exit;
    }

    $action = $data['action'] ?? '';

    // POST: Issue a book
    if ($action === 'issue') {

        $bookId = filter_var(
            $data['book_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $studentId = filter_var(
            $data['student_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$bookId || !$studentId) {
            http_response_code(422);
            echo json_encode([
                'error' => 'Valid book_id and student_id are required'
            ]);
            exit;
        }

        $conn->begin_transaction();

        try {

            $stmt = $conn->prepare(
                "SELECT available FROM books
                 WHERE id = ? FOR UPDATE"
            );

            $stmt->bind_param("i", $bookId);
            $stmt->execute();

            $book = $stmt->get_result()->fetch_assoc();

            if (!$book || (int)$book['available'] < 1) {
                throw new RuntimeException(
                    'Book not found or unavailable'
                );
            }

            $stmt = $conn->prepare(
                "SELECT id FROM students WHERE id = ?"
            );

            $stmt->bind_param("i", $studentId);
            $stmt->execute();

            if (!$stmt->get_result()->fetch_assoc()) {
                throw new RuntimeException('Student not found');
            }

            $stmt = $conn->prepare(
                "INSERT INTO issued_books
                 (book_id, student_id, issue_date, status)
                 VALUES (?, ?, CURDATE(), 'issued')"
            );

            $stmt->bind_param("ii", $bookId, $studentId);
            $stmt->execute();

            $stmt = $conn->prepare(
                "UPDATE books
                 SET available = available - 1
                 WHERE id = ? AND available > 0"
            );

            $stmt->bind_param("i", $bookId);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException(
                    'Book availability changed'
                );
            }

            $conn->commit();

            echo json_encode([
                'message' => 'Book issued successfully'
            ]);

        } catch (Throwable $e) {

            $conn->rollback();
            throw $e;
        }

        exit;
    }

    // POST: Return an issued book
    if ($action === 'return') {

        $issueId = filter_var(
            $data['issue_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$issueId) {
            http_response_code(422);
            echo json_encode([
                'error' => 'Valid issue_id is required'
            ]);
            exit;
        }

        $conn->begin_transaction();

        try {

            $stmt = $conn->prepare(
                "SELECT book_id FROM issued_books
                 WHERE id = ? AND status = 'issued'
                 FOR UPDATE"
            );

            $stmt->bind_param("i", $issueId);
            $stmt->execute();

            $record = $stmt->get_result()->fetch_assoc();

            if (!$record) {
                throw new RuntimeException(
                    'Issue record not found or already returned'
                );
            }

            $bookId = (int)$record['book_id'];

            $stmt = $conn->prepare(
                "UPDATE issued_books
                 SET status = 'returned', return_date = CURDATE()
                 WHERE id = ? AND status = 'issued'"
            );

            $stmt->bind_param("i", $issueId);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException(
                    'Could not update return record'
                );
            }

            $stmt = $conn->prepare(
                "UPDATE books
                 SET available = available + 1
                 WHERE id = ? AND available < quantity"
            );

            $stmt->bind_param("i", $bookId);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException(
                    'Could not update book availability'
                );
            }

            $conn->commit();

            echo json_encode([
                'message' => 'Book returned successfully'
            ]);

        } catch (Throwable $e) {

            $conn->rollback();
            throw $e;
        }

        exit;
    }

    http_response_code(400);

    echo json_encode([
        'error' => 'Action must be issue or return'
    ]);

} catch (Throwable $e) {

    error_log('Issue/return error: ' . $e->getMessage());

    http_response_code(400);

    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
