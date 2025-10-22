describe('Admin Reports Management Tests', () => {
  beforeEach(() => {
    cy.loginAsAdmin()
    cy.visit('/src/admin/view_reports.php')
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

  it('should handle report CRUD operations and validation', () => {
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

    // Print report
    cy.get('button[data-bs-target*="print"]').first().click()
    cy.get('.modal').should('be.visible')
    cy.get('.modal-title').should('contain', 'Print Report')
    cy.get('button').contains('Print').should('be.visible')
    cy.get('.modal .btn-close').click()

    // Test validation
    cy.get('button[data-bs-target*="edit"]').first().click()
    cy.get('.modal').should('be.visible')
    cy.get('input[name="firstName"]').clear()
    cy.get('button[type="submit"]').click()
    cy.get('.invalid-feedback').should('be.visible')
    cy.get('button').contains('Cancel').click()
  })

  it('should handle export, bulk actions, and sorting', () => {
    cy.get('button').contains('Export').should('be.visible')
    cy.get('button').contains('Export').click()
    cy.get('a[href*="export_reports.php"]').should('be.visible')
    
    cy.get('input[type="checkbox"]').first().should('be.visible')
    cy.get('input[type="checkbox"]').eq(1).check()
    cy.get('input[type="checkbox"]').eq(1).should('be.checked')
    cy.get('button').contains('Bulk Actions').should('be.visible')
    
    cy.testSorting('Bite Date')
    cy.testSorting('Patient Name')
  })
})