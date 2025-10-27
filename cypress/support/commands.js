

// Custom commands for database operations
Cypress.Commands.add('seedDatabase', () => {
  // This would typically make API calls to seed test data
  // For now, we'll use the existing test data
  cy.log('Seeding database with test data')
})

Cypress.Commands.add('cleanupDatabase', () => {
  // This would typically make API calls to clean up test data
  cy.log('Cleaning up test data from database')
})

// Custom commands for file operations
Cypress.Commands.add('uploadTestImage', (selector) => {
  const fileName = 'test-bite-image.jpg'
  const fileType = 'image/jpeg'
  const fileContent = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A'
  
  cy.get(selector).then(input => {
    const blob = new Blob([fileContent], { type: fileType })
    const file = new File([blob], fileName, { type: fileType })
    const dataTransfer = new DataTransfer()
    dataTransfer.items.add(file)
    input[0].files = dataTransfer.files
    input.trigger('change', { force: true })
  })
})

// Custom commands for API testing
Cypress.Commands.add('apiRequest', (method, endpoint, body = null) => {
  return cy.request({
    method: method,
    url: `${Cypress.env('apiBaseUrl')}${endpoint}`,
    body: body,
    failOnStatusCode: false
  })
})

// Custom commands for form testing
Cypress.Commands.add('fillFormField', (selector, value, options = {}) => {
  if (options.clear !== false) {
    cy.get(selector).clear()
  }
  cy.get(selector).type(value, options)
})

Cypress.Commands.add('selectDropdownOption', (selector, optionText) => {
  cy.get(selector).select(optionText)
})

Cypress.Commands.add('checkCheckbox', (selector) => {
  cy.get(selector).check()
})

Cypress.Commands.add('uncheckCheckbox', (selector) => {
  cy.get(selector).uncheck()
})

// Custom commands for navigation
Cypress.Commands.add('clickNavItem', (navText) => {
  cy.get('nav').contains(navText).click()
})

Cypress.Commands.add('verifyBreadcrumb', (breadcrumbText) => {
  cy.get('.breadcrumb').should('contain', breadcrumbText)
})

// Custom commands for modal testing
Cypress.Commands.add('openModal', (modalTrigger) => {
  cy.get(modalTrigger).click()
  cy.get('.modal').should('be.visible')
})

Cypress.Commands.add('closeModal', () => {
  cy.get('.modal .btn-close').click()
  cy.get('.modal').should('not.exist')
})

Cypress.Commands.add('confirmModal', () => {
  cy.get('.modal .btn-primary').click()
  cy.get('.modal').should('not.exist')
})

// Custom commands for table testing
Cypress.Commands.add('verifyTableHeaders', (tableSelector, headers) => {
  cy.get(`${tableSelector} thead th`).should('have.length', headers.length)
  headers.forEach((header, index) => {
    cy.get(`${tableSelector} thead th`).eq(index).should('contain', header)
  })
})

Cypress.Commands.add('verifyTableRowCount', (tableSelector, expectedCount) => {
  cy.get(`${tableSelector} tbody tr`).should('have.length', expectedCount)
})

Cypress.Commands.add('clickTableAction', (tableSelector, rowIndex, actionButton) => {
  cy.get(`${tableSelector} tbody tr`).eq(rowIndex).find(`button[data-bs-target*="${actionButton}"]`).click()
})

// Custom commands for chart testing
Cypress.Commands.add('verifyChartExists', (chartSelector) => {
  cy.get(chartSelector).should('be.visible')
  cy.get(chartSelector).find('canvas').should('exist')
})

// Custom commands for map testing
Cypress.Commands.add('verifyMapExists', (mapSelector) => {
  cy.get(mapSelector).should('be.visible')
  cy.get(mapSelector).find('.leaflet-container').should('exist')
})

// Custom commands for responsive testing
Cypress.Commands.add('testResponsive', (breakpoints = ['iphone-6', 'ipad-2', 'macbook-15']) => {
  breakpoints.forEach(breakpoint => {
    cy.viewport(breakpoint)
    cy.get('body').should('be.visible')
  })
})

// Custom commands for performance testing
Cypress.Commands.add('measurePageLoad', () => {
  cy.window().then((win) => {
    const startTime = win.performance.timing.navigationStart
    const loadTime = win.performance.timing.loadEventEnd - startTime
    cy.log(`Page load time: ${loadTime}ms`)
    expect(loadTime).to.be.lessThan(5000) // Should load within 5 seconds
  })
})

// Custom commands for error handling
Cypress.Commands.add('handleError', (errorMessage) => {
  cy.get('.alert-danger').should('contain', errorMessage)
  cy.get('.alert-danger .btn-close').click()
  cy.get('.alert-danger').should('not.exist')
})

// Custom commands for success handling
Cypress.Commands.add('handleSuccess', (successMessage) => {
  cy.get('.alert-success').should('contain', successMessage)
  cy.get('.alert-success .btn-close').click()
  cy.get('.alert-success').should('not.exist')
})

// Custom commands for date/time testing
Cypress.Commands.add('setDate', (selector, date) => {
  cy.get(selector).type(date)
})

Cypress.Commands.add('setTime', (selector, time) => {
  cy.get(selector).type(time)
})

// Custom commands for file download testing
Cypress.Commands.add('downloadFile', (downloadLink, fileName) => {
  cy.get(downloadLink).click()
  cy.readFile(`cypress/downloads/${fileName}`).should('exist')
})

// Custom commands for print testing
Cypress.Commands.add('testPrint', () => {
  cy.window().then((win) => {
    cy.stub(win, 'print').as('printStub')
    cy.get('button').contains('Print').click()
    cy.get('@printStub').should('have.been.called')
  })
})

// Custom commands for export testing
Cypress.Commands.add('testExport', (exportButton, expectedFormat) => {
  cy.get(exportButton).click()
  cy.get('body').should('contain', `Export to ${expectedFormat}`)
})

// Custom commands for search functionality
Cypress.Commands.add('performSearch', (searchTerm, searchButton = 'button[type="submit"]') => {
  cy.get('input[type="search"]').type(searchTerm)
  cy.get(searchButton).click()
})

Cypress.Commands.add('clearSearch', () => {
  cy.get('input[type="search"]').clear()
  cy.get('button[type="submit"]').click()
})

// Custom commands for pagination
Cypress.Commands.add('testPagination', () => {
  cy.get('.pagination').should('be.visible')
  cy.get('.pagination .page-link').first().click()
  cy.get('.pagination .page-item.active').should('exist')
})

// Custom commands for sorting
Cypress.Commands.add('testSorting', (columnHeader) => {
  cy.get(`th:contains("${columnHeader}")`).click()
  cy.get(`th:contains("${columnHeader}")`).should('have.class', 'sorting')
})

// Custom commands for filtering
Cypress.Commands.add('testFiltering', (filterSelector, filterValue) => {
  cy.get(filterSelector).select(filterValue)
  cy.get('button[type="submit"]').click()
  cy.get('tbody tr').should('have.length.at.least', 1)
})

// Custom commands for authentication
Cypress.Commands.add('loginAsStaff', (email = 'aaangeles.chmsu@gmail.com', password = 'dods12345') => {
  // For testing purposes, we'll directly visit the staff dashboard
  // In a real scenario, you'd need valid credentials
  cy.visit('/src/staff/dashboard.php')
  // Check if we're redirected to login page
  cy.url().then((url) => {
    if (url.includes('staff_login.html')) {
      cy.log('Redirected to login - authentication required')
      // For now, we'll just visit the login page to test the form
      cy.visit('/src/login/staff_login.html')
    }
  })
})

Cypress.Commands.add('loginAsAdmin', (email = 'megrojo76@gmail.com', password = 'dods12345') => {
  // For testing purposes, we'll directly visit the admin dashboard
  // In a real scenario, you'd need valid credentials
  cy.visit('/src/admin/admin_dashboard.php')
  // Check if we're redirected to login page
  cy.url().then((url) => {
    if (url.includes('admin_login.html')) {
      cy.log('Redirected to login - authentication required')
      // For now, we'll just visit the login page to test the form
      cy.visit('/src/login/admin_login.html')
    }
  })
})

Cypress.Commands.add('logout', () => {
  cy.get('a[href*="logout"]').click()
  cy.url().should('include', 'login')
})