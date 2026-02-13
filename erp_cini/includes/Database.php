<?php
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            // Criar diretório db se não existir
            $dbDir = dirname(DB_PATH);
            if (!file_exists($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            $this->pdo = new PDO('sqlite:' . DB_PATH);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            $this->initDatabase();
        } catch (PDOException $e) {
            die("Erro ao conectar ao banco de dados: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    private function initDatabase() {
        // Criar tabelas se não existirem
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                dept TEXT NOT NULL,
                role TEXT DEFAULT 'user' CHECK(role IN ('admin', 'user')),
                avatar_path TEXT,
                active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS plans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT,
                priority TEXT DEFAULT 'medium' CHECK(priority IN ('high', 'medium', 'low')),
                status TEXT DEFAULT 'active' CHECK(status IN ('active', 'completed', 'cancelled')),
                start_date DATE,
                end_date DATE,
                created_by INTEGER,
                responsible_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id),
                FOREIGN KEY (responsible_id) REFERENCES users(id)
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                plan_id INTEGER NOT NULL,
                description TEXT NOT NULL,
                responsible_id INTEGER,
                due_date DATE NOT NULL,
                status TEXT DEFAULT 'Pendente' CHECK(status IN ('Pendente', 'Executando', 'Concluída')),
                notes TEXT,
                completed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,
                FOREIGN KEY (responsible_id) REFERENCES users(id)
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                message TEXT NOT NULL,
                \"read\" INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        // Manutenção
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS maintenance_equipment (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                type TEXT NOT NULL,
                location TEXT NOT NULL,
                acquisition_date DATE,
                warranty_expires DATE,
                status TEXT DEFAULT 'operational',
                specifications TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS maintenance_schedule (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                equipment_id INTEGER NOT NULL,
                type TEXT NOT NULL CHECK(type IN ('preventiva', 'corretiva')),
                scheduled_date DATE NOT NULL,
                periodicity TEXT,
                responsible_id INTEGER,
                status TEXT DEFAULT 'pendente',
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (equipment_id) REFERENCES maintenance_equipment(id),
                FOREIGN KEY (responsible_id) REFERENCES users(id)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS maintenance_execution (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                equipment_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                start_date DATETIME NOT NULL,
                end_date DATETIME,
                technician_id INTEGER,
                problem_description TEXT,
                solution_description TEXT,
                status TEXT DEFAULT 'em_progresso',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (equipment_id) REFERENCES maintenance_equipment(id),
                FOREIGN KEY (technician_id) REFERENCES users(id)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS maintenance_technicians (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                specialization TEXT,
                certification TEXT,
                phone TEXT,
                availability TEXT DEFAULT 'available',
                bio TEXT,
                photo_path TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        // Estoque
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS stock_products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                category TEXT,
                unit TEXT,
                quantity INTEGER DEFAULT 0,
                minimum_quantity INTEGER DEFAULT 0,
                maximum_quantity INTEGER DEFAULT 0,
                unit_price REAL DEFAULT 0,
                supplier TEXT,
                last_update DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS stock_movements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                type TEXT NOT NULL CHECK(type IN ('entrada', 'saida', 'ajuste')),
                quantity INTEGER NOT NULL,
                reason TEXT,
                user_id INTEGER,
                reference TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES stock_products(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");

        // TI - Help Desk
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS ti_tickets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT NOT NULL,
                category TEXT NOT NULL,
                priority TEXT DEFAULT 'media',
                status TEXT DEFAULT 'aberto',
                user_id INTEGER NOT NULL,
                assigned_to INTEGER,
                estimated_resolution DATE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (assigned_to) REFERENCES users(id)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS ti_assets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                asset_type TEXT NOT NULL,
                serial_number TEXT,
                location TEXT,
                responsible_id INTEGER,
                acquisition_date DATE,
                status TEXT DEFAULT 'ativo',
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (responsible_id) REFERENCES users(id)
            )
        ");

        // RH - Férias
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_vacations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                days INTEGER,
                status TEXT DEFAULT 'solicitado',
                approved_by INTEGER,
                observations TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (approved_by) REFERENCES users(id)
            )
        ");

        // Projetos - Já existem plans e tasks, manter compatibilidade
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                start_date DATE,
                end_date DATE,
                status TEXT DEFAULT 'ativo',
                leader_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (leader_id) REFERENCES users(id)
            )
        ");

        // RBAC - Permissões e Roles
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                description TEXT,
                module TEXT NOT NULL,
                action TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS role_permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_id INTEGER NOT NULL,
                permission_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(role_id, permission_id),
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS user_roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, role_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
            )
        ");

        // Auditoria
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                entity TEXT NOT NULL,
                entity_id INTEGER,
                action TEXT NOT NULL CHECK(action IN ('create', 'update', 'delete', 'view', 'export')),
                old_values TEXT,
                new_values TEXT,
                ip_address TEXT,
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        // Melhorias nas notificações
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS notifications_expanded (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                entity_type TEXT,
                entity_id INTEGER,
                action_url TEXT,
                priority TEXT DEFAULT 'normal' CHECK(priority IN ('low', 'normal', 'high')),
                \"read\" INTEGER DEFAULT 0,
                read_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        // Verificação de migrations
        $this->runMigrations();

        // Inicializar RBAC com roles e permissões padrão
        $this->initRBAC();
        
        // Verificar se existe usuário admin, se não criar
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            // Criar usuário admin padrão (senha: admin123 em MD5)
            $this->pdo->exec("
                INSERT INTO users (name, email, password, dept, role, active) 
                VALUES ('Administrador', 'admin@sistema.com', '0192023a7bbd73250516f069df18b500', 'Administração', 'admin', 1)
            ");
            
            // Criar usuário comum de exemplo (senha: 123456 em MD5)
            $this->pdo->exec("
                INSERT INTO users (name, email, password, dept, role, active) 
                VALUES ('João Silva', 'joao@empresa.com', 'e10adc3949ba59abbe56e057f20f883e', 'Operações', 'user', 1)
            ");
        }
    }
    private function initRBAC() {
        // Verificar se roles já foram criadas
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM roles");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            // Criar roles padrão
            $this->pdo->exec("INSERT INTO roles (name, description) VALUES ('admin', 'Administrador com acesso total')");
            $this->pdo->exec("INSERT INTO roles (name, description) VALUES ('gerente', 'Gerente com acesso a relatórios e aprovações')");
            $this->pdo->exec("INSERT INTO roles (name, description) VALUES ('usuario', 'Usuário comum com acesso básico')");
            $this->pdo->exec("INSERT INTO roles (name, description) VALUES ('tecnico', 'Técnico com acesso a manutenção')");
            $this->pdo->exec("INSERT INTO roles (name, description) VALUES ('visualizador', 'Apenas visualização de dados')");

            // Inserir permissões por módulo
            $modules = ['usuarios', 'projetos', 'manutencao', 'estoque', 'ti', 'rh'];
            $actions = ['criar', 'editar', 'deletar', 'visualizar', 'exportar'];
            
            foreach ($modules as $module) {
                foreach ($actions as $action) {
                    $this->pdo->exec("
                        INSERT INTO permissions (name, description, module, action) 
                        VALUES ('{$module}.{$action}', '{$action} em {$module}', '{$module}', '{$action}')
                    ");
                }
            }

            // Atribuir permissões aos roles
            // Admin: full access
            $stmt = $this->pdo->query("SELECT id FROM roles WHERE name = 'admin'");
            $admin_role = $stmt->fetch();
            $stmt = $this->pdo->query("SELECT id FROM permissions");
            $permissions = $stmt->fetchAll();
            foreach ($permissions as $perm) {
                $this->pdo->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES ({$admin_role['id']}, {$perm['id']})");
            }

            // Gerente: maioria das permissões menos deletar usuários
            $stmt = $this->pdo->query("SELECT id FROM roles WHERE name = 'gerente'");
            $gerente_role = $stmt->fetch();
            $stmt = $this->pdo->query("SELECT id FROM permissions WHERE NOT (module = 'usuarios' AND action = 'deletar')");
            $permissions = $stmt->fetchAll();
            foreach ($permissions as $perm) {
                $this->pdo->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES ({$gerente_role['id']}, {$perm['id']})");
            }

            // Técnico: acesso total a manutenção + visualizar em outros
            $stmt = $this->pdo->query("SELECT id FROM roles WHERE name = 'tecnico'");
            $tecnico_role = $stmt->fetch();
            $stmt = $this->pdo->query("SELECT id FROM permissions WHERE module = 'manutencao' OR action = 'visualizar'");
            $permissions = $stmt->fetchAll();
            foreach ($permissions as $perm) {
                $this->pdo->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES ({$tecnico_role['id']}, {$perm['id']})");
            }

            // Usuário: criar, editar, visualizar, exportar (não deletar)
            $stmt = $this->pdo->query("SELECT id FROM roles WHERE name = 'usuario'");
            $user_role = $stmt->fetch();
            $stmt = $this->pdo->query("SELECT id FROM permissions WHERE action IN ('criar', 'editar', 'visualizar', 'exportar') AND module != 'usuarios'");
            $permissions = $stmt->fetchAll();
            foreach ($permissions as $perm) {
                $this->pdo->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES ({$user_role['id']}, {$perm['id']})");
            }

            // Visualizador: apenas visualizar
            $stmt = $this->pdo->query("SELECT id FROM roles WHERE name = 'visualizador'");
            $viewer_role = $stmt->fetch();
            $stmt = $this->pdo->query("SELECT id FROM permissions WHERE action = 'visualizar'");
            $permissions = $stmt->fetchAll();
            foreach ($permissions as $perm) {
                $this->pdo->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES ({$viewer_role['id']}, {$perm['id']})");
            }
        }
    }

    public function logAudit($user_id, $entity, $entity_id, $action, $old_values = null, $new_values = null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_log (user_id, entity, entity_id, action, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $user_id,
            $entity,
            $entity_id,
            $action,
            $old_values ? json_encode($old_values) : null,
            $new_values ? json_encode($new_values) : null,
            $ip,
            $user_agent
        ]);
    }

    public function hasPermission($user_id, $module, $action) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM role_permissions rp
            INNER JOIN user_roles ur ON rp.role_id = ur.role_id
            INNER JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = ? AND p.module = ? AND p.action = ?
        ");
        
        $stmt->execute([$user_id, $module, $action]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    public function getUserRoles($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT r.* FROM roles r
            INNER JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    private function runMigrations() {
        // Adicionar coluna responsible_id na tabela plans se não existir
        try {
            $stmt = $this->pdo->query("PRAGMA table_info(plans)");
            $columns = $stmt->fetchAll();
            $hasResponsibleId = false;
            
            foreach ($columns as $col) {
                if ($col['name'] == 'responsible_id') {
                    $hasResponsibleId = true;
                    break;
                }
            }
            
            if (!$hasResponsibleId) {
                $this->pdo->exec("ALTER TABLE plans ADD COLUMN responsible_id INTEGER REFERENCES users(id)");
            }
        } catch (Exception $e) {
            // Coluna já existe, continuar silenciosamente
        }

        // Adicionar coluna responsible_id na tabela stock_movements se não existir
        try {
            $stmt = $this->pdo->query("PRAGMA table_info(stock_movements)");
            $columns = $stmt->fetchAll();
            $hasResponsibleId = false;
            
            foreach ($columns as $col) {
                if ($col['name'] == 'responsible_id') {
                    $hasResponsibleId = true;
                    break;
                }
            }
            
            if (!$hasResponsibleId) {
                $this->pdo->exec("ALTER TABLE stock_movements ADD COLUMN responsible_id INTEGER REFERENCES users(id)");
            }
        } catch (Exception $e) {
            // Coluna já existe, continuar silenciosamente
        }
    }
}
