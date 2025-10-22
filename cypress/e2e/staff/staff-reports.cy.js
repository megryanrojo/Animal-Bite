describe('Staff Reports Management Tests', () => {
  beforeEach(() => {
    cy.loginAsStaff()
    cy.visit('/src/staff/reports.php')
  })

  it('should display reports table with search, filter, and CRUD operations', () => {
    cy.contains('.navbar a.nav-link.active, .navbar a.nav-link', 'Reports').should('exist')
    cy.get('.table').should('be.visible')
    cy.verifyTableHeaders('.table', [
      'Report ID', 'Patient Name', 'Animal Type', 'Bite Date', 'Status', 'Urgency', 'Actions'
    ])
    cy.get('.table tbody tr').should('have.length.at.least', 1)
    cy.get('input[type="search"]').should('be.visible')
    cy.get('select').should('be.visible')
    
    // Test search and filter
    cy.performSearch('John')
    cy.get('tbody tr').should('have.length.at.least', 1)
    cy.clearSearch()
    cy.testFiltering('select[name="status"]', 'Pending')
    cy.get('tbody tr').should('have.length.at.least', 1)
  })

  it('should handle report CRUD operations and create new reports', () => {
    // View report details
    cy.get('button[data-bs-target*="view"]').first().click()
    cy.get('.modal').should('be.visible')
    cy.get('.modal-title').should('contain', 'Report Details')
    cy.get('.modal-body').should('contain', 'Patient Information')
    cy.get('.modal .btn-close').click()

    // Edit report
    cy.get('button[data-bs-target*="edit"]').first().click()
    cy.get('.modal').should('be.visible')
    cy.get('.modal-title').should('contain', 'Edit Report')
    cy.get('select[name="status"]').select('Reviewed')
    cy.get('select[name="urgency"]').select('High')
    cy.get('button[type="submit"]').click()
    cy.get('.modal').should('not.exist')
    cy.checkNotification('Report updated successfully', 'success')

    // Create new report
    cy.get('a[href*="new_report.php"]').click()
    cy.url().should('include', 'new_report.php')
    
    cy.contains('button, a', 'New Report').should('exist')
    cy.get('form').should('be.visible')
    cy.get('input[name="firstName"]').should('be.visible')
    cy.get('input[name="lastName"]').should('be.visible')
    cy.get('input[name="contactNumber"]').should('be.visible')
    cy.get('select[name="animalType"]').should('be.visible')
    cy.get('input[name="biteDate"]').should('be.visible')
    
    // Test validation
    cy.get('button[type="submit"]').click()
    cy.get('.invalid-feedback').should('be.visible')
    
    // Test valid submission
    cy.fillPatientForm()
    cy.fillBiteForm()
    cy.get('button[type="submit"]').click()
    cy.checkNotification('Report created successfully', 'success')
  })
})