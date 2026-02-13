<?php
require_once 'config/config.php';
$page_title = 'Dashboard';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/Database.php';

// Obter dados do usuário
$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['is_admin'] ?? false;

// Conectar ao banco
$db = Database::getInstance();
$conn = $db->getConnection();

// Buscar estatísticas
try {
    // Total de planos
    $stmt = $conn->query("SELECT COUNT(*) as count FROM plans WHERE status = 'active'");
    $total_plans = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Total de tarefas
    $stmt = $conn->query("SELECT COUNT(*) as count FROM tasks");
    $total_tasks = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Total de usuários
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE active = 1");
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Tarefas atrasadas
    $stmt = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE status != 'Concluída' AND due_date < date('now')");
    $overdue_tasks = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Tarefas do usuário
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tasks WHERE responsible_id = ? AND status != 'Concluída'");
    $stmt->execute([$user_id]);
    $my_tasks = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Tarefas concluídas
    $stmt = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'Concluída'");
    $completed_tasks = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;

} catch (Exception $e) {
    $total_plans = $total_tasks = $total_users = $overdue_tasks = $my_tasks = $completed_tasks = 0;
    $completion_rate = 0;
}
?>

<div class="main-content">
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <div class="page-info">
                <h1>Dashboard</h1>
                <p>Bem-vindo ao seu painel de controle</p>
            </div>
        </div>
        <div class="topbar-right">
            <div class="user-badge">
                <div class="user-avatar" id="userInitials"><?php echo strtoupper($user_name[0]); ?></div>
                <div>
                    <span id="userName" style="font-size: 13px; font-weight: 600; color: var(--text-dark);">
                        <?php echo htmlspecialchars($user_name); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Conteúdo -->
    <div class="content">
        <!-- Widgets de Resumo -->
        <div class="widgets-grid">
            <div class="widget">
                <div class="widget-content">
                    <h3><?php echo $total_plans; ?></h3>
                    <p>Planos Ativos</p>
                </div>
                <div class="widget-icon primary">
                    <i class="bi bi-list-task"></i>
                </div>
            </div>

            <div class="widget">
                <div class="widget-content">
                    <h3><?php echo $total_tasks; ?></h3>
                    <p>Tarefas Totais</p>
                </div>
                <div class="widget-icon info">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>

            <div class="widget">
                <div class="widget-content">
                    <h3><?php echo $completed_tasks; ?></h3>
                    <p>Concluídas (<?php echo $completion_rate; ?>%)</p>
                </div>
                <div class="widget-icon success">
                    <i class="bi bi-check-all"></i>
                </div>
            </div>

            <div class="widget">
                <div class="widget-content">
                    <h3><?php echo $overdue_tasks; ?></h3>
                    <p>Atrasadas</p>
                </div>
                <div class="widget-icon danger">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
            </div>

            <div class="widget">
                <div class="widget-content">
                    <h3><?php echo $my_tasks; ?></h3>
                    <p>Minhas Tarefas</p>
                </div>
                <div class="widget-icon warning">
                    <i class="bi bi-person-check"></i>
                </div>
            </div>

            <?php if ($is_admin): ?>
            <div class="widget">
                <div class="widget-content">
                    <h3><?php echo $total_users; ?></h3>
                    <p>Usuários Ativos</p>
                </div>
                <div class="widget-icon info">
                    <i class="bi bi-people"></i>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Cards de Informação -->
        <div class="grid grid-2 mt-20">
            <!-- Atalhos Rápidos -->
            <div class="card">
                <div class="card-header">Atalhos Rápidos</div>
                <div class="card-body">
                    <div class="flex gap-20" style="flex-direction: column;">
                        <a href="/modules/projetos/index.php?s=planos" class="btn btn-primary" style="text-decoration: none;">
                            <i class="bi bi-plus-circle"></i> Novo Plano
                        </a>
                        <a href="/modules/ti/index.php?s=chamados" class="btn btn-secondary" style="text-decoration: none;">
                            <i class="bi bi-ticket-detailed"></i> Novo Chamado
                        </a>
                        <a href="/modules/estoque/index.php?s=produtos" class="btn btn-secondary" style="text-decoration: none;">
                            <i class="bi bi-box"></i> Consultar Estoque
                        </a>
                    </div>
                </div>
            </div>

            <!-- Status de Progresso -->
            <div class="card">
                <div class="card-header">Progresso Geral</div>
                <div class="card-body">
                    <div style="margin-bottom: 16px;">
                        <div class="flex-between mb-10">
                            <span style="font-size: 13px;">Conclusão de Tarefas</span>
                            <span style="font-size: 13px; font-weight: 600;"><?php echo $completion_rate; ?>%</span>
                        </div>
                        <div style="height: 8px; background: var(--gray); border-radius: 4px; overflow: hidden;">
                            <div style="height: 100%; background: var(--primary); width: <?php echo $completion_rate; ?>%; transition: all 0.3s ease;"></div>
                        </div>
                    </div>

                    <table style="width: 100%; font-size: 13px; margin-top: 16px;">
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid var(--gray);">Concluídas</td>
                            <td style="padding: 8px 0; border-bottom: 1px solid var(--gray); text-align: right; font-weight: 600;"><?php echo $completed_tasks; ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid var(--gray);">Em Execução</td>
                            <td style="padding: 8px 0; border-bottom: 1px solid var(--gray); text-align: right; font-weight: 600;">-</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0;">Pendentes</td>
                            <td style="padding: 8px 0; text-align: right; font-weight: 600;"><?php echo $total_tasks - $completed_tasks; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Informações do Sistema -->
        <div class="card mt-20">
            <div class="card-header">Informações do Sistema</div>
            <div class="card-body">
                <table style="width: 100%; font-size: 13px;">
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid var(--gray);">Versão</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid var(--gray); text-align: right;">v1.0</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid var(--gray);">Ambiente</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid var(--gray); text-align: right;">Desenvolvimento</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0;">Última Sincronização</td>
                        <td style="padding: 8px 0; text-align: right;"><?php echo date('d/m/Y H:i'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
