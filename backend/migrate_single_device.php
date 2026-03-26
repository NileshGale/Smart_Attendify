<?php


require_once 'db_config.php';

echo "<h2>Starting Database Migration...</h2>";

try {
    // Check if the column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'session_token'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "<p style='color: green;'>Migration already applied! The `session_token` column exists.</p>";
    } else {
        // Add the column
        $pdo->exec("ALTER TABLE users ADD COLUMN session_token VARCHAR(255) DEFAULT NULL");
        echo "<p style='color: green;'>Success! The `session_token` column has been added to the `users` table.</p>";
    }
    
    echo "<p>You can now safely delete this `migrate_single_device.php` file from your server.</p>";
    echo "<p><a href='../index.php'>Go back to the application</a>.</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error during migration: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
