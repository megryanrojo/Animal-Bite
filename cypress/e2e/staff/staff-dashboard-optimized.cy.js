describe('Staff Dashboard Tests', () => {
  beforeEach(() => {
    cy.loginAsStaff()
    cy.visit('/src/staff/dashboard.php')
  })

  describe('Dashboard Core Functionality', () => {
    it('should display dashboard with navigation and statistics', () => {
      cy.get('h1').should('contain', 'Dashboard')
      cy.get('.navbar-brand').should('contain', 'BHW Animal Bite Portal')
      cy.get('.navbar-nav').should('be.visible')
      cy.get('.card').should('have.length.at.least', 3)
      cy.get('.card').should('contain', 'My Reports')
      cy.get('.card').should('contain', 'My Patients')
      cy.get('.card').should('contain', 'Pending Reports')
    })

    it('should display recent activity and quick actions', () => {
      cy.get('h5').should('contain', 'Recent Reports')
      cy.get('.table').should('be.visible')
      cy.get('.table tbody tr').should('have.length.at.least', 1)
      cy.get('.btn').should('contain', 'New Report')
      cy.get('.btn').should('contain', 'New Patient')
      cy.get('.btn').should('contain', 'View Reports')
    })

    it('should navigate to key pages', () => {
      cy.get('a[href*="new_report.php"]').click()
      cy.url().should('include', 'new_report.php')
      cy.visit('/src/staff/dashboard.php')
      
      cy.get('a[href*="new_patient.php"]').click()
      cy.url().should('include', 'new_patient.php')
      cy.visit('/src/staff/dashboard.php')
      
      cy.get('a[href*="reports.php"]').click()
      cy.url().should('include', 'reports.php')
    })

    it('should be responsive and handle logout', () => {
      cy.viewport('iphone-6')
      cy.get('.navbar-toggler').should('be.visible')
      cy.get('.navbar-toggler').click()
      cy.get('.navbar-collapse').should('be.visible')
      
      cy.logout()
      cy.url().should('include', 'login')
    })
  })
})
