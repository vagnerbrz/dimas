@echo off
title Inicializando Sistema Restaurante Dimas
echo ========================================
echo  Iniciando Sistema Restaurante Dimas
echo ========================================
echo.

REM Verificar se o Node.js está instalado
where node >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERRO] Node.js não encontrado!
    echo Instale o Node.js: https://nodejs.org/
    pause
    exit /b 1
)

REM Verificar se o PHP está instalado
where php >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERRO] PHP não encontrado!
    echo Instale o PHP: https://www.php.net/downloads.php
    pause
    exit /b 1
)

REM Verificar se o Composer está instalado
where composer >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERRO] Composer não encontrado!
    echo Instale o Composer: https://getcomposer.org/download/
    pause
    exit /b 1
)

echo [1/5] Iniciando MySQL...
start "" "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe" --console
timeout /t 8 /nobreak >nul

echo [2/5] Iniciando Servidor Laravel...
start "Servidor Laravel" cmd /k "cd /d "C:\sistema\dimas" && php artisan serve --port=8000"
timeout /t 3 /nobreak >nul

echo [3/5] Iniciando Servidor WebSockets...
start "Servidor WebSockets" cmd /k "cd /d "C:\sistema\dimas" && php artisan websockets:serve"
timeout /t 3 /nobreak >nul

echo [4/5] Iniciando WPPConnect Server...
start "WPPConnect Server" cmd /k "cd /d "C:\sistema\wppconnect-server" && npm start"
timeout /t 5 /nobreak >nul

echo [5/5] Iniciando n8n (Opcional)...
start "n8n Workflow" cmd /k "cd /d "C:\sistema\n8n" && npx n8n start"
timeout /t 3 /nobreak >nul

echo.
echo ========================================
echo  Serviços Iniciados:
echo ========================================
echo.
echo 1. MySQL:        Banco de dados
echo 2. Laravel:      http://localhost:8000
echo 3. WebSockets:   ws://localhost:6001
echo 4. WPPConnect:   http://localhost:21465
echo 5. n8n:          http://localhost:5678 (Opcional)
echo.
echo Dashboard: http://localhost:8000/dashboard
echo.
echo ========================================
echo  Ambiente pronto!
echo ========================================
pause