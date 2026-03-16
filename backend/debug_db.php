<?php
require_once 'db_config.php';
header('Content-Type: text/plain');

$tables = ['attendance_codes', 'attendance', 'users', 'subjects'];

foreach ($tables as $table) {
    echo "--- Structure of $table ---\n";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "Field: {$row['Field']} | Type: {$row['Type']} | Null: {$row['Null']} | Key: {$row['Key']} | Default: {$row['Default']} | Extra: {$row['Extra']}\n";
        }
    } catch (PDOException $e) {
        echo "Error or table not found: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "--- Current max IDs ---\n";
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT MAX(id) FROM $table");
        $maxId = $stmt->fetchColumn();
        echo "$table max(id): " . ($maxId !== null ? $maxId : "NULL (empty table)") . "\n";
    } catch (PDOException $e) {
        // ignore
    }
}
?>
