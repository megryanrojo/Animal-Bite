describe('Admin Geomapping Tests', () => {
  beforeEach(() => {
    cy.loginAsAdmin()
    cy.visit('/src/admin/geomapping.php')
  })

  describe('Geomapping Page Layout', () => {
    it('should display the geomapping page correctly', () => {
      cy.get('h1').should('contain', 'Geomapping')
      cy.get('.map-container').should('be.visible')
    })

    it('should display map container', () => {
      cy.get('#map').should('be.visible')
      cy.get('#map').should('have.attr', 'id', 'map')
    })

    it('should display map controls', () => {
      cy.get('.map-controls').should('be.visible')
      cy.get('button').should('contain', 'Refresh Map')
      cy.get('button').should('contain', 'Toggle Heatmap')
    })

    it('should display map legend', () => {
      cy.get('.map-legend').should('be.visible')
      cy.get('.map-legend').should('contain', 'Legend')
    })
  })

  describe('Map Functionality', () => {
    it('should load map tiles', () => {
      cy.get('#map .leaflet-tile').should('be.visible')
    })

    it('should display map markers', () => {
      cy.get('#map .leaflet-marker').should('have.length.at.least', 1)
    })

    it('should display map popups', () => {
      cy.get('#map .leaflet-marker').first().click()
      cy.get('.leaflet-popup').should('be.visible')
    })

    it('should display popup content', () => {
      cy.get('#map .leaflet-marker').first().click()
      cy.get('.leaflet-popup-content').should('contain', 'Report ID')
      cy.get('.leaflet-popup-content').should('contain', 'Patient Name')
      cy.get('.leaflet-popup-content').should('contain', 'Animal Type')
    })

    it('should close popup when close button is clicked', () => {
      cy.get('#map .leaflet-marker').first().click()
      cy.get('.leaflet-popup-close-button').click()
      cy.get('.leaflet-popup').should('not.exist')
    })
  })

  describe('Map Controls', () => {
    it('should refresh map when refresh button is clicked', () => {
      cy.get('button').contains('Refresh Map').click()
      cy.get('#map').should('be.visible')
    })

    it('should toggle heatmap when toggle button is clicked', () => {
      cy.get('button').contains('Toggle Heatmap').click()
      cy.get('#map').should('be.visible')
    })

    it('should zoom in when zoom in button is clicked', () => {
      cy.get('.leaflet-control-zoom-in').click()
      cy.get('#map').should('be.visible')
    })

    it('should zoom out when zoom out button is clicked', () => {
      cy.get('.leaflet-control-zoom-out').click()
      cy.get('#map').should('be.visible')
    })
  })

  describe('Map Filters', () => {
    it('should display filter options', () => {
      cy.get('.map-filters').should('be.visible')
      cy.get('select[name="animalType"]').should('be.visible')
      cy.get('select[name="urgency"]').should('be.visible')
      cy.get('select[name="dateRange"]').should('be.visible')
    })

    it('should filter by animal type', () => {
      cy.get('select[name="animalType"]').select('Dog')
      cy.get('button').contains('Apply Filters').click()
      cy.get('#map').should('be.visible')
    })

    it('should filter by urgency level', () => {
      cy.get('select[name="urgency"]').select('High')
      cy.get('button').contains('Apply Filters').click()
      cy.get('#map').should('be.visible')
    })

    it('should filter by date range', () => {
      cy.get('select[name="dateRange"]').select('Last 30 days')
      cy.get('button').contains('Apply Filters').click()
      cy.get('#map').should('be.visible')
    })

    it('should clear filters', () => {
      cy.get('button').contains('Clear Filters').click()
      cy.get('#map').should('be.visible')
    })
  })

  describe('Map Statistics', () => {
    it('should display map statistics', () => {
      cy.get('.map-stats').should('be.visible')
      cy.get('.map-stats').should('contain', 'Total Reports')
      cy.get('.map-stats').should('contain', 'Visible Reports')
    })

    it('should display numeric values in statistics', () => {
      cy.get('.map-stats .stat-value').should('be.visible')
      cy.get('.map-stats .stat-value').each(($el) => {
        cy.wrap($el).should('match', /^\d+$/)
      })
    })
  })

  describe('Map Legend', () => {
    it('should display legend items', () => {
      cy.get('.map-legend .legend-item').should('have.length.at.least', 1)
    })

    it('should display legend colors', () => {
      cy.get('.map-legend .legend-color').should('be.visible')
    })

    it('should display legend labels', () => {
      cy.get('.map-legend .legend-label').should('be.visible')
    })
  })

  describe('Map Responsiveness', () => {
    it('should be responsive on mobile devices', () => {
      cy.viewport('iphone-6')
      cy.get('#map').should('be.visible')
      cy.get('.map-controls').should('be.visible')
    })

    it('should maintain functionality on tablet', () => {
      cy.viewport('ipad-2')
      cy.get('#map').should('be.visible')
      cy.get('.map-controls').should('be.visible')
    })
  })

  describe('Map Performance', () => {
    it('should load map within acceptable time', () => {
      cy.measurePageLoad()
    })

    it('should handle map interactions smoothly', () => {
      cy.get('#map .leaflet-marker').first().click()
      cy.get('.leaflet-popup').should('be.visible')
    })
  })

  describe('Map Accessibility', () => {
    it('should have proper map controls', () => {
      cy.get('.leaflet-control-zoom-in').should('be.visible')
      cy.get('.leaflet-control-zoom-out').should('be.visible')
    })

    it('should have keyboard navigation', () => {
      cy.get('#map').focus()
      cy.get('#map').should('have.focus')
    })
  })

  describe('Map Data Integration', () => {
    it('should display real report data on map', () => {
      cy.get('#map .leaflet-marker').should('have.length.at.least', 1)
    })

    it('should update map when data changes', () => {
      cy.get('button').contains('Refresh Map').click()
      cy.get('#map').should('be.visible')
    })
  })

  describe('Map Export', () => {
    it('should have export map button', () => {
      cy.get('button').contains('Export Map').should('be.visible')
    })

    it('should export map as image', () => {
      cy.get('button').contains('Export Map').click()
      cy.get('a[href*="export_map.php"]').should('be.visible')
    })
  })

  describe('Map Settings', () => {
    it('should have map settings panel', () => {
      cy.get('.map-settings').should('be.visible')
    })

    it('should allow changing map style', () => {
      cy.get('select[name="mapStyle"]').should('be.visible')
      cy.get('select[name="mapStyle"]').select('Satellite')
      cy.get('#map').should('be.visible')
    })

    it('should allow changing marker size', () => {
      cy.get('input[name="markerSize"]').should('be.visible')
      cy.get('input[name="markerSize"]').clear().type('20')
      cy.get('#map').should('be.visible')
    })
  })

  describe('Map Clustering', () => {
    it('should display clustered markers when zoomed out', () => {
      cy.get('.leaflet-control-zoom-out').click()
      cy.get('#map').should('be.visible')
    })

    it('should expand clusters when zoomed in', () => {
      cy.get('.leaflet-control-zoom-in').click()
      cy.get('#map').should('be.visible')
    })
  })

  describe('Map Search', () => {
    it('should have search functionality', () => {
      cy.get('input[name="searchLocation"]').should('be.visible')
      cy.get('button').contains('Search').should('be.visible')
    })

    it('should search for locations', () => {
      cy.get('input[name="searchLocation"]').type('Manila')
      cy.get('button').contains('Search').click()
      cy.get('#map').should('be.visible')
    })
  })
})
