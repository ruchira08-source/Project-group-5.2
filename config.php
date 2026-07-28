<?php
// config.php
$db_host = 'localhost';
$db_name = 'erp_system';
$db_user = 'root';
$db_pass = ''; // Leave empty for default XAMPP setup

try {
    // Attempt to connect to the specific database
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // If the database doesn't exist, we might be running setup_db.php.
    // Allow connection without db_name to create it.
    try {
        $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch(PDOException $ex) {
        die("Database connection failed: " . $ex->getMessage());
    }
}
?>



