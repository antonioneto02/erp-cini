<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Notification.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$notification = new Notification();

try {
    switch ($method) {
        case 'GET':
            requireAuth();
            $userId = $_SESSION['user_id'];
            
            if (isset($_GET['unread_count'])) {
                $count = $notification->getUnreadCount($userId);
                jsonResponse(['count' => $count]);
            } else {
                $notifications = $notification->getByUserId($userId);
                jsonResponse($notifications);
            }
            break;
            
        case 'POST':
            requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Marcar todas como lidas
            if (isset($data['mark_all_read'])) {
                $userId = $_SESSION['user_id'];
                $success = $notification->markAllAsRead($userId);
                jsonResponse(['success' => $success, 'message' => 'Todas as notificações marcadas como lidas']);
            } else {
                jsonResponse(['error' => 'Ação inválida'], 400);
            }
            break;
            
        case 'PUT':
            requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                jsonResponse(['error' => 'ID não fornecido'], 400);
            }
            
            // Marcar como lida
            $success = $notification->markAsRead($data['id']);
            jsonResponse(['success' => $success, 'message' => 'Notificação marcada como lida']);
            break;
            
        case 'DELETE':
            requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                jsonResponse(['error' => 'ID não fornecido'], 400);
            }
            
            $success = $notification->delete($data['id']);
            jsonResponse(['success' => $success, 'message' => 'Notificação excluída']);
            break;
            
        default:
            jsonResponse(['error' => 'Método não permitido'], 405);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
