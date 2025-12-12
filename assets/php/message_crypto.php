<?php
// ============================================================
// MESSAGE_CRYPTO.PHP - CHIFFREMENT DES MESSAGES PRIVÉS
// ============================================================
// AES-256-GCM pour le texte et les pièces jointes des messages.
// Clé dérivée depuis env TASK_MSG_KEY (Base64 possible), sinon
// fallback machine. Nonces 12 octets, tag d'authentification GCM.
// ============================================================
// Chiffrement messages privés AES-256-GCM
function messageCryptoKey(): string
{
    static $k = null;
    if ($k !== null)
        return $k;
    $seed = getenv('TASK_MSG_KEY');
    if ($seed && strlen($seed) > 0) {
        $raw = base64_decode($seed, true);
        if ($raw !== false)
            $seed = $raw;
        $k = hash('sha256', $seed, true);
    } else {
        $k = hash('sha256', __DIR__ . '|' . php_uname(), true);
    }
    return substr($k, 0, 32);
}
function encryptMessage(string $plaintext, &$nonce, &$tag): string
{
    $nonce = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', messageCryptoKey(), OPENSSL_RAW_DATA, $nonce, $tag);
    return $cipher === false ? '' : $cipher;
}
function decryptMessage(string $cipher, string $nonce, string $tag): string|false
{
    return openssl_decrypt($cipher, 'aes-256-gcm', messageCryptoKey(), OPENSSL_RAW_DATA, $nonce, $tag);
}
