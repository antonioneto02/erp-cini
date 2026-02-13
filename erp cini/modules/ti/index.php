<?php
require_once '../../config/config.php';
$page_title = 'TI - Help Desk';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/Database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$section = $_GET['s'] ?? 'chamados';

try {
    $tickets = $conn->query("SELECT tt.*, u1.name as user_name, u2.name as assigned_name FROM ti_tickets tt LEFT JOIN users u1 ON tt.user_id = u1.id LEFT JOIN users u2 ON tt.assigned_to = u2.id ORDER BY tt.created_at DESC LIMIT 100")->fetchAll();
    $assets = $conn->query("SELECT ta.*, u.name as responsible_name FROM ti_assets ta LEFT JOIN users u ON ta.responsible_id = u.id ORDER BY ta.code ASC")->fetchAll();
    $users = $conn->query("SELECT id, name FROM users WHERE active = 1")->fetchAll();
    
    $stats = $conn->query("SELECT (SELECT COUNT(*) FROM ti_tickets WHERE status = 'aberto') as open, (SELECT COUNT(*) FROM ti_tickets WHERE status = 'resolvido') as resolved, (SELECT COUNT(*) FROM ti_assets WHERE status = 'ativo') as assets")->fetch();
} catch (Exception $e) {
    $tickets = $assets = $users = [];
    $stats = ['open' => 0, 'resolved' => 0, 'assets' => 0];
}
?>

<main class="main-content">
    <div class="page-header">
        <h1><i class="bi bi-pc-display"></i> TI - Help Desk e Ativos</h1>
        <p>Gestão de chamados técnicos e inventário de equipamentos</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon" style="background: #fee2e2; color: #991b1b;"><i class="bi bi-exclamation-circle" style="font-size: 28px;"></i></div><div><div class="stat-value"><?php echo $stats['open']; ?></div><div class="stat-label">Chamados Abertos</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #d1fae5; color: #065f46;"><i class="bi bi-check-circle" style="font-size: 28px;"></i></div><div><div class="stat-value"><?php echo $stats['resolved']; ?></div><div class="stat-label">Resolvidos</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #dbeafe; color: #1e40af;"><i class="bi bi-hdd" style="font-size: 28px;"></i></div><div><div class="stat-value"><?php echo $stats['assets']; ?></div><div class="stat-label">Equipamentos</div></div></div>
    </div>

    <div class="tabs-container">
        <a href="?s=chamados" class="nav-tab <?php echo $section === 'chamados' ? 'active' : ''; ?>"><i class="bi bi-ticket-detailed"></i> Chamados</a>
        <a href="?s=ativos" class="nav-tab <?php echo $section === 'ativos' ? 'active' : ''; ?>"><i class="bi bi-hdd"></i> Ativos</a>
    </div>

    <?php if ($section === 'chamados'): ?>
    <div class="content-section">
        <div class="section-header"><h2>Chamados de Suporte</h2><button class="btn btn-primary" onclick="showModal('modalTicket')"><i class="bi bi-plus-circle"></i> Novo</button></div>
        <?php if (count($tickets) > 0): ?>
        <div class="table-responsive"><table class="data-table"><thead><tr><th>ID</th><th>Título</th><th>Categoria</th><th>Prioridade</th><th>Solicitante</th><th><i class="bi bi-person-check"></i> Atribuído a</th><th>Status</th><th>Ações</th></tr></thead><tbody>
        <?php foreach ($tickets as $t): ?>
        <tr><td>#<?php echo $t['id']; ?></td><td><?php echo htmlspecialchars(substr($t['title'], 0, 30)); ?></td><td><?php echo ucfirst($t['category']); ?></td><td><span class="badge" style="background: <?php echo $t['priority'] == 'alta' ? '#fee2e2' : '#fef3c7'; ?>; color: <?php echo $t['priority'] == 'alta' ? '#991b1b' : '#92400e'; ?>"><?php echo ucfirst($t['priority']); ?></span></td><td><?php echo htmlspecialchars($t['user_name']); ?></td><td><span style="padding: 4px 8px; background: #f0f4f8; border-radius: 4px; font-size: 12px;"><?php echo $t['assigned_name'] ?? '<em style="color: #94a3b8;">Não atribuído</em>'; ?></span></td><td><span class="badge" style="background: <?php echo $t['status'] == 'aberto' ? '#fee2e2' : '#d1fae5'; ?>; color: <?php echo $t['status'] == 'aberto' ? '#991b1b' : '#065f46'; ?>"><?php echo ucfirst($t['status']); ?></span></td><td><button class="btn btn-sm btn-info"><i class="bi bi-pencil"></i></button></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?>
        <div class="empty-state"><i class="bi bi-inbox"></i><p>Nenhum chamado registrado</p></div>
        <?php endif; ?>
    </div>

    <?php elseif ($section === 'ativos'): ?>
    <div class="content-section">
        <div class="section-header"><h2>Ativos de TI</h2><button class="btn btn-primary" onclick="showModal('modalAsset')"><i class="bi bi-plus-circle"></i> Novo</button></div>
        <?php if (count($assets) > 0): ?>
        <div class="table-responsive"><table class="data-table"><thead><tr><th>Código</th><th>Nome</th><th>Tipo</th><th>Serial</th><th>Localização</th><th>Responsável</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($assets as $a): ?>
        <tr><td><?php echo $a['code']; ?></td><td><?php echo $a['name']; ?></td><td><?php echo $a['asset_type']; ?></td><td><?php echo $a['serial_number']; ?></td><td><?php echo $a['location']; ?></td><td><?php echo $a['responsible_name']; ?></td><td><span class="badge" style="background: #d1fae5; color: #065f46;"><?php echo ucfirst($a['status']); ?></span></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?>
        <div class="empty-state"><i class="bi bi-hdd"></i><p>Nenhum ativo cadastrado</p></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<div id="modalTicket" class="modal"><div class="modal-content"><div class="modal-header"><h2><i class="bi bi-plus-circle"></i> Novo Chamado</h2><button class="modal-close" onclick="closeModal('modalTicket')">&times;</button></div><form onsubmit="saveTicket(event)"><div class="modal-body"><div class="form-group"><label>Título *</label><input type="text" name="title" required></div><div class="form-row"><div class="form-group"><label>Categoria *</label><select name="category" required><option value="">Selecione</option><option value="software">Software</option><option value="hardware">Hardware</option><option value="rede">Rede</option></select></div><div class="form-group"><label>Prioridade</label><select name="priority"><option value="baixa">Baixa</option><option value="media" selected>Média</option><option value="alta">Alta</option></select></div></div><div class="form-group"><label>Descrição *</label><textarea name="description" required rows="3"></textarea></div><div class="form-group"><label><i class="bi bi-person-check"></i> Atribuir a *</label><select name="assigned_to" required><option value="">Selecionar...</option><?php foreach ($users as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option><?php endforeach; ?></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modalTicket')">Cancelar</button><button type="submit" class="btn btn-primary">Criar</button></div></form></div></div>

<div id="modalAsset" class="modal"><div class="modal-content"><div class="modal-header"><h2><i class="bi bi-plus-circle"></i> Novo Ativo</h2><button class="modal-close" onclick="closeModal('modalAsset')">&times;</button></div><form onsubmit="saveAsset(event)"><div class="modal-body"><div class="form-row"><div class="form-group"><label>Código *</label><input type="text" name="code" required></div><div class="form-group"><label>Nome *</label><input type="text" name="name" required></div></div><div class="form-row"><div class="form-group"><label>Tipo *</label><input type="text" name="asset_type" required></div><div class="form-group"><label>Serial</label><input type="text" name="serial_number"></div></div><div class="form-group"><label>Localização *</label><input type="text" name="location" required></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modalAsset')">Cancelar</button><button type="submit" class="btn btn-primary">Cadastrar</button></div></form></div></div>

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
    function saveTicket(e) { e.preventDefault(); const fd = new FormData(e.target); fetch('/api/ti.php?action=ticket_create', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(Object.fromEntries(fd))}).then(r => r.json()).then(r => { if (r.success) { alert('Chamado criado!'); location.reload(); } }); }
    function saveAsset(e) { e.preventDefault(); const fd = new FormData(e.target); fetch('/api/ti.php?action=asset_create', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(Object.fromEntries(fd))}).then(r => r.json()).then(r => { if (r.success) { alert('Ativo cadastrado!'); location.reload(); } }); }
    document.querySelectorAll('.modal').forEach(m => { m.addEventListener('click', (e) => { if (e.target === m) m.classList.remove('active'); }); });
</script>

<?php require_once '../../includes/footer.php'; ?>
