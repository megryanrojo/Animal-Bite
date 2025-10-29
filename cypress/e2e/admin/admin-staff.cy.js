  describe('Admin Staff Tests', () => {
    beforeEach(() => {
      cy.loginAsAdmin()
      cy.visit('/src/admin/view_staff.php')
    })

    it('should display staff page layout', () => {
      cy.contains('.navbar a.nav-link', 'Staff').should('have.class', 'active')
      cy.contains('h2', 'Staff Management').should('be.visible')
      cy.contains('p', 'View and manage barangay health workers').should('be.visible')
      cy.contains('a.btn', 'Add New Staff').should('be.visible')
    })

    it('should display staff table and filters', () => {
      cy.get('.filter-card').should('be.visible')
      cy.get('input[name="search"]').should('be.visible')
      cy.contains('button', 'Search').should('be.visible')
      cy.contains('a', 'Clear').should('be.visible')
    })

    it('should handle search functionality', () => {
      cy.get('input[name="search"]').type('test')
      cy.contains('button', 'Search').click()
      cy.url().should('include', 'search=test')
    })

    it('should display staff data in table', () => {
      cy.get('.table tbody tr').should('have.length.at.least', 1)
      cy.get('.table th').should('contain', 'Name')
      cy.get('.table th').should('contain', 'Contact Number')
      cy.get('.table th').should('contain', 'Email')
      cy.get('.table th').should('contain', 'Birth Date')
      cy.get('.table th').should('contain', 'Actions')
    })

    it('should handle staff actions', () => {
      cy.get('.table tbody tr').first().within(() => {
        cy.contains('a,button', 'Edit').should('be.visible')
        cy.contains('a,button', 'Delete').should('be.visible')
      })
    })

    it('should display pagination', () => {
    cy.get('body').then(($body) => {
      if ($body.find('.pagination').length) {
        cy.get('.pagination').should('be.visible')
        cy.get('.page-link').should('be.visible')
      } else {
        // When total records fit on a single page, pagination is not rendered
        cy.contains('.text-center.mt-3 p', 'Showing').should('be.visible')
        cy.contains('.text-center.mt-3 p', 'staff members').should('be.visible')
      }
    })
    })
  })