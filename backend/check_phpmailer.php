<?php
header('Content-Type: text/plain');

echo "Current Directory: " . __DIR__ . "\n";

$pathsToCheck = [
    '/PHPMailer/src/',
    '/PHPMailer/',
    '/phpmailer/src/',
    '/phpmailer/'
];

foreach ($pathsToCheck as $p) {
    $fullPath = __DIR__ . $p;
    echo "\nChecking Path: $fullPath\n";
    if (is_dir($fullPath)) {
        echo "Status: IS A DIRECTORY\n";
        echo "Contents:\n";
        $files = scandir($fullPath);
        print_r($files);
        
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            if (is_file($fullPath . $f)) {
                echo "  FILE: $f (" . filesize($fullPath . $f) . " bytes)\n";
            } elseif (is_dir($fullPath . $f)) {
                echo "  DIR: $f\n";
            }
        }
    } else {
        echo "Status: DOES NOT EXIST OR IS NOT A DIRECTORY\n";
    }
}

echo "\n--- SEARCH COMPLETED ---\n";
?>
