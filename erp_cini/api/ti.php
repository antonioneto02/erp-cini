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
    // TICKETS
    if (strpos($action, 'ticket') === 0) {
        switch ($action) {
            case 'ticket_list':
                $result = $conn->query("SELECT tt.*, u1.name as user_name, u2.name as assigned_name FROM ti_tickets tt LEFT JOIN users u1 ON tt.user_id = u1.id LEFT JOIN users u2 ON tt.assigned_to = u2.id ORDER BY tt.created_at DESC");
                jsonResponse(['success' => true, 'data' => $result->fetchAll()]);
                break;

            case 'ticket_create':
                if ($method !== 'POST') throw new Exception('Método não permitido');
                $data = json_decode(file_get_contents('php://input'), true);
                $stmt = $conn->prepare("INSERT INTO ti_tickets (title, description, category, priority, user_id, assigned_to, status) VALUES (?, ?, ?, ?, ?, ?, 'aberto')");
                $stmt->execute([$data['title'] ?? '', $data['description'] ?? '', $data['category'] ?? '', $data['priority'] ?? 'media', $_SESSION['user_id'] ?? 0, $data['assigned_to'] ?? null]);
                $ticket_id = $conn->lastInsertId();
                
                // Notificar responsável atribuído
                if (isset($data['assigned_to']) && !empty($data['assigned_to'])) {
                    AuthHelper::createNotification($data['assigned_to'], 'chamado_atribuido', 'Novo Chamado Atribuído', 'Um chamado de TI foi atribuído a você: ' . ($data['title'] ?? ''), 'high', 'chamado', $ticket_id);
                }
                
                jsonResponse(['success' => true, 'id' => $ticket_id], 201);
                break;

            case 'ticket_update_status':
                if ($method !== 'POST') throw new Exception('Método não permitido');
                $data = json_decode(file_get_contents('php://input'), true);
                $conn->prepare("UPDATE ti_tickets SET status = ? WHERE id = ?")->execute([$data['status'] ?? '', $data['id'] ?? 0]);
                jsonResponse(['success' => true]);
                break;
        }
    }
    
    // ATIVOS
    elseif (strpos($action, 'asset') === 0) {
        switch ($action) {
            case 'asset_list':
                $result = $conn->query("SELECT ta.*, u.name as responsible_name FROM ti_assets ta LEFT JOIN users u ON ta.responsible_id = u.id ORDER BY ta.code ASC");
                jsonResponse(['success' => true, 'data' => $result->fetchAll()]);
                break;

            case 'asset_create':
                if ($method !== 'POST') throw new Exception('Método não permitido');
                $data = json_decode(file_get_contents('php://input'), true);
                $stmt = $conn->prepare("INSERT INTO ti_assets (code, name, asset_type, serial_number, location, acquisition_date, status) VALUES (?, ?, ?, ?, ?, ?, 'ativo')");
                $stmt->execute([$data['code'] ?? '', $data['name'] ?? '', $data['asset_type'] ?? '', $data['serial_number'] ?? '', $data['location'] ?? '', $data['acquisition_date'] ?? null]);
                jsonResponse(['success' => true, 'id' => $conn->lastInsertId()], 201);
                break;
        }
    }
    
    // Estatísticas
    else {
        switch ($action) {
            case 'get_statistics':
                $stats = [];
                $stmt = $conn->query("SELECT COUNT(*) as count FROM ti_tickets WHERE status = 'aberto'");
                $stats['open_tickets'] = $stmt->fetch()['count'];
                $stmt = $conn->query("SELECT COUNT(*) as count FROM ti_assets WHERE status = 'ativo'");
                $stats['active_assets'] = $stmt->fetch()['count'];
                jsonResponse(['success' => true, 'data' => $stats]);
                break;

            default:
                throw new Exception('Ação não reconhecida', 404);
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    jsonResponse(['success' => false, 'error' => $e->getMessage()]);
}
