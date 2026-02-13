<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/User.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$user = new User();

try {
    switch ($method) {
        case 'GET':
            requireAuth();
            
            if (isset($_GET['id'])) {
                $result = $user->getById($_GET['id']);
                jsonResponse($result ?: ['error' => 'Usuário não encontr ado'], $result ? 200 : 404);
            } elseif (isset($_GET['stats']) && isset($_GET['user_id'])) {
                $stats = $user->getStats($_GET['user_id']);
                jsonResponse($stats);
            } else {
                requireAdmin();
                $users = $user->getAll();
                jsonResponse($users);
            }
            break;
            
        case 'POST':
            requireAdmin();
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validações
            if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
                jsonResponse(['error' => 'Campos obrigatórios faltando'], 400);
            }
            
            if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
                jsonResponse(['error' => 'Senha deve ter no mínimo ' . PASSWORD_MIN_LENGTH . ' caracteres'], 400);
            }
            
            try {
                $id = $user->create($data);
                jsonResponse(['id' => $id, 'message' => 'Usuário criado com sucesso'], 201);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                    jsonResponse(['error' => 'E-mail já cadastrado'], 409);
                }
                throw $e;
            }
            break;
            
        case 'PUT':
            requireAdmin();
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                jsonResponse(['error' => 'ID não fornecido'], 400);
            }
            
            $success = $user->update($data['id'], $data);
            jsonResponse(['success' => $success, 'message' => 'Usuário atualizado']);
            break;
            
        case 'DELETE':
            requireAdmin();
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                jsonResponse(['error' => 'ID não fornecido'], 400);
            }
            
            if ($data['id'] == 1) {
                jsonResponse(['error' => 'Não é possível excluir o usuário administrador padrão'], 403);
            }
            
            $success = $user->delete($data['id']);
            jsonResponse(['success' => $success, 'message' => 'Usuário excluído']);
            break;
            
        default:
            jsonResponse(['error' => 'Método não permitido'], 405);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
