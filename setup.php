<?php

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

try {

    $queries = [

        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS books (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            author VARCHAR(150) NOT NULL,
            isbn VARCHAR(50) UNIQUE,
            category VARCHAR(100),
            quantity INT NOT NULL DEFAULT 1,
            available INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            roll_no VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(150),
            phone VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS issued_books (
            id INT AUTO_INCREMENT PRIMARY KEY,
            book_id INT NOT NULL,
            student_id INT NOT NULL,
            issue_date DATE NOT NULL,
            return_date DATE DEFAULT NULL,
            status ENUM('issued', 'returned')
                NOT NULL DEFAULT 'issued',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (book_id)
                REFERENCES books(id),
            FOREIGN KEY (student_id)
                REFERENCES students(id)
        ) ENGINE=InnoDB"

    ];

    foreach ($queries as $query) {
        $conn->query($query);
    }

    echo "SUCCESS: All four database tables are ready.";

} catch (Throwable $e) {

    http_response_code(500);

    error_log("Database setup error: " . $e->getMessage());

    echo "Database setup failed. Please check the server logs.";

}
