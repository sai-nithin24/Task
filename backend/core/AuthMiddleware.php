<?php
declare(strict_types=1);

/**
 * AuthMiddleware — validates the Bearer JWT on every protected route.
 */
class AuthMiddleware
{
    /**
     * Checks the Authorization header, decodes the token, and returns the payload.
     *
     * @return array<string,mixed>  The decoded JWT payload (includes user_id, email, role).
     */
    public static function handle(): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            Response::unauthorized('No token provided.');
        }

        $token = substr($header, 7);

        try {
            return JwtHelper::decode($token);
        } catch (InvalidArgumentException $e) {
            Response::unauthorized($e->getMessage());
        }
    }
}
