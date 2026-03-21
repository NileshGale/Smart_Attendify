<?php
require_once 'backend/db_config.php';

try {
    // Add geolocation columns to events table if they don't exist
    $pdo->exec("ALTER TABLE events ADD COLUMN teacher_lat DECIMAL(10, 8) DEFAULT NULL");
    $pdo->exec("ALTER TABLE events ADD COLUMN teacher_lng DECIMAL(11, 8) DEFAULT NULL");
    $pdo->exec("ALTER TABLE events ADD COLUMN max_distance_meters INT DEFAULT 50");
    $pdo->exec("ALTER TABLE events ADD COLUMN teacher_accuracy DECIMAL(10, 2) DEFAULT NULL");
    
    echo "Successfully updated 'events' table with geolocation columns.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist in 'events' table.\n";
    } else {
        echo "Error updating 'events' table: " . $e->getMessage() . "\n";
    }
}
?>
