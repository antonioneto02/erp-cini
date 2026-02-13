<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AuthHelper.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Não autenticado']));
}

$db = Database::getInstance();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Normalize priority values from UI (Portuguese) to DB-expected values (English)
function normalizePriority($p) {
    if (!$p) return 'medium';
    $p = mb_strtolower(trim($p));
    if (in_array($p, ['alta','high'])) return 'high';
    if (in_array($p, ['média','media','médio','medio','medium'])) return 'medium';
    if (in_array($p, ['baixa','low'])) return 'low';
    return 'medium';
}

// Normalize plan status values (Portuguese -> DB English values)
function normalizeStatus($s) {
    if (!$s) return 'active';
    $s = mb_strtolower(trim($s));
    if (in_array($s, ['aberto','ativo','active','open'])) return 'active';
    if (in_array($s, ['concluído','concluida','concluido','concluídos','concluidos','completed','concluded'])) return 'completed';
    if (in_array($s, ['cancelado','cancelada','cancelled','canceled'])) return 'cancelled';
    return 'active';
}

// Normalize task status values to match DB CHECK values (Portuguese with proper capitalization)
function normalizeTaskStatus($s) {
    if (!$s) return 'Pendente';
    $s = mb_strtolower(trim($s));
    if (in_array($s, ['pendente','pending','pend'])) return 'Pendente';
    if (in_array($s, ['executando','executando','in progress','executing','execut'])) return 'Executando';
    if (in_array($s, ['concluida','concluído','concluido','concluídos','concluidos','concluída','completed','done'])) return 'Concluída';
    return 'Pendente';
}

try {
    switch ($action) {
        // ========== PLANOS ==========
        case 'plan_list':
            AuthHelper::requirePermission('projetos', 'visualizar');
            $stmt = $conn->query("SELECT p.*, u.name as creator FROM plans p LEFT JOIN users u ON p.created_by = u.id ORDER BY p.start_date DESC");
            $plans = $stmt->fetchAll();
            AuthHelper::logActivity('plans', null, 'view');
            echo AuthHelper::response(true, $plans);
            break;
            
        case 'plan_create':
            AuthHelper::requirePermission('projetos', 'criar');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $error = AuthHelper::validateRequired($data, ['title', 'start_date', 'end_date']);
            if ($error) echo AuthHelper::response(false, null, $error) and exit;
            
            if (!AuthHelper::validateDate($data['start_date']) || !AuthHelper::validateDate($data['end_date'])) {
                echo AuthHelper::response(false, null, 'Data inválida');
                exit;
            }
            
            $stmt = $conn->prepare("
                INSERT INTO plans (title, description, priority, status, start_date, end_date, created_by, responsible_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $priority = normalizePriority($data['priority'] ?? null);
            $status = normalizeStatus($data['status'] ?? null);
            $stmt->execute([
                AuthHelper::sanitize($data['title']),
                AuthHelper::sanitize($data['description'] ?? ''),
                $priority,
                $status,
                $data['start_date'],
                $data['end_date'],
                $user_id,
                $data['responsible_id'] ?? null
            ]);
            
            $plan_id = $conn->lastInsertId();
            AuthHelper::logActivity('plans', $plan_id, 'create', null, $data);
            
            // Se houver responsável, notificar
            if (isset($data['responsible_id']) && !empty($data['responsible_id'])) {
                AuthHelper::createNotification($data['responsible_id'], 'plano_atribuido', 'Novo Plano Atribuído', 'Um plano foi atribuído a você: ' . $data['title'], 'high', 'plano', $plan_id);
            }
            
            // Notificar criador
            AuthHelper::createNotification($user_id, 'plano_criado', 'Novo Plano Criado', 'Plano "' . $data['title'] . '" foi criado', 'normal', 'plano', $plan_id);
            
            echo AuthHelper::response(true, ['id' => $plan_id]);
            break;
            
        case 'plan_update':
            AuthHelper::requirePermission('projetos', 'editar');
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id'])) {
                echo AuthHelper::response(false, null, 'ID do plano é obrigatório');
                exit;
            }
            
            // Buscar valores antigos para auditoria
            $stmt = $conn->prepare("SELECT * FROM plans WHERE id = ?");
            $stmt->execute([$data['id']]);
            $old_plan = $stmt->fetch();
            
            if (!$old_plan) {
                echo AuthHelper::response(false, null, 'Plano não encontrado');
                exit;
            }
            
            $updates = [];
            $params = [];
            
            if (isset($data['title'])) {
                $updates[] = "title = ?";
                $params[] = AuthHelper::sanitize($data['title']);
            }
            if (isset($data['description'])) {
                $updates[] = "description = ?";
                $params[] = AuthHelper::sanitize($data['description']);
            }
            if (isset($data['priority'])) {
                $updates[] = "priority = ?";
                $params[] = normalizePriority($data['priority']);
            }
            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = normalizeStatus($data['status']);
            }
            if (isset($data['start_date'])) {
                $updates[] = "start_date = ?";
                $params[] = $data['start_date'];
            }
            if (isset($data['end_date'])) {
                $updates[] = "end_date = ?";
                $params[] = $data['end_date'];
            }
            
            if (empty($updates)) {
                echo AuthHelper::response(false, null, 'Nenhum campo para atualizar');
                exit;
            }
            
            $params[] = $data['id'];
            $sql = "UPDATE plans SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            AuthHelper::logActivity('plans', $data['id'], 'update', $old_plan, $data);
            AuthHelper::createNotification($user_id, 'plano_atualizado', 'Plano Atualizado', 'O plano foi modificado', 'normal', 'plano', $data['id']);
            
            echo AuthHelper::response(true, ['updated' => true]);
            break;
            
        case 'plan_delete':
            AuthHelper::requirePermission('projetos', 'deletar');
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id'])) {
                echo AuthHelper::response(false, null, 'ID do plano é obrigatório');
                exit;
            }
            
            $stmt = $conn->prepare("SELECT * FROM plans WHERE id = ?");
            $stmt->execute([$data['id']]);
            $old_plan = $stmt->fetch();
            
            if (!$old_plan) {
                echo AuthHelper::response(false, null, 'Plano não encontrado');
                exit;
            }
            
            $stmt = $conn->prepare("DELETE FROM plans WHERE id = ?");
            $stmt->execute([$data['id']]);
            
            AuthHelper::logActivity('plans', $data['id'], 'delete', $old_plan, null);
            AuthHelper::createNotification($user_id, 'plano_deletado', 'Plano Deletado', 'O plano foi removido', 'high', 'plano', $data['id']);
            
            echo AuthHelper::response(true, ['deleted' => true]);
            break;

        // ========== TAREFAS ==========
        case 'task_list':
            AuthHelper::requirePermission('projetos', 'visualizar');
            $stmt = $conn->query("SELECT t.*, p.title as plan_title, u.name as responsible FROM tasks t LEFT JOIN plans p ON t.plan_id = p.id LEFT JOIN users u ON t.responsible_id = u.id ORDER BY t.due_date DESC");
            $tasks = $stmt->fetchAll();
            AuthHelper::logActivity('tasks', null, 'view');
            echo AuthHelper::response(true, $tasks);
            break;
            
        case 'task_create':
            AuthHelper::requirePermission('projetos', 'criar');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $error = AuthHelper::validateRequired($data, ['description', 'plan_id', 'due_date']);
            if ($error) echo AuthHelper::response(false, null, $error) and exit;
            
            if (!AuthHelper::validateDate($data['due_date'])) {
                echo AuthHelper::response(false, null, 'Data de vencimento inválida');
                exit;
            }
            
            $stmt = $conn->prepare("
                INSERT INTO tasks (plan_id, description, responsible_id, due_date, status, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $taskStatus = normalizeTaskStatus($data['status'] ?? null);
            $stmt->execute([
                $data['plan_id'],
                AuthHelper::sanitize($data['description']),
                $data['responsible_id'] ?? null,
                $data['due_date'],
                $taskStatus,
                AuthHelper::sanitize($data['notes'] ?? '')
            ]);
            
            $task_id = $conn->lastInsertId();
            AuthHelper::logActivity('tasks', $task_id, 'create', null, $data);
            
            if (isset($data['responsible_id'])) {
                AuthHelper::createNotification($data['responsible_id'], 'tarefa_atribuida', 'Nova Tarefa Atribuída', 'Uma tarefa foi atribuída a você', 'high', 'tarefa', $task_id);
            }
            
            echo AuthHelper::response(true, ['id' => $task_id]);
            break;
            
        case 'task_update':
            AuthHelper::requirePermission('projetos', 'editar');
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id'])) {
                echo AuthHelper::response(false, null, 'ID da tarefa é obrigatório');
                exit;
            }
            
            $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$data['id']]);
            $old_task = $stmt->fetch();
            
            if (!$old_task) {
                echo AuthHelper::response(false, null, 'Tarefa não encontrada');
                exit;
            }
            
            $updates = [];
            $params = [];
            
            if (isset($data['description'])) {
                $updates[] = "description = ?";
                $params[] = AuthHelper::sanitize($data['description']);
            }
            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = normalizeTaskStatus($data['status']);
            }
            if (isset($data['responsible_id'])) {
                $updates[] = "responsible_id = ?";
                $params[] = $data['responsible_id'];
            }
            if (isset($data['due_date'])) {
                $updates[] = "due_date = ?";
                $params[] = $data['due_date'];
            }
            if (isset($data['notes'])) {
                $updates[] = "notes = ?";
                $params[] = AuthHelper::sanitize($data['notes']);
            }
            // If request indicates completion, set completed_at timestamp.
            if ((isset($data['completed_at']) && $data['completed_at']) || (isset($data['status']) && normalizeTaskStatus($data['status']) === 'Concluída')) {
                $updates[] = "completed_at = ?";
                $params[] = date('Y-m-d H:i:s');
            }
            
            if (empty($updates)) {
                echo AuthHelper::response(false, null, 'Nenhum campo para atualizar');
                exit;
            }
            
            $params[] = $data['id'];
            $sql = "UPDATE tasks SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            AuthHelper::logActivity('tasks', $data['id'], 'update', $old_task, $data);
            
            echo AuthHelper::response(true, ['updated' => true]);
            break;
            
        case 'task_delete':
            AuthHelper::requirePermission('projetos', 'deletar');
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id'])) {
                echo AuthHelper::response(false, null, 'ID da tarefa é obrigatório');
                exit;
            }
            
            $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$data['id']]);
            $old_task = $stmt->fetch();
            
            if (!$old_task) {
                echo AuthHelper::response(false, null, 'Tarefa não encontrada');
                exit;
            }
            
            $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$data['id']]);
            
            AuthHelper::logActivity('tasks', $data['id'], 'delete', $old_task, null);
            
            echo AuthHelper::response(true, ['deleted' => true]);
            break;
            
        default:
            echo AuthHelper::response(false, null, 'Ação desconhecida');
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao processar requisição: ' . $e->getMessage()
    ]);
}
