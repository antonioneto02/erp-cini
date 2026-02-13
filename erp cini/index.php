<?php
require_once 'config/config.php';

// Se já tem sessão, redirecionar para dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        // Conectar ao banco
        require_once 'includes/Database.php';
        $db = Database::getInstance();
        $conn = $db->getConnection();

        // Buscar usuário
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['password'] === md5($password)) {
            // Login bem-sucedido
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['is_admin'] = ($user['role'] === 'admin');
            $_SESSION['user_dept'] = $user['dept'];

            header('Location: /dashboard.php');
            exit;
        } else {
            $error = 'Email ou senha inválidos';
        }
    } else {
        $error = 'Preencha todos os campos';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ERP CINI</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/styles.css">
    <style>
        body {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        .login-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        .login-logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            object-fit: contain;
            padding: 10px;
        }
        .login-header h1 {
            font-size: 24px;
            margin: 0;
            font-weight: 700;
        }
        .login-header p {
            margin: 8px 0 0;
            font-size: 13px;
            opacity: 0.9;
        }
        .login-body {
            padding: 32px;
        }
        .alert-box {
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 16px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .alert-danger {
            background: rgba(220, 38, 38, 0.1);
            border-left: 4px solid var(--danger);
            color: var(--danger);
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-dark);
        }
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
            font-family: inherit;
            transition: all 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
        .input-group {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-light);
            padding: 4px;
        }
        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }
        .form-check input {
            width: auto;
            cursor: pointer;
        }
        .form-check label {
            margin: 0;
            font-size: 12px;
            cursor: pointer;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-login:hover {
            background: var(--primary-dark);
        }
        .login-footer {
            padding: 16px 32px;
            background: var(--light);
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: 11px;
            color: var(--text-light);
        }
        .credentials {
            margin-top: 16px;
            padding: 12px;
            background: var(--light);
            border-radius: 6px;
            font-size: 12px;
        }
        .credentials strong {
            display: block;
            color: var(--text-dark);
            margin-bottom: 6px;
        }
        .credentials div {
            color: var(--text-light);
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <img src="/assets/image/Cini.png" alt="CINI" class="login-logo">
                <h1>ERP CINI</h1>
                <p>Sistema Integrado de Gestão Industrial</p>
            </div>

            <!-- Body -->
            <div class="login-body">
                <?php if ($error): ?>
                <div class="alert-box alert-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span><?php echo $error; ?></span>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Email ou Usuário</label>
                        <input type="text" name="email" placeholder="admin@sistema.com" required autofocus>
                    </div>

                    <div class="form-group">
                        <label>Senha</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" placeholder="••••••••" required>
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Lembrar-me neste dispositivo</label>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="bi bi-box-arrow-in-right" style="margin-right: 8px;"></i>
                        Entrar
                    </button>
                </form>

                <div class="credentials">
                    <strong>Credenciais de Teste:</strong>
                    <div><strong>Admin:</strong> admin@sistema.com / admin123</div>
                    <div><strong>User:</strong> joao@empresa.com / 123456</div>
                </div>
            </div>

            <!-- Footer -->
            <div class="login-footer">
                <i class="bi bi-shield-check"></i> ERP CINI v1.0 • © 2026 CINI.DEV
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>
</body>
</html>
