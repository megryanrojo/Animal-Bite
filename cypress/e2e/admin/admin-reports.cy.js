describe('Admin Reports Tests', () => {
  beforeEach(() => {
    cy.loginAsAdmin()
    cy.visit('/src/admin/view_reports.php')
  })

  it('should display reports page layout', () => {
    cy.contains('.navbar a.nav-link', 'Reports').should('have.class', 'active')
    cy.contains('h2', 'Animal Bite Reports').should('be.visible')
    cy.contains('p', 'Manage and track all reported animal bite cases').should('be.visible')
  })

  it('should display reports table and filters', () => {
    cy.get('.filter-card').should('be.visible')
    cy.get('input[name="search"]').should('be.visible')
    cy.get('select[name="status"]').should('be.visible')
    cy.get('select[name="animal_type"]').should('be.visible')
    cy.get('select[name="bite_type"]').should('be.visible')
    cy.get('select[name="barangay"]').should('be.visible')
    cy.get('input[name="date_from"]').should('be.visible')
    cy.get('input[name="date_to"]').should('be.visible')
  })

  it('should handle search functionality', () => {
    cy.get('input[name="search"]').type('test')
    cy.contains('button', 'Filter Reports').click()
    cy.url().should('include', 'search=test')
  })

  it('should display report data in table', () => {
    cy.get('.table tbody tr').should('have.length.at.least', 1)
    cy.get('.table th').should('contain', 'ID')
    cy.get('.table th').should('contain', 'Patient Name')
    cy.get('.table th').should('contain', 'Contact')
    cy.get('.table th').should('contain', 'Barangay')
    cy.get('.table th').should('contain', 'Animal')
    cy.get('.table th').should('contain', 'Bite Date')
    cy.get('.table th').should('contain', 'Category')
    cy.get('.table th').should('contain', 'Status')
    cy.get('.table th').should('contain', 'Report Date')
    cy.get('.table th').should('contain', 'Actions')
  })

  it('should handle report actions', () => {
    cy.get('.table tbody tr').first().within(() => {
      // The Actions column uses an anchor icon for View and a dropdown for Edit
      cy.get('.btn-group > a.btn.btn-sm.btn-primary').should('be.visible')
      cy.get('.btn-group .dropdown-toggle').click()
      cy.get('.dropdown-menu').should('be.visible')
      cy.contains('.dropdown-menu a', 'Edit Report').should('be.visible')
    })
  })

  it('should display pagination', () => {
    cy.get('.pagination').should('be.visible')
    cy.get('.page-link').should('be.visible')
  })
})