<?php
// Sidebar com navegação modular
$user_role = isset($_SESSION['is_admin']) ? 'admin' : 'user';
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="/erp_cini/assets/image/Cini.png" alt="CINI Logo" style="width: 40px; height: 40px; object-fit: contain;">
        </div>
        <div class="sidebar-title">ERP CINI</div>
    </div>

    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <div class="nav-section">
            <div class="nav-label">GERAL</div>
            <a href="/erp_cini/dashboard.php" class="nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        </div>

        <!-- Módulo TI -->
        <div class="nav-section">
            <div class="nav-label">TI</div>
            <a href="/erp_cini/modules/ti/index.php?s=chamados" class="nav-item <?php echo $current_module === 'ti' ? 'active' : ''; ?>">
                <i class="bi bi-ticket-detailed"></i>
                <span>Chamados</span>
            </a>
            <a href="/erp_cini/modules/ti/index.php?s=ativos" class="nav-item">
                <i class="bi bi-hdd"></i>
                <span>Ativos</span>
            </a>
        </div>

        <!-- Módulo RH -->
        <div class="nav-section">
            <div class="nav-label">RECURSOS HUMANOS</div>
            <a href="/erp_cini/modules/rh/index.php?s=usuarios" class="nav-item">
                <i class="bi bi-people"></i>
                <span>Usuários</span>
            </a>
            <a href="/erp_cini/modules/rh/index.php?s=ferias" class="nav-item">
                <i class="bi bi-calendar-event"></i>
                <span>Férias</span>
            </a>
        </div>

        <!-- Módulo Projetos -->
        <div class="nav-section">
            <div class="nav-label">PROJETOS</div>
            <a href="/erp_cini/modules/projetos/index.php?s=planos" class="nav-item">
                <i class="bi bi-list-task"></i>
                <span>Planos de Ação</span>
            </a>
            <a href="/erp_cini/modules/projetos/index.php?s=tarefas" class="nav-item">
                <i class="bi bi-check-circle"></i>
                <span>Tarefas</span>
            </a>
        </div>

        <!-- Módulo Estoque -->
        <div class="nav-section">
            <div class="nav-label">ESTOQUE</div>
            <a href="/erp_cini/modules/estoque/index.php?s=produtos" class="nav-item">
                <i class="bi bi-box"></i>
                <span>Produtos</span>
            </a>
            <a href="/erp_cini/modules/estoque/index.php?s=movimentacoes" class="nav-item">
                <i class="bi bi-arrow-left-right"></i>
                <span>Movimentações</span>
            </a>
        </div>

        <!-- Módulo Manutenção -->
        <div class="nav-section">
            <div class="nav-label">MANUTENÇÃO</div>
            <a href="/erp_cini/modules/manutencao/index.php?s=programacao" class="nav-item">
                <i class="bi bi-calendar-check"></i>
                <span>Programação</span>
            </a>
            <a href="/erp_cini/modules/manutencao/index.php?s=execucao" class="nav-item">
                <i class="bi bi-tools"></i>
                <span>Execução</span>
            </a>
            <a href="/erp_cini/modules/manutencao/index.php?s=equipamentos" class="nav-item">
                <i class="bi bi-gear"></i>
                <span>Equipamentos</span>
            </a>
            <a href="/erp_cini/modules/manutencao/index.php?s=historico" class="nav-item">
                <i class="bi bi-clock-history"></i>
                <span>Histórico</span>
            </a>
        </div>

        <!-- Admin (apenas para admins) -->
        <?php if ($user_role === 'admin'): ?>
        <div class="nav-section">
            <div class="nav-label">ADMINISTRAÇÃO</div>
            <a href="/erp_cini/modules/admin/index.php?s=auditoria" class="nav-item <?php echo $current_module === 'admin' ? 'active' : ''; ?>">
                <i class="bi bi-shield-lock"></i>
                <span>Segurança & Auditoria</span>
            </a>
            <a href="/erp_cini/modules/admin/index.php?s=usuarios" class="nav-item">
                <i class="bi bi-people-gear"></i>
                <span>Usuários & Roles</span>
            </a>
            <a href="/erp_cini/modules/admin/index.php?s=sistema" class="nav-item">
                <i class="bi bi-gear"></i>
                <span>Sistema</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- Suporte -->
        <div class="nav-section">
            <div class="nav-label">CONTA</div>
            <a href="/erp_cini/modules/rh/perfil.php" class="nav-item">
                <i class="bi bi-person"></i>
                <span>Meu Perfil</span>
            </a>
            <a href="/erp_cini/includes/logout.php" class="nav-item" onclick="return confirm('Tem certeza que deseja sair?')">
                <i class="bi bi-box-arrow-right"></i>
                <span>Sair</span>
            </a>
        </div>
    </nav>
</aside>
