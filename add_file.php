<?php
if (isset($_FILES['file']) && isset($_POST['dossier'])) {
    $folder = $_POST['dossier'];

    $file = $_FILES['file'];
    $fileName = basename($file['name']);
    $fileTmpPath = $file['tmp_name'];
    $fileDestination = 'documents' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $fileName;

    if (move_uploaded_file($fileTmpPath, $fileDestination)) {
        echo json_encode(['message' => 'Fichier ajouté avec succès.']);
    } else {
        echo json_encode(['message' => 'Erreur lors de l\'ajout du fichier.']);
    }
} else {
    echo json_encode(['message' => 'Aucun fichier ou dossier spécifié.']);
}
?>