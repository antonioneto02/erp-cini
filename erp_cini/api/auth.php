<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/User.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$user = new User();

try {
    switch ($method) {
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['action'])) {
                switch ($data['action']) {
                    case 'login':
                        $result = $user->login($data['email'], $data['password']);
                        jsonResponse($result);
                        break;
                        
                    case 'logout':
                        $result = $user->logout();
                        jsonResponse($result);
                        break;
                        
                    case 'check':
                        if (isset($_SESSION['user_id'])) {
                            jsonResponse([
                                'authenticated' => true,
                                'user' => [
                                    'id' => $_SESSION['user_id'],
                                    'name' => $_SESSION['user_name'],
                                    'email' => $_SESSION['user_email'],
                                    'isAdmin' => $_SESSION['is_admin']
                                ]
                            ]);
                        } else {
                            jsonResponse(['authenticated' => false]);
                        }
                        break;
                        
                    default:
                        jsonResponse(['error' => 'Ação inválida'], 400);
                }
            } else {
                jsonResponse(['error' => 'Ação não especificada'], 400);
            }
            break;
            
        default:
            jsonResponse(['error' => 'Método não permitido'], 405);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
