@echo off
REM ============================================================
REM  Instala o agente de impressao para iniciar junto com o Windows
REM  (ao fazer login do usuario) e ja o inicia agora.
REM  Execute uma vez na maquina do restaurante.
REM ============================================================
setlocal
set "TASK=Dimas - Agente de Impressao"
set "VBS=%~dp0agente-impressao-oculto.vbs"

schtasks /Create /TN "%TASK%" /TR "wscript.exe \"%VBS%\"" /SC ONLOGON /RL LIMITED /F
if errorlevel 1 (
    echo.
    echo Nao foi possivel criar a tarefa agendada.
    pause
    exit /b 1
)

echo.
echo Iniciando o agente agora...
wscript.exe "%VBS%"

echo.
echo Pronto. O agente vai iniciar sozinho sempre que este usuario entrar no Windows.
echo Acompanhe em: %~dp0..\..\storage\logs\agente-impressao.log
pause
