@echo off
setlocal enabledelayedexpansion


set "WP_PATH=C:\wamp64\www\cob"

set "CSV_FILE=C:\wamp64\www\cob\wp-content\csv-imports\dido_data.nawy_properties_en.csv"

set "LANGUAGE=en"

set "CORES=8"

set "EXTRA_FLAGS=--fast-images --allow-root"


cd /d "%WP_PATH%"
if %errorlevel% neq 0 (
    echo ERROR: WordPress path not found at %WP_PATH%
    exit /b
)

if not exist "%CSV_FILE%" (
    echo ERROR: CSV file not found at %CSV_FILE%
    exit /b
)

echo Counting lines in the file...
for /f %%a in ('type "%CSV_FILE%" ^| find /c /v ""') do set /a TOTAL_LINES=%%a - 1

if %TOTAL_LINES% leq 0 (
    echo ERROR: No data rows found in CSV file.
    exit /b
)

echo Total data rows to import: %TOTAL_LINES%
echo Running with extra flags: %EXTRA_FLAGS%
echo Starting parallel import across %CORES% cores...
echo ------------------------------------------------

set /a "LINES_PER_CORE=(TOTAL_LINES + CORES - 1) / CORES"

for /L %%i in (0, 1, %CORES%-1) do (
    set /a "OFFSET=%%i * LINES_PER_CORE"
    echo Core %%i: Starting import of %LINES_PER_CORE% rows from offset !OFFSET!...
    start "Core %%i" cmd /c "wp cob property import ""%CSV_FILE%"" --language=%LANGUAGE% --offset=!OFFSET! --limit=%LINES_PER_CORE% %EXTRA_FLAGS%"
)

echo ------------------------------------------------
echo All parallel import processes have been launched in new windows.
echo Please monitor the individual windows for progress.
echo IMPORTANT: If you used --fast-images, you must now regenerate thumbnails using a plugin.

