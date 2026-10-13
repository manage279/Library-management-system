<?php

header("Content-Type: application/json; charset=UTF-8");

echo json_encode([
    "success" => true,
    "project" => "Library Management System",
    "message" => "Library Management System backend is running."
]);

?>
