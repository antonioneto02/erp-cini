<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Plan.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$plan = new Plan();

try {
    switch ($method) {
        case 'GET':
            requireAuth();
            
            if (isset($_GET['id'])) {
                $result = $plan->getById($_GET['id']);
                
                if ($result) {
                    // Adicionar progresso
                    $progress = $plan->getProgress($_GET['id']);
                    $result['progress'] = $progress;
                    jsonResponse($result);
                } else {
                    jsonResponse(['error' => 'Plano não encontrado'], 404);
                }
            } else {
                $plans = $plan->getAll();
                jsonResponse($plans);
            }
            break;
            
        case 'POST':
            requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['title'])) {
                jsonResponse(['error' => 'Título obrigatório'], 400);
            }
            
            $id = $plan->create($data);
            jsonResponse(['id' => $id, 'message' => 'Plano criado com sucesso'], 201);
            break;
            
        case 'PUT':
            requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                jsonResponse(['error' => 'ID não fornecido'], 400);
            }
            
            $success = $plan->update($data['id'], $data);
            jsonResponse(['success' => $success, 'message' => 'Plano atualizado']);
            break;
            
        case 'DELETE':
            requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                jsonResponse(['error' => 'ID não fornecido'], 400);
            }
            
            $success = $plan->delete($data['id']);
            jsonResponse(['success' => $success, 'message' => 'Plano excluído']);
            break;
            
        default:
            jsonResponse(['error' => 'Método não permitido'], 405);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
