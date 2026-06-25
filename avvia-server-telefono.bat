@echo off
title Server Sito Scuola - NON chiudere questa finestra
color 0b
echo ============================================================
echo   SERVER SITO SCUOLA  (lascia aperta questa finestra)
echo ============================================================
echo.
echo   Su QUESTO PC apri nel browser:
echo       http://localhost:8088/           (sito)
echo       http://localhost:8088/admin/     (editor)
echo.
echo   Sul TELEFONO (Oppo, via Tailscale):
echo       http://100.87.113.43:8088/
echo       http://100.87.113.43:8088/admin/
echo.
echo   Per fermare il server: chiudi questa finestra (o premi Ctrl+C).
echo ============================================================
echo.
set "PHP=php"
where php >nul 2>nul || set "PHP=C:\Users\Zarletti\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
"%PHP%" -S 0.0.0.0:8088 -t "%~dp0"
echo.
echo Il server si e' fermato. Premi un tasto per chiudere.
pause >nul
