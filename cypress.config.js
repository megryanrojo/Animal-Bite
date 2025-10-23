// Cypress configuration for Animal Bite System
// Note: Install Cypress first with: npm install cypress --save-dev

module.exports = {
  e2e: {
    projectId: "wbo4nj",
    baseUrl: 'http://localhost/Animal-Bite',
    viewportWidth: 1280,
    viewportHeight: 720,
    defaultCommandTimeout: 10000,
    requestTimeout: 10000,
    responseTimeout: 10000,
    pageLoadTimeout: 30000,
    video: true,
    screenshotOnRunFailure: true,
    supportFile: 'cypress/support/e2e.js',
    specPattern: [
      'cypress/e2e/admin/admin-dashboard.cy.js',
      'cypress/e2e/admin/admin-patients.cy.js',
      'cypress/e2e/admin/admin-reports.cy.js',
      'cypress/e2e/admin/admin-staff.cy.js',
      'cypress/e2e/admin/admin-geomapping.cy.js',
      'cypress/e2e/staff/staff-dashboard.cy.js',
      'cypress/e2e/staff/staff-patients.cy.js',
      'cypress/e2e/staff/staff-reports.cy.js',
      'cypress/e2e/staff/staff-search.cy.js',
      'cypress/e2e/authentication/authentication.cy.js',
      'cypress/e2e/integration/*.cy.js'
    ],
    excludeSpecPattern: ['cypress/e2e/user/**', 'cypress/e2e/**/*-optimized.cy.{js,jsx,ts,tsx}'],
    setupNodeEvents(on, config) {
      // implement node event listeners here
    },
    env: {
      // Test data
      testAdminEmail: 'megrojo76@gmail.com',
      testAdminPassword: 'dods12345',
      testStaffEmail: 'aaangeles.chmsu@gmail.com',
      testStaffPassword: 'dods12345',
      testUserEmail: 'user@test.com',
      testUserPassword: 'user123',
      
      // API endpoints
      apiBaseUrl: 'http://localhost/Animal-Bite/src',
      
      // Test data for reports
      testPatientData: {
        firstName: 'John',
        lastName: 'Doe',
        middleName: 'Michael',
        dateOfBirth: '1990-01-01',
        gender: 'Male',
        contactNumber: '09123456789',
        email: 'john.doe@test.com',
        address: '123 Test Street',
        barangay: 'Test Barangay',
        city: 'Test City',
        province: 'Test Province'
      },
      
      testBiteData: {
        animalType: 'Dog',
        biteDate: '2024-01-15',
        biteTime: '14:30',
        biteLocation: 'Left arm',
        biteDescription: 'Test bite description',
        firstAid: 'Washed with soap and water',
        previousRabiesVaccine: 'No',
        urgency: 'Normal'
      }
    }
  }
}