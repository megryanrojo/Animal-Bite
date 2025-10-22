describe('Data Flow Tests (Staff/Admin only)', () => {
  beforeEach(() => {
      cy.loginAsStaff()
  })

  it('staff can view and edit report list and details', () => {
      cy.visit('/src/staff/reports.php')
    cy.get('table tbody tr').first().should('be.visible')
    cy.get('table tbody tr').first().find('a').contains('View').click()
    cy.url().should('include', 'view_report.php')
    cy.contains('h2, h3, .card-title', 'Report Details', { matchCase: false })
    cy.contains('button, a', 'Edit').click()
    cy.url().should('include', 'edit_report.php')
    cy.get('textarea').first().type('Staff notes added')
      cy.get('button[type="submit"], button').contains('Save').click({ force: true })
    cy.url().should('include', 'reports.php')
      
  })

  it('admin can review staff-updated report', () => {
      cy.visit('/src/staff/reports.php')
    cy.get('table tbody tr').first().should('be.visible')
    cy.get('table tbody tr').first().find('a').contains('View').click()
    cy.url().should('include', 'view_report.php')
    cy.contains('h2, h3, .card-title', 'Report Details', { matchCase: false })
    cy.contains('button, a', 'Edit').click()
    cy.url().should('include', 'edit_report.php')
    cy.get('textarea').first().type('Staff notes added')
      cy.get('button[type="submit"], button').contains('Save').click({ force: true })
    cy.url().should('include', 'reports.php')
      
    // Admin reviews report
      cy.loginAsAdmin()
      cy.visit('/src/admin/view_reports.php')
    cy.get('table tbody tr').first().should('be.visible')
    cy.get('table tbody tr').first().find('a').contains('View').click()
    cy.url().should('include', 'view_report.php')
    cy.contains('h2, h3, .card-title', 'Report Details', { matchCase: false })
    cy.contains('button, a', 'Edit').click()
    cy.url().should('include', 'edit_report.php')
    cy.get('textarea').first().type('Admin notes added')
      cy.get('button[type="submit"], button').contains('Save').click({ force: true })
    cy.url().should('include', 'view_reports.php')
  })
})