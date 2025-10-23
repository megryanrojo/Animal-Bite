describe('Admin Geomapping Tests', () => {
  beforeEach(() => {
    cy.loginAsAdmin()
    cy.visit('/src/admin/geomapping.php')
  })

  it('should display geomapping page layout', () => {
    cy.contains('.navbar a.nav-link', 'Geomapping').should('have.class', 'active')
    cy.contains('h2', 'Geomapping Analysis').should('be.visible')
    cy.contains('p', 'Visualize animal bite cases by location for better decision making').should('be.visible')
  })

  it('should display map container and controls', () => {
    cy.get('#map').should('be.visible')
    cy.contains('button', 'Heatmap').should('be.visible')
    cy.contains('button', 'Markers').should('be.visible')
  })

  it('should display map filters and statistics', () => {
    cy.get('.filter-form').should('be.visible')
    cy.get('select[name="animal_type"]').should('be.visible')
    cy.get('select[name="bite_category"]').should('be.visible')
    cy.get('select[name="status"]').should('be.visible')
    cy.get('input[name="date_from"]').should('be.visible')
    cy.get('input[name="date_to"]').should('be.visible')
    cy.contains('button', 'Apply Filters').should('be.visible')
    cy.contains('a', 'Reset Filters').should('be.visible')
  })

  it('should handle map interactions', () => {
    cy.get('#map').should('be.visible')
    cy.contains('button', 'Heatmap').click()
    cy.get('#map').should('be.visible')
    
    cy.contains('button', 'Markers').click()
    cy.get('#map').should('be.visible')
  })

  it('should display map statistics', () => {
    cy.get('.stats-card').should('be.visible')
    cy.contains('.stats-card', 'Total Cases').should('be.visible')
    cy.contains('.stats-card', 'Hotspot Areas').should('be.visible')
    cy.contains('.stats-card', 'Highest Concentration').should('be.visible')
  })
})