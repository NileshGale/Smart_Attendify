<?php
require_once 'db_config.php';

$subjects = [
    ['C Programming',       'CP101',  'Commerce and Management', 4],
    ['Core Java',           'CJ201',  'Commerce and Management', 4],
    ['Python Programming',  'PP301',  'Commerce and Management', 4],
    ['PHP',                 'PHP101', 'Commerce and Management', 4],
    ['SQL with Oracle',     'SQL201', 'Commerce and Management', 4],
    ['E Commerce',          'EC301',  'Commerce and Management',         3],
    ['Cloud Computing',     'CC401',  'Commerce and Management', 3],
    ['Digital Marketing',   'DM201',  'Commerce and Management',         3]
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
