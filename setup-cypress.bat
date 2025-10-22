@echo off
echo Setting up Cypress for Animal Bite System...
echo.

REM Check if Node.js is installed
node --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Node.js is not installed or not in PATH
    echo Please install Node.js from https://nodejs.org/
    pause
    exit /b 1
)

echo Node.js version:
node --version
echo.

REM Check if npm is available
npm --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: npm is not available
    echo Please check your Node.js installation
    pause
    exit /b 1
)

echo npm version:
npm --version
echo.

REM Initialize package.json if it doesn't exist
if not exist package.json (
    echo Initializing package.json...
    npm init -y
    echo.
)

REM Install Cypress
echo Installing Cypress...
npm install cypress --save-dev
echo.

REM Install additional dependencies
echo Installing additional dependencies...
npm install eslint prettier eslint-plugin-cypress eslint-config-prettier eslint-plugin-prettier --save-dev
echo.

echo Setup complete!
echo.
echo To run Cypress:
echo   npx cypress open
echo.
echo To run tests in headless mode:
echo   npx cypress run
echo.
pause
