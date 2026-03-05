<?php
require_once 'db_config.php';

$subjects = [
    ['C Programming',       'CP101',  'Computer Science', 4],
    ['Core Java',           'CJ201',  'Computer Science', 4],
    ['Python Programming',  'PP301',  'Computer Science', 4],
    ['PHP',                 'PHP101', 'Computer Science', 4],
    ['SQL with Oracle',     'SQL201', 'Computer Science', 4],
    ['E Commerce',          'EC301',  'Commerce',         3],
    ['Cloud Computing',     'CC401',  'Computer Science', 3],
    ['Digital Marketing',   'DM201',  'Commerce',         3]
];

try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO subjects (subject_name, subject_code, department, credits) VALUES (?, ?, ?, ?)");
    $count = 0;
    foreach ($subjects as $s) {
        $stmt->execute($s);
        if ($stmt->rowCount() > 0) {
            $count++;
        }
    }
    echo "Successfully added $count new subjects.\n";
} catch (PDOException $e) {
    echo "Error inserting subjects: " . $e->getMessage() . "\n";
}
