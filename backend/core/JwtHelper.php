<?php
declare(strict_types=1);

/**
 * JwtHelper — lightweight HS256 JWT implementation.
 * No external dependencies required.
 */
class JwtHelper
{
    private static function getSecret(): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if (strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET must be at least 32 characters.', 500);
        }
        return $secret;
    }

    /**
     * Creates a signed JWT token.
     *
     * @param array<string,mixed> $payload
     */
    public static function encode(array $payload): string
    {
        $expiry  = (int)($_ENV['JWT_EXPIRY'] ?? 3600);
        $payload = array_merge($payload, [
            'iat' => time(),
            'exp' => time() + $expiry,
        ]);

        $header    = self::base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body      = self::base64url(json_encode($payload));
        $signature = self::base64url(hash_hmac('sha256', "{$header}.{$body}", self::getSecret(), true));

        return "{$header}.{$body}.{$signature}";
    }

    /**
     * Validates and decodes a JWT token.
     *
     * @return array<string,mixed>
     * @throws InvalidArgumentException On invalid / expired token.
     */
    public static function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Invalid token format.', 401);
        }

        [$header, $body, $sig] = $parts;

        $expected = self::base64url(
            hash_hmac('sha256', "{$header}.{$body}", self::getSecret(), true)
        );

        if (!hash_equals($expected, $sig)) {
            throw new InvalidArgumentException('Token signature mismatch.', 401);
        }

        $payload = json_decode(self::base64urlDecode($body), true);

        if (!is_array($payload)) {
            throw new InvalidArgumentException('Invalid token payload.', 401);
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new InvalidArgumentException('Token has expired.', 401);
        }

        return $payload;
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
