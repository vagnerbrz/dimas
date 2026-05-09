@echo off
title Parando Sistema Restaurante Dimas
echo ========================================
echo  Parando Sistema Restaurante Dimas
echo ========================================
echo.

echo [1/5] Parando MySQL...
taskkill /FI "IMAGENAME eq mysqld.exe" /F >nul 2>nul

echo [2/5] Parando Servidor Laravel...
taskkill /FI "WINDOWTITLE eq Servidor Laravel" /F >nul 2>nul
taskkill /FI "IMAGENAME eq php.exe" /F >nul 2>nul

echo [3/5] Parando Servidor WebSockets...
taskkill /FI "WINDOWTITLE eq Servidor WebSockets" /F >nul 2>nul

echo [4/5] Parando WPPConnect Server...
taskkill /FI "WINDOWTITLE eq WPPConnect Server" /F >nul 2>nul
taskkill /FI "IMAGENAME eq node.exe" /F >nul 2>nul

echo [5/5] Parando n8n...
taskkill /FI "WINDOWTITLE eq n8n Workflow" /F >nul 2>nul

echo.
echo Todos os serviços foram parados.
echo.
pause