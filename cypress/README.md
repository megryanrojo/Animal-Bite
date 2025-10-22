# Animal Bite System - Cypress Test Suite

This comprehensive Cypress test suite provides complete coverage for the Animal Bite Incident Reporting System, including authentication, admin features, staff features, user features, and end-to-end integration tests.

## 📁 Test Structure

```
cypress/
├── e2e/
│   ├── authentication/
│   │   ├── admin-login.cy.js
│   │   ├── staff-login.cy.js
│   │   └── google-auth.cy.js
│   ├── admin/
│   │   ├── admin-dashboard.cy.js
│   │   ├── admin-reports.cy.js
│   │   ├── admin-patients.cy.js
│   │   ├── admin-staff.cy.js
│   │   └── admin-geomapping.cy.js
│   ├── staff/
│   │   ├── staff-dashboard.cy.js
│   │   ├── staff-reports.cy.js
│   │   ├── staff-patients.cy.js
│   │   └── staff-search.cy.js
│   ├── user/
│   │   ├── user-homepage.cy.js
│   │   ├── user-report.cy.js
│   │   └── user-submit-report.cy.js
│   └── integration/
│       ├── complete-workflow.cy.js
│       ├── data-flow.cy.js
│       └── security-flow.cy.js
├── fixtures/
│   └── test-data.json
├── support/
│   ├── e2e.js
│   ├── commands.js
│   └── test-helpers.js
└── README.md
```

## 🚀 Getting Started

### Prerequisites

- Node.js (v14 or higher)
- Cypress installed in your project
- Animal Bite System running locally

### Installation

1. Install Cypress (if not already installed):
```bash
npm install cypress --save-dev
```

2. Open Cypress:
```bash
npx cypress open
```

3. Run tests in headless mode:
```bash
npx cypress run
```

## 🧪 Test Categories

### 1. Authentication Tests
- **Admin Login**: Tests admin login functionality, validation, error handling, and Google OAuth
- **Staff Login**: Tests staff login functionality, validation, error handling, and Google OAuth
- **Google Authentication**: Tests Google OAuth integration, error handling, and security

### 2. Admin Features Tests
- **Dashboard**: Tests admin dashboard layout, statistics, navigation, and functionality
- **Reports Management**: Tests report viewing, editing, deleting, searching, and filtering
- **Patients Management**: Tests patient viewing, editing, deleting, and data consistency
- **Staff Management**: Tests staff CRUD operations, password reset, and permissions
- **Geomapping**: Tests map functionality, markers, filters, and data visualization

### 3. Staff Features Tests
- **Dashboard**: Tests staff dashboard layout, statistics, and navigation
- **Reports Management**: Tests report creation, editing, viewing, and management
- **Patients Management**: Tests patient creation, editing, viewing, and management
- **Search Functionality**: Tests advanced search, filters, and result handling

### 4. User Features Tests
- **Homepage**: Tests public homepage, form validation, and submission
- **Report Submission**: Tests bite report form, validation, and success handling
- **User Experience**: Tests responsive design, accessibility, and performance

### 5. Integration Tests
- **Complete Workflow**: Tests end-to-end user journeys and cross-user workflows
- **Data Flow**: Tests data consistency across modules and real-time updates
- **Security Flow**: Tests authentication, authorization, and security measures

## 🔧 Configuration

### Environment Variables

The test suite uses the following environment variables (defined in `cypress.config.js`):

```javascript
env: {
  testAdminEmail: 'admin@test.com',
  testAdminPassword: 'admin123',
  testStaffEmail: 'staff@test.com',
  testStaffPassword: 'staff123',
  testUserEmail: 'user@test.com',
  testUserPassword: 'user123',
  apiBaseUrl: 'http://localhost/Animal-Bite/src'
}
```

### Test Data

Test data is stored in `cypress/fixtures/test-data.json` and includes:
- Test user credentials
- Sample patient data
- Sample bite report data
- Sample staff data
- Search queries and filters
- Error and success messages

## 🛠️ Custom Commands

The test suite includes custom Cypress commands for common operations:

### Authentication Commands
```javascript
cy.loginAsAdmin(email, password)
cy.loginAsStaff(email, password)
cy.logout()
```

### Form Commands
```javascript
cy.fillPatientForm(patientData)
cy.fillBiteForm(biteData)
cy.fillFormField(selector, value)
cy.selectDropdownOption(selector, optionText)
```

### Navigation Commands
```javascript
cy.navigateToPage(pageName)
cy.clickNavItem(navText)
cy.verifyBreadcrumb(breadcrumbText)
```

### Modal Commands
```javascript
cy.openModal(modalTrigger)
cy.closeModal()
cy.confirmModal()
```

### Table Commands
```javascript
cy.verifyTableHeaders(tableSelector, headers)
cy.verifyTableData(tableSelector, expectedData)
cy.clickTableAction(tableSelector, rowIndex, actionButton)
```

### Search Commands
```javascript
cy.performSearch(searchTerm)
cy.clearSearch()
cy.testFiltering(filterSelector, filterValue)
```

### Utility Commands
```javascript
cy.uploadFile(selector, filePath)
cy.waitForPageLoad()
cy.checkNotification(message, type)
cy.measurePageLoad()
cy.checkAccessibility()
```

## 📊 Test Coverage

### Features Covered
- ✅ User authentication (Admin, Staff, Google OAuth)
- ✅ User registration and login
- ✅ Report submission and management
- ✅ Patient management
- ✅ Staff management
- ✅ Dashboard functionality
- ✅ Search and filtering
- ✅ Data export
- ✅ Geomapping
- ✅ File uploads
- ✅ Form validation
- ✅ Error handling
- ✅ Security measures
- ✅ Responsive design
- ✅ Accessibility
- ✅ Performance

### Test Scenarios
- ✅ Happy path scenarios
- ✅ Error handling scenarios
- ✅ Edge cases
- ✅ Security vulnerabilities
- ✅ Cross-browser compatibility
- ✅ Mobile responsiveness
- ✅ Performance benchmarks
- ✅ Data integrity
- ✅ User workflows

## 🔒 Security Testing

The test suite includes comprehensive security testing:

- **Authentication Security**: Tests for proper authentication and authorization
- **Input Validation**: Tests for SQL injection and XSS prevention
- **File Upload Security**: Tests for malicious file upload prevention
- **Session Management**: Tests for proper session handling and timeout
- **CSRF Protection**: Tests for cross-site request forgery prevention
- **Rate Limiting**: Tests for brute force attack prevention

## 📱 Responsive Testing

Tests are designed to work across different screen sizes:

- **Mobile**: iPhone 6 (375x667)
- **Tablet**: iPad 2 (768x1024)
- **Desktop**: MacBook 15 (1440x900)

## ♿ Accessibility Testing

The test suite includes accessibility checks:

- **Alt Text**: Verifies all images have alt text
- **Form Labels**: Verifies all form fields have proper labels
- **Keyboard Navigation**: Tests keyboard accessibility
- **ARIA Attributes**: Verifies proper ARIA implementation
- **Color Contrast**: Tests for proper color contrast ratios

## 🚀 Performance Testing

Performance tests measure:

- **Page Load Time**: Ensures pages load within acceptable time limits
- **Form Submission Time**: Measures form processing performance
- **Database Query Performance**: Tests database response times
- **File Upload Performance**: Measures file upload speeds

## 📝 Running Tests

### Run All Tests
```bash
npx cypress run
```

### Run Specific Test Suite
```bash
npx cypress run --spec "cypress/e2e/authentication/**/*.cy.js"
```

### Run Tests in Browser
```bash
npx cypress open
```

### Run Tests with Video Recording
```bash
npx cypress run --record --key YOUR_RECORD_KEY
```

## 🐛 Debugging

### Debug Mode
```bash
npx cypress run --headed --no-exit
```

### Screenshot on Failure
Screenshots are automatically taken on test failures and saved to `cypress/screenshots/`

### Video Recording
Videos are automatically recorded and saved to `cypress/videos/`

## 📈 Continuous Integration

### GitHub Actions Example
```yaml
name: Cypress Tests
on: [push, pull_request]
jobs:
  cypress-run:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup Node.js
        uses: actions/setup-node@v2
        with:
          node-version: '16'
      - name: Install dependencies
        run: npm install
      - name: Run Cypress tests
        run: npx cypress run
```

## 🔧 Maintenance

### Adding New Tests
1. Create test file in appropriate directory
2. Follow naming convention: `feature-name.cy.js`
3. Use existing custom commands and helpers
4. Add test data to `test-data.json` if needed
5. Update this README with new test coverage

### Updating Test Data
1. Modify `cypress/fixtures/test-data.json`
2. Update environment variables in `cypress.config.js`
3. Ensure test data is consistent across all tests

### Custom Commands
1. Add new commands to `cypress/support/commands.js`
2. Add helper functions to `cypress/support/test-helpers.js`
3. Document new commands in this README

## 📞 Support

For questions or issues with the test suite:

1. Check the Cypress documentation: https://docs.cypress.io/
2. Review test logs and screenshots
3. Check browser console for errors
4. Verify test data and environment configuration

## 📄 License

This test suite is part of the Animal Bite Incident Reporting System and follows the same license terms.
