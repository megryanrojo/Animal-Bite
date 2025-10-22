describe('Staff Patients Management Tests', () => {
  beforeEach(() => {
    cy.loginAsStaff()
    cy.visit('/src/staff/patients.php')
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

  it('should handle patient CRUD operations and create new patients', () => {
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

    // Create new patient
    cy.get('a[href*="new_patient.php"]').click()
    cy.url().should('include', 'new_patient.php')
    
    cy.contains('button, a', 'New Patient').should('exist')
    cy.get('form').should('be.visible')
    cy.get('input[name="firstName"]').should('be.visible')
    cy.get('input[name="lastName"]').should('be.visible')
    cy.get('input[name="contactNumber"]').should('be.visible')
    cy.get('input[name="dateOfBirth"]').should('be.visible')
    cy.get('select[name="gender"]').should('be.visible')
    
    // Test validation
    cy.get('button[type="submit"]').click()
    cy.get('.invalid-feedback').should('be.visible')
    
    // Test valid submission
    cy.fillPatientForm()
    cy.get('button[type="submit"]').click()
    cy.checkNotification('Patient created successfully', 'success')
  })
})