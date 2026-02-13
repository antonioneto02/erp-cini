<?php
require_once '../../config/config.php';
$page_title = 'Administração';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/Database.php';
require_once '../../includes/AuthHelper.php';

// Verificar se é admin
if (!$_SESSION['is_admin']) {
    header('Location: /');
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();
$section = $_GET['s'] ?? 'auditoria';

try {
    $audit_logs = $conn->query("
        SELECT al.*, u.name as user_name FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC LIMIT 100
    ")->fetchAll();
    
    $users = $conn->query("SELECT * FROM users ORDER BY name ASC")->fetchAll();
    $roles = $conn->query("SELECT * FROM roles")->fetchAll();
    $permissions = $conn->query("SELECT * FROM permissions ORDER BY module, action ASC")->fetchAll();
    
    $stats = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM audit_log) as total_audits,
            (SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = DATE('now')) as audits_today,
            (SELECT COUNT(*) FROM notifications_expanded WHERE \"read\" = 0) as total_notifications
    ")->fetch();
} catch (Exception $e) {
    $audit_logs = $users = $roles = $permissions = [];
    $stats = ['total_users' => 0, 'total_audits' => 0, 'audits_today' => 0];
}
?>

<main class="main-content">
    <div class="page-header">
        <h1><i class="bi bi-shield-lock"></i> Administração</h1>
        <p>Gestão de segurança, permissões e auditoria do sistema</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon" style="background: #dbeafe; color: #1e40af;"><i class="bi bi-people-fill" style="font-size: 28px;"></i></div><div><div class="stat-value"><?php echo $stats['total_users']; ?></div><div class="stat-label">Usuários</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #d1fae5; color: #065f46;"><i class="bi bi-shield-check" style="font-size: 28px;"></i></div><div><div class="stat-value"><?php echo $stats['total_audits']; ?></div><div class="stat-label">Eventos Auditados</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #fef3c7; color: #92400e;"><i class="bi bi-clock-history" style="font-size: 28px;"></i></div><div><div class="stat-value"><?php echo $stats['audits_today']; ?></div><div class="stat-label">Auditorias Hoje</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #fee2e2; color: #7f1d1d;"><i class="bi bi-bell" style="font-size: 28px;"></i></div><div><div class="stat-value"><?php echo $stats['total_notifications']; ?></div><div class="stat-label">Notificações</div></div></div>
    </div>

    <div class="tabs-container">
        <a href="?s=auditoria" class="nav-tab <?php echo $section === 'auditoria' ? 'active' : ''; ?>"><i class="bi bi-file-text"></i> Auditoria</a>
        <a href="?s=usuarios" class="nav-tab <?php echo $section === 'usuarios' ? 'active' : ''; ?>"><i class="bi bi-people"></i> Usuários</a>
        <a href="?s=permissoes" class="nav-tab <?php echo $section === 'permissoes' ? 'active' : ''; ?>"><i class="bi bi-lock"></i> Permissões</a>
        <a href="?s=sistema" class="nav-tab <?php echo $section === 'sistema' ? 'active' : ''; ?>"><i class="bi bi-gear"></i> Sistema</a>
    </div>

    <?php if ($section === 'auditoria'): ?>
    <div class="content-section">
        <div class="section-header"><h2>Log de Auditoria</h2><button class="btn btn-info" onclick="exportAuditExcel()"><i class="bi bi-file-earmark-excel"></i> Exportar</button></div>
        <div class="filters">
            <input type="text" id="searchAudit" placeholder="Buscar por usuário ou entidade..." onkeyup="filterAudit()">
            <select id="filterAction" onchange="filterAudit()">
                <option value="">Todas as ações</option>
                <option value="create">Criar</option>
                <option value="update">Atualizar</option>
                <option value="delete">Deletar</option>
                <option value="view">Visualizar</option>
                <option value="export">Exportar</option>
            </select>
        </div>
        <?php if (count($audit_logs) > 0): ?>
        <div class="table-responsive"><table class="data-table" id="auditTable"><thead><tr><th>Data/Hora</th><th>Usuário</th><th>Entidade</th><th>Ação</th><th>Detalhes</th><th>IP</th></tr></thead><tbody>
        <?php foreach ($audit_logs as $log): ?>
        <tr data-action="<?php echo $log['action']; ?>">
            <td><small><?php echo date('d/m H:i:s', strtotime($log['created_at'])); ?></small></td>
            <td><?php echo $log['user_name'] ?? 'Sistema'; ?></td>
            <td><strong><?php echo $log['entity']; ?></strong> #<?php echo $log['entity_id']; ?></td>
            <td><span class="badge" style="background: <?php echo match($log['action']) { 'create' => '#d1fae5', 'update' => '#fef3c7', 'delete' => '#fee2e2', 'view' => '#dbeafe', 'export' => '#e0e7ff', default => '#f3f4f6' }; ?>; color: <?php echo match($log['action']) { 'create' => '#065f46', 'update' => '#92400e', 'delete' => '#7f1d1d', 'view' => '#1e40af', 'export' => '#3730a3', default => '#1f2937' }; ?>"><?php echo ucfirst($log['action']); ?></span></td>
            <td><button class="btn-sm btn-info" onclick="showAuditDetails('<?php echo htmlspecialchars($log['old_values']); ?>', '<?php echo htmlspecialchars($log['new_values']); ?>')">Ver</button></td>
            <td><small><?php echo $log['ip_address']; ?></small></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?>
        <div class="empty-state"><i class="bi bi-file-text"></i><p>Nenhum evento registrado</p></div>
        <?php endif; ?>
    </div>

    <?php elseif ($section === 'usuarios'): ?>
    <div class="content-section">
        <div class="section-header"><h2>Gestão de Usuários</h2><button class="btn btn-primary" onclick="showModal('modalUser')"><i class="bi bi-plus-circle"></i> Novo Usuário</button></div>
        <?php if (count($users) > 0): ?>
        <div class="table-responsive"><table class="data-table"><thead><tr><th>Nome</th><th>Email</th><th>Departamento</th><th>Roles</th><th>Status</th><th>Ações</th></tr></thead><tbody>
        <?php foreach ($users as $u): 
            $user_roles = $conn->query("SELECT r.name FROM roles r INNER JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = {$u['id']}")->fetchAll();
        ?>
        <tr>
            <td><?php echo $u['name']; ?></td>
            <td><?php echo $u['email']; ?></td>
            <td><?php echo $u['dept']; ?></td>
            <td><?php echo implode(', ', array_column($user_roles, 'name')); ?></td>
            <td><span class="badge" style="background: <?php echo $u['active'] ? '#d1fae5' : '#fee2e2'; ?>; color: <?php echo $u['active'] ? '#065f46' : '#7f1d1d'; ?>"><?php echo $u['active'] ? 'Ativo' : 'Inativo'; ?></span></td>
            <td><button class="btn-sm btn-info" onclick="editUser(<?php echo $u['id']; ?>)"><i class="bi bi-pencil"></i></button> <button class="btn-sm btn-danger" onclick="deleteUser(<?php echo $u['id']; ?>)"><i class="bi bi-trash"></i></button></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </div>

    <?php elseif ($section === 'permissoes'): ?>
    <div class="content-section">
        <div class="section-header"><h2>Gestão de Permissões e Roles</h2></div>
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <h3 style="margin-bottom: 15px;">Roles</h3>
                <?php foreach ($roles as $role): ?>
                <div style="padding: 10px; background: white; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 10px; cursor: pointer;" onclick="selectRole(<?php echo $role['id']; ?>)">
                    <strong><?php echo ucfirst($role['name']); ?></strong>
                    <p style="font-size: 12px; color: #64748b; margin: 5px 0 0 0;"><?php echo $role['description']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <div>
                <h3 style="margin-bottom: 15px;">Permissões por Módulo</h3>
                <?php 
                    $modules_perms = [];
                    foreach ($permissions as $perm) {
                        if (!isset($modules_perms[$perm['module']])) {
                            $modules_perms[$perm['module']] = [];
                        }
                        $modules_perms[$perm['module']][] = $perm;
                    }
                ?>
                <?php foreach ($modules_perms as $module => $perms): ?>
                <div style="margin-bottom: 15px; padding: 10px; background: #f8fafc; border-radius: 6px;">
                    <strong style="display: block; margin-bottom: 8px;"><i class="bi bi-folder"></i> <?php echo ucfirst($module); ?></strong>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($perms as $perm): ?>
                        <span class="badge" style="background: #dbeafe; color: #1e40af;"><?php echo ucfirst($perm['action']); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php elseif ($section === 'sistema'): ?>
    <div class="content-section">
        <div class="section-header"><h2>Configurações do Sistema</h2></div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div style="padding: 20px; background: white; border: 1px solid #e2e8f0; border-radius: 8px;">
                <h3 style="margin: 0 0 15px 0;">Versão do Sistema</h3>
                <p style="font-size: 24px; font-weight: 700; color: #1e40af;">2.0 Professional</p>
                <small style="color: #64748b;">Última atualização: 13/02/2026</small>
            </div>
            <div style="padding: 20px; background: white; border: 1px solid #e2e8f0; border-radius: 8px;">
                <h3 style="margin: 0 0 15px 0;">Banco de Dados</h3>
                <p style="font-size: 24px; font-weight: 700; color: #065f46;">SQLite3</p>
                <button class="btn btn-warning" onclick="backupDatabase()" style="margin-top: 10px;"><i class="bi bi-download"></i> Fazer Backup</button>
            </div>
            <div style="padding: 20px; background: white; border: 1px solid #e2e8f0; border-radius: 8px;">
                <h3 style="margin: 0 0 15px 0;">PHP Version</h3>
                <p style="font-size: 24px; font-weight: 700; color: #1e40af;"><?php echo phpversion(); ?></p>
                <small style="color: #64748b;">Extensões carregadas: exif, json, pdo</small>
            </div>
        </div>
        <div style="margin-top: 20px; padding: 20px; background: white; border: 1px solid #e2e8f0; border-radius: 8px;">
            <h3>Limpeza de Sistema</h3>
            <button class="btn btn-secondary" onclick="clearNotifications()"><i class="bi bi-trash"></i> Limpar Notificações Antigas</button>
            <button class="btn btn-secondary" onclick="clearOldAudit()"><i class="bi bi-trash"></i> Limpar Auditoria Anterior a 90 dias</button>
        </div>
    </div>
    <?php endif; ?>
</main>

<div id="modalUser" class="modal"><div class="modal-content"><div class="modal-header"><h2><i class="bi bi-person-plus"></i> Novo Usuário</h2><button class="modal-close" onclick="closeModal('modalUser')">&times;</button></div><form onsubmit="saveUser(event)"><div class="modal-body"><div class="form-group"><label>Nome *</label><input type="text" name="name" required></div><div class="form-group"><label>Email *</label><input type="email" name="email" required></div><div class="form-group"><label>Departamento</label><input type="text" name="dept"></div><div class="form-group"><label>Senha *</label><input type="password" name="password" required></div><div class="form-group"><label>Role</label><select name="role_id"><?php foreach($roles as $r): ?><option value="<?php echo $r['id']; ?>"><?php echo $r['name']; ?></option><?php endforeach; ?></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modalUser')">Cancelar</button><button type="submit" class="btn btn-primary">Criar</button></div></form></div></div>

<div id="modalAuditDetails" class="modal"><div class="modal-content"><div class="modal-header"><h2>Detalhes da Alteração</h2><button class="modal-close" onclick="closeModal('modalAuditDetails')">&times;</button></div><div class="modal-body"><h4>Valores Anteriores</h4><pre id="oldValues" style="background: #f8fafc; padding: 10px; border-radius: 4px; font-size: 11px; overflow-x: auto;"></pre><h4>Novos Valores</h4><pre id="newValues" style="background: #f8fafc; padding: 10px; border-radius: 4px; font-size: 11px; overflow-x: auto;"></pre></div></div></div>

<style>
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .stat-card { background: white; border-radius: 8px; padding: 15px; border: 1px solid #e2e8f0; display: flex; gap: 12px; align-items: center; }
    .stat-icon { width: 45px; height: 45px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .stat-value { font-size: 24px; font-weight: 700; }
    .stat-label { font-size: 12px; color: #64748b; }
    .tabs-container { display: flex; gap: 10px; border-bottom: 2px solid #e2e8f0; padding-bottom: 5px; margin: 20px 0; overflow-x: auto; }
    .nav-tab { padding: 10px 15px; display: inline-flex; align-items: center; gap: 8px; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.3s; white-space: nowrap; }
    .nav-tab:hover, .nav-tab.active { color: #1e40af; border-bottom-color: #1e40af; }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; }
    .section-header h2 { margin: 0; font-size: 18px; }
    .filters { display: flex; gap: 10px; margin-bottom: 20px; }
    .filters input, .filters select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; font-family: inherit; }
    .filters input { flex: 1; }
    .data-table { width: 100%; border-collapse: collapse; background: white; border: 1px solid #e2e8f0; border-radius: 6px; }
    .data-table thead { background: #f8fafc; font-weight: 600; font-size: 12px; }
    .data-table th { padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; }
    .data-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
    .data-table tbody tr:hover { background: #f8fafc; }
    .badge { padding: 4px 10px; border-radius: 16px; font-size: 11px; font-weight: 600; display: inline-block; }
    .btn-sm { padding: 6px 10px; font-size: 12px; border: none; border-radius: 4px; cursor: pointer; background: #e2e8f0; color: #334155; }
    .btn-info { background: #dbeafe !important; color: #1e40af !important; }
    .btn-danger { background: #fee2e2 !important; color: #7f1d1d !important; }
    .btn-warning { background: #fef3c7 !important; color: #92400e !important; }
    .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center; }
    .modal.active { display: flex; }
    .modal-content { background: white; border-radius: 8px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #e2e8f0; }
    .modal-header h2 { margin: 0; font-size: 18px; }
    .modal-close { background: none; border: none; font-size: 24px; color: #64748b; cursor: pointer; }
    .modal-body { padding: 20px; }
    .modal-footer { padding: 15px 20px; border-top: 1px solid #e2e8f0; display: flex; gap: 10px; justify-content: flex-end; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 600; color: #334155; margin-bottom: 5px; font-size: 13px; }
    .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; font-family: inherit; }
    .table-responsive { overflow-x: auto; }
    .btn { padding: 8px 14px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; transition: 0.3s; }
    .btn-primary { background: #1e40af; color: white; }
    .btn-primary:hover { background: #1e3a8a; }
    .btn-secondary { background: #e2e8f0; color: #334155; }
    .btn-secondary:hover { background: #cbd5e1; }
    .empty-state { text-align: center; padding: 50px 20px; background: white; border-radius: 8px; border: 1px dashed #e2e8f0; }
    .empty-state i { font-size: 40px; color: #cbd5e1; }
    .empty-state p { color: #64748b; margin: 10px 0; }
</style>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
    function showModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }
    
    function filterAudit() {
        const search = document.getElementById('searchAudit')?.value.toLowerCase();
        const action = document.getElementById('filterAction')?.value;
        const rows = document.querySelectorAll('#auditTable tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const rowAction = row.dataset.action;
            const matchSearch = !search || text.includes(search);
            const matchAction = !action || rowAction === action;
            row.style.display = (matchSearch && matchAction) ? '' : 'none';
        });
    }
    
    function showAuditDetails(oldVal, newVal) {
        document.getElementById('oldValues').textContent = oldVal || 'Nenhum valor anterior';
        document.getElementById('newValues').textContent = newVal || 'Nenhum valor novo';
        showModal('modalAuditDetails');
    }
    
    function exportAuditExcel() {
        const table = document.getElementById('auditTable');
        const ws = XLSX.utils.table_to_sheet(table);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Auditoria');
        XLSX.writeFile(wb, `auditoria_${new Date().toISOString().split('T')[0]}.xlsx`);
    }
    
    function saveUser(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        const data = Object.fromEntries(fd);

        if (!data.name || !data.email || !data.password) {
            alert('Preencha nome, email e senha');
            return;
        }

        fetch('<?php echo BASE_URL; ?>/api/users_pro.php?action=user_create', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(response => {
            if (!response.ok) return response.text().then(text => { throw new Error('HTTP ' + response.status + ': ' + text); });
            return response.json();
        })
        .then(r => {
            if (r.success) {
                alert('Usuário criado com sucesso');
                location.reload();
            } else {
                alert('Erro: ' + (r.error || 'Resposta inválida'));
            }
        })
        .catch(err => {
            console.error('Erro ao criar usuário:', err);
            alert('Erro ao criar usuário: ' + err.message);
        });
    }
    
    function editUser(id) { 
        alert('Editar usuário ID: ' + id); 
    }
    
    function deleteUser(id) { 
        if (confirm('Tem certeza que deseja deletar?')) {
            alert('Deletar usuário ID: ' + id);
        }
    }
    
    function selectRole(id) { 
        alert('Editar permissões do role: ' + id); 
    }
    
    function backupDatabase() { 
        alert('Iniciando backup notável de dados...'); 
    }
    
    function clearNotifications() { 
        if (confirm('Isso vai limpar todas as notificações?' )) alert('Limpeza concluída');
    }
    
    function clearOldAudit() { 
        if (confirm('Isso vai limpar auditoria anterior a 90 dias?')) alert('Auditoria limpada');
    }
    
    document.querySelectorAll('.modal').forEach(m => { 
        m.addEventListener('click', (e) => { if (e.target === m) m.classList.remove('active'); }); 
    });
</script>

<?php require_once '../../includes/footer.php'; ?>
