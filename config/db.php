<?php
// ============================================================
//  config/db.php — Connexion à la base de données MySQL
//  XAMPP : par défaut user=root, password vide
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // ← laisser root avec XAMPP
define('DB_PASS', '');           // ← laisser vide avec XAMPP
define('DB_NAME', 'healthqueue');
define('DB_CHARSET', 'utf8mb4');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['erreur' => 'Connexion base de données impossible : ' . $e->getMessage()]));
        }
    }
    return $pdo;
}
