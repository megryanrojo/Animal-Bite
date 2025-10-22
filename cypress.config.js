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
    specPattern: 'cypress/e2e/**/*.cy.{js,jsx,ts,tsx}',
    setupNodeEvents(on, config) {
      // implement node event listeners here
    },
    env: {
      // Test data
      testAdminEmail: 'megrojo76@gmail.com',
      testAdminPassword: 'dods12345',
      testStaffEmail: 'staff@test.com',
      testStaffPassword: 'staff123',
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