describe('Admin Dashboard Tests', () => {
  beforeEach(() => {
    cy.loginAsAdmin()
    cy.visit('/src/admin/admin_dashboard.php')
  })

  describe('Dashboard Core Functionality', () => {
    it('should display dashboard with navigation and statistics', () => {
      cy.get('h1').should('contain', 'Admin Dashboard')
      cy.get('.navbar-brand').should('contain', 'Animal Bite Admin Portal')
      cy.get('.navbar-nav').should('be.visible')
      cy.get('.card').should('have.length.at.least', 4)
      cy.get('.card').should('contain', 'Total Reports')
      cy.get('.card').should('contain', 'Total Patients')
      cy.get('.card').should('contain', 'Urgent Cases')
      cy.get('.card').should('contain', 'Staff Members')
    })

    it('should display recent activity and quick actions', () => {
      cy.get('h5').should('contain', 'Recent Reports')
      cy.get('.table').should('be.visible')
      cy.get('.table tbody tr').should('have.length.at.least', 1)
      cy.get('.btn').should('contain', 'Add Staff')
      cy.get('.btn').should('contain', 'View Reports')
      cy.get('.btn').should('contain', 'View Patients')
    })

    it('should navigate to key pages', () => {
      cy.get('a[href*="add_staff.html"]').click()
      cy.url().should('include', 'add_staff.html')
      cy.visit('/src/admin/admin_dashboard.php')
      
      cy.get('a[href*="view_reports.php"]').click()
      cy.url().should('include', 'view_reports.php')
      cy.visit('/src/admin/admin_dashboard.php')
      
      cy.get('a[href*="view_patients.php"]').click()
      cy.url().should('include', 'view_patients.php')
    })

    it('should be responsive and accessible', () => {
      cy.viewport('iphone-6')
      cy.get('.navbar-toggler').should('be.visible')
      cy.get('.navbar-toggler').click()
      cy.get('.navbar-collapse').should('be.visible')
      
      cy.get('h1').should('exist')
      cy.get('img').each(($img) => {
        cy.wrap($img).should('have.attr', 'alt')
      })
    })

    it('should handle logout and session management', () => {
      cy.logout()
      cy.url().should('include', 'login')
      cy.visit('/src/admin/admin_dashboard.php')
      cy.url().should('include', 'login')
    })
  })
})
