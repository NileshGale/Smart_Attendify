<?php
require_once 'db_config.php';
header('Content-Type: text/plain');

echo "Database Fix Script\n";
echo "===================\n\n";

$tablesToFix = ['attendance_codes', 'attendance', 'subjects', 'users', 'events', 'event_attendance'];

foreach ($tablesToFix as $table) {
    echo "Checking table: $table... ";
    try {
        $stmt = $pdo->query("DESCRIBE `$table`");
        $fields = $stmt->fetchAll();
        
        $idField = null;
        foreach ($fields as $f) {
            if ($f['Field'] === 'id') {
                $idField = $f;
                break;
            }
        }
        
        if ($idField) {
            if (strpos($idField['Extra'], 'auto_increment') === false) {
                echo "Missing AUTO_INCREMENT. Fixing conflicts...\n";
                
                // 1. Remove ID 0 if it exists to prevent resequencing errors
                $pdo->exec("SET @max_id = (SELECT IFNULL(MAX(id), 0) FROM `$table`) + 1;
                           UPDATE `$table` SET id = @max_id WHERE id = 0;");
                
                // 2. Apply AUTO_INCREMENT
                $pdo->exec("ALTER TABLE `$table` MODIFY id INT AUTO_INCREMENT");
                echo "SUCCESS: $table fixed.\n";
            } else {
                echo "OK (already has AUTO_INCREMENT).\n";
            }
        } else {
            echo "SKIPPED (no 'id' column found).\n";
        }
    } catch (PDOException $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "\nDone.";
?>
