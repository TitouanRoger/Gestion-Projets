<?php
session_start();
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['dir'])) {
    die("Aucun dossier spécifié.");
}

$sousDossier = $_GET['dir'];

$dossier_versions = 'versions/';
$dossier_complet = realpath($dossier_versions . DIRECTORY_SEPARATOR . $sousDossier);

if (!$dossier_complet || !is_dir($dossier_complet) || strpos($dossier_complet, realpath($dossier_versions)) !== 0) {
    die("Dossier invalide ou inexistant.");
}

function compresserDossier($dossier, $fichierZip)
{
    $zip = new ZipArchive();
    if ($zip->open($fichierZip, ZipArchive::CREATE) === TRUE) {
        $dossierRealPath = realpath($dossier);
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dossierRealPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($dossierRealPath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
        return true;
    }
    return false;
}

$nomZip = basename($sousDossier) . '.zip';
$fichierZip = sys_get_temp_dir() . '/' . $nomZip;

if (compresserDossier($dossier_complet, $fichierZip)) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($fichierZip) . '"');
    header('Content-Length: ' . filesize($fichierZip));

    readfile($fichierZip);

    unlink($fichierZip);
    exit;
} else {
    echo "Une erreur est survenue lors de la compression du dossier.";
}
?>