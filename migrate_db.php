<?php
require_once 'backend/db_config.php';

try {
    // Add columns to users table
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS failed_attempts INT DEFAULT 0");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS lockout_until DATETIME DEFAULT NULL");
    
    echo "Migration successful: Added failed_attempts and lockout_until columns to users table.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
