describe('Staff Reports Tests', () => {
  beforeEach(() => {
    cy.loginAsStaff()
    cy.visit('/src/staff/reports.php')
  })

  it('should display reports page layout', () => {
    cy.contains('.navbar a.nav-link', 'Reports').should('have.class', 'active')
    cy.contains('h2', 'Animal Bite Reports').should('be.visible')
    cy.contains('p', 'Manage and track all animal bite cases').should('be.visible')
    cy.contains('a.btn', 'New Report').should('be.visible')
  })

  it('should display reports table and filters', () => {
    cy.get('.content-card').should('be.visible')
    cy.contains('.content-card h5', 'Reports List').should('be.visible')
    cy.get('.filter-form').should('be.visible')
    cy.get('input[name="search"]').should('be.visible')
    cy.contains('button', 'Advanced Filters').should('be.visible')
  })

  it('should handle search functionality', () => {
    cy.get('input[name="search"]').type('test')
    cy.get('.filter-form form .input-group button[type="submit"]').click()
    cy.url().should('include', 'search=test')
  })

  it('should display advanced filters', () => {
    cy.contains('button', 'Advanced Filters').click()
    cy.get('input[name="date_from"]').should('be.visible')
    cy.get('input[name="date_to"]').should('be.visible')
    cy.get('select[name="animal_type"]').should('be.visible')
    cy.get('select[name="bite_category"]').should('be.visible')
    cy.get('select[name="status"]').should('be.visible')
    cy.contains('button', 'Apply Filters').should('be.visible')
    cy.get('.filter-form a[href="reports.php"]').should('be.visible')
  })

  it('should display report data in table', () => {
    cy.get('.table tbody tr').should('have.length.at.least', 1)
    cy.get('.table th').should('contain', 'ID')
    cy.get('.table th').should('contain', 'Patient')
    cy.get('.table th').should('contain', 'Animal')
    cy.get('.table th').should('contain', 'Bite Date')
    cy.get('.table th').should('contain', 'Category')
    cy.get('.table th').should('contain', 'Status')
    cy.get('.table th').should('contain', 'Report Date')
    cy.get('.table th').should('contain', 'Actions')
  })

  it('should handle report actions', () => {
    cy.get('.table tbody tr').first().within(() => {
      cy.get('a[href*="view_report.php"]').should('be.visible')
      cy.get('a[href*="edit_report.php"]').should('be.visible')
    })
  })

  it('should display pagination', () => {
    cy.get('body').then(($body) => {
      if ($body.find('.pagination').length) {
        cy.get('.pagination').should('be.visible')
        cy.get('.page-link').should('be.visible')
      } else {
        cy.contains('.d-flex.p-3 span.text-muted', 'Showing').should('be.visible')
      }
    })
  })
})