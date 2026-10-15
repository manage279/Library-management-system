<?php
// Errors ko screen par HTML ki tarah display hone se rokein
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function sendJson($status, $data)
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
        throw new RuntimeException('Database connection unavailable');
    }

    $conn->set_charset('utf8mb4');
    $method = $_SERVER['REQUEST_METHOD'];

    // GET: List and search books
    if ($method === 'GET') {
        $search = trim($_GET['search'] ?? '');

        if ($search !== '') {
            $stmt = $conn->prepare(
                "SELECT * FROM books
                 WHERE title LIKE ?
                    OR author LIKE ?
                    OR isbn LIKE ?
                 ORDER BY id DESC"
            );

            $term = '%' . $search . '%';
            $stmt->bind_param('sss', $term, $term, $term);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query(
                "SELECT * FROM books ORDER BY id DESC"
            );
        }

        $books = [];

        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }

        sendJson(200, $books);
    }

    if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        header('Allow: GET, POST, PUT, DELETE');
        sendJson(405, ['error' => 'Method not allowed']);
    }

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!is_array($data)) {
        sendJson(400, ['error' => 'Invalid JSON request']);
    }

    // POST: Add a book
    if ($method === 'POST') {
        $title = trim($data['title'] ?? '');
        $author = trim($data['author'] ?? '');
        $isbn = trim($data['isbn'] ?? '');
        $quantity = filter_var(
            $data['quantity'] ?? 1,
            FILTER_VALIDATE_INT
        );

        if ($title === '' || $author === '' ||
            $quantity === false || $quantity < 0) {
            sendJson(422, [
                'error' => 'Valid title, author and quantity are required'
            ]);
        }

        $stmt = $conn->prepare(
            "INSERT INTO books (title, author, isbn, quantity)
             VALUES (?, ?, NULLIF(?, ''), ?)"
        );

        $stmt->bind_param('sssi', $title, $author, $isbn, $quantity);
        $stmt->execute();

        sendJson(201, [
            'success' => true,
            'message' => 'Book added successfully',
            'id' => $conn->insert_id
        ]);
    }

    // PUT: Update a book
    if ($method === 'PUT') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $title = trim($data['title'] ?? '');
        $author = trim($data['author'] ?? '');
        $isbn = trim($data['isbn'] ?? '');
        $quantity = filter_var(
            $data['quantity'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id || $title === '' || $author === '' ||
            $quantity === false || $quantity < 0) {
            sendJson(422, [
                'error' => 'Valid ID, title, author and quantity are required'
            ]);
        }

        $stmt = $conn->prepare(
            "UPDATE books
             SET title = ?, author = ?, isbn = NULLIF(?, ''),
                 quantity = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            'sssii', $title, $author, $isbn, $quantity, $id
        );
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            $check = $conn->prepare(
                "SELECT id FROM books WHERE id = ?"
            );
            $check->bind_param('i', $id);
            $check->execute();

            if (!$check->get_result()->fetch_assoc()) {
                sendJson(404, ['error' => 'Book not found']);
            }
        }

        sendJson(200, [
            'success' => true,
            'message' => 'Book updated successfully'
        ]);
    }

    // DELETE: Delete a book
    if ($method === 'DELETE') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

        $stmt = $conn->prepare(
            "DELETE FROM books WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            sendJson(404, ['error' => 'Book not found']);
        }

        sendJson(200, [
            'success' => true,
            'message' => 'Book deleted successfully'
        ]);
    }

} catch (Throwable $e) {
    error_log('Books API error: ' . $e->getMessage());

    sendJson(500, [
        'error' => 'Books API failed. Check Render logs.',
        'details' => $e->getMessage()
    ]);
}
