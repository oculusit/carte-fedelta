@echo off
chcp 65001 >nul
echo ============================================
echo  FidAPPti - Build per deploy
echo ============================================
echo.

echo [1/3] Installazione dipendenze...
call npm.cmd install
if %ERRORLEVEL% neq 0 (
  echo ERRORE: npm install fallito
  pause
  exit /b 1
)

echo.
echo [2/3] Build frontend...
call npm.cmd run build
if %ERRORLEVEL% neq 0 (
  echo ERRORE: Build fallita
  pause
  exit /b 1
)

echo.
echo [3/3] Preparazione pacchetto deploy...
if exist "deploy" rmdir /s /q deploy
mkdir deploy

xcopy /E /I /Y dist\* deploy\
if %ERRORLEVEL% neq 0 (
  echo ERRORE: Copia dist fallita
  pause
  exit /b 1
)

xcopy /E /I /Y api\* deploy\api\
if %ERRORLEVEL% neq 0 (
  echo ERRORE: Copia api fallita
  pause
  exit /b 1
)

xcopy /E /I /Y uploads\* deploy\uploads\
if %ERRORLEVEL% neq 0 (
  echo ERRORE: Copia uploads fallita
  pause
  exit /b 1
)

xcopy /Y .htaccess deploy\.htaccess
if %ERRORLEVEL% neq 0 (
  echo ERRORE: Copia .htaccess fallita
  pause
  exit /b 1
)

xcopy /Y database\schema.sql deploy\database\
if %ERRORLEVEL% neq 0 (
  echo ERRORE: Copia schema.sql fallita
  pause
  exit /b 1
)

echo.
echo [4/4] Creazione zip...
call node build-zip.cjs
if %ERRORLEVEL% neq 0 (
  echo ERRORE: Creazione zip fallita
  pause
  exit /b 1
)

echo.
echo File generati:
echo   deploy\    (cartella con i file estratti)
echo   progetto-cards.zip  (file da caricare sul server)
echo.
echo ============================================
echo  BUILD COMPLETATO!
echo ============================================
pause
