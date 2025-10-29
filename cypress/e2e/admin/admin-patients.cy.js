describe('Admin Patients Tests', () => {
  beforeEach(() => {
    cy.loginAsAdmin()
    cy.visit('/src/admin/view_patients.php')
  })

  it('should display patients page layout', () => {
    cy.contains('.navbar a.nav-link', 'Patients').should('have.class', 'active')
    cy.contains('h2', 'Patients').should('be.visible')
  })

  it('should display patients table and filters', () => {
    cy.get('.content-card').should('be.visible')
    cy.get('input[name="search"]').should('be.visible')
    cy.get('select[name="gender"]').should('be.visible')
    cy.get('select[name="barangay"]').should('be.visible')
    cy.contains('button', 'Search').should('be.visible')
  })

  it('should handle search functionality', () => {
    cy.get('input[name="search"]').type('test')
    cy.contains('button', 'Search').click()
    cy.url().should('include', 'search=test')
  })

  it('should display patient data in table', () => {
    cy.get('.table tbody tr').should('have.length.at.least', 1)
    cy.get('.table th').should('contain', 'ID')
    cy.get('.table th').should('contain', 'Name')
    cy.get('.table th').should('contain', 'Gender')
    cy.get('.table th').should('contain', 'Age')
    cy.get('.table th').should('contain', 'Contact')
    cy.get('.table th').should('contain', 'Barangay')
    cy.get('.table th').should('contain', 'Reports')
    cy.get('.table th').should('contain', 'Last Visit')
    cy.get('.table th').should('contain', 'Actions')
  })

  it('should handle patient actions', () => {
    cy.get('.table tbody tr').first().within(() => {
      // Actions use an anchor styled as a button
      cy.contains('a', 'View').should('be.visible')
    })
  })

  it('should display pagination', () => {
    cy.get('.pagination').should('be.visible')
    cy.get('.page-link').should('be.visible')
  })
})