<?php
require_once '../../config/config.php';
$page_title = 'Estoque';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/Database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$section = $_GET['s'] ?? 'produtos';

try {
    $users = $conn->query("SELECT id, name FROM users WHERE active = 1 ORDER BY name")->fetchAll();
    $products = $conn->query("SELECT * FROM stock_products ORDER BY name ASC")->fetchAll();
    $movements = $conn->query("SELECT sm.*, sp.name as product_name, u.name as responsible_name FROM stock_movements sm LEFT JOIN stock_products sp ON sm.product_id = sp.id LEFT JOIN users u ON sm.responsible_id = u.id ORDER BY sm.created_at DESC LIMIT 50")->fetchAll();
    
    $stats = $conn->query("SELECT (SELECT COUNT(*) FROM stock_products) as total_products, (SELECT COUNT(*) FROM stock_products WHERE quantity <= minimum_quantity) as low_stock, (SELECT SUM(quantity * unit_price) FROM stock_products) as total_value")->fetch();
} catch (Exception $e) {
    $users = $products = $movements = [];
    $stats = ['total_products' => 0, 'low_stock' => 0, 'total_value' => 0];
}
?>

<main class="main-content">
    <div class="page-header">
        <h1><i class="bi bi-box-seam"></i> Estoque</h1>
        <p>Gestão de produtos e movimentações</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon" style="background: #dbeafe; color: #1e40af;"><i class="bi bi-box2" style="font-size: 28px;"></i></div><div><div class="stat-value"><?php echo $stats['total_products']; ?></div><div class="stat-label">Produtos</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #fee2e2; color: #7f1d1d;"><i class="bi bi-exclamation-triangle" style="font-size: 28px;"></i></div><div><div class="stat-value"><?php echo $stats['low_stock']; ?></div><div class="stat-label">Estoque Baixo</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #d1fae5; color: #065f46;"><i class="bi bi-cash-coin" style="font-size: 28px;"></i></div><div><div class="stat-value">R$ <?php echo number_format($stats['total_value'] ?? 0, 2, ',', '.'); ?></div><div class="stat-label">Valor Total</div></div></div>
    </div>

    <div class="tabs-container">
        <a href="?s=produtos" class="nav-tab <?php echo $section === 'produtos' ? 'active' : ''; ?>"><i class="bi bi-box-seam"></i> Produtos</a>
        <a href="?s=movimentacoes" class="nav-tab <?php echo $section === 'movimentacoes' ? 'active' : ''; ?>"><i class="bi bi-arrow-left-right"></i> Movimentações</a>
    </div>

    <?php if ($section === 'produtos'): ?>
    <div class="content-section">
        <div class="section-header"><h2>Produtos em Estoque</h2><button class="btn btn-primary" onclick="showModal('modalProduct')"><i class="bi bi-plus-circle"></i> Novo Produto</button></div>
        <?php if (count($products) > 0): ?>
        <div class="table-responsive"><table class="data-table"><thead><tr><th>Código</th><th>Nome</th><th>Categoria</th><th>Quantidade</th><th>Valor Unit.</th><th>Fornecedor</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($products as $p): $status_class = $p['quantity'] <= $p['minimum_quantity'] ? 'low' : 'ok'; ?>
        <tr><td><strong><?php echo $p['code']; ?></strong></td><td><?php echo $p['name']; ?></td><td><?php echo $p['category']; ?></td><td><?php echo $p['quantity']; ?> <?php echo $p['unit']; ?></td><td>R$ <?php echo number_format($p['unit_price'], 2, ',', '.'); ?></td><td><?php echo $p['supplier']; ?></td><td><span class="badge" style="background: <?php echo $status_class === 'low' ? '#fee2e2' : '#d1fae5'; ?>; color: <?php echo $status_class === 'low' ? '#7f1d1d' : '#065f46'; ?>"><?php echo $status_class === 'low' ? 'Baixo' : 'OK'; ?></span></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?>
        <div class="empty-state"><i class="bi bi-box-seam"></i><p>Nenhum produto registrado</p></div>
        <?php endif; ?>
    </div>

    <?php elseif ($section === 'movimentacoes'): ?>
    <div class="content-section">
        <div class="section-header"><h2>Movimentações de Estoque</h2><button class="btn btn-primary" onclick="showModal('modalMovement')"><i class="bi bi-plus-circle"></i> Nova Movimentação</button></div>
        <?php if (count($movements) > 0): ?>
        <div class="table-responsive"><table class="data-table"><thead><tr><th>Produto</th><th>Tipo</th><th>Quantidade</th><th>Motivo</th><th><i class="bi bi-person-check"></i> Responsável</th><th>Data/Hora</th></tr></thead><tbody>
        <?php foreach ($movements as $m): ?>
        <tr><td><?php echo htmlspecialchars($m['product_name']); ?></td><td><span class="badge" style="background: <?php echo $m['type'] == 'entrada' ? '#d1fae5' : '#fee2e2'; ?>; color: <?php echo $m['type'] == 'entrada' ? '#065f46' : '#7f1d1d'; ?>"><?php echo ucfirst($m['type']); ?></span></td><td><?php echo $m['quantity']; ?></td><td><?php echo htmlspecialchars($m['reason']); ?></td><td><span style="padding: 4px 8px; background: #f0f4f8; border-radius: 4px; font-size: 12px;"><?php echo $m['responsible_name'] ?? '<em style="color: #94a3b8;">Não registrado</em>'; ?></span></td><td><?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?>
        <div class="empty-state"><i class="bi bi-arrow-left-right"></i><p>Nenhuma movimentação registrada</p></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<div id="modalProduct" class="modal"><div class="modal-content"><div class="modal-header"><h2><i class="bi bi-plus-circle"></i> Novo Produto</h2><button class="modal-close" onclick="closeModal('modalProduct')">&times;</button></div><form onsubmit="saveProduct(event)"><div class="modal-body"><div class="form-row"><div class="form-group"><label>Código *</label><input type="text" name="code" required></div><div class="form-group"><label>Nome *</label><input type="text" name="name" required></div></div><div class="form-row"><div class="form-group"><label>Categoria</label><input type="text" name="category"></div><div class="form-group"><label>Unidade</label><input type="text" name="unit" value="unid."></div></div><div class="form-row"><div class="form-group"><label>Quantidade Mín. *</label><input type="number" name="minimum_quantity" value="0" required></div><div class="form-group"><label>Quantidade Máx.</label><input type="number" name="maximum_quantity" value="0"></div></div><div class="form-row"><div class="form-group"><label>Valor Unit. (R$) *</label><input type="number" name="unit_price" step="0.01" required></div><div class="form-group"><label>Fornecedor</label><input type="text" name="supplier"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modalProduct')">Cancelar</button><button type="submit" class="btn btn-primary">Criar</button></div></form></div></div>

<div id="modalMovement" class="modal"><div class="modal-content"><div class="modal-header"><h2><i class="bi bi-plus-circle"></i> Nova Movimentação</h2><button class="modal-close" onclick="closeModal('modalMovement')">&times;</button></div><form onsubmit="saveMovement(event)"><div class="modal-body"><div class="form-group"><label>Produto *</label><select name="product_id" required><?php foreach ($products as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?> (<?php echo $p['quantity']; ?> <?php echo $p['unit']; ?>)</option><?php endforeach; ?></select></div><div class="form-row"><div class="form-group"><label>Tipo *</label><select name="type" required><option value="entrada">Entrada</option><option value="saída">Saída</option></select></div><div class="form-group"><label>Quantidade *</label><input type="number" name="quantity" min="1" required></div></div><div class="form-row"><div class="form-group"><label>Motivo *</label><input type="text" name="reason" required></div><div class="form-group"><label><i class="bi bi-person-check"></i> Responsável *</label><select name="responsible_id" required><option value="">Selecionar...</option><?php foreach ($users as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option><?php endforeach; ?></select></div></div><div class="form-group"><label>Referência</label><input type="text" name="reference" placeholder="NF, Pedido, etc."></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modalMovement')">Cancelar</button><button type="submit" class="btn btn-primary">Registrar</button></div></form></div></div>

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
    function saveProduct(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fetch('<?php echo BASE_URL; ?>/api/stock.php?action=product_create', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(Object.fromEntries(fd))
        })
        .then(response => {
            if (!response.ok) return response.text().then(text => { throw new Error('HTTP ' + response.status + ': ' + text); });
            return response.json();
        })
        .then(r => {
            if (r.success) { alert('Produto criado!'); location.reload(); } else { alert('Erro: ' + (r.error || 'Resposta inválida')); }
        })
        .catch(err => { console.error('Erro ao criar produto:', err); alert('Erro ao criar produto: ' + err.message); });
    }

    function saveMovement(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        // normalize type (frontend-friendly value may include accent)
        const data = Object.fromEntries(fd);
        if (data.type) data.type = data.type.replace('á','a');
        fetch('<?php echo BASE_URL; ?>/api/stock.php?action=movement_create', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(response => {
            if (!response.ok) return response.text().then(text => { throw new Error('HTTP ' + response.status + ': ' + text); });
            return response.json();
        })
        .then(r => {
            if (r.success) { alert('Movimentação registrada!'); location.reload(); } else { alert('Erro: ' + (r.error || 'Resposta inválida')); }
        })
        .catch(err => { console.error('Erro ao registrar movimentação:', err); alert('Erro ao registrar movimentação: ' + err.message); });
    }
    document.querySelectorAll('.modal').forEach(m => { m.addEventListener('click', (e) => { if (e.target === m) m.classList.remove('active'); }); });
</script>

<?php require_once '../../includes/footer.php'; ?>
