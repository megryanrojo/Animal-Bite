// ***********************************************************
// This example support/e2e.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Import commands.js using ES2015 syntax:
import './commands'

// Alternatively you can use CommonJS syntax:
// require('./commands')

// Global test configuration
beforeEach(() => {
  // Clear cookies and local storage before each test
  cy.clearCookies()
  cy.clearLocalStorage()
  
  // Set default viewport
  cy.viewport(1280, 720)
})

// Global error handling
Cypress.on('uncaught:exception', (err, runnable) => {
  // Prevent Cypress from failing the test on uncaught exceptions
  // that are not related to the application under test
  if (err.message.includes('ResizeObserver loop limit exceeded')) {
    return false
  }
  if (err.message.includes('Non-Error promise rejection captured')) {
    return false
  }
  return true
})

// Custom commands for common actions
Cypress.Commands.add('loginAsAdmin', (email = Cypress.env('testAdminEmail'), password = Cypress.env('testAdminPassword')) => {
  cy.visit('/src/login/admin_login.html')
  cy.get('#email').type(email)
  cy.get('#password').type(password)
  cy.get('button[type="submit"]').click()
  cy.url().should('include', '/admin/admin_dashboard.php')
})

Cypress.Commands.add('loginAsStaff', (email = Cypress.env('testStaffEmail'), password = Cypress.env('testStaffPassword')) => {
  cy.visit('/src/login/staff_login.html')
  cy.get('#email').type(email)
  cy.get('#password').type(password)
  cy.get('button[type="submit"]').click()
  cy.url().should('include', '/staff/dashboard.php')
})

Cypress.Commands.add('logout', () => {
  cy.get('a[href*="logout"]').click()
  cy.url().should('include', 'login')
})

Cypress.Commands.add('fillPatientForm', (patientData = Cypress.env('testPatientData')) => {
  cy.get('#firstName').type(patientData.firstName)
  cy.get('#lastName').type(patientData.lastName)
  cy.get('#middleName').type(patientData.middleName)
  cy.get('#dateOfBirth').type(patientData.dateOfBirth)
  cy.get('#gender').select(patientData.gender)
  cy.get('#contactNumber').type(patientData.contactNumber)
  cy.get('#email').type(patientData.email)
  cy.get('#address').type(patientData.address)
  cy.get('#barangay').type(patientData.barangay)
  cy.get('#city').type(patientData.city)
  cy.get('#province').type(patientData.province)
})

Cypress.Commands.add('fillBiteForm', (biteData = Cypress.env('testBiteData')) => {
  cy.get('#animalType').select(biteData.animalType)
  cy.get('#biteDate').type(biteData.biteDate)
  cy.get('#biteTime').type(biteData.biteTime)
  cy.get('#biteLocation').type(biteData.biteLocation)
  cy.get('#biteDescription').type(biteData.biteDescription)
  cy.get('#firstAid').type(biteData.firstAid)
  cy.get('#previousRabiesVaccine').select(biteData.previousRabiesVaccine)
  cy.get('#urgency').select(biteData.urgency)
})

Cypress.Commands.add('uploadFile', (selector, filePath) => {
  cy.get(selector).selectFile(filePath, { force: true })
})

Cypress.Commands.add('waitForPageLoad', () => {
  cy.get('body').should('be.visible')
  cy.get('[data-testid="loading"]', { timeout: 10000 }).should('not.exist')
})

Cypress.Commands.add('checkNotification', (message, type = 'success') => {
  cy.get('.alert').should('be.visible')
  cy.get('.alert').should('contain', message)
  if (type === 'success') {
    cy.get('.alert-success').should('be.visible')
  } else if (type === 'error') {
    cy.get('.alert-danger').should('be.visible')
  }
})

Cypress.Commands.add('navigateToPage', (pageName) => {
  cy.get(`a[href*="${pageName}"]`).click()
  cy.url().should('include', pageName)
})

Cypress.Commands.add('verifyTableData', (tableSelector, expectedData) => {
  cy.get(tableSelector).should('be.visible')
  expectedData.forEach((row, index) => {
    cy.get(`${tableSelector} tbody tr`).eq(index).should('contain', row)
  })
})

Cypress.Commands.add('searchAndVerify', (searchTerm, expectedResults) => {
  cy.get('input[type="search"]').type(searchTerm)
  cy.get('button[type="submit"]').click()
  cy.get('tbody tr').should('have.length.at.least', expectedResults)
})

Cypress.Commands.add('deleteRecord', (confirmText = 'Yes, delete it!') => {
  cy.get('button[data-bs-target*="delete"]').click()
  cy.get('.modal').should('be.visible')
  cy.get('button').contains(confirmText).click()
  cy.get('.modal').should('not.exist')
})

Cypress.Commands.add('editRecord', () => {
  cy.get('button[data-bs-target*="edit"]').first().click()
  cy.get('.modal').should('be.visible')
})

Cypress.Commands.add('saveForm', () => {
  cy.get('button[type="submit"]').click()
  cy.wait(1000) // Wait for form submission
})

Cypress.Commands.add('verifyFormValidation', (fieldSelector, errorMessage) => {
  cy.get(fieldSelector).clear()
  cy.get('button[type="submit"]').click()
  cy.get('.invalid-feedback').should('contain', errorMessage)
})

Cypress.Commands.add('checkAccessibility', () => {
  // Basic accessibility checks
  cy.get('img').each(($img) => {
    cy.wrap($img).should('have.attr', 'alt')
  })
  
  cy.get('a').each(($link) => {
    cy.wrap($link).should('have.attr', 'href')
  })
  
  cy.get('button').each(($button) => {
    cy.wrap($button).should('not.be.empty')
  })
})