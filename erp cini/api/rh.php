<?php
require_once '../config/config.php';
require_once '../includes/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$conn = $db->getConnection();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'vacation_list':
            $where = isset($_GET['user_id']) ? "WHERE user_id = " . intval($_GET['user_id']) : "";
            $result = $conn->query("SELECT rv.*, u.name as user_name, a.name as approver_name FROM rh_vacations rv LEFT JOIN users u ON rv.user_id = u.id LEFT JOIN users a ON rv.approved_by = a.id $where ORDER BY rv.start_date DESC");
            jsonResponse(['success' => true, 'data' => $result->fetchAll()]);
            break;

        case 'vacation_create':
            if ($method !== 'POST') throw new Exception('Método não permitido');
            $data = json_decode(file_get_contents('php://input'), true);
            $start = new DateTime($data['start_date']);
            $end = new DateTime($data['end_date']);
            $days = $end->diff($start)->days + 1;
            
            $stmt = $conn->prepare("INSERT INTO rh_vacations (user_id, start_date, end_date, days, status, observations) VALUES (?, ?, ?, ?, 'solicitado', ?)");
            $stmt->execute([$data['user_id'] ?? $_SESSION['user_id'], $data['start_date'] ?? '', $data['end_date'] ?? '', $days, $data['observations'] ?? '']);
            jsonResponse(['success' => true, 'id' => $conn->lastInsertId()], 201);
            break;

        case 'vacation_approve':
            if ($method !== 'POST') throw new Exception('Método não permitido');
            if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) throw new Exception('Permissão negada');
            
            $data = json_decode(file_get_contents('php://input'), true);
            $conn->prepare("UPDATE rh_vacations SET status = 'aprovado', approved_by = ? WHERE id = ?")->execute([$_SESSION['user_id'], $data['id'] ?? 0]);
            jsonResponse(['success' => true]);
            break;

        case 'user_list':
            $result = $conn->query("SELECT id, name, email, dept, role FROM users WHERE active = 1 ORDER BY name ASC");
            jsonResponse(['success' => true, 'data' => $result->fetchAll()]);
            break;

        case 'get_statistics':
            $stats = [];
            $stmt = $conn->query("SELECT COUNT(*) as count FROM rh_vacations WHERE status = 'solicitado'");
            $stats['pending_requests'] = $stmt->fetch()['count'];
            $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE active = 1");
            $stats['total_users'] = $stmt->fetch()['count'];
            jsonResponse(['success' => true, 'data' => $stats]);
            break;

        default:
            throw new Exception('Ação não reconhecida', 404);
    }
} catch (Exception $e) {
    http_response_code(400);
    jsonResponse(['success' => false, 'error' => $e->getMessage()]);
}
