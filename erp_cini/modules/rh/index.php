<?php
require_once '../../config/config.php';
$page_title = 'RH - Recursos Humanos';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/Database.php';
require_once '../../includes/AuthHelper.php';

$db = Database::getInstance();
$conn = $db->getConnection();
$section = $_GET['s'] ?? 'dashboard';

try {
    // Estatísticas
    $stats_query = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE active = 1) as total_colaboradores,
            (SELECT COUNT(*) FROM rh_vacations WHERE status = 'solicitado') as ferias_pendentes,
            (SELECT COUNT(*) FROM rh_vacations WHERE status = 'aprovado' AND STRFTIME('%Y', start_date) = STRFTIME('%Y', 'now')) as ferias_aprovadas_ano,
            (SELECT COUNT(*) FROM rh_vacations WHERE STRFTIME('%m', start_date) = STRFTIME('%m', 'now') AND STRFTIME('%Y', start_date) = STRFTIME('%Y', 'now')) as ferias_mes
    ");
    $stats_query->execute();
    $stats = $stats_query->fetch();
    // Ensure consistent stat keys used in different UI sections
    $stats['pending'] = $stats['ferias_pendentes'] ?? $stats['pending'] ?? 0;
    $stats['approved'] = $stats['ferias_aprovadas_ano'] ?? $stats['approved'] ?? 0;
    $stats['total_users'] = $stats['total_colaboradores'] ?? $stats['total_users'] ?? 0;
    // Current user's admin flag
    $is_admin = $_SESSION['is_admin'] ?? false;
    
    // Dados para as abas
    $vacations = [];
    $users = [];
    
    if ($section !== 'dashboard') {
        $stmt = $conn->prepare("
            SELECT rv.*, u.name as user_name, a.name as approver_name 
            FROM rh_vacations rv 
            LEFT JOIN users u ON rv.user_id = u.id 
            LEFT JOIN users a ON rv.approved_by = a.id 
            ORDER BY rv.start_date DESC
        ");
        $stmt->execute();
        $vacations = $stmt->fetchAll();
        
        $stmt = $conn->prepare("SELECT id, name, email, dept, active, created_at FROM users ORDER BY name ASC");
        $stmt->execute();
        $users = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $stats = ['total_colaboradores' => 0, 'ferias_pendentes' => 0, 'ferias_aprovadas_ano' => 0, 'ferias_mes' => 0];
    $stats['pending'] = $stats['ferias_pendentes'];
    $stats['approved'] = $stats['ferias_aprovadas_ano'];
    $stats['total_users'] = $stats['total_colaboradores'];
    $is_admin = $_SESSION['is_admin'] ?? false;
    $vacations = [];
    $users = [];
}
?>

<main class="main-content">
    <div class="page-header">
        <h1><i class="bi bi-people"></i> Recursos Humanos</h1>
        <p>Gestão de colaboradores, férias e recursos humanos</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #dbeafe; color: #1e40af;"><i class="bi bi-people-fill" style="font-size: 28px;"></i></div>
            <div><div class="stat-value"><?php echo $stats['total_colaboradores']; ?></div><div class="stat-label">Colaboradores Ativos</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef3c7; color: #92400e;"><i class="bi bi-hourglass-split" style="font-size: 28px;"></i></div>
            <div><div class="stat-value"><?php echo $stats['ferias_pendentes']; ?></div><div class="stat-label">Férias Pendentes</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #d1fae5; color: #065f46;"><i class="bi bi-check-circle" style="font-size: 28px;"></i></div>
            <div><div class="stat-value"><?php echo $stats['ferias_aprovadas_ano']; ?></div><div class="stat-label">Férias Aprovadas (Ano)</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #f3e8ff; color: #6d28d9;"><i class="bi bi-calendar-week" style="font-size: 28px;"></i></div>
            <div><div class="stat-value"><?php echo $stats['ferias_mes']; ?></div><div class="stat-label">Férias Este Mês</div></div>
        </div>
    </div>

    <div class="tabs-container" style="margin-top: 25px;">
        <a href="?s=dashboard" class="nav-tab <?php echo $section === 'dashboard' ? 'active' : ''; ?>"><i class="bi bi-graph-up"></i> Dashboard</a>
        <a href="?s=ferias" class="nav-tab <?php echo $section === 'ferias' ? 'active' : ''; ?>"><i class="bi bi-calendar-event"></i> Férias</a>
        <a href="?s=colaboradores" class="nav-tab <?php echo $section === 'colaboradores' ? 'active' : ''; ?>"><i class="bi bi-person-vcard"></i> Colaboradores</a>
        <a href="?s=relatorios" class="nav-tab <?php echo $section === 'relatorios' ? 'active' : ''; ?>"><i class="bi bi-file-earmark-pdf"></i> Relatórios</a>
    </div>

    <?php if ($section === 'dashboard'): ?>
    <div class="section-full" style="margin-top: 25px;">
        <div class="section-header"><h2>Visão Geral</h2></div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="dashboard-card">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <div class="icon-box icon-box-primary"><i class="bi bi-calendar-check" style="font-size: 20px;"></i></div>
                    <h3 style="margin: 0;">Próximas Férias</h3>
                </div>
                <?php 
                $stmt = $conn->prepare("
                    SELECT rv.*, u.name FROM rh_vacations rv 
                    JOIN users u ON rv.user_id = u.id 
                    WHERE rv.start_date >= DATE('now')
                    ORDER BY rv.start_date ASC LIMIT 5
                ");
                $stmt->execute();
                $upcoming = $stmt->fetchAll();
                if (count($upcoming) > 0):
                    foreach ($upcoming as $item):
                ?>
                <div style="padding: 10px 0; border-bottom: 1px solid #e2e8f0;">
                    <strong><?php echo substr($item['name'], 0, 20); ?></strong>
                    <small style="color: #64748b; display: block;"><i class="bi bi-calendar2"></i> <?php echo date('d/m', strtotime($item['start_date'])); ?></small>
                </div>
                <?php endforeach; else: ?>
                <p style="color: #64748b; font-size: 13px;"><i class="bi bi-info-circle"></i> Nenhuma féria programada</p>
                <?php endif; ?>
            </div>
            
            <div class="dashboard-card">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <div class="icon-box icon-box-warning"><i class="bi bi-exclamation-circle" style="font-size: 20px;"></i></div>
                    <h3 style="margin: 0;">Aprovações Pendentes</h3>
                </div>
                <?php 
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count FROM rh_vacations WHERE status = 'solicitado'
                ");
                $stmt->execute();
                $pending_count = $stmt->fetch()['count'];
                ?>
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 36px; font-weight: 700; color: #f59e0b;"><?php echo $pending_count; ?></div>
                    <button class="btn btn-primary" onclick="window.location='?s=ferias'" style="margin-top: 10px; width: 100%;"><i class="bi bi-arrow-right"></i> Ver Pendências</button>
                </div>
            </div>
            
            <div class="dashboard-card">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <div class="icon-box icon-box-info"><i class="bi bi-diagram-3" style="font-size: 20px;"></i></div>
                    <h3 style="margin: 0;">Departamentos</h3>
                </div>
                <?php 
                $stmt = $conn->prepare("
                    SELECT dept, COUNT(*) as count FROM users WHERE active = 1 GROUP BY dept ORDER BY count DESC
                ");
                $stmt->execute();
                $depts = $stmt->fetchAll();
                foreach ($depts as $d):
                ?>
                <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center;">
                    <span><i class="bi bi-building"></i> <?php echo $d['dept']; ?></span>
                    <span style="font-weight: 700; background: #f1f5f9; padding: 2px 8px; border-radius: 12px;"><?php echo $d['count']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php elseif ($section === 'ferias'): ?>
    <div class="section-full" style="margin-top: 25px;">
        <div class="section-header">
            <h2><i class="bi bi-calendar-event"></i> Gestão de Férias</h2>
            <button class="btn btn-primary" onclick="showModal('modalVacation')"><i class="bi bi-plus-circle"></i> Solicitar Férias</button>
        </div>
        
        <?php if (count($vacations) > 0): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Colaborador</th><th>Início</th><th>Término</th><th>Dias</th><th>Status</th><th>Aprovado Por</th><th>Ações</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($vacations as $v): ?>
                    <tr>
                        <td><strong><?php echo $v['user_name'] ?? 'N/A'; ?></strong></td>
                        <td><?php echo date('d/m/Y', strtotime($v['start_date'])); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($v['end_date'])); ?></td>
                        <td><strong><?php echo $v['days']; ?></strong></td>
                        <td><span class="badge" style="background: <?php echo $v['status'] == 'solicitado' ? '#fef3c7' : ($v['status'] == 'aprovado' ? '#d1fae5' : '#fee2e2'); ?>; color: <?php echo $v['status'] == 'solicitado' ? '#92400e' : ($v['status'] == 'aprovado' ? '#065f46' : '#7f1d1d'); ?>"><?php echo ucfirst($v['status']); ?></span></td>
                        <td><?php echo $v['approver_name'] ?? '-'; ?></td>
                        <td><small><a href="#" style="color: #1e40af;">Detalhes</a></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><i class="bi bi-calendar-x"></i><p>Nenhuma solicitação de férias registrada</p></div>
        <?php endif; ?>
    </div>

    <?php elseif ($section === 'colaboradores'): ?>
    <div class="section-full" style="margin-top: 25px;">
        <div class="section-header">
            <h2><i class="bi bi-person-vcard"></i> Colaboradores</h2>
        </div>
        
        <?php if (count($users) > 0): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Nome</th><th>Email</th><th>Departamento</th><th>Status</th><th>Membro Desde</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><strong><?php echo $u['name']; ?></strong></td>
                        <td><?php echo $u['email']; ?></td>
                        <td><?php echo $u['dept']; ?></td>
                        <td><span class="badge" style="background: <?php echo $u['active'] ? '#d1fae5' : '#fee2e2'; ?>; color: <?php echo $u['active'] ? '#065f46' : '#7f1d1d'; ?>"><?php echo $u['active'] ? 'Ativo' : 'Inativo'; ?></span></td>
                        <td><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><i class="bi bi-people"></i><p>Nenhum colaborador ativo</p></div>
        <?php endif; ?>
    </div>

    <?php elseif ($section === 'relatorios'): ?>
    <div class="section-full" style="margin-top: 25px;">
        <div class="section-header"><h2><i class="bi bi-file-earmark-pdf"></i> Relatórios</h2></div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            <div class="report-card" onclick="alert('Relatório em desenvolvimento')">
                <i class="bi bi-file-pdf"></i>
                <h3>Relat. Férias Ano</h3>
                <p>Consolidado de férias</p>
            </div>
            <div class="report-card" onclick="alert('Relatório em desenvolvimento')">
                <i class="bi bi-file-pdf"></i>
                <h3>Relat. Colaboradores</h3>
                <p>Lista de colaboradores</p>
            </div>
            <div class="report-card" onclick="alert('Relatório em desenvolvimento')">
                <i class="bi bi-file-pdf"></i>
                <h3>Relat. Departamentos</h3>
                <p>Por departamento</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

<div id="modalVacation" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="bi bi-calendar-plus"></i> Solicitar Férias</h2>
            <button class="modal-close" onclick="closeModal('modalVacation')">&times;</button>
        </div>
        <form onsubmit="saveVacation(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label>Data Início *</label>
                    <input type="date" name="start_date" required>
                </div>
                <div class="form-group">
                    <label>Data Término *</label>
                    <input type="date" name="end_date" required>
                </div>
                <div class="form-group">
                    <label>Dias de Férias</label>
                    <input type="number" name="days" readonly style="background: #f1f5f9;">
                </div>
                <div class="form-group">
                    <label>Observações</label>
                    <textarea name="notes" rows="4"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalVacation')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Solicitar Férias</button>
            </div>
        </form>
    </div>
</div>

<style>
    .section-full { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; }
    .section-header { display: flex; justify-content: space-between; align-items: center; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; margin-bottom: 20px; }
    .section-header h2 { margin: 0; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .stat-card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; display: flex; align-items: center; gap: 15px; }
    .stat-icon { width: 50px; height: 50px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
    .stat-value { font-size: 24px; font-weight: 700; color: #1e293b; }
    .stat-label { font-size: 12px; color: #64748b; }
    .dashboard-card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; }
    .dashboard-card h3 { margin: 0 0 15px 0; font-size: 14px; display: flex; align-items: center; gap: 8px; }
    .report-card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; text-align: center; cursor: pointer; transition: 0.3s; }
    .report-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .report-card i { font-size: 32px; color: #e74c3c; margin-bottom: 10px; }
    .report-card h3 { margin: 10px 0 5px 0; }
    .report-card p { margin: 0; font-size: 12px; color: #64748b; }
    .tabs-container { display: flex; gap: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 0; }
    .nav-tab { padding: 12px 16px; border-bottom: 3px solid transparent; cursor: pointer; text-decoration: none; color: #64748b; transition: 0.3s; display: flex; align-items: center; gap: 6px; }
    .nav-tab:hover { color: #1e40af; }
    .nav-tab.active { border-bottom-color: #1e40af; color: #1e40af; font-weight: 600; }
    .table-responsive { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; background: white; }
    .data-table thead { background: #f8fafc; font-weight: 600; font-size: 12px; }
    .data-table th { padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; }
    .data-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
    .data-table tbody tr:hover { background: #f8fafc; }
    .badge { padding: 4px 10px; border-radius: 16px; font-size: 11px; font-weight: 600; display: inline-block; }
    .empty-state { text-align: center; padding: 50px 20px; background: white; border-radius: 8px; border: 1px dashed #e2e8f0; }
    .empty-state i { font-size: 40px; color: #cbd5e1; }
    .empty-state p { color: #64748b; margin: 10px 0; }
    .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center; }
    .modal.active { display: flex; }
    .modal-content { background: white; border-radius: 8px; width: 90%; max-width: 500px; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #e2e8f0; }
    .modal-header h2 { margin: 0; font-size: 18px; }
    .modal-close { background: none; border: none; font-size: 24px; color: #64748b; cursor: pointer; }
    .modal-body { padding: 20px; }
    .modal-footer { padding: 15px 20px; border-top: 1px solid #e2e8f0; display: flex; gap: 10px; justify-content: flex-end; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 600; color: #334155; margin-bottom: 5px; font-size: 13px; }
    .form-group input, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; font-family: inherit; }
    .btn { padding: 8px 14px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; transition: 0.3s; }
    .btn-primary { background: #1e40af; color: white; }
    .btn-primary:hover { background: #1e3a8a; }
    .btn-secondary { background: #e2e8f0; color: #334155; }
    .btn-secondary:hover { background: #cbd5e1; }
</style>

<script>
    function showModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }
    
    function saveVacation(e) {
        e.preventDefault();
        const start = new Date(e.target.start_date.value);
        const end = new Date(e.target.end_date.value);
        const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
        e.target.days.value = days;
        alert('Férias solicitadas com sucesso!');
        closeModal('modalVacation');
    }
    
    document.querySelectorAll('.modal').forEach(m => {
        m.addEventListener('click', (e) => {
            if (e.target === m) m.classList.remove('active');
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>


<main class="main-content">
    <div class="page-header">
        <h1><i class="bi bi-people"></i> Recursos Humanos</h1>
        <p>Gestão de férias, usuários e recursos humanos</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon" style="background: #fef3c7;"><i class="bi bi-calendar-check" style="color: #92400e;"></i></div><div><div class="stat-value"><?php echo $stats['pending']; ?></div><div class="stat-label">Férias Pendentes</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #d1fae5;"><i class="bi bi-check-circle" style="color: #065f46;"></i></div><div><div class="stat-value"><?php echo $stats['approved']; ?></div><div class="stat-label">Férias Aprovadas</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #dbeafe;"><i class="bi bi-people-fill" style="color: #1e40af;"></i></div><div><div class="stat-value"><?php echo $stats['total_users']; ?></div><div class="stat-label">Colaboradores</div></div></div>
    </div>

    <div class="tabs-container">
        <a href="?s=ferias" class="nav-tab <?php echo $section === 'ferias' ? 'active' : ''; ?>"><i class="bi bi-calendar-event"></i> Férias</a>
        <?php if ($is_admin): ?>
        <a href="?s=usuarios" class="nav-tab <?php echo $section === 'usuarios' ? 'active' : ''; ?>"><i class="bi bi-people"></i> Usuários</a>
        <?php endif; ?>
    </div>

    <?php if ($section === 'ferias'): ?>
    <div class="content-section">
        <div class="section-header"><h2>Férias</h2><button class="btn btn-primary" onclick="showModal('modalVacation')"><i class="bi bi-plus-circle"></i> Solicitar Férias</button></div>
        <?php if (count($vacations) > 0): ?>
        <div class="table-responsive"><table class="data-table"><thead><tr><th>Colaborador</th><th>Data Início</th><th>Data Fim</th><th>Dias</th><th>Status</th><th>Aprovado Por</th></tr></thead><tbody>
        <?php foreach ($vacations as $v): ?>
        <tr><td><?php echo $v['user_name']; ?></td><td><?php echo date('d/m/Y', strtotime($v['start_date'])); ?></td><td><?php echo date('d/m/Y', strtotime($v['end_date'])); ?></td><td><?php echo $v['days']; ?></td><td><span class="badge" style="background: <?php echo $v['status'] == 'solicitado' ? '#fef3c7' : '#d1fae5'; ?>; color: <?php echo $v['status'] == 'solicitado' ? '#92400e' : '#065f46'; ?>"><?php echo ucfirst($v['status']); ?></span></td><td><?php echo $v['approver_name']; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?>
        <div class="empty-state"><i class="bi bi-calendar-check"></i><p>Nenhuma férias registrada</p></div>
        <?php endif; ?>
    </div>

    <?php elseif ($section === 'usuarios' && $is_admin): ?>
    <div class="content-section">
        <div class="section-header"><h2>Usuários do Sistema</h2><button class="btn btn-primary" onclick="showModal('modalUser')"><i class="bi bi-plus-circle"></i> Novo Usuário</button></div>
        <?php if (count($users) > 0): ?>
        <div class="table-responsive"><table class="data-table"><thead><tr><th>Nome</th><th>Email</th><th>Departamento</th><th>Status</th><th>Ações</th></tr></thead><tbody>
        <?php foreach ($users as $u): ?>
        <tr><td><?php echo $u['name']; ?></td><td><?php echo $u['email']; ?></td><td><?php echo $u['dept']; ?></td><td><span class="badge" style="background: #d1fae5; color: #065f46;">Ativo</span></td><td><button class="btn btn-sm btn-info"><i class="bi bi-pencil"></i></button></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?>
        <div class="empty-state"><i class="bi bi-people"></i><p>Nenhum usuário registrado</p></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<div id="modalVacation" class="modal"><div class="modal-content"><div class="modal-header"><h2><i class="bi bi-calendar-plus"></i> Solicitar Férias</h2><button class="modal-close" onclick="closeModal('modalVacation')">&times;</button></div><form onsubmit="saveVacation(event)"><div class="modal-body"><div class="form-row"><div class="form-group"><label>Data Início *</label><input type="date" name="start_date" required></div><div class="form-group"><label>Data Fim *</label><input type="date" name="end_date" required></div></div><div class="form-group"><label>Observações</label><textarea name="observations" rows="3"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modalVacation')">Cancelar</button><button type="submit" class="btn btn-primary">Solicitar</button></div></form></div></div>

<div id="modalUser" class="modal"><div class="modal-content"><div class="modal-header"><h2><i class="bi bi-plus-circle"></i> Novo Usuário</h2><button class="modal-close" onclick="closeModal('modalUser')">&times;</button></div><form onsubmit="saveUser(event)"><div class="modal-body"><div class="form-group"><label>Nome *</label><input type="text" name="name" required></div><div class="form-group"><label>Email *</label><input type="email" name="email" required></div><div class="form-row"><div class="form-group"><label>Departamento</label><input type="text" name="dept"></div><div class="form-group"><label>Senha *</label><input type="password" name="password" required></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modalUser')">Cancelar</button><button type="submit" class="btn btn-primary">Criar</button></div></form></div></div>

<style>
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .stat-card { background: white; border-radius: 8px; padding: 15px; border: 1px solid #e2e8f0; display: flex; gap: 12px; align-items: center; }
    .stat-icon { width: 45px; height: 45px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .stat-value { font-size: 24px; font-weight: 700; }
    .stat-label { font-size: 12px; color: #64748b; }
    .tabs-container { display: flex; gap: 10px; border-bottom: 2px solid #e2e8f0; padding-bottom: 5px; margin: 20px 0; }
    .nav-tab { padding: 10px 15px; display: inline-flex; align-items: center; gap: 8px; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.3s; }
    .nav-tab:hover, .nav-tab.active { color: #1e40af; border-bottom-color: #1e40af; }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; }
    .section-header h2 { margin: 0; font-size: 18px; }
    .data-table { width: 100%; border-collapse: collapse; background: white; border: 1px solid #e2e8f0; border-radius: 6px; }
    .data-table thead { background: #f8fafc; font-weight: 600; font-size: 12px; }
    .data-table th { padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; }
    .data-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
    .data-table tbody tr:hover { background: #f8fafc; }
    .badge { padding: 4px 10px; border-radius: 16px; font-size: 11px; font-weight: 600; display: inline-block; }
    .btn-sm { padding: 6px 10px; font-size: 12px; border: none; border-radius: 4px; cursor: pointer; background: #e2e8f0; color: #334155; }
    .btn-info { background: #dbeafe; color: #1e40af; }
    .empty-state { text-align: center; padding: 50px 20px; background: white; border-radius: 8px; border: 1px dashed #e2e8f0; }
    .empty-state i { font-size: 40px; color: #cbd5e1; }
    .empty-state p { color: #64748b; margin: 10px 0; }
    .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center; }
    .modal.active { display: flex; }
    .modal-content { background: white; border-radius: 8px; width: 90%; max-width: 500px; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #e2e8f0; }
    .modal-header h2 { margin: 0; font-size: 18px; }
    .modal-close { background: none; border: none; font-size: 24px; color: #64748b; cursor: pointer; }
    .modal-body { padding: 20px; }
    .modal-footer { padding: 15px 20px; border-top: 1px solid #e2e8f0; display: flex; gap: 10px; justify-content: flex-end; }
    .form-group { margin-bottom: 15px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .form-group label { display: block; font-weight: 600; color: #334155; margin-bottom: 5px; font-size: 13px; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; font-family: inherit; }
    .table-responsive { overflow-x: auto; }
</style>

<script>
    function showModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }
    function saveVacation(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fetch('<?php echo BASE_URL; ?>/api/rh.php?action=vacation_create', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(Object.fromEntries(fd))
        })
        .then(response => {
            if (!response.ok) return response.text().then(text => { throw new Error('HTTP ' + response.status + ': ' + text); });
            return response.json();
        })
        .then(r => {
            if (r.success) {
                alert('Férias solicitadas!');
                location.reload();
            } else {
                alert('Erro: ' + (r.error || 'Resposta inválida'));
            }
        })
        .catch(err => {
            console.error('Erro ao solicitar férias:', err);
            alert('Erro ao solicitar férias: ' + err.message);
        });
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
    document.querySelectorAll('.modal').forEach(m => { m.addEventListener('click', (e) => { if (e.target === m) m.classList.remove('active'); }); });
</script>

<?php require_once '../../includes/footer.php'; ?>
