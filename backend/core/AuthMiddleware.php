<?php
declare(strict_types=1);

/**
 * AuthMiddleware — validates the Bearer JWT on every protected route.
 */
class AuthMiddleware
{
    /**
     * Checks the Authorization header, decodes the token, and returns the payload.
     * Checks multiple locations because Apache can place it differently.
     *
     * @return array<string,mixed>  The decoded JWT payload (includes user_id, email, role).
     */
    public static function handle(): array
    {
        // Apache may strip Authorization header — check all possible locations
        $header = $_SERVER['HTTP_AUTHORIZATION']
               ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
               ?? getallheaders()['Authorization']
               ?? getallheaders()['authorization']
               ?? '';

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
