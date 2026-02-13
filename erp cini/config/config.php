<?php
// Configurações gerais do sistema
define('DB_PATH', __DIR__ . '/../db/database.sqlite');
define('BASE_URL', '/analise-plano-acao');
define('SESSION_TIMEOUT', 3600); // 1 hora
define('ENVIRONMENT', 'production');

// Configurações de segurança
define('PASSWORD_MIN_LENGTH', 6);
define('SESSION_NAME', 'plano_acao_session');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 15 * 60); // 15 minutos em segundos

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net; img-src \'self\' data:');

// Carregar classes automaticamente
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AuthHelper.php';

// Função auxiliar para retornar JSON
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para verificar autenticação
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['error' => 'Não autenticado'], 401);
    }
    return $_SESSION['user_id'];
}

// Função para verificar se é admin
function requireAdmin() {
    $userId = requireAuth();
    if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        jsonResponse(['error' => 'Acesso negado. Apenas administradores.'], 403);
    }
    return $userId;
}
