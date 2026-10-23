@echo off
setlocal enabledelayedexpansion

:: -------------------------------------------------------------------
:: deploy.bat
:: Tag & trigger build for SatuSehat KYC API (Klinik Keluarga)
::
:: Usage:
::   deploy.bat
::   deploy.bat --version 1.0.0
::   deploy.bat --version 1.0.0 --prod
:: -------------------------------------------------------------------

set "SERVICE=kyc"
set "VERSION=dev"
set "IS_PROD=false"
set "REMOTE=origin"

:parse_args
if "%~1"=="" goto after_args
if "%~1"=="--version" (
    set "VERSION=%~2"
    shift
    shift
    goto parse_args
)
if "%~1"=="--prod" (
    set "IS_PROD=true"
    shift
    goto parse_args
)
if "%~1"=="--force" (
    set "FORCE_PUSH=true"
    shift
    goto parse_args
)
if "%~1"=="--remote" (
    set "REMOTE=%~2"
    shift
    shift
    goto parse_args
)
if "%~1"=="-v" (
    set "VERSION=%~2"
    shift
    shift
    goto parse_args
)
:: positional fallback
if "%VERSION%"=="dev" (
    set "VERSION=%~1"
    shift
    goto parse_args
)
if "%~1"=="prod" (
    set "IS_PROD=true"
    shift
    goto parse_args
)
shift
goto parse_args

:after_args

if "%IS_PROD%"=="true" if "%VERSION%"=="dev" (
    echo [ERROR] Cannot deploy PROD with version 'dev'. Provide a version, e.g.: deploy.bat --version 1.0.0 --prod
    exit /b 1
)

set "BASE_TAG=%SERVICE%-%VERSION%"
set "TAG_NAME=%BASE_TAG%"
if "%IS_PROD%"=="true" (
    set "TAG_NAME=%BASE_TAG%+prod"
)

echo.
echo ==============================================
if "%IS_PROD%"=="true" (
    echo   BUILD INTENT: PRODUCTION
) else (
    echo   BUILD INTENT: STAGING
)
echo   Service: %SERVICE%
echo   Version: %VERSION%
echo   Tag:     %TAG_NAME%
echo   Remote:  %REMOTE%
echo ==============================================
echo.

if "%IS_PROD%"=="true" if not "%FORCE_PUSH%"=="true" (
    set /p "CONFIRM=Type EXACTLY 'DEPLOY PROD' to continue: "
    if not "!CONFIRM!"=="DEPLOY PROD" (
        echo Confirmation mismatch. Aborting.
        exit /b 1
    )
)

:: Check if tag exists locally
git rev-parse %TAG_NAME% >nul 2>&1
if %ERRORLEVEL%==0 (
    echo [i] Tag already exists locally. Replacing it...
    git tag -d %TAG_NAME%
    git push %REMOTE% :refs/tags/%TAG_NAME% >nul 2>&1
)

:: Create and push tag
git tag %TAG_NAME%
git push %REMOTE% %TAG_NAME% --force

echo.
echo [OK] Pushed tag: %TAG_NAME% to %REMOTE%
echo [*] Action will build and push the image to registry.medisy.id
echo.
