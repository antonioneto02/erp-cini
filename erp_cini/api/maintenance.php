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
        case 'list_equipment':
            $stmt = $conn->query("SELECT * FROM maintenance_equipment ORDER BY name ASC");
            $equipment = $stmt->fetchAll();
            jsonResponse(['success' => true, 'data' => $equipment]);
            break;

        case 'add_equipment':
            if ($method !== 'POST') throw new Exception('Método não permitido');
            
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO maintenance_equipment 
                (name, type, location, acquisition_date, warranty_expires, status, specifications) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $data['name'] ?? '',
                $data['type'] ?? '',
                $data['location'] ?? '',
                $data['acquisition_date'] ?? null,
                $data['warranty_expires'] ?? null,
                'operational',
                $data['specifications'] ?? ''
            ]);
            
            jsonResponse(['success' => true, 'id' => $conn->lastInsertId()], 201);
            break;

        case 'list_schedule':
            $stmt = $conn->query("
                SELECT ms.*, me.name as equipment_name, u.name as responsible_name 
                FROM maintenance_schedule ms
                LEFT JOIN maintenance_equipment me ON ms.equipment_id = me.id
                LEFT JOIN users u ON ms.responsible_id = u.id
                ORDER BY ms.scheduled_date ASC
            ");
            $schedule = $stmt->fetchAll();
            jsonResponse(['success' => true, 'data' => $schedule]);
            break;

        case 'add_schedule':
            if ($method !== 'POST') throw new Exception('Método não permitido');
            
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO maintenance_schedule 
                (equipment_id, type, scheduled_date, periodicity, responsible_id, status, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $data['equipment_id'] ?? 0,
                $data['type'] ?? 'preventiva',
                $data['scheduled_date'] ?? '',
                $data['periodicity'] ?? '',
                $data['responsible_id'] ?? null,
                'pendente',
                $data['notes'] ?? ''
            ]);
            
            $schedule_id = $conn->lastInsertId();
            
            // Notificar responsável
            if (isset($data['responsible_id']) && !empty($data['responsible_id'])) {
                AuthHelper::createNotification($data['responsible_id'], 'manutencao_programada', 'Manutenção Programada', 'Uma manutenção foi programada e atribuída a você', 'high', 'manutencao', $schedule_id);
            }
            
            jsonResponse(['success' => true, 'id' => $schedule_id], 201);
            break;

        case 'add_execution':
            if ($method !== 'POST') throw new Exception('Método não permitido');
            
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO maintenance_execution 
                (equipment_id, type, start_date, technician_id, problem_description, solution_description, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $data['equipment_id'] ?? 0,
                $data['type'] ?? 'corretiva',
                $data['start_date'] ?? date('Y-m-d H:i:s'),
                $data['technician_id'] ?? null,
                $data['problem_description'] ?? '',
                $data['solution_description'] ?? '',
                'em_progresso'
            ]);
            
            $execution_id = $conn->lastInsertId();
            
            // Notificar técnico
            if (isset($data['technician_id']) && !empty($data['technician_id'])) {
                AuthHelper::createNotification($data['technician_id'], 'manutencao_execucao', 'Manutenção Atribuída', 'Uma manutenção foi atribuída a você para execução', 'high', 'manutencao', $execution_id);
            }
            
            jsonResponse(['success' => true, 'id' => $execution_id], 201);
            break;

        case 'list_execution':
            $stmt = $conn->query("
                SELECT me.*, eq.name as equipment_name, u.name as technician_name 
                FROM maintenance_execution me
                LEFT JOIN maintenance_equipment eq ON me.equipment_id = eq.id
                LEFT JOIN users u ON me.technician_id = u.id
                ORDER BY me.start_date DESC
            ");
            $execution = $stmt->fetchAll();
            jsonResponse(['success' => true, 'data' => $execution]);
            break;

        case 'update_execution_status':
            if ($method !== 'POST') throw new Exception('Método não permitido');
            
            $data = json_decode(file_get_contents('php://input'), true);
            $status = $data['status'] ?? '';
            $id = $data['id'] ?? 0;
            
            $end_date = $status === 'concluida' ? date('Y-m-d H:i:s') : null;
            
            $stmt = $conn->prepare("UPDATE maintenance_execution SET status = ?, end_date = ? WHERE id = ?");
            $stmt->execute([$status, $end_date, $id]);
            
            jsonResponse(['success' => true]);
            break;

        case 'get_statistics':
            $stats = [];
            
            // Equipamentos operacionais
            $stmt = $conn->query("SELECT COUNT(*) as count FROM maintenance_equipment WHERE status = 'operational'");
            $stats['operational_equipment'] = $stmt->fetch()['count'];
            
            // Manutenções agendadas
            $stmt = $conn->query("SELECT COUNT(*) as count FROM maintenance_schedule WHERE status = 'pendente'");
            $stats['scheduled_maintenance'] = $stmt->fetch()['count'];
            
            // Manutenções em progresso
            $stmt = $conn->query("SELECT COUNT(*) as count FROM maintenance_execution WHERE status = 'em_progresso'");
            $stats['in_progress'] = $stmt->fetch()['count'];
            
            jsonResponse(['success' => true, 'data' => $stats]);
            break;

        default:
            throw new Exception('Ação não reconhecida', 404);
    }
} catch (Exception $e) {
    http_response_code(400);
    jsonResponse(['success' => false, 'error' => $e->getMessage()]);
}
