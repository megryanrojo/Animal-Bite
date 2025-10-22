describe('Staff Search Tests', () => {
  beforeEach(() => {
    cy.loginAsStaff()
    cy.visit('/src/staff/search.php')
  })

  describe('Search Page Layout', () => {
    it('should display the search page correctly', () => {
      cy.get('h1').should('contain', 'Search')
      cy.get('form').should('be.visible')
    })

    it('should display search form', () => {
      cy.get('input[type="search"]').should('be.visible')
      cy.get('button[type="submit"]').should('be.visible')
    })

    it('should display search filters', () => {
      cy.get('select[name="searchType"]').should('be.visible')
      cy.get('select[name="searchType"]').should('contain', 'All')
      cy.get('select[name="searchType"]').should('contain', 'Reports')
      cy.get('select[name="searchType"]').should('contain', 'Patients')
    })

    it('should display advanced search options', () => {
      cy.get('button').contains('Advanced Search').should('be.visible')
    })
  })

  describe('Basic Search Functionality', () => {
    it('should search for reports', () => {
      cy.get('select[name="searchType"]').select('Reports')
      cy.get('input[type="search"]').type('John')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
    })

    it('should search for patients', () => {
      cy.get('select[name="searchType"]').select('Patients')
      cy.get('input[type="search"]').type('Doe')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
    })

    it('should search all records', () => {
      cy.get('select[name="searchType"]').select('All')
      cy.get('input[type="search"]').type('test')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
    })

    it('should display no results message for empty search', () => {
      cy.get('input[type="search"]').type('nonexistent')
      cy.get('button[type="submit"]').click()
      cy.get('.no-results').should('be.visible')
    })

    it('should clear search results', () => {
      cy.get('input[type="search"]').type('test')
      cy.get('button[type="submit"]').click()
      cy.get('button').contains('Clear').click()
      cy.get('.search-results').should('not.exist')
    })
  })

  describe('Advanced Search', () => {
    beforeEach(() => {
      cy.get('button').contains('Advanced Search').click()
    })

    it('should display advanced search form', () => {
      cy.get('.advanced-search').should('be.visible')
      cy.get('input[name="firstName"]').should('be.visible')
      cy.get('input[name="lastName"]').should('be.visible')
      cy.get('input[name="contactNumber"]').should('be.visible')
    })

    it('should search by first name', () => {
      cy.get('input[name="firstName"]').type('John')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
    })

    it('should search by last name', () => {
      cy.get('input[name="lastName"]').type('Doe')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
    })

    it('should search by contact number', () => {
      cy.get('input[name="contactNumber"]').type('09123456789')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
    })

    it('should search by date range', () => {
      cy.get('input[name="startDate"]').type('2024-01-01')
      cy.get('input[name="endDate"]').type('2024-12-31')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
    })

    it('should search by animal type', () => {
      cy.get('select[name="animalType"]').select('Dog')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
    })

    it('should search by urgency level', () => {
      cy.get('select[name="urgency"]').select('High')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
    })

    it('should combine multiple search criteria', () => {
      cy.get('input[name="firstName"]').type('John')
      cy.get('select[name="animalType"]').select('Dog')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
    })

    it('should reset advanced search form', () => {
      cy.get('input[name="firstName"]').type('John')
      cy.get('button').contains('Reset').click()
      cy.get('input[name="firstName"]').should('have.value', '')
    })
  })

  describe('Search Results', () => {
    beforeEach(() => {
      cy.get('input[type="search"]').type('John')
      cy.get('button[type="submit"]').click()
    })

    it('should display search results', () => {
      cy.get('.search-results').should('be.visible')
      cy.get('.search-results .result-item').should('have.length.at.least', 1)
    })

    it('should display result information', () => {
      cy.get('.search-results .result-item').first().should('contain', 'Name:')
      cy.get('.search-results .result-item').first().should('contain', 'Type:')
      cy.get('.search-results .result-item').first().should('contain', 'Date:')
    })

    it('should have view details button', () => {
      cy.get('.search-results .result-item').first().find('button').contains('View Details').should('be.visible')
    })

    it('should display result count', () => {
      cy.get('.search-results-count').should('be.visible')
      cy.get('.search-results-count').should('contain', 'results found')
    })
  })

  describe('Search Result Actions', () => {
    beforeEach(() => {
      cy.get('input[type="search"]').type('John')
      cy.get('button[type="submit"]').click()
    })

    it('should view result details', () => {
      cy.get('.search-results .result-item').first().find('button').contains('View Details').click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Details')
    })

    it('should edit result', () => {
      cy.get('.search-results .result-item').first().find('button').contains('Edit').click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Edit')
    })

    it('should delete result', () => {
      cy.get('.search-results .result-item').first().find('button').contains('Delete').click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-body').should('contain', 'Are you sure')
    })
  })

  describe('Search Filters', () => {
    it('should filter results by type', () => {
      cy.get('input[type="search"]').type('test')
      cy.get('button[type="submit"]').click()
      cy.get('select[name="filterType"]').select('Reports')
      cy.get('.search-results').should('be.visible')
    })

    it('should filter results by date', () => {
      cy.get('input[type="search"]').type('test')
      cy.get('button[type="submit"]').click()
      cy.get('select[name="filterDate"]').select('Last 30 days')
      cy.get('.search-results').should('be.visible')
    })

    it('should filter results by status', () => {
      cy.get('input[type="search"]').type('test')
      cy.get('button[type="submit"]').click()
      cy.get('select[name="filterStatus"]').select('Active')
      cy.get('.search-results').should('be.visible')
    })
  })

  describe('Search History', () => {
    it('should display recent searches', () => {
      cy.get('.search-history').should('be.visible')
      cy.get('.search-history .history-item').should('have.length.at.least', 1)
    })

    it('should allow clicking on history items', () => {
      cy.get('.search-history .history-item').first().click()
      cy.get('input[type="search"]').should('have.value')
    })

    it('should clear search history', () => {
      cy.get('button').contains('Clear History').click()
      cy.get('.search-history .history-item').should('not.exist')
    })
  })

  describe('Search Suggestions', () => {
    it('should display search suggestions', () => {
      cy.get('input[type="search"]').type('Jo')
      cy.get('.search-suggestions').should('be.visible')
      cy.get('.search-suggestions .suggestion-item').should('have.length.at.least', 1)
    })

    it('should allow selecting suggestions', () => {
      cy.get('input[type="search"]').type('Jo')
      cy.get('.search-suggestions .suggestion-item').first().click()
      cy.get('input[type="search"]').should('have.value')
    })

    it('should hide suggestions when clicking outside', () => {
      cy.get('input[type="search"]').type('Jo')
      cy.get('.search-suggestions').should('be.visible')
      cy.get('body').click()
      cy.get('.search-suggestions').should('not.exist')
    })
  })

  describe('Export Search Results', () => {
    beforeEach(() => {
      cy.get('input[type="search"]').type('test')
      cy.get('button[type="submit"]').click()
    })

    it('should have export button', () => {
      cy.get('button').contains('Export Results').should('be.visible')
    })

    it('should export results to CSV', () => {
      cy.get('button').contains('Export Results').click()
      cy.get('a[href*="export_search.php"]').should('be.visible')
    })

    it('should export results to Excel', () => {
      cy.get('button').contains('Export Results').click()
      cy.get('a[href*="export_search.php?format=excel"]').should('be.visible')
    })
  })

  describe('Responsive Design', () => {
    it('should be responsive on mobile devices', () => {
      cy.viewport('iphone-6')
      cy.get('form').should('be.visible')
      cy.get('input[type="search"]').should('be.visible')
    })

    it('should maintain functionality on tablet', () => {
      cy.viewport('ipad-2')
      cy.get('form').should('be.visible')
      cy.get('button[type="submit"]').should('be.visible')
    })
  })

  describe('Accessibility', () => {
    it('should have proper form labels', () => {
      cy.get('label').should('be.visible')
    })

    it('should be keyboard navigable', () => {
      cy.get('body').tab()
      cy.focused().should('be.visible')
    })

    it('should have proper ARIA attributes', () => {
      cy.get('input[type="search"]').should('have.attr', 'aria-label')
    })
  })

  describe('Performance', () => {
    it('should load search page within acceptable time', () => {
      cy.measurePageLoad()
    })

    it('should handle search queries efficiently', () => {
      cy.get('input[type="search"]').type('test')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
    })
  })
})
