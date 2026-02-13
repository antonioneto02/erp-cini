<?php
require_once '../../config/config.php';
$page_title = 'Manutenção';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/Database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$section = $_GET['s'] ?? 'programacao';

try {
    $schedule = $conn->query("SELECT ms.*, me.name as equipment_name, u.name as responsible_name FROM maintenance_schedule ms LEFT JOIN maintenance_equipment me ON ms.equipment_id = me.id LEFT JOIN users u ON ms.responsible_id = u.id ORDER BY ms.scheduled_date DESC")->fetchAll();
    $execution = $conn->query("SELECT me.*, eq.name as equipment_name, u.name as technician_name FROM maintenance_execution me LEFT JOIN maintenance_equipment eq ON me.equipment_id = eq.id LEFT JOIN users u ON me.technician_id = u.id ORDER BY me.start_date DESC")->fetchAll();
    $equipment = $conn->query("SELECT * FROM maintenance_equipment ORDER BY name ASC")->fetchAll();
    $users = $conn->query("SELECT id, name FROM users WHERE active = 1")->fetchAll();
    
    $stats = $conn->query("SELECT (SELECT COUNT(*) FROM maintenance_equipment WHERE status = 'operational') as operational, (SELECT COUNT(*) FROM maintenance_schedule WHERE status = 'pendente') as scheduled, (SELECT COUNT(*) FROM maintenance_execution WHERE status = 'em_progresso') as in_progress, (SELECT COUNT(*) FROM maintenance_execution WHERE status = 'concluida') as completed")->fetch();
} catch (Exception $e) {
    $schedule = $execution = $equipment = $users = [];
    $stats = ['operational' => 0, 'scheduled' => 0, 'in_progress' => 0, 'completed' => 0];
}
?>

<main class="main-content">
    <div class="page-header">
        <h1><i class="bi bi-wrench"></i> Manutenção Industrial</h1>
        <p>Gestão de manutenção: preventiva, corretiva e equipamentos</p>
    </div>

    <!-- Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #d1fae5; color: #065f46;"><i class="bi bi-check-circle" style="font-size: 28px;"></i></div>
            <div><div class="stat-value"><?php echo $stats['operational']; ?></div><div class="stat-label">Operacionais</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef3c7; color: #92400e;"><i class="bi bi-calendar-check" style="font-size: 28px;"></i></div>
            <div><div class="stat-value"><?php echo $stats['scheduled']; ?></div><div class="stat-label">Programadas</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #cffafe; color: #164e63;"><i class="bi bi-tools" style="font-size: 28px;"></i></div>
            <div><div class="stat-value"><?php echo $stats['in_progress']; ?></div><div class="stat-label">Em Execução</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #dbeafe; color: #1e40af;"><i class="bi bi-check2-all" style="font-size: 28px;"></i></div>
            <div><div class="stat-value"><?php echo $stats['completed']; ?></div><div class="stat-label">Concluídas</div></div>
        </div>
    </div>

    <!-- Abas -->
    <div class="tabs-container">
        <a href="?s=programacao" class="nav-tab <?php echo $section === 'programacao' ? 'active' : ''; ?>"><i class="bi bi-calendar-check"></i> Programação</a>
        <a href="?s=execucao" class="nav-tab <?php echo $section === 'execucao' ? 'active' : ''; ?>"><i class="bi bi-tools"></i> Execução</a>
        <a href="?s=equipamentos" class="nav-tab <?php echo $section === 'equipamentos' ? 'active' : ''; ?>"><i class="bi bi-gear"></i> Equipamentos</a>
    </div>

    <!-- Programação -->
    <?php if ($section === 'programacao'): ?>
    <div class="content-section">
        <div class="section-header">
            <h2>Manutenção Programada</h2>
            <button class="btn btn-primary" onclick="showModal('modalSchedule')"><i class="bi bi-plus-circle"></i> Nova</button>
        </div>
        <?php if (count($schedule) > 0): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Equipamento</th><th>Tipo</th><th>Data</th><th>Periodicidade</th><th>Responsável</th><th>Status</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($schedule as $s): ?>
                <tr>
                    <td>#<?php echo $s['id']; ?></td>
                    <td><?php echo $s['equipment_name']; ?></td>
                    <td><span class="badge" style="background: <?php echo $s['type'] == 'preventiva' ? '#dbeafe' : '#fee2e2'; ?>; color: <?php echo $s['type'] == 'preventiva' ? '#1e40af' : '#991b1b'; ?>"><?php echo ucfirst($s['type']); ?></span></td>
                    <td><?php echo date('d/m/Y', strtotime($s['scheduled_date'])); ?></td>
                    <td><?php echo $s['periodicity']; ?></td>
                    <td><?php echo $s['responsible_name']; ?></td>
                    <td><span class="badge" style="background: #fef3c7; color: #92400e;"><?php echo ucfirst($s['status']); ?></span></td>
                    <td><button class="btn btn-sm btn-info" onclick="editSchedule(<?php echo $s['id']; ?>)"><i class="bi bi-pencil"></i></button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><i class="bi bi-calendar-x"></i><p>Nenhuma manutenção programada</p></div>
        <?php endif; ?>
    </div>

    <!-- Execução -->
    <?php elseif ($section === 'execucao'): ?>
    <div class="content-section">
        <div class="section-header">
            <h2>Manutenção em Execução</h2>
            <button class="btn btn-primary" onclick="showModal('modalExecution')"><i class="bi bi-plus-circle"></i> Registrar</button>
        </div>
        <?php if (count($execution) > 0): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Equipamento</th><th>Tipo</th><th>Início</th><th>Técnico</th><th>Problema</th><th>Status</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($execution as $e): ?>
                <tr>
                    <td>#<?php echo $e['id']; ?></td>
                    <td><?php echo $e['equipment_name']; ?></td>
                    <td><span class="badge" style="background: <?php echo $e['type'] == 'preventiva' ? '#dbeafe' : '#fee2e2'; ?>; color: <?php echo $e['type'] == 'preventiva' ? '#1e40af' : '#991b1b'; ?>"><?php echo ucfirst($e['type']); ?></span></td>
                    <td><?php echo date('d/m H:i', strtotime($e['start_date'])); ?></td>
                    <td><?php echo $e['technician_name']; ?></td>
                    <td><?php echo substr($e['problem_description'], 0, 25) . '...'; ?></td>
                    <td><span class="badge" style="background: #cffafe; color: #164e63;"><?php echo ucfirst(str_replace('_', ' ', $e['status'])); ?></span></td>
                    <td><?php if ($e['status'] === 'em_progresso'): ?><button class="btn btn-sm btn-success" onclick="finishExecution(<?php echo $e['id']; ?>)"><i class="bi bi-check"></i></button><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><i class="bi bi-tools"></i><p>Nenhuma manutenção em execução</p></div>
        <?php endif; ?>
    </div>

    <!-- Equipamentos -->
    <?php elseif ($section === 'equipamentos'): ?>
    <div class="content-section">
        <div class="section-header">
            <h2>Equipamentos</h2>
            <button class="btn btn-primary" onclick="showModal('modalEquipment')"><i class="bi bi-plus-circle"></i> Novo</button>
        </div>
        <?php if (count($equipment) > 0): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Nome</th><th>Tipo</th><th>Localização</th><th>Aquisição</th><th>Garantia</th><th>Status</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($equipment as $eq): ?>
                <tr>
                    <td>#<?php echo $eq['id']; ?></td>
                    <td><?php echo $eq['name']; ?></td>
                    <td><?php echo $eq['type']; ?></td>
                    <td><?php echo $eq['location']; ?></td>
                    <td><?php echo $eq['acquisition_date'] ? date('d/m/Y', strtotime($eq['acquisition_date'])) : '-'; ?></td>
                    <td><?php if ($eq['warranty_expires']) { $days = (strtotime($eq['warranty_expires']) - time()) / 86400; echo $days > 0 ? '<span style="color: #065f46;">✓ Até ' . date('d/m/Y', strtotime($eq['warranty_expires'])) . '</span>' : '<span style="color: #991b1b;">✗ Expirada</span>'; } else echo '-'; ?></td>
                    <td><span class="badge" style="background: #d1fae5; color: #065f46;"><?php echo ucfirst($eq['status']); ?></span></td>
                    <td><button class="btn btn-sm btn-info" onclick="editEquipment(<?php echo $eq['id']; ?>)"><i class="bi bi-pencil"></i></button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><i class="bi bi-gear"></i><p>Nenhum equipamento cadastrado</p></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<!-- Modal Programação -->
<div id="modalSchedule" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="bi bi-calendar-plus"></i> Nova Programação</h2>
            <button class="modal-close" onclick="closeModal('modalSchedule')">&times;</button>
        </div>
        <form onsubmit="saveSchedule(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label>Equipamento *</label>
                    <select name="equipment_id" required>
                        <option value="">Selecione</option>
                        <?php foreach ($equipment as $eq): ?><option value="<?php echo $eq['id']; ?>"><?php echo $eq['name']; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo *</label>
                        <select name="type" required><option value="preventiva">Preventiva</option><option value="corretiva">Corretiva</option></select>
                    </div>
                    <div class="form-group">
                        <label>Data *</label>
                        <input type="date" name="scheduled_date" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Periodicidade</label>
                        <select name="periodicity"><option value="">Selecione</option><option value="7 dias">7 dias</option><option value="15 dias">15 dias</option><option value="30 dias">30 dias</option><option value="60 dias">60 dias</option></select>
                    </div>
                    <div class="form-group">
                        <label><i class="bi bi-person-check"></i> Responsável *</label>
                        <select name="responsible_id" required><option value="">Selecionar...</option><?php foreach ($users as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option><?php endforeach; ?></select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalSchedule')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Criar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Equipamento -->
<div id="modalEquipment" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="bi bi-plus-circle"></i> Novo Equipamento</h2>
            <button class="modal-close" onclick="closeModal('modalEquipment')">&times;</button>
        </div>
        <form onsubmit="saveEquipment(event)">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label>Nome *</label><input type="text" name="name" required></div>
                    <div class="form-group"><label>Tipo *</label><input type="text" name="type" required></div>
                </div>
                <div class="form-group"><label>Localização *</label><input type="text" name="location" required></div>
                <div class="form-row">
                    <div class="form-group"><label>Aquisição</label><input type="date" name="acquisition_date"></div>
                    <div class="form-group"><label>Garantia</label><input type="date" name="warranty_expires"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEquipment')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Cadastrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Execução -->
<div id="modalExecution" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="bi bi-tools"></i> Registrar Manutenção</h2>
            <button class="modal-close" onclick="closeModal('modalExecution')">&times;</button>
        </div>
        <form onsubmit="saveExecution(event)">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Equipamento *</label>
                        <select name="equipment_id" required><option value="">Selecione</option><?php foreach ($equipment as $eq): ?><option value="<?php echo $eq['id']; ?>"><?php echo $eq['name']; ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="form-group">
                        <label>Tipo *</label>
                        <select name="type" required><option value="preventiva">Preventiva</option><option value="corretiva">Corretiva</option></select>
                    </div>
                </div>
                <div class="form-group"><label>Problema *</label><textarea name="problem_description" required rows="2"></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label>Solução</label><textarea name="solution_description" rows="2"></textarea></div>
                    <div class="form-group"><label><i class="bi bi-person-check"></i> Técnico *</label><select name="technician_id" required><option value="">Selecionar...</option><?php foreach ($users as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option><?php endforeach; ?></select></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalExecution')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Registrar</button>
            </div>
        </form>
    </div>
</div>

<style>
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .stat-card { background: white; border-radius: 8px; padding: 15px; border: 1px solid #e2e8f0; display: flex; gap: 12px; align-items: center; }
    .stat-icon { width: 45px; height: 45px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .stat-value { font-size: 24px; font-weight: 700; color: #1e293b; }
    .stat-label { font-size: 12px; color: #64748b; }
    .tabs-container { display: flex; gap: 10px; border-bottom: 2px solid #e2e8f0; padding-bottom: 5px; margin: 20px 0; }
    .nav-tab { padding: 10px 15px; display: inline-flex; align-items: center; gap: 8px; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; text-decoration: none; font-size: 14px; font-weight: 500; transition: all 0.3s; }
    .nav-tab:hover, .nav-tab.active { color: #1e40af; border-bottom-color: #1e40af; }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; }
    .section-header h2 { margin: 0; font-size: 18px; }
    .data-table { width: 100%; border-collapse: collapse; background: white; border: 1px solid #e2e8f0; border-radius: 6px; }
    .data-table thead { background: #f8fafc; font-weight: 600; font-size: 12px; }
    .data-table th { padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; }
    .data-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
    .data-table tbody tr:hover { background: #f8fafc; }
    .badge { display: inline-block; padding: 4px 10px; border-radius: 16px; font-size: 11px; font-weight: 600; }
    .btn-sm { padding: 6px 10px; font-size: 12px; border: none; border-radius: 4px; cursor: pointer; background: #e2e8f0; color: #334155; transition: 0.2s; }
    .btn-info { background: #dbeafe; color: #1e40af; }
    .btn-success { background: #d1fae5; color: #065f46; }
    .btn-sm:hover { opacity: 0.8; }
    .empty-state { text-align: center; padding: 50px 20px; background: white; border-radius: 8px; border: 1px dashed #e2e8f0; }
    .empty-state i { font-size: 40px; color: #cbd5e1; }
    .empty-state p { color: #64748b; margin: 10px 0; }
    .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center; }
    .modal.active { display: flex; }
    .modal-content { background: white; border-radius: 8px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
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
    function saveSchedule(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fetch('<?php echo BASE_URL; ?>/api/maintenance.php?action=add_schedule', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(Object.fromEntries(fd))
        })
        .then(response => { if (!response.ok) return response.text().then(text => { throw new Error('HTTP ' + response.status + ': ' + text); }); return response.json(); })
        .then(r => { if (r.success) { alert('Programação criada!'); location.reload(); } else { alert('Erro: ' + (r.error || 'Resposta inválida')); } })
        .catch(err => { console.error('Erro ao criar programação:', err); alert('Erro ao criar programação: ' + err.message); });
    }

    function saveEquipment(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fetch('<?php echo BASE_URL; ?>/api/maintenance.php?action=add_equipment', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(Object.fromEntries(fd))
        })
        .then(response => { if (!response.ok) return response.text().then(text => { throw new Error('HTTP ' + response.status + ': ' + text); }); return response.json(); })
        .then(r => { if (r.success) { alert('Equipamento cadastrado!'); location.reload(); } else { alert('Erro: ' + (r.error || 'Resposta inválida')); } })
        .catch(err => { console.error('Erro ao cadastrar equipamento:', err); alert('Erro ao cadastrar equipamento: ' + err.message); });
    }

    function saveExecution(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        const data = Object.fromEntries(fd);
        data.start_date = new Date().toISOString();
        fetch('<?php echo BASE_URL; ?>/api/maintenance.php?action=add_execution', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(response => { if (!response.ok) return response.text().then(text => { throw new Error('HTTP ' + response.status + ': ' + text); }); return response.json(); })
        .then(r => { if (r.success) { alert('Manutenção registrada!'); location.reload(); } else { alert('Erro: ' + (r.error || 'Resposta inválida')); } })
        .catch(err => { console.error('Erro ao registrar manutenção:', err); alert('Erro ao registrar manutenção: ' + err.message); });
    }

    function finishExecution(id) {
        if (!confirm('Finalizar?')) return;
        fetch('<?php echo BASE_URL; ?>/api/maintenance.php?action=update_execution_status', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id, status: 'concluida'})
        })
        .then(response => { if (!response.ok) return response.text().then(text => { throw new Error('HTTP ' + response.status + ': ' + text); }); return response.json(); })
        .then(r => { if (r.success) location.reload(); else alert('Erro: ' + (r.error || 'Resposta inválida')); })
        .catch(err => { console.error('Erro ao finalizar execução:', err); alert('Erro ao finalizar execução: ' + err.message); });
    }
    function editSchedule(id) { alert('Edição em desenvolvimento'); }
    function editEquipment(id) { alert('Edição em desenvolvimento'); }
    document.querySelectorAll('.modal').forEach(m => { m.addEventListener('click', (e) => { if (e.target === m) m.classList.remove('active'); }); });
</script>

<?php require_once '../../includes/footer.php'; ?>
