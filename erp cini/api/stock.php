<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/AuthHelper.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$conn = $db->getConnection();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'list_products':
            $result = $conn->query("SELECT * FROM stock_products ORDER BY name ASC");
            jsonResponse(['success' => true, 'data' => $result->fetchAll()]);
            break;

        case 'add_product':
        case 'product_create':
            if ($method !== 'POST') throw new Exception('Método não permitido');
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO stock_products (code, name, category, unit, minimum_quantity, unit_price) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['code'] ?? '', $data['name'] ?? '', $data['category'] ?? '', $data['unit'] ?? 'un', $data['minimum_quantity'] ?? 0, $data['unit_price'] ?? 0]);
            jsonResponse(['success' => true, 'id' => $conn->lastInsertId()], 201);
            break;

        case 'list_movements':
            $stmt = $conn->query("SELECT sm.*, sp.name as product_name, u.name as user_name FROM stock_movements sm LEFT JOIN stock_products sp ON sm.product_id = sp.id LEFT JOIN users u ON sm.user_id = u.id ORDER BY sm.created_at DESC");
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'add_movement':
        case 'movement_create':
            if ($method !== 'POST') throw new Exception('Método não permitido');
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO stock_movements (product_id, type, quantity, reason, user_id, reference, responsible_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['product_id'] ?? 0, $data['type'] ?? 'entrada', $data['quantity'] ?? 0, $data['reason'] ?? '', $_SESSION['user_id'] ?? null, $data['reference'] ?? '', $data['responsible_id'] ?? null]);
            
            $movement_id = $conn->lastInsertId();
            
            // Atualizar quantidade
            $qty = $data['type'] === 'entrada' ? $data['quantity'] : -$data['quantity'];
            $conn->prepare("UPDATE stock_products SET quantity = quantity + ? WHERE id = ?")->execute([$qty, $data['product_id']]);
            
            // Notificar responsável
            if (isset($data['responsible_id']) && !empty($data['responsible_id']) && $data['responsible_id'] != $_SESSION['user_id']) {
                $product = $conn->query("SELECT name FROM stock_products WHERE id = " . ($data['product_id'] ?? 0))->fetch();
                AuthHelper::createNotification($data['responsible_id'], 'movimentacao_estoque', 'Movimentação de Estoque', 'Movimentação registrada: ' . ($product['name'] ?? 'Produto') . ' (' . ($data['type'] ?? '') . ')', 'normal', 'estoque', $movement_id);
            }
            
            jsonResponse(['success' => true, 'id' => $movement_id], 201);
            break;

        case 'get_statistics':
            $stats = [];
            $stmt = $conn->query("SELECT COUNT(*) as count FROM stock_products");
            $stats['total_products'] = $stmt->fetch()['count'];
            $stmt = $conn->query("SELECT COUNT(*) as count FROM stock_products WHERE quantity < minimum_quantity");
            $stats['low_stock'] = $stmt->fetch()['count'];
            $stmt = $conn->query("SELECT SUM(quantity * unit_price) as total FROM stock_products");
            $stats['total_value'] = $stmt->fetch()['total'] ?? 0;
            jsonResponse(['success' => true, 'data' => $stats]);
            break;

        default:
            throw new Exception('Ação não reconhecida', 404);
    }
} catch (Exception $e) {
    http_response_code(400);
    jsonResponse(['success' => false, 'error' => $e->getMessage()]);
}
