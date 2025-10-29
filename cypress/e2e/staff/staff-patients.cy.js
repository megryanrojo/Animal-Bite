describe('Staff Patients Tests', () => {
  beforeEach(() => {
    cy.loginAsStaff()
    cy.visit('/src/staff/patients.php')
  })

  it('should display patients page layout', () => {
    cy.contains('.navbar a.nav-link', 'Patients').should('have.class', 'active')
    cy.contains('h2', 'Patient Management').should('be.visible')
    cy.contains('p', 'View and manage patient records').should('be.visible')
    cy.contains('a.btn', 'New Patient').should('be.visible')
  })

  it('should display patients table and filters', () => {
    cy.get('.content-card').should('be.visible')
    cy.contains('.content-card h5', 'Patient Records').should('be.visible')
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
    cy.get('select[name="gender"]').should('be.visible')
    cy.get('select[name="barangay"]').should('be.visible')
    cy.contains('button', 'Apply Filters').should('be.visible')
    cy.contains('a,button', 'Clear Filters').should('be.visible')
  })

  it('should display patient data in table', () => {
    cy.get('.table tbody tr').should('have.length.at.least', 1)
    cy.get('.table th').should('contain', 'ID')
    cy.get('.table th').should('contain', 'First Name')
    cy.get('.table th').should('contain', 'Last Name')
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
      cy.get('a[href*="view_patient.php"]').should('be.visible')
      cy.get('a[href*="edit_patient.php"]').should('be.visible')
      cy.get('a[href*="new_report.php"]').should('be.visible')
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