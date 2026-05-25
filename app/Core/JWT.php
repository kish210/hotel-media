<?php
declare(strict_types=1);

namespace App\Core;

/**
 * JWT - Pure PHP JWT implementation (HS256)
 */
class JWT
{
    private static string $secret;
    private static int $expiry = 86400;

    public static function init(): void
    {
        self::$secret = env('JWT_SECRET', 'change-this-secret-in-production-32chars');
        self::$expiry = (int)env('JWT_EXPIRY', 86400);
    }

    public static function encode(array $payload): string
    {
        self::init();
        $header  = self::base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::base64url(json_encode(array_merge($payload, [
            'iat' => time(),
            'exp' => time() + self::$expiry,
            'jti' => bin2hex(random_bytes(16)),
        ])));
        $sig = self::base64url(hash_hmac('sha256', "$header.$payload", self::$secret, true));
        return "$header.$payload.$sig";
    }

    public static function decode(string $token): ?array
    {
        self::init();
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $sig] = $parts;
        $expectedSig = self::base64url(hash_hmac('sha256', "$header.$payload", self::$secret, true));

        if (!hash_equals($expectedSig, $sig)) return null;

        $data = json_decode(self::base64urlDecode($payload), true);
        if (!$data) return null;

        if (isset($data['exp']) && $data['exp'] < time()) return null;

        return $data;
    }

    public static function refresh(string $token): ?string
    {
        self::init();
        $data = self::decode($token);
        if (!$data) return null;

        unset($data['iat'], $data['exp'], $data['jti']);
        return self::encode($data);
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
