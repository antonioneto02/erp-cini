<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    // Autenticação
    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && $user['password'] === md5($password)) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['is_admin'] = ($user['role'] === 'admin');
            $_SESSION['last_activity'] = time();
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'dept' => $user['dept'],
                    'role' => $user['role']
                ]
            ];
        }
        
        return ['success' => false, 'message' => 'E-mail ou senha inválidos'];
    }
    
    public function logout() {
        session_destroy();
        return ['success' => true];
    }
    
    // CRUD
    public function getAll() {
        $stmt = $this->db->query("SELECT id, name, email, dept, role, active, created_at FROM users ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT id, name, email, dept, role, active, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password, dept, role, active) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['email'],
            md5($data['password']),
            $data['dept'],
            $data['role'] ?? 'user',
            $data['active'] ?? 1
        ]);
        
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $values[] = $data['name'];
        }
        if (isset($data['email'])) {
            $fields[] = "email = ?";
            $values[] = $data['email'];
        }
        if (isset($data['password']) && !empty($data['password'])) {
            $fields[] = "password = ?";
            $values[] = md5($data['password']);
        }
        if (isset($data['dept'])) {
            $fields[] = "dept = ?";
            $values[] = $data['dept'];
        }
        if (isset($data['role'])) {
            $fields[] = "role = ?";
            $values[] = $data['role'];
        }
        if (isset($data['active'])) {
            $fields[] = "active = ?";
            $values[] = $data['active'];
        }
        
        $values[] = $id;
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
    
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ? AND id != 1");
        return $stmt->execute([$id]);
    }
    
    public function getStats($userId) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Concluída' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'Executando' THEN 1 ELSE 0 END) as inProgress,
                SUM(CASE WHEN status = 'Pendente' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status != 'Concluída' AND date(due_date) < date('now') THEN 1 ELSE 0 END) as overdue
            FROM tasks 
            WHERE responsible_id = ?
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();
        
        $stats['completionRate'] = $stats['total'] > 0 
            ? round(($stats['completed'] / $stats['total']) * 100) 
            : 0;
        
        return $stats;
    }
}
