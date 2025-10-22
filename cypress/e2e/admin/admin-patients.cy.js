describe('Admin Patients Management Tests', () => {
  beforeEach(() => {
    cy.loginAsAdmin()
    cy.visit('/src/admin/view_patients.php')
  })

  it('should display patients table with search, filter, and CRUD operations', () => {
    cy.contains('.navbar a.nav-link.active, .navbar a.nav-link', 'Patients').should('exist')
    cy.get('.table').should('be.visible')
    cy.verifyTableHeaders('.table', [
      'Patient ID', 'Name', 'Contact', 'Address', 'Date of Birth', 'Gender', 'Actions'
    ])
    cy.get('.table tbody tr').should('have.length.at.least', 1)
    cy.get('input[type="search"]').should('be.visible')
    cy.get('select').should('be.visible')
    
    // Test search and filter
    cy.performSearch('John')
    cy.get('tbody tr').should('have.length.at.least', 1)
    cy.clearSearch()
    cy.testFiltering('select[name="gender"]', 'Male')
    cy.get('tbody tr').should('have.length.at.least', 1)
  })

  it('should handle patient CRUD operations and validation', () => {
    // View patient details
    cy.get('button[data-bs-target*="view"]').first().click()
    cy.get('.modal').should('be.visible')
    cy.get('.modal-title').should('contain', 'Patient Details')
    cy.get('.modal-body').should('contain', 'Personal Information')
    cy.get('.modal .btn-close').click()

    // Edit patient
    cy.get('button[data-bs-target*="edit"]').first().click()
    cy.get('.modal').should('be.visible')
    cy.get('.modal-title').should('contain', 'Edit Patient')
    cy.get('input[name="contactNumber"]').clear().type('09123456789')
    cy.get('button[type="submit"]').click()
    cy.get('.modal').should('not.exist')
    cy.checkNotification('Patient updated successfully', 'success')

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
    cy.get('a[href*="export_patients.php"]').should('be.visible')
    
    cy.get('input[type="checkbox"]').first().should('be.visible')
    cy.get('input[type="checkbox"]').eq(1).check()
    cy.get('input[type="checkbox"]').eq(1).should('be.checked')
    cy.get('button').contains('Bulk Actions').should('be.visible')
    
    cy.testSorting('Name')
    cy.testSorting('Date of Birth')
  })
})