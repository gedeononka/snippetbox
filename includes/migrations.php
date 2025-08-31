<?php
require_once __DIR__ . '/db.php'; // Connexion $pdo

try {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS snippets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description VARCHAR(500) NOT NULL,
    category ENUM('PHP', 'HTML', 'CSS') NOT NULL,
    code TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    die("Erreur création table : " . $e->getMessage());
}
