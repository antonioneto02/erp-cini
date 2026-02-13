<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/AuthHelper.php';

$action = $_GET['action'] ?? '';
$db = Database::getInstance();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

try {
    switch ($action) {
        // ===== TÉCNICOS =====
        case 'tecnico_list':
            AuthHelper::requirePermission('manutencao', 'visualizar');
            $stmt = $conn->prepare("
                SELECT mt.*, u.name, u.email, u.dept 
                FROM maintenance_technicians mt
                JOIN users u ON mt.user_id = u.id
                ORDER BY mt.created_at DESC
            ");
            $stmt->execute();
            $technicians = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $technicians]);
            break;
            
        case 'tecnico_create':
            AuthHelper::requirePermission('manutencao', 'criar');
            $data = json_decode(file_get_contents('php://input'), true);
            
            AuthHelper::validateRequired(['user_id', 'specialization'], $data);
            
            // Verificar se usuário existe e não é outro técnico
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$data['user_id']]);
            if (!$stmt->fetch()) throw new Exception('Usuário não encontrado');
            
            // Verificar se já é técnico
            $stmt = $conn->prepare("SELECT id FROM maintenance_technicians WHERE user_id = ?");
            $stmt->execute([$data['user_id']]);
            if ($stmt->fetch()) throw new Exception('Este usuário já é um técnico');
            
            $stmt = $conn->prepare("
                INSERT INTO maintenance_technicians (user_id, specialization, certification, phone, availability, bio)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['user_id'],
                AuthHelper::sanitize($data['specialization']),
                AuthHelper::sanitize($data['certification'] ?? ''),
                AuthHelper::sanitize($data['phone'] ?? ''),
                $data['availability'] ?? 'available',
                AuthHelper::sanitize($data['bio'] ?? '')
            ]);
            
            $tech_id = $conn->lastInsertId();
            
            // Atribuir role 'tecnico' ao usuário
            $stmt = $conn->prepare("SELECT id FROM roles WHERE name = 'tecnico'");
            $stmt->execute();
            $role = $stmt->fetch();
            if ($role) {
                $stmt = $conn->prepare("INSERT OR IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)");
                $stmt->execute([$data['user_id'], $role['id']]);
            }
            
            AuthHelper::logActivity($user_id, 'maintenance_technicians', $tech_id, 'create', [], $data);
            AuthHelper::createNotification($data['user_id'], 'system', 'Você foi promovido a Técnico de Manutenção');
            
            echo json_encode(['success' => true, 'message' => 'Técnico cadastrado com sucesso', 'id' => $tech_id]);
            break;
            
        case 'tecnico_update':
            AuthHelper::requirePermission('manutencao', 'editar');
            $data = json_decode(file_get_contents('php://input'), true);
            AuthHelper::validateRequired(['id'], $data);
            
            $stmt = $conn->prepare("SELECT * FROM maintenance_technicians WHERE id = ?");
            $stmt->execute([$data['id']]);
            $old_tech = $stmt->fetch();
            if (!$old_tech) throw new Exception('Técnico não encontrado');
            
            $stmt = $conn->prepare("
                UPDATE maintenance_technicians 
                SET specialization = ?, certification = ?, phone = ?, availability = ?, bio = ?
                WHERE id = ?
            ");
            $stmt->execute([
                AuthHelper::sanitize($data['specialization'] ?? $old_tech['specialization']),
                AuthHelper::sanitize($data['certification'] ?? $old_tech['certification']),
                AuthHelper::sanitize($data['phone'] ?? $old_tech['phone']),
                $data['availability'] ?? $old_tech['availability'],
                AuthHelper::sanitize($data['bio'] ?? $old_tech['bio']),
                $data['id']
            ]);
            
            AuthHelper::logActivity($user_id, 'maintenance_technicians', $data['id'], 'update', $old_tech, $data);
            echo json_encode(['success' => true, 'message' => 'Técnico atualizado com sucesso']);
            break;
            
        case 'tecnico_delete':
            AuthHelper::requirePermission('manutencao', 'deletar');
            $data = json_decode(file_get_contents('php://input'), true);
            AuthHelper::validateRequired(['id'], $data);
            
            $stmt = $conn->prepare("SELECT user_id FROM maintenance_technicians WHERE id = ?");
            $stmt->execute([$data['id']]);
            $tech = $stmt->fetch();
            if (!$tech) throw new Exception('Técnico não encontrado');
            
            // Remover role de técnico
            $stmt = $conn->prepare("SELECT id FROM roles WHERE name = 'tecnico'");
            $stmt->execute();
            $role = $stmt->fetch();
            if ($role) {
                $stmt = $conn->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id = ?");
                $stmt->execute([$tech['user_id'], $role['id']]);
            }
            
            $stmt = $conn->prepare("DELETE FROM maintenance_technicians WHERE id = ?");
            $stmt->execute([$data['id']]);
            
            AuthHelper::logActivity($user_id, 'maintenance_technicians', $data['id'], 'delete', $tech, []);
            AuthHelper::createNotification($tech['user_id'], 'system', 'Seu acesso de Técnico foi removido');
            
            echo json_encode(['success' => true, 'message' => 'Técnico removido com sucesso']);
            break;
            
        // ===== EQUIPAMENTOS =====
        case 'equipment_list':
            AuthHelper::requirePermission('manutencao', 'visualizar');
            $stmt = $conn->prepare("SELECT * FROM maintenance_equipment ORDER BY name ASC");
            $stmt->execute();
            $equipment = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $equipment]);
            break;
            
        case 'equipment_create':
            AuthHelper::requirePermission('manutencao', 'criar');
            $data = json_decode(file_get_contents('php://input'), true);
            AuthHelper::validateRequired(['name', 'type', 'location'], $data);
            
            $stmt = $conn->prepare("
                INSERT INTO maintenance_equipment (name, type, location, acquisition_date, warranty_expires, status, specifications)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                AuthHelper::sanitize($data['name']),
                AuthHelper::sanitize($data['type']),
                AuthHelper::sanitize($data['location']),
                $data['acquisition_date'] ?? null,
                $data['warranty_expires'] ?? null,
                $data['status'] ?? 'operational',
                AuthHelper::sanitize($data['specifications'] ?? '')
            ]);
            
            $equip_id = $conn->lastInsertId();
            AuthHelper::logActivity($user_id, 'maintenance_equipment', $equip_id, 'create', [], $data);
            
            echo json_encode(['success' => true, 'message' => 'Equipamento cadastrado com sucesso', 'id' => $equip_id]);
            break;
            
        case 'equipment_update':
            AuthHelper::requirePermission('manutencao', 'editar');
            $data = json_decode(file_get_contents('php://input'), true);
            AuthHelper::validateRequired(['id'], $data);
            
            $stmt = $conn->prepare("SELECT * FROM maintenance_equipment WHERE id = ?");
            $stmt->execute([$data['id']]);
            $old_equip = $stmt->fetch();
            if (!$old_equip) throw new Exception('Equipamento não encontrado');
            
            $stmt = $conn->prepare("
                UPDATE maintenance_equipment 
                SET name = ?, type = ?, location = ?, status = ?, specifications = ?
                WHERE id = ?
            ");
            $stmt->execute([
                AuthHelper::sanitize($data['name'] ?? $old_equip['name']),
                AuthHelper::sanitize($data['type'] ?? $old_equip['type']),
                AuthHelper::sanitize($data['location'] ?? $old_equip['location']),
                $data['status'] ?? $old_equip['status'],
                AuthHelper::sanitize($data['specifications'] ?? $old_equip['specifications']),
                $data['id']
            ]);
            
            AuthHelper::logActivity($user_id, 'maintenance_equipment', $data['id'], 'update', $old_equip, $data);
            echo json_encode(['success' => true, 'message' => 'Equipamento atualizado com sucesso']);
            break;
            
        // ===== MANUTENÇÕES =====
        case 'maintenance_create':
            AuthHelper::requirePermission('manutencao', 'criar');
            $data = json_decode(file_get_contents('php://input'), true);
            AuthHelper::validateRequired(['equipment_id', 'type', 'problem_description', 'technician_id'], $data);
            
            $stmt = $conn->prepare("
                INSERT INTO maintenance_execution (equipment_id, type, start_date, technician_id, problem_description, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['equipment_id'],
                $data['type'],
                date('Y-m-d H:i:s'),
                $data['technician_id'],
                AuthHelper::sanitize($data['problem_description']),
                'em_progresso'
            ]);
            
            $maint_id = $conn->lastInsertId();
            AuthHelper::logActivity($user_id, 'maintenance_execution', $maint_id, 'create', [], $data);
            
            // Notificar técnico
            $stmt = $conn->prepare("SELECT user_id FROM maintenance_technicians WHERE id = ?");
            $stmt->execute([$data['technician_id']]);
            $tech = $stmt->fetch();
            if ($tech) {
                AuthHelper::createNotification($tech['user_id'], 'task', 'Nova manutenção atribuída a você');
            }
            
            echo json_encode(['success' => true, 'message' => 'Manutenção iniciada com sucesso', 'id' => $maint_id]);
            break;
            
        case 'maintenance_complete':
            AuthHelper::requirePermission('manutencao', 'editar');
            $data = json_decode(file_get_contents('php://input'), true);
            AuthHelper::validateRequired(['id', 'solution_description'], $data);
            
            $stmt = $conn->prepare("SELECT * FROM maintenance_execution WHERE id = ?");
            $stmt->execute([$data['id']]);
            $old_maint = $stmt->fetch();
            if (!$old_maint) throw new Exception('Manutenção não encontrado');
            
            $stmt = $conn->prepare("
                UPDATE maintenance_execution 
                SET status = ?, end_date = ?, solution_description = ?
                WHERE id = ?
            ");
            $stmt->execute([
                'concluido',
                date('Y-m-d H:i:s'),
                AuthHelper::sanitize($data['solution_description']),
                $data['id']
            ]);
            
            AuthHelper::logActivity($user_id, 'maintenance_execution', $data['id'], 'update', $old_maint, $data);
            echo json_encode(['success' => true, 'message' => 'Manutenção concluída com sucesso']);
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
