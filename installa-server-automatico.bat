@echo off
chcp 65001 >nul
net session >nul 2>&1
if errorlevel 1 (
  echo.
  echo  ============================================================
  echo   Questo file va eseguito come AMMINISTRATORE.
  echo   Chiudi, poi TASTO DESTRO su "installa-server-automatico.bat"
  echo   ^> "Esegui come amministratore".
  echo  ============================================================
  echo.
  pause
  exit /b 1
)
echo Creo l'attivita' pianificata "SitoScuolaServer"...
schtasks /create /tn "SitoScuolaServer" /tr "cmd /c \"%~dp0avvia-server-telefono.bat\"" /sc onlogon /f
echo Libero la porta 8088 da eventuali server vecchi...
taskkill /f /im php.exe >nul 2>&1
schtasks /run /tn "SitoScuolaServer"
echo.
echo  ============================================================
echo   FATTO. Il server del sito ora parte DA SOLO a ogni accesso
echo   a Windows e non si ferma piu'.
echo.
echo   Su questo PC:  http://localhost:8088/
echo   Sul telefono:  http://100.87.113.43:8088/
echo.
echo   Per RIMUOVERLO in futuro, esegui (sempre come admin):
echo       schtasks /delete /tn "SitoScuolaServer" /f
echo  ============================================================
echo.
pause
