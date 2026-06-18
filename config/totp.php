<?php

/**
 * TOTP (Time-based One-Time Password) for Google Authenticator
 * RFC 6238 / RFC 4226
 */

define('TOTP_ISSUER', 'RS PAASWORD MANAGER');

/**
 * Generate a random base32 secret (160 bits = 32 base32 chars)
 */
function generateTOTPSecret(int $length = 32): string {
    $bytes = random_bytes(20); // 160 bits
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < 20; $i++) {
        $secret .= $chars[ord($bytes[$i]) & 31];
    }
    return $secret;
}

/**
 * Generate otpauth:// URI for QR code
 */
function getTOTPProvisioningUri(string $username, string $secret): string {
    $label = rawurlencode(TOTP_ISSUER . ':' . $username);
    $issuer = rawurlencode(TOTP_ISSUER);
    return "otpauth://totp/$label?secret=$secret&issuer=$issuer&algorithm=SHA1&digits=6&period=30";
}

/**
 * Get QR code image URL (using free API)
 */
function getTOTPQRCodeUrl(string $username, string $secret): string {
    $data = getTOTPProvisioningUri($username, $secret);
    return 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . rawurlencode($data);
}

/**
 * Generate TOTP code for a given secret and time
 */
function generateTOTP(string $secret, int $timeSlice = null): string {
    if ($timeSlice === null) {
        $timeSlice = floor(time() / 30);
    }
    $key = base32Decode($secret);
    // Pack time as 8-byte big-endian
    $msg = pack('J', $timeSlice);
    $hash = hash_hmac('sha1', $msg, $key, true);
    $offset = ord($hash[19]) & 0x0f;
    $code = (
        ((ord($hash[$offset]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    ) % 1000000;
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

/**
 * Verify TOTP code with a window of ±1 step
 */
function verifyTOTP(string $secret, string $code): bool {
    $code = trim($code);
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    $timeSlice = floor(time() / 30);
    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(generateTOTP($secret, $timeSlice + $i), $code)) {
            return true;
        }
    }
    return false;
}

/**
 * Decode base32 string to raw bytes
 */
function base32Decode(string $input): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper($input);
    $input = str_replace('=', '', $input);
    $bits = '';
    $len = strlen($input);
    for ($i = 0; $i < $len; $i++) {
        $val = strpos($chars, $input[$i]);
        if ($val === false) continue;
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    $bitsLen = strlen($bits);
    for ($i = 0; $i + 8 <= $bitsLen; $i += 8) {
        $bytes .= chr(bindec(substr($bits, $i, 8)));
    }
    return $bytes;
}
