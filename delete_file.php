<?php
if (isset($_POST['file']) && isset($_POST['folder'])) {
    $folder = $_POST['folder'];
    $file = $_POST['file'];

    $filePath = 'documents' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $file;

    if (file_exists($filePath)) {
        if (unlink($filePath)) {
            echo json_encode(['message' => 'Fichier supprimé avec succès.']);
        } else {
            echo json_encode(['message' => 'Erreur lors de la suppression du fichier.']);
        }
    } else {
        echo json_encode(['message' => 'Fichier non trouvé.']);
    }
} else {
    echo json_encode(['message' => 'Aucun fichier ou dossier spécifié.']);
}
?>