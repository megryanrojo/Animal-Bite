describe('Staff Search Tests', () => {
  beforeEach(() => {
    cy.loginAsStaff()
    cy.visit('/src/staff/search.php')
  })

  it('should perform basic and advanced search operations', () => {
    cy.contains('.navbar a.nav-link.active, .navbar a.nav-link', 'Search').should('exist')
    cy.get('form').should('be.visible')
    cy.get('input[type="search"]').should('be.visible')
    cy.get('button[type="submit"]').should('be.visible')
    cy.get('select[name="searchType"]').should('be.visible')
    cy.get('button').contains('Advanced Search').should('be.visible')
    
    // Test basic search
    cy.get('select[name="searchType"]').select('Reports')
    cy.get('input[type="search"]').type('John')
    cy.get('button[type="submit"]').click()
    cy.get('.search-results').should('be.visible')
    
    cy.get('select[name="searchType"]').select('Patients')
    cy.get('input[type="search"]').type('Doe')
    cy.get('button[type="submit"]').click()
    cy.get('.search-results').should('be.visible')
    
    // Test empty results
    cy.get('input[type="search"]').type('nonexistent')
    cy.get('button[type="submit"]').click()
    cy.get('.no-results').should('be.visible')
    
    cy.get('button').contains('Clear').click()
    cy.get('.search-results').should('not.exist')
  })

  it('should handle advanced search and search results', () => {
    // Test advanced search
    cy.get('button').contains('Advanced Search').click()
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
    
    // Test search results
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
})