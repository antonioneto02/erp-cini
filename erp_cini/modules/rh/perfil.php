<?php
require_once '../../config/config.php';
$page_title = 'Meu Perfil';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/Database.php';
require_once '../../includes/AuthHelper.php';

$db = Database::getInstance();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];

try {
    // Buscar dados do usuário
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Buscar roles do usuário
    $stmt = $conn->prepare("SELECT r.name, r.description FROM roles r INNER JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
    $stmt->execute([$user_id]);
    $roles = $stmt->fetchAll();
    
    // Buscar histórico de auditoria do usuário
    $stmt = $conn->prepare("SELECT * FROM audit_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user_id]);
    $history = $stmt->fetchAll();
    
    // Buscar notificações não lidas do usuário
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications_expanded WHERE user_id = ? AND \"read\" = 0");
    $stmt->execute([$user_id]);
    $unread_notif = $stmt->fetch();
    
} catch (Exception $e) {
    $user = [];
    $roles = [];
    $history = [];
    $unread_notif = ['count' => 0];
}
?>

<main class="main-content">
    <div class="page-header">
        <h1><i class="bi bi-person-circle"></i> Meu Perfil</h1>
        <p>Gerencie suas informações pessoais e preferências</p>
    </div>

    <div class="profile-grid">
        <!-- Coluna 1: Informações Pessoais -->
        <div class="profile-section">
            <div class="section-header"><h2><i class="bi bi-user"></i> Informações Pessoais</h2></div>
            
            <div style="display: grid; grid-template-columns: 100px 1fr; gap: 20px; margin-bottom: 25px;">
                <div style="text-align: center;">
                    <div id="avatarContainer" style="position: relative; width: 100px; height: 100px; margin: 0 auto;">
                        <div class="avatar" style="width: 100%; height: 100%; background: #1e40af; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 40px; position: relative; overflow: hidden;">
                            <?php if (!empty($user['avatar_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $user['avatar_path'])): ?>
                                <img id="avatarImg" src="<?php echo $user['avatar_path']; ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <i class="bi bi-person"></i>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="avatar-edit-btn" onclick="showModal('modalUploadAvatar')" title="Editar avatar" style="position: absolute; bottom: -5px; right: -5px; background: #1e40af; color: white; border: 2px solid white; border-radius: 50%; width: 30px; height: 30px; padding: 0; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-camera-fill"></i>
                        </button>
                    </div>
                    <p style="margin: 10px 0 0 0; font-size: 12px; color: #64748b;">ID: #<?php echo $user['id']; ?></p>
                </div>
                
                <div>
                    <div class="info-row">
                        <label><i class="bi bi-person" style="margin-right: 6px;"></i>Nome Completo</label>
                        <p><?php echo $user['name']; ?></p>
                    </div>
                    <div class="info-row">
                        <label><i class="bi bi-envelope" style="margin-right: 6px;"></i>Email</label>
                        <p><?php echo $user['email']; ?></p>
                    </div>
                    <div class="info-row">
                        <label><i class="bi bi-building" style="margin-right: 6px;"></i>Departamento</label>
                        <p><?php echo $user['dept'] ?? 'Não informado'; ?></p>
                    </div>
                    <div class="info-row">
                        <label><i class="bi bi-circle-fill" style="margin-right: 6px; font-size: 8px;"></i>Status</label>
                        <p><span class="badge" style="background: <?php echo $user['active'] ? '#d1fae5' : '#fee2e2'; ?>; color: <?php echo $user['active'] ? '#065f46' : '#7f1d1d'; ?>"><i class="bi <?php echo $user['active'] ? 'bi-check-circle' : 'bi-x-circle'; ?>" style="margin-right: 4px;"></i><?php echo $user['active'] ? 'Ativo' : 'Inativo'; ?></span></p>
                    </div>
                    <div class="info-row">
                        <label><i class="bi bi-calendar" style="margin-right: 6px;"></i>Membro desde</label>
                        <p><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
                    </div>
                </div>
            </div>

            <button class="btn btn-primary" onclick="showModal('modalEditProfile')" style="width: 100%;"><i class="bi bi-pencil-square"></i> Editar Informações</button>
        </div>

        <!-- Coluna 2: Segurança & Roles -->
        <div class="profile-section">
            <div class="section-header"><h2><i class="bi bi-shield-lock"></i> Segurança & Permissões</h2></div>
            
            <div class="security-box">
                <h3 style="margin: 0 0 15px 0;">Seus Roles</h3>
                <?php if (count($roles) > 0): ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <?php foreach ($roles as $role): ?>
                        <div style="padding: 10px 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                            <strong style="display: block; font-size: 14px;"><?php echo ucfirst($role['name']); ?></strong>
                            <small style="color: #64748b;"><?php echo $role['description']; ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #64748b;">Nenhum role atribuído</p>
                <?php endif; ?>
            </div>

            <div class="security-box" style="margin-top: 15px;">
                <h3 style="margin: 0 0 15px 0;">Senha</h3>
                <p style="color: #64748b; font-size: 13px; margin-bottom: 12px;">Altere sua senha a qualquer momento</p>
                <button class="btn btn-secondary" onclick="showModal('modalChangePassword')" style="width: 100%;"><i class="bi bi-key"></i> Alterar Senha</button>
            </div>

            <div class="security-box" style="margin-top: 15px;">
                <h3 style="margin: 0 0 15px 0;">Notificações Não Lidas</h3>
                <p style="font-size: 24px; font-weight: 700; color: #1e40af; margin: 0;"><?php echo $unread_notif['count']; ?></p>
                <button class="btn btn-info" onclick="window.location='/dashboard.php'" style="width: 100%; margin-top: 10px;"><i class="bi bi-bell"></i> Ver Notificações</button>
            </div>
        </div>
    </div>

    <!-- Histórico de Atividades -->
    <div class="section-full" style="margin-top: 25px;">
        <div class="section-header"><h2><i class="bi bi-clock-history"></i> Suas Últimas Atividades</h2></div>
        <?php if (count($history) > 0): ?>
        <div class="table-responsive"><table class="data-table"><thead><tr><th>Data/Hora</th><th>Ação</th><th>Entidade</th><th>Detalhes</th></tr></thead><tbody>
        <?php foreach ($history as $h): ?>
        <tr>
            <td><small><?php echo date('d/m H:i:s', strtotime($h['created_at'])); ?></small></td>
            <td><span class="badge" style="background: <?php echo match($h['action']) { 'create' => '#d1fae5', 'update' => '#fef3c7', 'delete' => '#fee2e2', 'view' => '#dbeafe', 'export' => '#e0e7ff', default => '#f3f4f6' }; ?>; color: <?php echo match($h['action']) { 'create' => '#065f46', 'update' => '#92400e', 'delete' => '#7f1d1d', 'view' => '#1e40af', 'export' => '#3730a3', default => '#1f2937' }; ?>"><?php echo ucfirst($h['action']); ?></span></td>
            <td><strong><?php echo $h['entity']; ?></strong> #<?php echo $h['entity_id']; ?></td>
            <td><small style="color: #64748b;"><?php echo $h['ip_address']; ?></small></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?>
        <div class="empty-state"><i class="bi bi-clock-history"></i><p>Nenhuma atividade registrada</p></div>
        <?php endif; ?>
    </div>
</main>

<!-- Modal: Upload Avatar -->
<div id="modalUploadAvatar" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="bi bi-image"></i> Mudar Foto de Perfil</h2>
            <button class="modal-close" onclick="closeModal('modalUploadAvatar')">&times;</button>
        </div>
        <form onsubmit="uploadAvatar(event)">
            <div class="modal-body">
                <div style="background: #f8fafc; border: 2px dashed #ccc; border-radius: 8px; padding: 30px; text-align: center; cursor: pointer; transition: 0.2s;" id="dropZone">
                    <input type="file" id="avatarFile" accept="image/*" hidden onchange="previewAvatar(event)">
                    <i class="bi bi-cloud-upload" style="font-size: 32px; color: #1e40af; display: block; margin-bottom: 10px;"></i>
                    <p style="margin: 0 0 5px 0; font-weight: 500;">Arraste a imagem ou clique para selecionar</p>
                    <small style="color: #64748b;">PNG, JPG ou GIF - Máximo 5MB</small>
                </div>
                
                <div id="previewContainer" style="display: none; text-align: center; margin-top: 20px;">
                    <label style="display: block; font-size: 12px; color: #64748b; margin-bottom: 8px;">Pré-visualização:</label>
                    <img id="previewImg" src="" alt="Preview" style="max-width: 150px; max-height: 150px; border-radius: 8px; border: 1px solid #e2e8f0;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalUploadAvatar')">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="submitAvatarBtn" disabled>Salvar Foto</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Perfil -->
<div id="modalEditProfile" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="bi bi-pencil-square"></i> Editar Perfil</h2>
            <button class="modal-close" onclick="closeModal('modalEditProfile')">&times;</button>
        </div>
        <form onsubmit="saveProfile(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label>Nome Completo *</label>
                    <input type="text" name="name" value="<?php echo $user['name']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?php echo $user['email']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Departamento</label>
                    <input type="text" name="dept" value="<?php echo $user['dept']; ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditProfile')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Alterar Senha -->
<div id="modalChangePassword" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="bi bi-key"></i> Alterar Senha</h2>
            <button class="modal-close" onclick="closeModal('modalChangePassword')">&times;</button>
        </div>
        <form onsubmit="changePassword(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label>Senha Atual *</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>Nova Senha *</label>
                    <input type="password" name="new_password" minlength="6" required>
                </div>
                <div class="form-group">
                    <label>Confirmar Nova Senha *</label>
                    <input type="password" name="confirm_password" minlength="6" required>
                </div>
                <small style="color: #64748b;">A senha deve ter pelo menos 6 caracteres</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalChangePassword')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Alterar Senha</button>
            </div>
        </form>
    </div>
</div>

<style>
    .profile-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .profile-section { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; }
    .section-full { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; }
    .section-header h2 { margin: 0; font-size: 18px; }
    .info-row { margin-bottom: 15px; }
    .info-row label { display: block; font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 5px; }
    .info-row p { margin: 0; font-size: 14px; color: #1e293b; }
    .security-box { padding: 15px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; }
    .badge { padding: 4px 10px; border-radius: 16px; font-size: 11px; font-weight: 600; display: inline-block; }
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
    .form-group input { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; font-family: inherit; }
    .btn { padding: 8px 14px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; transition: 0.3s; }
    .btn-primary { background: #1e40af; color: white; }
    .btn-primary:hover { background: #1e3a8a; }
    .btn-secondary { background: #e2e8f0; color: #334155; }
    .btn-secondary:hover { background: #cbd5e1; }
    .btn-info { background: #dbeafe; color: #1e40af; }
    .btn-info:hover { background: #bfdbfe; }
    .empty-state { text-align: center; padding: 50px 20px; background: white; border-radius: 8px; border: 1px dashed #e2e8f0; }
    .empty-state i { font-size: 40px; color: #cbd5e1; }
    .empty-state p { color: #64748b; margin: 10px 0; }
    .data-table { width: 100%; border-collapse: collapse; background: white; border: 1px solid #e2e8f0; border-radius: 6px; }
    .data-table thead { background: #f8fafc; font-weight: 600; font-size: 12px; }
    .data-table th { padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; }
    .data-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
    .data-table tbody tr:hover { background: #f8fafc; }
    .table-responsive { overflow-x: auto; }
    @media (max-width: 768px) {
        .profile-grid { grid-template-columns: 1fr; }
        .info-row { display: grid; grid-template-columns: 1fr; }
    }
</style>

<script>
    function showModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }
    
    function saveProfile(e) {
        e.preventDefault();
        const form = e.target;
        const data = {
            name: form.name.value,
            email: form.email.value,
            dept: form.dept.value
        };
        
        fetch('<?php echo BASE_URL; ?>/api/users_pro.php?action=update_profile', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert('Perfil atualizado com sucesso!');
                location.reload();
            } else {
                alert('Erro: ' + d.error);
            }
        });
    }
    
    function changePassword(e) {
        e.preventDefault();
        const form = e.target;
        
        if (form.new_password.value !== form.confirm_password.value) {
            alert('As senhas não correspondem!');
            return;
        }
        
        const data = {
            current_password: form.current_password.value,
            new_password: form.new_password.value
        };
        
        fetch('<?php echo BASE_URL; ?>/api/users_pro.php?action=change_password', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert('Senha alterada com sucesso!');
                closeModal('modalChangePassword');
                form.reset();
            } else {
                alert('Erro: ' + d.error);
            }
        });
    }
    
    document.querySelectorAll('.modal').forEach(m => {
        m.addEventListener('click', (e) => {
            if (e.target === m) m.classList.remove('active');
        });
    });
    
    // Avatar Upload Functions
    const dropZone = document.getElementById('dropZone');
    const avatarFile = document.getElementById('avatarFile');
    const previewContainer = document.getElementById('previewContainer');
    const previewImg = document.getElementById('previewImg');
    const submitBtn = document.getElementById('submitAvatarBtn');
    
    dropZone.addEventListener('click', () => avatarFile.click());
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.background = '#e0e7ff';
    });
    dropZone.addEventListener('dragleave', () => {
        dropZone.style.background = '#f8fafc';
    });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.background = '#f8fafc';
        if (e.dataTransfer.files.length) {
            avatarFile.files = e.dataTransfer.files;
            previewAvatar({ target: { files: e.dataTransfer.files } });
        }
    });
    
    function previewAvatar(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        if (file.size > 5 * 1024 * 1024) {
            alert('Arquivo muito grande (máximo 5MB)');
            avatarFile.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = (evt) => {
            previewImg.src = evt.target.result;
            previewContainer.style.display = 'block';
            submitBtn.disabled = false;
        };
        reader.readAsDataURL(file);
    }
    
    function uploadAvatar(e) {
        e.preventDefault();
        const file = avatarFile.files[0];
        if (!file) return;
        
        const formData = new FormData();
        formData.append('avatar', file);
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Enviando...';
        
        fetch('<?php echo BASE_URL; ?>/api/users_pro.php?action=upload_avatar', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(d => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Salvar Foto';
            if (d.success) {
                alert('Foto atualizada com sucesso!');
                closeModal('modalUploadAvatar');
                setTimeout(() => location.reload(), 500);
            } else {
                alert('Erro: ' + d.error);
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-exclamation-circle"></i> Salvar Foto';
            alert('Erro ao enviar: ' + err.message);
        });
    }
</script>

<?php require_once '../../includes/footer.php'; ?>
