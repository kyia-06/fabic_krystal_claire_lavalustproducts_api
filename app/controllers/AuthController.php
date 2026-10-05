<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
 
class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
         $this->call->database();
        $this->call->library('api');
    
    }
 
   public function register()
    {
        $this->api->require_method('POST');
        $in       = $this->api->body();
        $username = trim($in['username'] ?? '');
        $email    = trim($in['email'] ?? '');
        $password = $in['password'] ?? '';

        if ($username === '' || $email === '' || strlen($password) < 6) {
            $this->api->respond_error('Username, email and a password (min 6 characters) are required', 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->api->respond_error('Invalid email address', 422);
        }

        $exists = $this->db->raw(
            'SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email]
        )->fetch(PDO::FETCH_ASSOC);

        if ($exists) {
            $this->api->respond_error('Username or email is already taken', 409);
        }

        // Role is ALWAYS 'user' here. Never accept a role from the client.
        $this->db->raw(
            "INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, 'user', NOW())",
            [$username, $email, password_hash($password, PASSWORD_BCRYPT)]
        );

        $this->api->respond(['message' => 'Account created'], 201);
    }
 
    public function login()
    {
        $this->api->require_method('POST');
        $in       = $this->api->body();
        $username = $in['username'] ?? '';
        $password = $in['password'] ?? '';
 
        $user = $this->db->raw('SELECT * FROM users WHERE username = ?', [$username])
                         ->fetch(PDO::FETCH_ASSOC);
 
        if ($user && password_verify($password, $user['password'])) {
            $tokens = $this->api->issue_tokens(['id' => $user['id'], 'role' => $user['role']]);
            $this->api->respond(array_merge($tokens, [
                'user' => [
                    'id'       => $user['id'],
                    'username' => $user['username'],
                    'role'     => $user['role'],
                ],
            ]));
        }
 
        $this->api->respond_error('Invalid username or password', 401);
    }
 
    public function me()
    {
        $this->api->require_method('GET');
        $auth = $this->api->require_jwt();
        $uid  = $auth['sub'] ?? $auth['id'] ?? 0;
 
        $user = $this->db->raw(
            'SELECT id, username, role FROM users WHERE id = ?', [$uid]
        )->fetch(PDO::FETCH_ASSOC);
 
        if (!$user) {
            $this->api->respond_error('User not found', 401);
        }
        $this->api->respond(['user' => $user]);
    }
 
    public function refresh()
    {
        $this->api->require_method('POST');
        $in = $this->api->body();
        $this->api->refresh_access_token($in['refresh_token'] ?? '');
    }
 
    public function logout()
    {
        $this->api->require_method('POST');
        $in = $this->api->body();
        $this->api->revoke_refresh_token($in['refresh_token'] ?? '');
        $this->api->respond(['message' => 'Logged out']);
    }
    // AuthController.php
public function set_role($id)
{
    $this->api->require_method('PUT');
    $me = $this->api->require_jwt();            // dapat may ganitong method sa Api library mo
    if (($me['role'] ?? '') !== 'admin') {
        $this->api->respond_error('Admins only', 403);
    }
    $in = $this->api->body();
    $role = $in['role'] ?? '';
    if (!in_array($role, ['user', 'admin'], true)) {
        $this->api->respond_error('Invalid role', 422);
    }
    $this->db->raw('UPDATE users SET role = ? WHERE id = ?', [$role, $id]);
    $this->api->respond(['message' => 'Role updated']);
}
}

