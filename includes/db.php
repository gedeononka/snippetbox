<?php
require_once __DIR__ . '/config.php';

try {
    // DSN pour MySQL
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

    // Connexion PDO avec options sécurisées
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // gestion des erreurs
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // fetch associatif par défaut
        PDO::ATTR_EMULATE_PREPARES   => false,                  // vrai prepared statements
    ]);

} catch (PDOException $e) {
    // Message d'erreur clair mais sans exposer les identifiants
    die("Impossible de se connecter à la base de données.");
}
