<?php
declare(strict_types=1);

class UserModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, password, role, avatar_color, is_active FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, role, avatar_color, is_active, created_at FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /** @return array<int, array> */
    public function all(): array
    {
        return $this->db->query(
            'SELECT id, name, email, role, avatar_color, created_at FROM users WHERE is_active = 1 ORDER BY name'
        )->fetchAll();
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password, avatar_color) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            trim($data['name']),
            strtolower(trim($data['email'])),
            password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            $data['avatar_color'] ?? $this->randomColor(),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        return (bool)$stmt->fetchColumn();
    }

    private function randomColor(): string
    {
        $colors = ['#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899'];
        return $colors[array_rand($colors)];
    }
}
