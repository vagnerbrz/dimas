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

:loop
REM Rotaciona o log quando passar de ~5 MB.
if exist "%LOG%" for %%A in ("%LOG%") do if %%~zA GTR 5000000 move /y "%LOG%" "%LOG%.old" >nul

echo [%date% %time%] Iniciando agente de impressao... >> "%LOG%"
"%PHP_BIN%" scripts\local_printer_agent.php >> "%LOG%" 2>&1

REM Codigo 2 = ja existe outro agente rodando; nao fica em loop a toa.
if %ERRORLEVEL%==2 (
    echo [%date% %time%] Outro agente ja esta rodando. Encerrando esta copia. >> "%LOG%"
    exit /b 0
)

echo [%date% %time%] Agente parou (codigo %ERRORLEVEL%). Reiniciando em 5s... >> "%LOG%"
timeout /t 5 /nobreak >nul
goto loop
