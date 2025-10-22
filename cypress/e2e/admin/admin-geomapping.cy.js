describe('Admin Geomapping Tests', () => {
  beforeEach(() => {
    cy.loginAsAdmin()
    cy.visit('/src/admin/geomapping.php')
  })

  it('should display map with controls, markers, and interactions', () => {
    cy.contains('.navbar a.nav-link.active, .navbar a.nav-link', 'Geomapping').should('exist')
    cy.get('.map-container').should('be.visible')
    cy.get('#map').should('be.visible')
    cy.get('#map .leaflet-tile').should('be.visible')
    cy.get('#map .leaflet-marker').should('have.length.at.least', 1)
    
    cy.get('.map-controls').should('be.visible')
    cy.get('button').should('contain', 'Refresh Map')
    cy.get('button').should('contain', 'Toggle Heatmap')
    cy.get('.map-legend').should('be.visible')
    
    // Test map interactions
    cy.get('#map .leaflet-marker').first().click()
    cy.get('.leaflet-popup').should('be.visible')
    cy.get('.leaflet-popup-content').should('contain', 'Report ID')
    cy.get('.leaflet-popup-content').should('contain', 'Patient Name')
    cy.get('.leaflet-popup-content').should('contain', 'Animal Type')
    cy.get('.leaflet-popup-close-button').click()
    cy.get('.leaflet-popup').should('not.exist')
  })

  it('should handle map controls, filters, and search', () => {
    // Test map controls
    cy.get('button').contains('Refresh Map').click()
    cy.get('#map').should('be.visible')
    
    cy.get('button').contains('Toggle Heatmap').click()
    cy.get('#map').should('be.visible')
    
    cy.get('.leaflet-control-zoom-in').click()
    cy.get('#map').should('be.visible')
    
    cy.get('.leaflet-control-zoom-out').click()
    cy.get('#map').should('be.visible')
    
    // Test filters
    cy.get('.map-filters').should('be.visible')
    cy.get('select[name="animalType"]').should('be.visible')
    cy.get('select[name="urgency"]').should('be.visible')
    cy.get('select[name="dateRange"]').should('be.visible')
    
    cy.get('select[name="animalType"]').select('Dog')
    cy.get('button').contains('Apply Filters').click()
    cy.get('#map').should('be.visible')
    
    cy.get('button').contains('Clear Filters').click()
    cy.get('#map').should('be.visible')
    
    // Test search
    cy.get('input[name="searchLocation"]').should('be.visible')
    cy.get('button').contains('Search').should('be.visible')
    
    cy.get('input[name="searchLocation"]').type('Manila')
    cy.get('button').contains('Search').click()
    cy.get('#map').should('be.visible')
  })

  it('should display statistics, export, and settings', () => {
    cy.get('.map-stats').should('be.visible')
    cy.get('.map-stats').should('contain', 'Total Reports')
    cy.get('.map-stats').should('contain', 'Visible Reports')
    cy.get('.map-stats .stat-value').should('be.visible')
    
    cy.get('button').contains('Export Map').should('be.visible')
    cy.get('button').contains('Export Map').click()
    cy.get('a[href*="export_map.php"]').should('be.visible')
    
    cy.get('.map-settings').should('be.visible')
    cy.get('select[name="mapStyle"]').should('be.visible')
    cy.get('input[name="markerSize"]').should('be.visible')
    
    cy.get('select[name="mapStyle"]').select('Satellite')
    cy.get('#map').should('be.visible')
    
    cy.get('input[name="markerSize"]').clear().type('20')
    cy.get('#map').should('be.visible')
  })
})