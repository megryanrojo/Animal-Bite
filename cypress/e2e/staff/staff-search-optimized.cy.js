describe('Staff Search Tests', () => {
  beforeEach(() => {
    cy.loginAsStaff()
    cy.visit('/src/staff/search.php')
  })

  describe('Search Core Functionality', () => {
    it('should display search page with basic and advanced options', () => {
      cy.get('h1').should('contain', 'Search')
      cy.get('form').should('be.visible')
      cy.get('input[type="search"]').should('be.visible')
      cy.get('button[type="submit"]').should('be.visible')
      cy.get('select[name="searchType"]').should('be.visible')
      cy.get('button').contains('Advanced Search').should('be.visible')
    })

    it('should perform basic search operations', () => {
      cy.get('select[name="searchType"]').select('Reports')
      cy.get('input[type="search"]').type('John')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
      
      cy.get('select[name="searchType"]').select('Patients')
      cy.get('input[type="search"]').type('Doe')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
      
      cy.get('select[name="searchType"]').select('All')
      cy.get('input[type="search"]').type('test')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
    })

    it('should handle empty search results', () => {
      cy.get('input[type="search"]').type('nonexistent')
      cy.get('button[type="submit"]').click()
      cy.get('.no-results').should('be.visible')
      
      cy.get('button').contains('Clear').click()
      cy.get('.search-results').should('not.exist')
    })
  })

  describe('Advanced Search', () => {
    beforeEach(() => {
      cy.get('button').contains('Advanced Search').click()
    })

    it('should perform advanced search with multiple criteria', () => {
      cy.get('.advanced-search').should('be.visible')
      cy.get('input[name="firstName"]').should('be.visible')
      cy.get('input[name="lastName"]').should('be.visible')
      cy.get('input[name="contactNumber"]').should('be.visible')
      
      cy.get('input[name="firstName"]').type('John')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
      
      cy.get('input[name="lastName"]').type('Doe')
      cy.get('select[name="animalType"]').select('Dog')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
      
      cy.get('button').contains('Reset').click()
      cy.get('input[name="firstName"]').should('have.value', '')
    })
  })

  describe('Search Results and Actions', () => {
    beforeEach(() => {
      cy.get('input[type="search"]').type('John')
      cy.get('button[type="submit"]').click()
    })

    it('should display and interact with search results', () => {
      cy.get('.search-results').should('be.visible')
      cy.get('.search-results .result-item').should('have.length.at.least', 1)
      cy.get('.search-results .result-item').first().should('contain', 'Name:')
      cy.get('.search-results .result-item').first().should('contain', 'Type:')
      cy.get('.search-results .result-item').first().should('contain', 'Date:')
      
      cy.get('.search-results-count').should('be.visible')
      cy.get('.search-results-count').should('contain', 'results found')
      
      cy.get('.search-results .result-item').first().find('button').contains('View Details').click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Details')
      cy.get('.modal .btn-close').click()
    })

    it('should handle search result actions', () => {
      cy.get('.search-results .result-item').first().find('button').contains('Edit').click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Edit')
      cy.get('.modal .btn-close').click()
      
      cy.get('.search-results .result-item').first().find('button').contains('Delete').click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-body').should('contain', 'Are you sure')
      cy.get('.modal .btn-close').click()
    })
  })

  describe('Search Filters and Export', () => {
    beforeEach(() => {
      cy.get('input[type="search"]').type('test')
      cy.get('button[type="submit"]').click()
    })

    it('should filter search results', () => {
      cy.get('select[name="filterType"]').select('Reports')
      cy.get('.search-results').should('be.visible')
      
      cy.get('select[name="filterDate"]').select('Last 30 days')
      cy.get('.search-results').should('be.visible')
      
      cy.get('select[name="filterStatus"]').select('Active')
      cy.get('.search-results').should('be.visible')
    })

    it('should export search results', () => {
      cy.get('button').contains('Export Results').should('be.visible')
      cy.get('button').contains('Export Results').click()
      cy.get('a[href*="export_search.php"]').should('be.visible')
    })
  })

  describe('Search History and Suggestions', () => {
    it('should handle search history and suggestions', () => {
      cy.get('.search-history').should('be.visible')
      cy.get('.search-history .history-item').should('have.length.at.least', 1)
      
      cy.get('.search-history .history-item').first().click()
      cy.get('input[type="search"]').should('have.value')
      
      cy.get('input[type="search"]').type('Jo')
      cy.get('.search-suggestions').should('be.visible')
      cy.get('.search-suggestions .suggestion-item').should('have.length.at.least', 1)
      
      cy.get('.search-suggestions .suggestion-item').first().click()
      cy.get('input[type="search"]').should('have.value')
    })
  })

  describe('Responsive Design and Performance', () => {
    it('should be responsive and performant', () => {
      cy.viewport('iphone-6')
      cy.get('form').should('be.visible')
      cy.get('input[type="search"]').should('be.visible')
      
      cy.viewport('ipad-2')
      cy.get('form').should('be.visible')
      cy.get('button[type="submit"]').should('be.visible')
      
      cy.measurePageLoad()
    })
  })
})
