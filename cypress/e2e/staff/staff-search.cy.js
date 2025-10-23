describe('Staff Search Tests', () => {
  beforeEach(() => {
    cy.loginAsStaff()
    cy.visit('/src/staff/search.php')
  })

  it('should display search page layout', () => {
    cy.contains('.navbar a.nav-link', 'Search').should('have.class', 'active')
    cy.contains('h1', 'Search Patients & Reports').should('be.visible')
    cy.contains('p', 'Find patient records or animal bite reports quickly and easily.').should('be.visible')
    })

    it('should display search form', () => {
    cy.get('form').should('be.visible')
    cy.get('input[name="q"]').should('be.visible')
    cy.contains('button', 'Search').should('be.visible')
  })

  it('should handle basic search', () => {
    cy.get('input[name="q"]').type('test')
    cy.contains('button', 'Search').click()
    cy.url().should('include', 'q=test')
    })

    it('should display search results', () => {
    cy.get('input[name="q"]').type('test')
    cy.contains('button', 'Search').click()
    cy.get('.result-section-title').should('be.visible')
    cy.get('.result-card').should('have.length.at.least', 1)
  })

  it('should display patients and reports sections', () => {
    cy.contains('.result-section-title', 'Patients').should('be.visible')
    cy.contains('.result-section-title', 'Reports').should('be.visible')
    cy.get('.table').should('be.visible')
  })

  it('should display report data in table', () => {
    cy.get('.table tbody tr').should('have.length.at.least', 1)
    cy.get('.table th').should('contain', 'Report #')
    cy.get('.table th').should('contain', 'Patient')
    cy.get('.table th').should('contain', 'Contact')
    cy.get('.table th').should('contain', 'Barangay')
    cy.get('.table th').should('contain', 'Animal')
    cy.get('.table th').should('contain', 'Bite Type')
    cy.get('.table th').should('contain', 'Date')
    cy.get('.table th').should('contain', 'Actions')
  })

  it('should display pagination', () => {
    cy.get('.pagination').should('be.visible')
    cy.get('.page-link').should('be.visible')
  })
})