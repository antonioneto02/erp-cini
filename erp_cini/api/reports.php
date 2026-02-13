<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Plan.php';
require_once __DIR__ . '/../models/Task.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method !== 'GET') {
        jsonResponse(['error' => 'Método não permitido'], 405);
    }
    
    requireAuth();
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'user_stats':
            // Estatísticas por usuário
            $userId = $_GET['user_id'] ?? $_SESSION['user_id'];
            $user = new User();
            $stats = $user->getStats($userId);
            jsonResponse($stats);
            break;
            
        case 'general_stats':
            // Estatísticas gerais (requer admin)
            requireAdmin();
            
            $db = Database::getInstance()->getConnection();
            
            // Total de usuários ativos
            $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE active = 1");
            $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Total de planos
            $stmt = $db->query("SELECT COUNT(*) as total FROM plans");
            $totalPlans = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Planos por status
            $stmt = $db->query("
                SELECT status, COUNT(*) as count 
                FROM plans 
                GROUP BY status
            ");
            $plansByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Total de tarefas
            $stmt = $db->query("SELECT COUNT(*) as total FROM tasks");
            $totalTasks = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Tarefas por status
            $stmt = $db->query("
                SELECT status, COUNT(*) as count 
                FROM tasks 
                GROUP BY status
            ");
            $tasksByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Tarefas atrasadas
            $stmt = $db->query("
                SELECT COUNT(*) as total 
                FROM tasks 
                WHERE status != 'Concluída' AND due_date < DATE('now')
            ");
            $overdueTasks = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            jsonResponse([
                'users' => $totalUsers,
                'plans' => $totalPlans,
                'plans_by_status' => $plansByStatus,
                'tasks' => $totalTasks,
                'tasks_by_status' => $tasksByStatus,
                'overdue_tasks' => $overdueTasks
            ]);
            break;
            
        case 'export_plans':
            // Exportar planos em JSON
            $plan = new Plan();
            $plans = $plan->getAll();
            
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="planos_' . date('Y-m-d') . '.json"');
            echo json_encode($plans, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'export_tasks':
            // Exportar tarefas em JSON
            $task = new Task();
            $tasks = $task->getAll([]);
            
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="tarefas_' . date('Y-m-d') . '.json"');
            echo json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'export_csv':
            // Exportar tarefas em CSV
            $task = new Task();
            $tasks = $task->getAll([]);
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="tarefas_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // BOM para UTF-8 no Excel
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Cabeçalho
            fputcsv($output, ['ID', 'Plano', 'Descrição', 'Responsável', 'Vencimento', 'Status', 'Concluída em']);
            
            // Dados
            foreach ($tasks as $t) {
                fputcsv($output, [
                    $t['id'],
                    $t['plan_title'] ?? '',
                    $t['description'],
                    $t['responsible_name'] ?? '',
                    $t['due_date'],
                    $t['status'],
                    $t['completed_at'] ?? ''
                ]);
            }
            
            fclose($output);
            exit;
            break;
            
        default:
            jsonResponse(['error' => 'Ação inválida'], 400);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
