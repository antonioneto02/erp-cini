@echo off
echo ========================================
echo   Sistema de Planos de Acao - Iniciar
echo ========================================
echo.

REM Verificar se PHP esta instalado
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERRO] PHP nao encontrado!
    echo.
    echo Por favor, instale o PHP 7.4+ e adicione ao PATH
    echo Download: https://windows.php.net/download/
    echo.
    pause
    exit /b 1
)

echo [OK] PHP encontrado
php --version | findstr /C:"PHP"
echo.

REM Verificar extensao SQLite
php -m | findstr /C:"sqlite3" >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERRO] Extensao SQLite3 nao encontrada!
    echo.
    echo Edite php.ini e descomente a linha:
    echo extension=sqlite3
    echo.
    pause
    exit /b 1
)

echo [OK] Extensao SQLite3 ativa
echo.

REM Criar pasta db se nao existir
if not exist "db" (
    echo [INFO] Criando pasta db/
    mkdir db
)

echo ========================================
echo   Iniciando servidor PHP...
echo ========================================
echo.
echo Servidor rodando em: http://localhost:8000
echo.
echo Pressione Ctrl+C para parar o servidor
echo.
echo Credenciais de acesso:
echo   Admin: admin@sistema.com / admin123
echo   User:  joao@empresa.com / 123456
echo.

REM Iniciar servidor PHP
php -S localhost:8000

pause
