<?php
require_once '../config/database.php';

try {
    $pdo->exec("ALTER TABLE tickets ADD COLUMN seat_number INTEGER NOT NULL DEFAULT 0");
    echo "Added seat_number column to tickets table successfully\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
