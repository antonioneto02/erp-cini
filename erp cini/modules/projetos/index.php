<?php
require_once '../../config/config.php';
$page_title = 'Projetos';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/Database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$section = $_GET['s'] ?? 'dashboard';

try {
    $users = $conn->query("SELECT id, name FROM users WHERE active = 1 ORDER BY name")->fetchAll();
    $plans = $conn->query("SELECT p.*, u.name as creator, r.name as responsible FROM plans p LEFT JOIN users u ON p.created_by = u.id LEFT JOIN users r ON p.responsible_id = r.id ORDER BY p.start_date DESC")->fetchAll();
    $tasks = $conn->query("SELECT t.*, p.title as plan_title, u.name as responsible FROM tasks t LEFT JOIN plans p ON t.plan_id = p.id LEFT JOIN users u ON t.responsible_id = u.id ORDER BY t.due_date DESC")->fetchAll();
    $projects = $conn->query("SELECT pr.*, u.name as leader FROM projects pr LEFT JOIN users u ON pr.leader_id = u.id ORDER BY pr.start_date DESC")->fetchAll();
    
    $stats = $conn->query("SELECT (SELECT COUNT(*) FROM plans WHERE status = 'aberto') as open_plans, (SELECT COUNT(*) FROM plans WHERE status = 'em andamento') as in_progress_plans, (SELECT COUNT(*) FROM plans WHERE status = 'concluído') as completed_plans, (SELECT COUNT(*) FROM tasks WHERE status = 'concluída') as completed_tasks, (SELECT COUNT(*) FROM tasks WHERE status != 'concluída') as pending_tasks, (SELECT COUNT(*) FROM tasks) as total_tasks")->fetch();
    
    $plan_status = $conn->query("SELECT status, COUNT(*) as count FROM plans GROUP BY status")->fetchAll();
    $task_status = $conn->query("SELECT status, COUNT(*) as count FROM tasks GROUP BY status")->fetchAll();
} catch (Exception $e) {
    $users = $plans = $tasks = $projects = [];
    $stats = ['open_plans' => 0, 'completed_tasks' => 0, 'pending_tasks' => 0];
}
?>

<main class="main-content">
    <div class="page-header">
        <h1><i class="bi bi-diagram-3"></i> Projetos</h1>
        <p>Gestão de planos, tarefas e análise visual</p>
    </div>

    <div class="tabs-container">
        <a href="?s=dashboard" class="nav-tab <?php echo $section === 'dashboard' ? 'active' : ''; ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="?s=planos" class="nav-tab <?php echo $section === 'planos' ? 'active' : ''; ?>"><i class="bi bi-list-check"></i> Planos de Ação</a>
        <a href="?s=tarefas" class="nav-tab <?php echo $section === 'tarefas' ? 'active' : ''; ?>"><i class="bi bi-checkbox-checked"></i> Tarefas</a>
    </div>

    <?php if ($section === 'dashboard'): ?>
    <div class="content-section">
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon" style="background: #fef3c7; color: #92400e;"><i class="bi bi-folder-open" style="font-size: 28px;"></i></div><div><div class="stat-value"><?php echo $stats['open_plans']; ?></div><div class="stat-label">Planos Abertos</div></div></div>
            <div class="stat-card"><div class="stat-icon" style="background: #fde047; color: #854d0e;"><i class="bi bi-hourglass" style="font-size: 28px;"></i></div><div><div class="stat-value"><?php echo $stats['in_progress_plans'] ?? 0; ?></div><div class="stat-label">Em Progresso</div></div></div>
            <div class="stat-card"><div class="stat-icon" style="background: #d1fae5; color: #065f46;"><i class="bi bi-check-circle" style="font-size: 28px;"></i></div><div><div class="stat-value"><?php echo $stats['completed_plans'] ?? 0; ?></div><div class="stat-label">Planos Concluídos</div></div></div>
            <div class="stat-card"><div class="stat-icon" style="background: #dbeafe; color: #1e40af;"><i class="bi bi-hourglass-split" style="font-size: 28px;"></i></div><div><div class="stat-value"><?php echo $stats['total_tasks']; ?></div><div class="stat-label">Total de Tarefas</div></div></div>
        </div>

        <div class="charts-grid">
            <div class="chart-container"><h3>Status dos Planos</h3><canvas id="chartPlans"></canvas></div>
            <div class="chart-container"><h3>Progresso de Tarefas</h3><canvas id="chartTasks"></canvas></div>
        </div>

        <div class="timeline-section">
            <h3>Planos Recentes</h3>
            <?php if (count($plans) > 0): ?>
            <div class="timeline">
            <?php foreach (array_slice($plans, 0, 5) as $p): $prct = ($stats['total_tasks'] > 0) ? round((($stats['completed_tasks'] ?? 0) / $stats['total_tasks']) * 100) : 0; ?>
                <div class="timeline-item">
                    <div class="timeline-marker" style="background: <?php echo $p['status'] == 'concluído' ? '#10b981' : ($p['status'] == 'em andamento' ? '#f59e0b' : '#6b7280'); ?>"></div>
                    <div class="timeline-content">
                        <h4><?php echo $p['title']; ?></h4>
                        <p><?php echo substr($p['description'], 0, 100); ?>...</p>
                        <div class="timeline-meta"><span><i class="bi bi-person"></i> <?php echo $p['creator']; ?></span> <span><i class="bi bi-calendar"></i> <?php echo date('d/m', strtotime($p['start_date'])); ?></span> <span class="badge" style="background: <?php echo $p['status'] == 'concluído' ? '#d1fae5' : '#fef3c7'; ?>; color: <?php echo $p['status'] == 'concluído' ? '#065f46' : '#92400e'; ?>"><?php echo ucfirst($p['status']); ?></span></div>
                        <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $prct; ?>%"></div></div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state"><i class="bi bi-folder"></i><p>Nenhum plano registrado</p></div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($section === 'planos'): ?>
    <div class="content-section">
        <div class="section-header"><h2>Planos de Ação</h2><div style="display: flex; gap: 10px;"><button class="btn btn-info" onclick="exportExcel('plans')"><i class="bi bi-file-earmark-excel"></i> Exportar Excel</button><button class="btn btn-primary" onclick="showModal('modalPlan')"><i class="bi bi-plus-circle"></i> Novo Plano</button></div></div>
        <div class="filters"><input type="text" id="searchPlan" placeholder="Buscar planos..." onkeyup="filterTable('plans')"><select id="filterStatus" onchange="filterTable('plans')"><option value="">Todos os Status</option><option value="aberto">Aberto</option><option value="em andamento">Em Andamento</option><option value="concluído">Concluído</option></select></div>
        <?php if (count($plans) > 0): ?>
        <div class="table-responsive"><table class="data-table" id="plans"><thead><tr><th>Título</th><th>Prioridade</th><th>Data Inicial</th><th>Data Final</th><th><i class="bi bi-person-check"></i> Responsável</th><th>Status</th><th>Criado Por</th></tr></thead><tbody>
        <?php foreach ($plans as $p): ?>
        <tr data-status="<?php echo $p['status']; ?>"><td><?php echo htmlspecialchars($p['title']); ?></td><td><span class="badge" style="background: <?php echo $p['priority'] == 'alta' ? '#fee2e2' : '#dbeafe'; ?>; color: <?php echo $p['priority'] == 'alta' ? '#7f1d1d' : '#1e40af'; ?>"><?php echo ucfirst($p['priority']); ?></span></td><td><?php echo date('d/m/Y', strtotime($p['start_date'])); ?></td><td><?php echo date('d/m/Y', strtotime($p['end_date'])); ?></td><td><span style="padding: 4px 8px; background: #f0f4f8; border-radius: 4px; font-size: 12px;"><?php echo $p['responsible'] ?? '<em style="color: #94a3b8;">Não atribuído</em>'; ?></span></td><td><span class="badge" style="background: <?php echo $p['status'] == 'concluído' ? '#d1fae5' : '#fef3c7'; ?>; color: <?php echo $p['status'] == 'concluído' ? '#065f46' : '#92400e'; ?>"><?php echo ucfirst($p['status']); ?></span></td><td><?php echo htmlspecialchars($p['creator']); ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?>
        <div class="empty-state"><i class="bi bi-folder"></i><p>Nenhum plano registrado</p></div>
        <?php endif; ?>
    </div>

    <?php elseif ($section === 'tarefas'): ?>
    <div class="content-section">
        <div class="section-header"><h2>Tarefas</h2><div style="display: flex; gap: 10px;"><button class="btn btn-info" onclick="exportExcel('tasks')"><i class="bi bi-file-earmark-excel"></i> Exportar Excel</button><button class="btn btn-primary" onclick="showModal('modalTask')"><i class="bi bi-plus-circle"></i> Nova Tarefa</button></div></div>
        <div class="filters"><input type="text" id="searchTask" placeholder="Buscar tarefas..." onkeyup="filterTable('tasks')"><select id="filterTaskStatus" onchange="filterTable('tasks')"><option value="">Todos os Status</option><option value="pendente">Pendente</option><option value="em andamento">Em Andamento</option><option value="concluída">Concluída</option></select></div>
        <?php if (count($tasks) > 0): ?>
        <div class="table-responsive"><table class="data-table" id="tasks"><thead><tr><th>Descrição</th><th>Plano</th><th><i class="bi bi-person-check"></i> Responsável</th><th>Data Vencimento</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($tasks as $t): ?>
        <tr data-status="<?php echo $t['status']; ?>"><td><?php echo htmlspecialchars($t['description']); ?></td><td><?php echo htmlspecialchars($t['plan_title']); ?></td><td><span style="padding: 4px 8px; background: #f0f4f8; border-radius: 4px; font-size: 12px;"><?php echo $t['responsible'] ?? '<em style="color: #94a3b8;">Não atribuído</em>'; ?></span></td><td><?php echo date('d/m/Y', strtotime($t['due_date'])); ?></td><td><span class="badge" style="background: <?php echo $t['status'] == 'concluída' ? '#d1fae5' : '#fef3c7'; ?>; color: <?php echo $t['status'] == 'concluída' ? '#065f46' : '#92400e'; ?>"><?php echo ucfirst($t['status']); ?></span></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?>
        <div class="empty-state"><i class="bi bi-list"></i><p>Nenhuma tarefa registrada</p></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<div id="modalPlan" class="modal"><div class="modal-content"><div class="modal-header"><h2><i class="bi bi-plus-circle"></i> Novo Plano de Ação</h2><button class="modal-close" onclick="closeModal('modalPlan')">&times;</button></div><form onsubmit="savePlan(event)"><div class="modal-body"><div class="form-group"><label>Título *</label><input type="text" name="title" required></div><div class="form-group"><label>Descrição *</label><textarea name="description" rows="3" required></textarea></div><div class="form-row"><div class="form-group"><label>Prioridade</label><select name="priority"><option value="baixa">Baixa</option><option value="média" selected>Média</option><option value="alta">Alta</option></select></div><div class="form-group"><label>Status</label><select name="status"><option value="aberto" selected>Aberto</option><option value="em andamento">Em Andamento</option><option value="concluído">Concluído</option></select></div></div><div class="form-row"><div class="form-group"><label>Data Inicial *</label><input type="date" name="start_date" required></div><div class="form-group"><label>Data Final *</label><input type="date" name="end_date" required></div></div><div class="form-group"><label><i class="bi bi-person-check"></i> Responsável</label><select name="responsible_id"><option value="">Selecionar responsável...</option><?php foreach ($users as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option><?php endforeach; ?></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modalPlan')">Cancelar</button><button type="submit" class="btn btn-primary">Criar</button></div></form></div></div>

<div id="modalTask" class="modal"><div class="modal-content"><div class="modal-header"><h2><i class="bi bi-plus-circle"></i> Nova Tarefa</h2><button class="modal-close" onclick="closeModal('modalTask')">&times;</button></div><form onsubmit="saveTask(event)"><div class="modal-body"><div class="form-group"><label>Descrição *</label><textarea name="description" rows="3" required></textarea></div><div class="form-row"><div class="form-group"><label>Plano *</label><select name="plan_id" required><?php foreach ($plans as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['title']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label><i class="bi bi-person-check"></i> Responsável *</label><select name="responsible_id" required><option value="">Selecionar...</option><?php foreach ($users as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option><?php endforeach; ?></select></div></div><div class="form-row"><div class="form-group"><label>Data Vencimento *</label><input type="date" name="due_date" required></div><div class="form-group"><label>Status</label><select name="status"><option value="pendente" selected>Pendente</option><option value="em andamento">Em Andamento</option><option value="concluída">Concluída</option></select></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modalTask')">Cancelar</button><button type="submit" class="btn btn-primary">Criar</button></div></form></div></div>

<style>
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .stat-card { background: white; border-radius: 8px; padding: 15px; border: 1px solid #e2e8f0; display: flex; gap: 12px; align-items: center; }
    .stat-icon { width: 45px; height: 45px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .stat-value { font-size: 24px; font-weight: 700; }
    .stat-label { font-size: 12px; color: #64748b; }
    .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 25px 0; }
    .chart-container { background: white; border-radius: 8px; padding: 20px; border: 1px solid #e2e8f0; }
    .chart-container h3 { margin: 0 0 15px 0; font-size: 16px; color: #1e293b; }
    .chart-container canvas { max-height: 300px; }
    .tabs-container { display: flex; gap: 10px; border-bottom: 2px solid #e2e8f0; padding-bottom: 5px; margin: 20px 0; }
    .nav-tab { padding: 10px 15px; display: inline-flex; align-items: center; gap: 8px; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.3s; }
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
    .btn-info { background: #dbeafe !important; color: #1e40af !important; }
    .timeline-section { margin-top: 30px; }
    .timeline-section h3 { font-size: 16px; color: #1e293b; margin-bottom: 20px; }
    .timeline { position: relative; padding-left: 30px; }
    .timeline-item { display: flex; gap: 15px; margin-bottom: 20px; padding: 15px; background: white; border-radius: 8px; border-left: 3px solid #e2e8f0; }
    .timeline-marker { width: 12px; height: 12px; border-radius: 50%; position: absolute; left: -8px; top: 22px; }
    .timeline-content h4 { margin: 0 0 5px 0; font-size: 14px; color: #1e293b; font-weight: 600; }
    .timeline-content p { margin: 0 0 10px 0; font-size: 12px; color: #64748b; }
    .timeline-meta { display: flex; gap: 15px; flex-wrap: wrap; font-size: 12px; color: #64748b; margin-bottom: 10px; }
    .progress-bar { width: 100%; height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden; }
    .progress-fill { height: 100%; background: #1e40af; transition: width 0.3s; }
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
    .btn { padding: 8px 14px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; transition: 0.3s; }
    .btn-primary { background: #1e40af; color: white; }
    .btn-primary:hover { background: #1e3a8a; }
    .btn-secondary { background: #e2e8f0; color: #334155; }
    .btn-secondary:hover { background: #cbd5e1; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
    function showModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }
    
    function savePlan(e) { 
        e.preventDefault(); 
        const fd = new FormData(e.target); 
        fetch('/api/tasks_pro.php?action=plan_create', {
            method: 'POST', 
            headers: {'Content-Type': 'application/json'}, 
            body: JSON.stringify(Object.fromEntries(fd))
        }).then(r => r.json()).then(r => { 
            if (r.success) { 
                alert('Plano criado com sucesso!'); 
                location.reload(); 
            } else {
                alert('Erro: ' + r.error);
            }
        }); 
    }
    
    function saveTask(e) { 
        e.preventDefault(); 
        const fd = new FormData(e.target); 
        fetch('/api/tasks_pro.php?action=task_create', {
            method: 'POST', 
            headers: {'Content-Type': 'application/json'}, 
            body: JSON.stringify(Object.fromEntries(fd))
        }).then(r => r.json()).then(r => { 
            if (r.success) { 
                alert('Tarefa criada com sucesso!'); 
                location.reload(); 
            } else {
                alert('Erro: ' + r.error);
            }
        }); 
    }
    
    function filterTable(tableId) {
        const search = tableId === 'plans' ? document.getElementById('searchPlan')?.value.toLowerCase() : document.getElementById('searchTask')?.value.toLowerCase();
        const statusFilter = tableId === 'plans' ? document.getElementById('filterStatus')?.value : document.getElementById('filterTaskStatus')?.value;
        const table = document.getElementById(tableId);
        if (!table) return;
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const status = row.dataset.status;
            const matchSearch = !search || text.includes(search);
            const matchStatus = !statusFilter || status === statusFilter;
            row.style.display = (matchSearch && matchStatus) ? '' : 'none';
        });
    }
    
    function exportExcel(type) {
        const table = document.getElementById(type);
        if (!table) return;
        const ws = XLSX.utils.table_to_sheet(table);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, type === 'plans' ? 'Planos' : 'Tarefas');
        XLSX.writeFile(wb, `${type === 'plans' ? 'planos' : 'tarefas'}_${new Date().toISOString().split('T')[0]}.xlsx`);
    }
    
    // Gráficos
    document.addEventListener('DOMContentLoaded', () => {
        <?php if ($section === 'dashboard' && count($plan_status) > 0): ?>
        const chartPlansCtx = document.getElementById('chartPlans')?.getContext('2d');
        if (chartPlansCtx) {
            new Chart(chartPlansCtx, {
                type: 'doughnut',
                data: {
                    labels: [<?php foreach ($plan_status as $s): ?>'<?php echo ucfirst($s['status']); ?>',<?php endforeach; ?>],
                    datasets: [{
                        data: [<?php foreach ($plan_status as $s): ?><?php echo $s['count']; ?>,<?php endforeach; ?>],
                        backgroundColor: ['#fef3c7', '#fde047', '#10b981'],
                        borderColor: 'white',
                        borderWidth: 2
                    }]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
            });
        }
        
        const chartTasksCtx = document.getElementById('chartTasks')?.getContext('2d');
        if (chartTasksCtx) {
            new Chart(chartTasksCtx, {
                type: 'bar',
                data: {
                    labels: [<?php foreach ($task_status as $s): ?>'<?php echo ucfirst($s['status']); ?>',<?php endforeach; ?>],
                    datasets: [{
                        label: 'Quantidade',
                        data: [<?php foreach ($task_status as $s): ?><?php echo $s['count']; ?>,<?php endforeach; ?>],
                        backgroundColor: '#1e40af'
                    }]
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
        }
        <?php endif; ?>
    });
    
    document.querySelectorAll('.modal').forEach(m => { m.addEventListener('click', (e) => { if (e.target === m) m.classList.remove('active'); }); });
</script>

<?php require_once '../../includes/footer.php'; ?>
