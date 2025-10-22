describe('Admin Staff Management Tests', () => {
  beforeEach(() => {
    cy.loginAsAdmin()
    cy.visit('/src/admin/view_staff.php')
  })

  it('should display staff table with search, filter, and CRUD operations', () => {
    cy.contains('a.nav-link', 'Staff').should('exist')
    cy.get('.table').should('be.visible')
    cy.verifyTableHeaders('.table', [
      'Staff ID', 'Name', 'Email', 'Contact', 'Position', 'Status', 'Actions'
    ])
    cy.get('.table tbody tr').should('have.length.at.least', 1)
    cy.get('input[type="search"]').should('be.visible')
    cy.get('select').should('be.visible')
    
    // Test search and filter
    cy.performSearch('John')
    cy.get('tbody tr').should('have.length.at.least', 1)
    cy.clearSearch()
    cy.testFiltering('select[name="position"]', 'Health Worker')
    cy.get('tbody tr').should('have.length.at.least', 1)
  })

  it('should handle staff CRUD operations and password reset', () => {
    // View staff details
    cy.get('button[data-bs-target*="view"]').first().click()
    cy.get('.modal').should('be.visible')
    cy.get('.modal-title').should('contain', 'Staff Details')
    cy.get('.modal-body').should('contain', 'Personal Information')
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
  })

  it('should add new staff member with validation', () => {
    cy.get('a[href*="add_staff.html"]').click()
    cy.url().should('include', 'add_staff.html')
    
    cy.contains('button, a', 'Add Staff').should('exist')
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