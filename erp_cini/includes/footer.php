<?php
// Footer e finalizações
?>
    </div> <!-- main-content -->
    </div> <!-- layout-container -->

    <script src="<?php echo BASE_URL; ?>/assets/app.js"></script>
    <script>
        // Inicializar tema e recursos globais
        document.addEventListener('DOMContentLoaded', function() {
            // Atualizar topbar com dados do usuário
            const userName = document.getElementById('userName');
            if (userName) {
                const name = '<?php echo $_SESSION['user_name']; ?>';
                const initial = name.charAt(0).toUpperCase();
                document.getElementById('userInitials').textContent = initial;
                userName.textContent = name;
            }
        });
    </script>
</body>
</html>
