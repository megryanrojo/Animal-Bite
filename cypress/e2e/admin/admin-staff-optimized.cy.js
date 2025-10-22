describe('Admin Staff Management Tests', () => {
  beforeEach(() => {
    cy.loginAsAdmin()
    cy.visit('/src/admin/view_staff.php')
  })

  describe('Staff List and Search', () => {
    it('should display staff table with search and filter functionality', () => {
      cy.get('h1').should('contain', 'Staff Management')
      cy.get('.table').should('be.visible')
      cy.verifyTableHeaders('.table', [
        'Staff ID', 'Name', 'Email', 'Contact', 'Position', 'Status', 'Actions'
      ])
      cy.get('.table tbody tr').should('have.length.at.least', 1)
      cy.get('input[type="search"]').should('be.visible')
      cy.get('select').should('be.visible')
    })

    it('should search and filter staff effectively', () => {
      cy.performSearch('John')
      cy.get('tbody tr').should('have.length.at.least', 1)
      cy.clearSearch()
      
      cy.testFiltering('select[name="position"]', 'Health Worker')
      cy.get('tbody tr').should('have.length.at.least', 1)
      cy.clearSearch()
      
      cy.testFiltering('select[name="status"]', 'Active')
      cy.get('tbody tr').should('have.length.at.least', 1)
    })
  })

  describe('Staff Actions', () => {
    it('should handle staff CRUD operations', () => {
      // View staff details
      cy.get('button[data-bs-target*="view"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Staff Details')
      cy.get('.modal-body').should('contain', 'Personal Information')
      cy.get('.modal-body').should('contain', 'Contact Information')
      cy.get('.modal .btn-close').click()

      // Edit staff
      cy.get('button[data-bs-target*="edit"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Edit Staff')
      cy.get('input[name="contactNumber"]').clear().type('09123456789')
      cy.get('button[type="submit"]').click()
      cy.get('.modal').should('not.exist')
      cy.checkNotification('Staff updated successfully', 'success')

      // Reset password
      cy.get('button[data-bs-target*="reset"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Reset Password')
      cy.get('input[name="newPassword"]').type('newpassword123')
      cy.get('input[name="confirmPassword"]').type('newpassword123')
      cy.get('button[type="submit"]').click()
      cy.get('.modal').should('not.exist')
      cy.checkNotification('Password reset successfully', 'success')

      // Delete staff confirmation
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

  describe('Add Staff Functionality', () => {
    it('should add new staff member with validation', () => {
      cy.get('a[href*="add_staff.html"]').click()
      cy.url().should('include', 'add_staff.html')
      
      cy.get('h1').should('contain', 'Add Staff')
      cy.get('form').should('be.visible')
      cy.get('input[name="firstName"]').should('be.visible')
      cy.get('input[name="lastName"]').should('be.visible')
      cy.get('input[name="email"]').should('be.visible')
      cy.get('input[name="contactNumber"]').should('be.visible')
      cy.get('select[name="position"]').should('be.visible')
      
      // Test validation
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
      
      // Test valid submission
      cy.fillFormField('input[name="firstName"]', 'John')
      cy.fillFormField('input[name="lastName"]', 'Doe')
      cy.fillFormField('input[name="email"]', 'john.doe@test.com')
      cy.fillFormField('input[name="contactNumber"]', '09123456789')
      cy.selectDropdownOption('select[name="position"]', 'Health Worker')
      cy.get('button[type="submit"]').click()
      cy.checkNotification('Staff member added successfully', 'success')
    })
  })

  describe('Export and Bulk Operations', () => {
    it('should handle export and bulk actions', () => {
      cy.get('button').contains('Export').should('be.visible')
      cy.get('button').contains('Export').click()
      cy.get('a[href*="export_staff.php"]').should('be.visible')
      
      cy.get('input[type="checkbox"]').first().should('be.visible')
      cy.get('input[type="checkbox"]').eq(1).check()
      cy.get('input[type="checkbox"]').eq(1).should('be.checked')
      cy.get('button').contains('Bulk Actions').should('be.visible')
    })

    it('should sort staff by different criteria', () => {
      cy.testSorting('Name')
      cy.testSorting('Email')
      cy.testSorting('Position')
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
