<?php
class AuthHelper {
    private static $db;
    
    public static function init() {
        self::$db = Database::getInstance()->getConnection();
    }
    
    // Verificar se usuário está autenticado
    public static function isAuthenticated() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
    }
    
    // Obter ID do usuário logado
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    // Obter email do usuário logado
    public static function getUserEmail() {
        return $_SESSION['user_email'] ?? null;
    }
    
    // Verificar se é admin
    public static function isAdmin() {
        return $_SESSION['is_admin'] ?? false;
    }
    
    // Verificar permissão (exige que use com a classe Database)
    public static function hasPermission($module, $action) {
        $db = Database::getInstance();
        $user_id = self::getUserId();
        
        if (!$user_id) return false;
        if (self::isAdmin()) return true; // Admin sempre tem acesso
        
        return $db->hasPermission($user_id, $module, $action);
    }
    
    // Exigir permissão ou retornar erro JSON
    public static function requirePermission($module, $action) {
        if (!self::hasPermission($module, $action)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acesso negado']);
            exit;
        }
    }
    
    // Validar entrada/sanitizar
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map(function($value) {
                return self::sanitize($value);
            }, $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
    
    // Validar email
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    // Validar data
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    // Validar campos obrigatórios
    public static function validateRequired($data, $fields) {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                return "Campo obrigatório: $field";
            }
        }
        return null;
    }
    
    // Registrar atividade de auditoria
    public static function logActivity($entity, $entity_id, $action, $old_values = null, $new_values = null) {
        $db = Database::getInstance();
        return $db->logAudit(self::getUserId(), $entity, $entity_id, $action, $old_values, $new_values);
    }
    
    // Retornar resposta JSON
    public static function response($success, $data = null, $error = null) {
        header('Content-Type: application/json');
        if ($success) {
            return json_encode(['success' => true, 'data' => $data]);
        } else {
            http_response_code(400);
            return json_encode(['success' => false, 'error' => $error]);
        }
    }
    
    // Criar notificação
    public static function createNotification($user_id, $type, $title, $message, $priority = 'normal', $entity_type = null, $entity_id = null, $action_url = null) {
        self::$db = self::$db ?? Database::getInstance()->getConnection();
        
        $stmt = self::$db->prepare("
            INSERT INTO notifications_expanded (user_id, type, title, message, entity_type, entity_id, action_url, priority)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $user_id,
            $type,
            $title,
            $message,
            $entity_type,
            $entity_id,
            $action_url,
            $priority
        ]);
    }
}

// Inicializar na primeira chamada
if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
    AuthHelper::init();
}
