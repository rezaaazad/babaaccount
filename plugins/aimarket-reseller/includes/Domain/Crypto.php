<?php
/**
 * Encryption at rest for delivered credentials and webhook secrets.
 *
 * Payload layout: "hmr1:" + base64( version || nonce || ciphertext ).
 * Version 1 is libsodium secretbox. Version 2 is OpenSSL AES-256-GCM.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

final class Crypto
{
    public const PREFIX = 'hmr1:';

    public function __construct(private readonly string $key)
    {
        if (strlen($key) !== 32) {
            throw new \InvalidArgumentException('Crypto key must be 32 bytes.');
        }
    }

    public static function generate(): self
    {
        return new self(random_bytes(32));
    }

    public static function fromExport(string $exported): self
    {
        $raw = base64_decode($exported, true);
        if ($raw === false || strlen($raw) !== 32) {
            throw new \InvalidArgumentException('Stored crypto key is unreadable.');
        }
        return new self($raw);
    }

    public function export(): string
    {
        return base64_encode($this->key);
    }

    public function isOurs(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    public function encrypt(string $plain): string
    {
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plain, $nonce, $this->key);
            return self::PREFIX . base64_encode("\x01" . $nonce . $cipher);
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false || strlen($tag) !== 16) {
            throw new \RuntimeException('Encryption failed.');
        }
        return self::PREFIX . base64_encode("\x02" . $iv . $tag . $cipher);
    }

    public function decrypt(string $stored): string
    {
        if (!$this->isOurs($stored)) {
            throw new \RuntimeException('Value is not encrypted with this plugin.');
        }
        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || $raw === '') {
            throw new \RuntimeException('Ciphertext is corrupt.');
        }
        $version = $raw[0];
        $body = substr($raw, 1);
        if ($version === "\x01") {
            if (!function_exists('sodium_crypto_secretbox_open')) {
                throw new \RuntimeException('libsodium is required to read this ciphertext.');
            }
            $nonceLen = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            $nonce = substr($body, 0, $nonceLen);
            $cipher = substr($body, $nonceLen);
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);
            if ($plain === false) {
                throw new \RuntimeException('Decryption failed.');
            }
            return $plain;
        }
        if ($version === "\x02") {
            $iv = substr($body, 0, 12);
            $tag = substr($body, 12, 16);
            $cipher = substr($body, 28);
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($plain === false) {
                throw new \RuntimeException('Decryption failed.');
            }
            return $plain;
        }
        throw new \RuntimeException('Unknown ciphertext version.');
    }
}
