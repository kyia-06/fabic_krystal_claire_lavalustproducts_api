<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
 
class ProductController extends Controller
{
    public function __construct()
    {
        parent::__construct();
         $this->call->database();
        $this->call->library('api');
    }
 
    /* ---------- auth helpers ---------- */
 
    private function current_user(): array
    {
        $auth = $this->api->require_jwt();
        $uid  = $auth['sub'] ?? $auth['id'] ?? 0;
 
        $user = $this->db->raw(
            'SELECT id, username, role FROM users WHERE id = ?', [$uid]
        )->fetch(PDO::FETCH_ASSOC);
 
        if (!$user) {
            $this->api->respond_error('User not found', 401);
        }
        return $user;
    }
 
    private function require_admin(): array
    {
        $user = $this->current_user();
        if ($user['role'] !== 'admin') {
            $this->api->respond_error('Forbidden: only admins can modify products', 403);
        }
        return $user;
    }
 
    /* ---------- data helpers ---------- */
 
    private function validate(array $in): array
    {
        $name  = trim($in['product_name'] ?? '');
        $price = $in['price'] ?? null;
        $qty   = $in['quantity'] ?? null;
 
        if ($name === '' || strlen($name) > 100) {
            $this->api->respond_error('Product name is required (max 100 characters)', 422);
        }
        if (!is_numeric($price) || $price < 0) {
            $this->api->respond_error('Price must be a number, 0 or higher', 422);
        }
        if (filter_var($qty, FILTER_VALIDATE_INT) === false || $qty < 0) {
            $this->api->respond_error('Quantity must be a whole number, 0 or higher', 422);
        }
 
        return [
            'product_name' => $name,
            'description'  => trim($in['description'] ?? ''),
            'price'        => (float) $price,
            'quantity'     => (int) $qty,
        ];
    }
 
    private function find_or_fail($id): array
    {
        $row = $this->db->raw('SELECT * FROM products WHERE id = ?', [$id])
                        ->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->api->respond_error('Product not found', 404);
        }
        return $row;
    }
 
    /* ---------- endpoints ---------- */
 
    // Any logged-in user
    public function index()
    {
        $this->api->require_method('GET');
        $this->current_user();
 
        $rows = $this->db->raw('SELECT * FROM products ORDER BY id DESC')
                         ->fetchAll(PDO::FETCH_ASSOC);
        $this->api->respond(['data' => $rows]);
    }
 
    public function show($id)
    {
        $this->api->require_method('GET');
        $this->current_user();
        $this->api->respond(['data' => $this->find_or_fail($id)]);
    }
 
    // Admin only
    public function store()
    {
        $this->api->require_method('POST');
        $this->require_admin();
 
        $d = $this->validate($this->api->body());
 
        $this->db->raw(
            'INSERT INTO products (product_name, description, price, quantity, created_at) VALUES (?, ?, ?, ?, NOW())',
            [$d['product_name'], $d['description'], $d['price'], $d['quantity']]
        );
        $id = $this->db->raw('SELECT LAST_INSERT_ID() AS id')->fetch(PDO::FETCH_ASSOC)['id'];
 
        $this->api->respond(['message' => 'Product created', 'data' => $this->find_or_fail($id)], 201);
    }
 
    public function update($id)
    {
        $this->api->require_method('PUT');
        $this->require_admin();
        $this->find_or_fail($id);
 
        $d = $this->validate($this->api->body());
 
        $this->db->raw(
            'UPDATE products SET product_name = ?, description = ?, price = ?, quantity = ? WHERE id = ?',
            [$d['product_name'], $d['description'], $d['price'], $d['quantity'], $id]
        );
 
        $this->api->respond(['message' => 'Product updated', 'data' => $this->find_or_fail($id)]);
    }
 
    public function delete($id)
    {
        $this->api->require_method('DELETE');
        $this->require_admin();
        $this->find_or_fail($id);
 
        $this->db->raw('DELETE FROM products WHERE id = ?', [$id]);
        $this->api->respond(['message' => 'Product deleted']);
    }
}
