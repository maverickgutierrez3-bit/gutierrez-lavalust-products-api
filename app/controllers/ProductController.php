<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
    }

    // GET /api/products
    public function index()
    {
        $this->api->require_method('GET');
        $this->api->require_jwt();

        $products = $this->db->raw(
            "SELECT id, product_name, description, price, quantity, created_at
             FROM products ORDER BY id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->api->respond(['data' => $products]);
    }

    // GET /api/products/{id}
    public function show($id)
    {
        $this->api->require_method('GET');
        $this->api->require_jwt();

        $this->api->respond(['data' => $this->find_or_404($id)]);
    }

    // POST /api/products
    public function store()
    {
        $this->api->require_method('POST');
        $this->api->require_jwt();

        $data = $this->validate($this->api->body());

        $this->db->raw(
            "INSERT INTO products (product_name, description, price, quantity)
             VALUES (?, ?, ?, ?)",
            [$data['product_name'], $data['description'], $data['price'], $data['quantity']]
        );

        $new = $this->db->raw("SELECT LAST_INSERT_ID() AS id")->fetch(PDO::FETCH_ASSOC);

        $this->api->respond([
            'message' => 'Product created',
            'data'    => $this->find_or_404($new['id']),
        ], 201);
    }

    // PUT/PATCH /api/products/{id}
    public function update($id)
    {
        $this->api->require_jwt();

        $current = $this->find_or_404($id);
        $body    = array_merge($current, $this->api->body());
        $data    = $this->validate($body);

        $this->db->raw(
            "UPDATE products SET product_name = ?, description = ?, price = ?, quantity = ?
             WHERE id = ?",
            [$data['product_name'], $data['description'], $data['price'], $data['quantity'], $id]
        );

        $this->api->respond([
            'message' => 'Product updated',
            'data'    => $this->find_or_404($id),
        ]);
    }

    // DELETE /api/products/{id}
    public function destroy($id)
    {
        $this->api->require_method('DELETE');
        $this->api->require_jwt();

        $this->find_or_404($id);
        $this->db->raw("DELETE FROM products WHERE id = ?", [$id]);

        $this->api->respond(['message' => 'Product deleted']);
    }

    // ---------- helpers ----------
    private function find_or_404($id)
    {
        $product = $this->db->raw(
            "SELECT id, product_name, description, price, quantity, created_at
             FROM products WHERE id = ? LIMIT 1",
            [$id]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $this->api->respond_error('Product not found', 404);
        }
        return $product;
    }

    private function validate($body)
    {
        $name  = trim($body['product_name'] ?? '');
        $price = $body['price'] ?? null;
        $qty   = $body['quantity'] ?? null;

        if ($name === '' || strlen($name) > 100) {
            $this->api->respond_error('product_name is required (max 100 characters)', 422);
        }
        if (!is_numeric($price) || $price < 0) {
            $this->api->respond_error('price must be a number >= 0', 422);
        }
        if (filter_var($qty, FILTER_VALIDATE_INT) === false || $qty < 0) {
            $this->api->respond_error('quantity must be a whole number >= 0', 422);
        }

        return [
            'product_name' => $name,
            'description'  => $body['description'] ?? null,
            'price'        => round((float) $price, 2),
            'quantity'     => (int) $qty,
        ];
    }
}