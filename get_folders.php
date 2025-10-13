<?php
$basePath = 'documents/';

function getDirectories($dir)
{
    $directories = [];
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item != '.' && $item != '..' && is_dir($dir . '/' . $item)) {
            $directories[] = $item;
        }
    }
    return $directories;
}

if (isset($_GET['dossier'])) {
    $dossier = $_GET['dossier'];
    $path = $basePath . $dossier;
    $files = [];
    $items = scandir($path);
    foreach ($items as $item) {
        if ($item != '.' && $item != '..' && is_file($path . '/' . $item)) {
            $files[] = $item;
        }
    }
    echo json_encode($files);
} else {
    echo json_encode(getDirectories($basePath));
}
?>