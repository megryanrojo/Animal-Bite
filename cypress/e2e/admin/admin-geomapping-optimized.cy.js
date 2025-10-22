describe('Admin Geomapping Tests', () => {
  beforeEach(() => {
    cy.loginAsAdmin()
    cy.visit('/src/admin/geomapping.php')
  })

  describe('Map Core Functionality', () => {
    it('should display map with controls and markers', () => {
      cy.get('h1').should('contain', 'Geomapping')
      cy.get('.map-container').should('be.visible')
      cy.get('#map').should('be.visible')
      cy.get('#map .leaflet-tile').should('be.visible')
      cy.get('#map .leaflet-marker').should('have.length.at.least', 1)
      
      cy.get('.map-controls').should('be.visible')
      cy.get('button').should('contain', 'Refresh Map')
      cy.get('button').should('contain', 'Toggle Heatmap')
      cy.get('.map-legend').should('be.visible')
    })

    it('should handle map interactions and popups', () => {
      cy.get('#map .leaflet-marker').first().click()
      cy.get('.leaflet-popup').should('be.visible')
      cy.get('.leaflet-popup-content').should('contain', 'Report ID')
      cy.get('.leaflet-popup-content').should('contain', 'Patient Name')
      cy.get('.leaflet-popup-content').should('contain', 'Animal Type')
      cy.get('.leaflet-popup-close-button').click()
      cy.get('.leaflet-popup').should('not.exist')
    })

    it('should handle map controls and zoom', () => {
      cy.get('button').contains('Refresh Map').click()
      cy.get('#map').should('be.visible')
      
      cy.get('button').contains('Toggle Heatmap').click()
      cy.get('#map').should('be.visible')
      
      cy.get('.leaflet-control-zoom-in').click()
      cy.get('#map').should('be.visible')
      
      cy.get('.leaflet-control-zoom-out').click()
      cy.get('#map').should('be.visible')
    })
  })

  describe('Map Filters and Search', () => {
    it('should filter map data by various criteria', () => {
      cy.get('.map-filters').should('be.visible')
      cy.get('select[name="animalType"]').should('be.visible')
      cy.get('select[name="urgency"]').should('be.visible')
      cy.get('select[name="dateRange"]').should('be.visible')
      
      cy.get('select[name="animalType"]').select('Dog')
      cy.get('button').contains('Apply Filters').click()
      cy.get('#map').should('be.visible')
      
      cy.get('select[name="urgency"]').select('High')
      cy.get('button').contains('Apply Filters').click()
      cy.get('#map').should('be.visible')
      
      cy.get('button').contains('Clear Filters').click()
      cy.get('#map').should('be.visible')
    })

    it('should search for locations', () => {
      cy.get('input[name="searchLocation"]').should('be.visible')
      cy.get('button').contains('Search').should('be.visible')
      
      cy.get('input[name="searchLocation"]').type('Manila')
      cy.get('button').contains('Search').click()
      cy.get('#map').should('be.visible')
    })
  })

  describe('Map Statistics and Export', () => {
    it('should display map statistics and export functionality', () => {
      cy.get('.map-stats').should('be.visible')
      cy.get('.map-stats').should('contain', 'Total Reports')
      cy.get('.map-stats').should('contain', 'Visible Reports')
      cy.get('.map-stats .stat-value').should('be.visible')
      
      cy.get('button').contains('Export Map').should('be.visible')
      cy.get('button').contains('Export Map').click()
      cy.get('a[href*="export_map.php"]').should('be.visible')
    })

    it('should handle map settings', () => {
      cy.get('.map-settings').should('be.visible')
      cy.get('select[name="mapStyle"]').should('be.visible')
      cy.get('input[name="markerSize"]').should('be.visible')
      
      cy.get('select[name="mapStyle"]').select('Satellite')
      cy.get('#map').should('be.visible')
      
      cy.get('input[name="markerSize"]').clear().type('20')
      cy.get('#map').should('be.visible')
    })
  })

  describe('Responsive Design and Performance', () => {
    it('should be responsive and performant', () => {
      cy.viewport('iphone-6')
      cy.get('#map').should('be.visible')
      cy.get('.map-controls').should('be.visible')
      
      cy.viewport('ipad-2')
      cy.get('#map').should('be.visible')
      cy.get('.map-controls').should('be.visible')
      
      cy.measurePageLoad()
    })
  })
})
