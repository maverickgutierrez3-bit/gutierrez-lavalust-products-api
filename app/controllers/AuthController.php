<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
    }

    public function register()
    {
        $this->api->require_method('POST');
        $body = $this->api->body();

        $username = trim($body['username'] ?? '');
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if ($username === '' || $email === '' || $password === '') {
            $this->api->respond_error('username, email and password are required', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->api->respond_error('Invalid email', 422);
        }
        if (strlen($password) < 6) {
            $this->api->respond_error('Password must be at least 6 characters', 422);
        }

        $exists = $this->db->raw(
            "SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1",
            [$email, $username]
        )->fetch(PDO::FETCH_ASSOC);

        if ($exists) {
            $this->api->respond_error('Username or email already exists', 409);
        }

        $this->db->raw(
            "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')",
            [$username, $email, password_hash($password, PASSWORD_DEFAULT)]
        );

        $this->api->respond(['message' => 'User registered successfully'], 201);
    }

    public function login()
    {
        $this->api->require_method('POST');
        $body = $this->api->body();

        // Tinatanggap ang email o username (ang API tester ay "username" ang ipinapadala)
        $login    = trim(($body['email'] ?? '') ?: ($body['username'] ?? ''));
        $password = $body['password'] ?? '';

        if ($login === '' || $password === '') {
            $this->api->respond_error('email (or username) and password are required', 422);
        }

        $user = $this->db->raw(
            "SELECT id, username, email, password, role, is_active
             FROM users WHERE email = ? OR username = ? LIMIT 1",
            [$login, $login]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->api->respond_error('Invalid email or password', 401);
        }
        if ((int) $user['is_active'] !== 1) {
            $this->api->respond_error('Account is disabled', 403);
        }

        $tokens = $this->api->issue_tokens([
            'id'   => $user['id'],
            'role' => $user['role'],
        ]);

        $this->api->respond([
            'message' => 'Login successful',
            'user'    => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'role'     => $user['role'],
            ],
            'tokens'  => $tokens,
        ]);
    }

    public function refresh()
    {
        $this->api->require_method('POST');
        $body = $this->api->body();

        if (empty($body['refresh_token'])) {
            $this->api->respond_error('refresh_token is required', 422);
        }

        $this->api->refresh_access_token($body['refresh_token']);
    }

    public function logout()
    {
        $this->api->require_method('POST');
        $body = $this->api->body();

        if (!empty($body['refresh_token'])) {
            $this->api->revoke_refresh_token($body['refresh_token']);
        }

        $this->api->respond(['message' => 'Logged out successfully']);
    }
}