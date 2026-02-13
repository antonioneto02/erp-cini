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
        case 'update_profile':
            // Atualizar perfil do usuário (nome, email, dept)
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validações
            if (empty($data['name']) || empty($data['email'])) {
                throw new Exception('Nome e email são obrigatórios');
            }
            
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }
            
            // Verificar se email já existe (exceto o do próprio usuário)
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$data['email'], $user_id]);
            if ($stmt->rowCount() > 0) {
                throw new Exception('Email já cadastrado');
            }
            
            // Atualizar
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, dept = ? WHERE id = ?");
            $stmt->execute([
                htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($data['email'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($data['dept'] ?? '', ENT_QUOTES, 'UTF-8'),
                $user_id
            ]);
            
            AuthHelper::logActivity($user_id, 'users', $user_id, 'update', ['field' => 'profile'], ['updated' => date('Y-m-d H:i:s')]);
            
            echo json_encode(['success' => true, 'message' => 'Perfil atualizado com sucesso']);
            break;
            
        case 'change_password':
            // Alterar senha do usuário
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['current_password']) || empty($data['new_password'])) {
                throw new Exception('Senhas obrigatórias');
            }
            
            // Buscar usuário atual
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            // Validar senha atual
            if (md5($data['current_password']) !== $user['password']) {
                throw new Exception('Senha atual incorreta');
            }
            
            // Validar comprimento da nova senha
            if (strlen($data['new_password']) < 6) {
                throw new Exception('Nova senha deve ter pelo menos 6 caracteres');
            }
            
            // Atualizar senha
            $new_password_hash = md5($data['new_password']);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$new_password_hash, $user_id]);
            
            AuthHelper::logActivity($user_id, 'users', $user_id, 'update', ['field' => 'password'], ['changed' => date('Y-m-d H:i:s')]);
            
            echo json_encode(['success' => true, 'message' => 'Senha alterada com sucesso']);
            break;
            
        case 'upload_avatar':
            // Upload de avatar do usuário
            if (!isset($_FILES['avatar'])) {
                throw new Exception('Arquivo não enviado');
            }
            
            $file = $_FILES['avatar'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erro no upload: ' . $file['error']);
            }
            
            if ($file['size'] > $max_size) {
                throw new Exception('Arquivo muito grande (máximo 5MB)');
            }
            
            // Validar tipo de arquivo
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime, $allowed_types)) {
                throw new Exception('Tipo de arquivo não permitido. Use PNG, JPG, GIF ou WebP');
            }
            
            // Criar diretório de avatares se não existir
            $avatar_dir = $_SERVER['DOCUMENT_ROOT'] . '/assets/avatars';
            if (!is_dir($avatar_dir)) {
                mkdir($avatar_dir, 0755, true);
            }
            
            // Gerar nome único para o arquivo
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            $filepath = $avatar_dir . '/' . $filename;
            $web_path = '/assets/avatars/' . $filename;
            
            // Mover arquivo
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Erro ao salvar arquivo');
            }
            
            // Atualizar campo avatar_path no banco
            $stmt = $conn->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
            $stmt->execute([$web_path, $user_id]);
            
            AuthHelper::logActivity($user_id, 'users', $user_id, 'update', ['field' => 'avatar'], ['uploaded' => $filename]);
            
            echo json_encode(['success' => true, 'message' => 'Avatar atualizado com sucesso', 'avatar_path' => $web_path]);
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
