@echo off
REM ============================================================
REM  Agente de impressao do Restaurante do Dimas
REM  Mantem o agente rodando: se ele cair, reinicia em 5 segundos.
REM  Log: storage\logs\agente-impressao.log
REM ============================================================
setlocal

REM Caminho do PHP. Troque se o php nao estiver no PATH, ex: C:\php\php.exe
if "%PHP_BIN%"=="" set "PHP_BIN=php"

cd /d "%~dp0..\.."
set "LOG=storage\logs\agente-impressao.log"
set "LOCK=storage\app\agente-impressao.lock"

REM Trava de instancia unica: enquanto esta copia roda, o arquivo LOCK fica
REM aberto. Uma segunda copia nao consegue abri-lo e encerra sem mexer no log.
2>nul (
    9>"%LOCK%" call :main
) || (
    echo Ja existe um agente de impressao rodando nesta maquina.
    echo Acompanhe em: %CD%\%LOG%
    timeout /t 5 >nul
    exit /b 0
)
exit /b 0

:main
REM Rotaciona o log quando passar de ~5 MB.
if exist "%LOG%" for %%A in ("%LOG%") do if %%~zA GTR 5000000 move /y "%LOG%" "%LOG%.old" >nul

echo [%date% %time%] Iniciando agente de impressao... >> "%LOG%"
"%PHP_BIN%" scripts\local_printer_agent.php >> "%LOG%" 2>&1
set "CODE=%ERRORLEVEL%"

if "%CODE%"=="2" (
    REM O agente PHP foi aberto manualmente em outro terminal. Espera ele fechar.
    echo [%date% %time%] Agente PHP ja aberto em outro terminal. Nova verificacao em 30s. >> "%LOG%"
    timeout /t 30 /nobreak >nul
    goto main
)

echo [%date% %time%] Agente parou (codigo %CODE%). Reiniciando em 5s... >> "%LOG%"
timeout /t 5 /nobreak >nul
goto main
