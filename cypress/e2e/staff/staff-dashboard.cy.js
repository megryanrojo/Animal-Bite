describe('Staff Dashboard Tests', () => {
  beforeEach(() => {
    cy.loginAsStaff()
    cy.visit('/src/staff/dashboard.php')
  })

  it('should display staff dashboard layout', () => {
    cy.get('.navbar .navbar-brand').should('contain', 'BHW Animal Bite Portal')
    cy.contains('.navbar a.nav-link', 'Dashboard').should('have.class', 'active')
    cy.contains('.navbar a.nav-link', 'Reports').should('be.visible')
    cy.contains('.navbar a.nav-link', 'Patients').should('be.visible')
    cy.contains('.navbar a.nav-link', 'Search').should('be.visible')
  })

  it('should display welcome section', () => {
    cy.contains('h2', 'Welcome').should('be.visible')
    cy.contains('p', 'Here\'s an overview of animal bite cases and activities').should('be.visible')
    cy.contains('a.btn', 'New Bite Report').should('be.visible')
  })

  it('should display statistics cards', () => {
    cy.get('.stats-card').should('have.length.at.least', 4)
    cy.contains('.stats-card h5', 'Total Cases').should('be.visible')
    cy.contains('.stats-card h5', 'Today\'s Cases').should('be.visible')
    cy.contains('.stats-card h5', 'Pending').should('be.visible')
    cy.contains('.stats-card h5', 'Category III').should('be.visible')
    cy.get('.stats-number').should('be.visible')
  })

  it('should display quick actions', () => {
    cy.contains('.content-card h5', 'Quick Actions').should('be.visible')
    cy.contains('.action-card', 'New Bite Report').should('be.visible')
    cy.contains('.action-card', 'Add Patient').should('be.visible')
    cy.contains('.action-card', 'Search Records').should('be.visible')
  })

  it('should display recent bite cases table', () => {
    cy.contains('.content-card h5', 'Recent Bite Cases').should('be.visible')
    cy.contains('a.btn', 'View All').should('be.visible')
    cy.get('.table tbody tr').should('have.length.at.least', 1)
    cy.get('.table th').should('contain', 'ID')
    cy.get('.table th').should('contain', 'Patient')
    cy.get('.table th').should('contain', 'Animal')
    cy.get('.table th').should('contain', 'Date')
    cy.get('.table th').should('contain', 'Category')
    cy.get('.table th').should('contain', 'Status')
    cy.get('.table th').should('contain', 'Actions')
  })

  it('should handle navigation to other pages', () => {
    cy.contains('.navbar a.nav-link', 'Reports').click()
    cy.url().should('include', 'reports.php')
    
    cy.contains('.navbar a.nav-link', 'Patients').click()
    cy.url().should('include', 'patients.php')
  })
})