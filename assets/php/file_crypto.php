<?php
// ============================================================
// FILE_CRYPTO.PHP - CHIFFREMENT DES FICHIERS DE TÂCHES
// ============================================================
// Fournit des helpers pour chiffrer/déchiffrer des blobs binaires
// stockés en base (pièces jointes de tâches). Clé dérivée depuis
// env TASK_FILE_KEY (Base64 possible), sinon fallback machine.
// AES-256-GCM avec nonce 12 octets et tag d'authentification.
// ============================================================
function fileCryptoKey()
{
    static $k = null;
    if ($k !== null) return $k;
    $env = getenv('TASK_FILE_KEY');
    if ($env && strlen($env) > 0) {
        $raw = base64_decode($env, true);
        if ($raw !== false) $env = $raw;
        $k = hash('sha256', $env, true);
    } else {
        $k = hash('sha256', __DIR__ . php_uname(), true);
    }
    return substr($k, 0, 32);
}
function encryptFileData($data, &$nonce, &$tag)
{
    $nonce = random_bytes(12);
    $key = fileCryptoKey();
    $cipher = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    return $cipher;
}
function decryptFileData($cipher, $nonce, $tag)
{
    $key = fileCryptoKey();
    return openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
}
