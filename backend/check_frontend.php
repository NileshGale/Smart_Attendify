<?php
header('Content-Type: text/plain');

$dir = __DIR__ . '/../frontend';
echo "Checking directory: $dir\n";

if (is_dir($dir)) {
    echo "Files found:\n";
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        echo "- " . $file . " (" . filesize($dir . '/' . $file) . " bytes)\n";
    }
} else {
    echo "Directory not found!\n";
}
?>
