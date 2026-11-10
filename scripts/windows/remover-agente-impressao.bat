@echo off
REM Remove a inicializacao automatica e encerra o agente de impressao.
setlocal
schtasks /Delete /TN "Dimas - Agente de Impressao" /F

REM Encerra o loop (.bat) e o processo PHP do agente.
powershell -NoProfile -Command "Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like '*agente-impressao.bat*' -or $_.CommandLine -like '*local_printer_agent.php*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }"

echo Agente de impressao removido.
pause
