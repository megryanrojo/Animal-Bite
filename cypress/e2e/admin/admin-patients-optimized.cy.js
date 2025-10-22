describe('Admin Patients Management Tests', () => {
  beforeEach(() => {
    cy.loginAsAdmin()
    cy.visit('/src/admin/view_patients.php')
  })

  describe('Patients List and Search', () => {
    it('should display patients table with search and filter functionality', () => {
      cy.get('h1').should('contain', 'Patients')
      cy.get('.table').should('be.visible')
      cy.verifyTableHeaders('.table', [
        'Patient ID', 'Name', 'Contact', 'Address', 'Date of Birth', 'Gender', 'Actions'
      ])
      cy.get('.table tbody tr').should('have.length.at.least', 1)
      cy.get('input[type="search"]').should('be.visible')
      cy.get('select').should('be.visible')
    })

    it('should search and filter patients effectively', () => {
      cy.performSearch('John')
      cy.get('tbody tr').should('have.length.at.least', 1)
      cy.clearSearch()
      
      cy.testFiltering('select[name="gender"]', 'Male')
      cy.get('tbody tr').should('have.length.at.least', 1)
      cy.clearSearch()
      
      cy.testFiltering('select[name="age_range"]', '18-30')
      cy.get('tbody tr').should('have.length.at.least', 1)
    })
  })

  describe('Patient Actions', () => {
    it('should handle patient CRUD operations', () => {
      // View patient details
      cy.get('button[data-bs-target*="view"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Patient Details')
      cy.get('.modal-body').should('contain', 'Personal Information')
      cy.get('.modal-body').should('contain', 'Contact Information')
      cy.get('.modal .btn-close').click()

      // Edit patient
      cy.get('button[data-bs-target*="edit"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Edit Patient')
      cy.get('input[name="contactNumber"]').clear().type('09123456789')
      cy.get('button[type="submit"]').click()
      cy.get('.modal').should('not.exist')
      cy.checkNotification('Patient updated successfully', 'success')

      // Delete patient confirmation
      cy.get('button[data-bs-target*="delete"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-body').should('contain', 'Are you sure')
      cy.get('button').contains('Cancel').click()
      cy.get('.modal').should('not.exist')
    })

    it('should validate form inputs and handle errors', () => {
      cy.get('button[data-bs-target*="edit"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('input[name="firstName"]').clear()
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
      cy.get('button').contains('Cancel').click()
    })
  })

  describe('Export and Bulk Operations', () => {
    it('should handle export and bulk actions', () => {
      cy.get('button').contains('Export').should('be.visible')
      cy.get('button').contains('Export').click()
      cy.get('a[href*="export_patients.php"]').should('be.visible')
      
      cy.get('input[type="checkbox"]').first().should('be.visible')
      cy.get('input[type="checkbox"]').eq(1).check()
      cy.get('input[type="checkbox"]').eq(1).should('be.checked')
      cy.get('button').contains('Bulk Actions').should('be.visible')
    })

    it('should sort patients by different criteria', () => {
      cy.testSorting('Name')
      cy.testSorting('Date of Birth')
      cy.testSorting('Contact')
    })
  })

  describe('Responsive Design and Performance', () => {
    it('should be responsive and performant', () => {
      cy.viewport('iphone-6')
      cy.get('.table-responsive').should('be.visible')
      cy.get('.table').should('be.visible')
      
      cy.measurePageLoad()
      cy.get('.table tbody tr').should('have.length.at.least', 1)
    })
  })
})
