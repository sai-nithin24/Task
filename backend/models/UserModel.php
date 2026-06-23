<?php
declare(strict_types=1);

/**
 * UserModel — Firestore-backed user operations.
 *
 * Collection: users/{uid}
 * Fields: name, email, password_hash, avatar_color, role, is_active, created_at
 */
class UserModel
{
    private FirestoreClient $db;
    private const COLLECTION = 'users';

    public function __construct()
    {
        $this->db = FirestoreClient::getInstance();
    }

    public function findByEmail(string $email): array|false
    {
        $results = $this->db->query(self::COLLECTION, [
            ['email', '==', strtolower(trim($email))],
        ]);
        return !empty($results) ? $results[0] : false;
    }

    public function findById(string $id): array|false
    {
        $doc = $this->db->getDocument(self::COLLECTION, $id);
        return $doc ?? false;
    }

    /** @return array<int, array> */
    public function all(): array
    {
        return $this->db->query(self::COLLECTION, [
            ['is_active', '==', true],
        ], [['name', 'ASCENDING']]);
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): string
    {
        $colors = ['#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899'];
        $color  = $data['avatar_color'] ?? $colors[array_rand($colors)];

        $docData = [
            'name'          => trim($data['name']),
            'email'         => strtolower(trim($data['email'])),
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'avatar_color'  => $color,
            'role'          => 'member',
            'is_active'     => true,
            'created_at'    => date('c'),
        ];

        return $this->db->addDocument(self::COLLECTION, $docData);
    }

    public function emailExists(string $email): bool
    {
        $results = $this->db->query(self::COLLECTION, [
            ['email', '==', strtolower(trim($email))],
        ]);
        return !empty($results);
    }
}
