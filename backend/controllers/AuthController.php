<?php
declare(strict_types=1);

class AuthController extends BaseController
{
    private UserModel $users;

    public function __construct()
    {
        $this->users = new UserModel();
    }

    /** POST /auth/register */
    public function register(): void
    {
        $data   = $this->body();
        $errors = $this->validate($data, ['name', 'email', 'password']);

        if ($errors) {
            Response::error('Missing required fields.', 422, $errors);
        }

        // Email format check
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email address.', 422);
        }

        // Password strength
        if (strlen($data['password']) < 8) {
            Response::error('Password must be at least 8 characters.', 422);
        }

        if ($this->users->emailExists($data['email'])) {
            Response::error('Email address is already registered.', 409);
        }

        $id   = $this->users->create($data);
        $user = $this->users->findById($id);

        $token = JwtHelper::encode([
            'user_id' => $user['id'],
            'email'   => $user['email'],
            'role'    => $user['role'],
        ]);

        Response::success([
            'token' => $token,
            'user'  => $this->publicUser($user),
        ], 'Registration successful.', 201);
    }

    /** POST /auth/login */
    public function login(): void
    {
        $data   = $this->body();
        $errors = $this->validate($data, ['email', 'password']);

        if ($errors) {
            Response::error('Email and password are required.', 422);
        }

        $user = $this->users->findByEmail($data['email']);

        // Constant-time check to prevent timing attacks
        if (!$user || !password_verify($data['password'], $user['password'])) {
            Response::error('Invalid email or password.', 401);
        }

        if (!$user['is_active']) {
            Response::error('Your account has been deactivated.', 403);
        }

        $token = JwtHelper::encode([
            'user_id' => $user['id'],
            'email'   => $user['email'],
            'role'    => $user['role'],
        ]);

        Response::success([
            'token' => $token,
            'user'  => $this->publicUser($user),
        ], 'Login successful.');
    }

    /** GET /auth/me — returns current authenticated user */
    public function me(): void
    {
        $this->requireAuth();
        $user = $this->users->findById((int)$this->authUser['user_id']);

        if (!$user) {
            Response::notFound('User not found.');
        }

        Response::success(['user' => $this->publicUser($user)]);
    }

    /** Strip sensitive fields before returning user data. */
    private function publicUser(array $user): array
    {
        unset($user['password']);
        return $user;
    }
}
