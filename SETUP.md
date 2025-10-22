# Cypress Setup Instructions for Animal Bite System

## 🚨 Current Issue
The error you're seeing is because Cypress is not installed in your project. The `cypress.config.js` file is trying to require the 'cypress' module, but it's not found.

## 🔧 Quick Fix

### Option 1: Use the Setup Script (Recommended)

**For Windows:**
1. Double-click `setup-cypress.bat` in your project folder
2. Follow the prompts

**For Mac/Linux:**
1. Open terminal in your project folder
2. Run: `chmod +x setup-cypress.sh && ./setup-cypress.sh`

### Option 2: Manual Installation

**Step 1: Fix PowerShell Execution Policy (Windows)**
```powershell
# Run PowerShell as Administrator and execute:
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

**Step 2: Install Node.js Dependencies**
```bash
# Navigate to your project folder
cd C:\xampp\htdocs\Animal-Bite

# Initialize package.json (if not exists)
npm init -y

# Install Cypress
npm install cypress --save-dev

# Install additional dependencies
npm install eslint prettier eslint-plugin-cypress eslint-config-prettier eslint-plugin-prettier --save-dev
```

**Step 3: Verify Installation**
```bash
# Check if Cypress is installed
npx cypress --version

# Open Cypress (this will create the cypress folder structure)
npx cypress open
```

## 🎯 After Installation

Once Cypress is installed, you can:

### Run Tests
```bash
# Open Cypress Test Runner
npx cypress open

# Run all tests in headless mode
npx cypress run

# Run specific test suite
npx cypress run --spec "cypress/e2e/authentication/**/*.cy.js"
```

### Use the Custom Scripts
```bash
# Run all tests
npm run test:all

# Run smoke tests
npm run test:smoke

# Run authentication tests
npm run test:auth
```

## 🔍 Troubleshooting

### Issue: "Cannot find module 'cypress'"
**Solution:** Run the setup script or manually install Cypress as shown above.

### Issue: PowerShell execution policy error
**Solution:** 
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

### Issue: Node.js not found
**Solution:** Install Node.js from https://nodejs.org/

### Issue: npm not found
**Solution:** Node.js installation includes npm. Reinstall Node.js if needed.

## 📁 Project Structure After Setup

```
Animal-Bite/
├── cypress/
│   ├── e2e/           # Test files
│   ├── fixtures/      # Test data
│   └── support/       # Commands and helpers
├── node_modules/      # Dependencies
├── package.json       # Project configuration
├── cypress.config.js  # Cypress configuration
└── setup-cypress.bat  # Setup script
```

## 🚀 Next Steps

1. **Run the setup script** to install Cypress
2. **Open Cypress** with `npx cypress open`
3. **Run your first test** by clicking on any test file
4. **Customize test data** in `cypress/fixtures/test-data.json`
5. **Add to CI/CD** pipeline for automated testing

## 📞 Need Help?

If you encounter any issues:
1. Check that Node.js is installed: `node --version`
2. Check that npm is available: `npm --version`
3. Ensure you're in the correct project directory
4. Try running the setup script again

The test suite is ready to use once Cypress is properly installed!
