<?php

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

try {

    // GET: Display all books or search books
    if ($method === 'GET') {

        $search = trim($_GET['search'] ?? '');

        if ($search !== '') {

            $stmt = $conn->prepare(
                "SELECT * FROM books
                 WHERE title LIKE ?
                 OR author LIKE ?
                 OR category LIKE ?
                 ORDER BY id DESC"
            );

            $term = "%{$search}%";

            $stmt->bind_param(
                "sss",
                $term,
                $term,
                $term
            );

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

        echo json_encode($books);
        exit;
    }

    // Read JSON request body
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

    // POST: Add a new book
    if ($method === 'POST') {

        $title = trim($data['title'] ?? '');
        $author = trim($data['author'] ?? '');
        $isbn = trim($data['isbn'] ?? '');
        $category = trim($data['category'] ?? '');
        $quantity = filter_var(
            $data['quantity'] ?? 1,
            FILTER_VALIDATE_INT
        );

        if (
            $title === '' ||
            $author === '' ||
            $quantity === false ||
            $quantity < 0
        ) {
            http_response_code(422);

            echo json_encode([
                'error' => 'Valid title, author and quantity are required'
            ]);

            exit;
        }

        $stmt = $conn->prepare(
            "INSERT INTO books
            (title, author, isbn, category, quantity, available)
            VALUES (?, ?, NULLIF(?, ''), ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssssii",
            $title,
            $author,
            $isbn,
            $category,
            $quantity,
            $quantity
        );

        $stmt->execute();

        http_response_code(201);

        echo json_encode([
            'message' => 'Book added successfully',
            'id' => $conn->insert_id
        ]);

        exit;
    }

    // PUT: Update a book
    if ($method === 'PUT') {

        $id = filter_var(
            $data['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $title = trim($data['title'] ?? '');
        $author = trim($data['author'] ?? '');
        $category = trim($data['category'] ?? '');
        $quantity = filter_var(
            $data['quantity'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            !$id ||
            $title === '' ||
            $author === '' ||
            $quantity === false ||
            $quantity < 0
        ) {
            http_response_code(422);

            echo json_encode([
                'error' => 'Invalid book details'
            ]);

            exit;
        }

        $stmt = $conn->prepare(
            "UPDATE books
             SET title = ?,
                 author = ?,
                 category = ?,
                 quantity = ?,
                 available = LEAST(
                     available,
                     ?
                 )
             WHERE id = ?"
        );

        $stmt->bind_param(
            "sssiii",
            $title,
            $author,
            $category,
            $quantity,
            $quantity,
            $id
        );

        $stmt->execute();

        echo json_encode([
            'message' => 'Book updated successfully'
        ]);

        exit;
    }

    // DELETE: Delete a book
    if ($method === 'DELETE') {

        $id = filter_var(
            $data['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id) {
            http_response_code(422);

            echo json_encode([
                'error' => 'Valid book ID is required'
            ]);

            exit;
        }

        $stmt = $conn->prepare(
            "DELETE FROM books
             WHERE id = ?
             AND NOT EXISTS (
                 SELECT 1
                 FROM issued_books
                 WHERE book_id = ?
                 AND status = 'issued'
             )"
        );

        $stmt->bind_param("ii", $id, $id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            http_response_code(409);

            echo json_encode([
                'error' => 'Book not found or currently issued'
            ]);

            exit;
        }

        echo json_encode([
            'message' => 'Book deleted successfully'
        ]);

        exit;
    }

    header('Allow: GET, POST, PUT, DELETE');

    http_response_code(405);

    echo json_encode([
        'error' => 'Method not allowed'
    ]);

} catch (Throwable $e) {

    error_log('Books API error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'error' => 'Server error. Check server logs.'
    ]);

}
