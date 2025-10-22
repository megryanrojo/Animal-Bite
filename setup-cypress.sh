#!/bin/bash

echo "Setting up Cypress for Animal Bite System..."
echo

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "ERROR: Node.js is not installed or not in PATH"
    echo "Please install Node.js from https://nodejs.org/"
    exit 1
fi

echo "Node.js version:"
node --version
echo

# Check if npm is available
if ! command -v npm &> /dev/null; then
    echo "ERROR: npm is not available"
    echo "Please check your Node.js installation"
    exit 1
fi

echo "npm version:"
npm --version
echo

# Initialize package.json if it doesn't exist
if [ ! -f package.json ]; then
    echo "Initializing package.json..."
    npm init -y
    echo
fi

# Install Cypress
echo "Installing Cypress..."
npm install cypress --save-dev
echo

# Install additional dependencies
echo "Installing additional dependencies..."
npm install eslint prettier eslint-plugin-cypress eslint-config-prettier eslint-plugin-prettier --save-dev
echo

echo "Setup complete!"
echo
echo "To run Cypress:"
echo "  npx cypress open"
echo
echo "To run tests in headless mode:"
echo "  npx cypress run"
echo
