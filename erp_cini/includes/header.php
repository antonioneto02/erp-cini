<?php
// Header com navegação principal

// Verificar se tem usuário autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_module = isset($_GET['m']) ? $_GET['m'] : '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' | ' : ''; ?>ERP CINI</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/styles.css">
</head>
<body>
    <div class="layout-container">
