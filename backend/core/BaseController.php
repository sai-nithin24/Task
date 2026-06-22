<?php
declare(strict_types=1);

/**
 * BaseController — shared helpers for all controllers.
 */
abstract class BaseController
{
    /** @var array<string,mixed> Decoded JWT payload set after auth. */
    protected array $authUser = [];

    /** Returns the parsed JSON request body as an associative array. */
    protected function body(): array
    {
        $raw = file_get_contents('php://input');
        // Strip UTF-8 BOM if present (some clients/proxies add it)
        if (str_starts_with((string)$raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        if (!$raw || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** Returns a sanitized query param or $default if absent. */
    protected function query(string $key, mixed $default = null): mixed
    {
        return isset($_GET[$key]) ? htmlspecialchars(trim((string)$_GET[$key]), ENT_QUOTES) : $default;
    }

    /**
     * Validates required fields. Returns array of missing field names.
     *
     * @param array<string,mixed> $data
     * @param string[]            $required
     * @return string[]
     */
    protected function validate(array $data, array $required): array
    {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    /** Requires auth, sets $this->authUser. */
    protected function requireAuth(): void
    {
        $this->authUser = AuthMiddleware::handle();
    }
}
