<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Task.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$task = new Task();

try {
    switch ($method) {
        case 'GET':
            requireAuth();
            
            if (isset($_GET['id'])) {
                $result = $task->getById($_GET['id']);
                
                if ($result) {
                    jsonResponse($result);
                } else {
                    jsonResponse(['error' => 'Tarefa não encontrada'], 404);
                }
            } else {
                // Suporte a filtros
                $filters = [
                    'plan_id' => $_GET['plan_id'] ?? null,
                    'responsible_id' => $_GET['responsible_id'] ?? null,
                    'status' => $_GET['status'] ?? null
                ];
                
                $tasks = $task->getAll($filters);
                jsonResponse($tasks);
            }
            break;
            
        case 'POST':
            requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validação
            if (empty($data['description'])) {
                jsonResponse(['error' => 'Descrição obrigatória'], 400);
            }
            
            if (empty($data['plan_id'])) {
                jsonResponse(['error' => 'ID do plano obrigatório'], 400);
            }
            
            if (empty($data['responsible_id'])) {
                jsonResponse(['error' => 'Responsável obrigatório'], 400);
            }
            
            if (empty($data['due_date'])) {
                jsonResponse(['error' => 'Data de vencimento obrigatória'], 400);
            }
            
            // Status padrão
            if (empty($data['status'])) {
                $data['status'] = 'Pendente';
            }
            
            $id = $task->create($data);
            jsonResponse(['id' => $id, 'message' => 'Tarefa criada com sucesso'], 201);
            break;
            
        case 'PUT':
            requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                jsonResponse(['error' => 'ID não fornecido'], 400);
            }
            
            $success = $task->update($data['id'], $data);
            jsonResponse(['success' => $success, 'message' => 'Tarefa atualizada']);
            break;
            
        case 'DELETE':
            requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                jsonResponse(['error' => 'ID não fornecido'], 400);
            }
            
            $success = $task->delete($data['id']);
            jsonResponse(['success' => $success, 'message' => 'Tarefa excluída']);
            break;
            
        default:
            jsonResponse(['error' => 'Método não permitido'], 405);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
