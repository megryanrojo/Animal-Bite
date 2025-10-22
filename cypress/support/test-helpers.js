// Test Helper Functions for Animal Bite System

/**
 * Generate random test data
 */
export const generateTestData = {
  randomName: () => {
    const firstNames = ['John', 'Jane', 'Bob', 'Alice', 'Charlie', 'David', 'Eve', 'Frank']
    const lastNames = ['Doe', 'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller']
    return {
      firstName: firstNames[Math.floor(Math.random() * firstNames.length)],
      lastName: lastNames[Math.floor(Math.random() * lastNames.length)]
    }
  },

  randomEmail: () => {
    const domains = ['test.com', 'example.com', 'sample.com']
    const name = generateTestData.randomName()
    return `${name.firstName.toLowerCase()}.${name.lastName.toLowerCase()}@${domains[Math.floor(Math.random() * domains.length)]}`
  },

  randomPhone: () => {
    const prefixes = ['0912', '0913', '0914', '0915', '0916', '0917', '0918', '0919']
    const prefix = prefixes[Math.floor(Math.random() * prefixes.length)]
    const suffix = Math.floor(Math.random() * 10000000).toString().padStart(7, '0')
    return prefix + suffix
  },

  randomDate: (start, end) => {
    const startDate = new Date(start)
    const endDate = new Date(end)
    const randomTime = startDate.getTime() + Math.random() * (endDate.getTime() - startDate.getTime())
    return new Date(randomTime).toISOString().split('T')[0]
  },

  randomAnimalType: () => {
    const types = ['Dog', 'Cat', 'Other']
    return types[Math.floor(Math.random() * types.length)]
  },

  randomUrgency: () => {
    const levels = ['Low', 'Normal', 'High']
    return levels[Math.floor(Math.random() * levels.length)]
  }
}

/**
 * Database test helpers
 */
export const dbHelpers = {
  seedTestData: () => {
    // This would typically make API calls to seed test data
    cy.log('Seeding test data...')
  },

  cleanupTestData: () => {
    // This would typically make API calls to clean up test data
    cy.log('Cleaning up test data...')
  },

  resetDatabase: () => {
    // This would typically make API calls to reset the database
    cy.log('Resetting database...')
  }
}

/**
 * Form test helpers
 */
export const formHelpers = {
  fillPatientForm: (patientData = {}) => {
    const defaultData = {
      firstName: 'John',
      lastName: 'Doe',
      middleName: 'Michael',
      dateOfBirth: '1990-01-01',
      gender: 'Male',
      contactNumber: '09123456789',
      email: 'john.doe@test.com',
      address: '123 Test Street',
      barangay: 'Test Barangay',
      city: 'Test City',
      province: 'Test Province'
    }
    
    const data = { ...defaultData, ...patientData }
    
    cy.get('input[name="firstName"]').type(data.firstName)
    cy.get('input[name="lastName"]').type(data.lastName)
    if (data.middleName) cy.get('input[name="middleName"]').type(data.middleName)
    if (data.dateOfBirth) cy.get('input[name="dateOfBirth"]').type(data.dateOfBirth)
    if (data.gender) cy.get('select[name="gender"]').select(data.gender)
    cy.get('input[name="contactNumber"]').type(data.contactNumber)
    if (data.email) cy.get('input[name="email"]').type(data.email)
    cy.get('input[name="address"]').type(data.address)
    if (data.barangay) cy.get('input[name="barangay"]').type(data.barangay)
    if (data.city) cy.get('input[name="city"]').type(data.city)
    if (data.province) cy.get('input[name="province"]').type(data.province)
  },

  fillBiteForm: (biteData = {}) => {
    const defaultData = {
      animalType: 'Dog',
      biteDate: '2024-01-15',
      biteTime: '14:30',
      biteLocation: 'Left arm',
      biteDescription: 'Test bite description',
      firstAid: 'Washed with soap and water',
      previousRabiesVaccine: 'No',
      urgency: 'Normal'
    }
    
    const data = { ...defaultData, ...biteData }
    
    cy.get('select[name="animalType"]').select(data.animalType)
    if (data.animalType === 'Other' && data.otherAnimal) {
      cy.get('input[name="otherAnimal"]').type(data.otherAnimal)
    }
    cy.get('input[name="biteDate"]').type(data.biteDate)
    if (data.biteTime) cy.get('input[name="biteTime"]').type(data.biteTime)
    cy.get('input[name="biteLocation"]').type(data.biteLocation)
    if (data.biteDescription) cy.get('textarea[name="biteDescription"]').type(data.biteDescription)
    if (data.firstAid) cy.get('textarea[name="firstAid"]').type(data.firstAid)
    if (data.previousRabiesVaccine) cy.get('select[name="previousRabiesVaccine"]').select(data.previousRabiesVaccine)
    if (data.urgency) cy.get('select[name="urgency"]').select(data.urgency)
  },

  fillStaffForm: (staffData = {}) => {
    const defaultData = {
      firstName: 'John',
      lastName: 'Doe',
      email: 'john.doe@test.com',
      contactNumber: '09123456789',
      position: 'Health Worker',
      status: 'Active'
    }
    
    const data = { ...defaultData, ...staffData }
    
    cy.get('input[name="firstName"]').type(data.firstName)
    cy.get('input[name="lastName"]').type(data.lastName)
    cy.get('input[name="email"]').type(data.email)
    cy.get('input[name="contactNumber"]').type(data.contactNumber)
    if (data.position) cy.get('select[name="position"]').select(data.position)
    if (data.status) cy.get('select[name="status"]').select(data.status)
  }
}

/**
 * Navigation helpers
 */
export const navigationHelpers = {
  goToHomepage: () => {
    cy.visit('/src/user/index.php')
  },

  goToReportPage: () => {
    cy.visit('/src/user/report.php')
  },

  goToAdminLogin: () => {
    cy.visit('/src/login/admin_login.html')
  },

  goToStaffLogin: () => {
    cy.visit('/src/login/staff_login.html')
  },

  goToAdminDashboard: () => {
    cy.visit('/src/admin/admin_dashboard.php')
  },

  goToStaffDashboard: () => {
    cy.visit('/src/staff/dashboard.php')
  },

  goToAdminReports: () => {
    cy.visit('/src/admin/view_reports.php')
  },

  goToStaffReports: () => {
    cy.visit('/src/staff/reports.php')
  },

  goToAdminPatients: () => {
    cy.visit('/src/admin/view_patients.php')
  },

  goToStaffPatients: () => {
    cy.visit('/src/staff/patients.php')
  },

  goToAdminStaff: () => {
    cy.visit('/src/admin/view_staff.php')
  },

  goToGeomapping: () => {
    cy.visit('/src/admin/geomapping.php')
  },

  goToSearch: () => {
    cy.visit('/src/staff/search.php')
  }
}

/**
 * Assertion helpers
 */
export const assertionHelpers = {
  shouldHaveSuccessMessage: (message) => {
    cy.get('.alert-success').should('be.visible')
    cy.get('.alert-success').should('contain', message)
  },

  shouldHaveErrorMessage: (message) => {
    cy.get('.alert-danger').should('be.visible')
    cy.get('.alert-danger').should('contain', message)
  },

  shouldHaveValidationError: (fieldName) => {
    cy.get(`input[name="${fieldName}"], select[name="${fieldName}"]`).should('have.class', 'is-invalid')
    cy.get(`input[name="${fieldName}"], select[name="${fieldName}"]`).next('.invalid-feedback').should('be.visible')
  },

  shouldBeOnPage: (pageName) => {
    cy.url().should('include', pageName)
  },

  shouldHaveTableData: (tableSelector, expectedData) => {
    cy.get(tableSelector).should('be.visible')
    expectedData.forEach((row, index) => {
      cy.get(`${tableSelector} tbody tr`).eq(index).should('contain', row)
    })
  },

  shouldHaveFormData: (formData) => {
    Object.entries(formData).forEach(([field, value]) => {
      cy.get(`input[name="${field}"], select[name="${field}"], textarea[name="${field}"]`).should('have.value', value)
    })
  }
}

/**
 * Modal helpers
 */
export const modalHelpers = {
  openModal: (triggerSelector) => {
    cy.get(triggerSelector).click()
    cy.get('.modal').should('be.visible')
  },

  closeModal: () => {
    cy.get('.modal .btn-close').click()
    cy.get('.modal').should('not.exist')
  },

  confirmModal: () => {
    cy.get('.modal .btn-primary').click()
    cy.get('.modal').should('not.exist')
  },

  cancelModal: () => {
    cy.get('.modal .btn-secondary').click()
    cy.get('.modal').should('not.exist')
  },

  fillModalForm: (formData) => {
    Object.entries(formData).forEach(([field, value]) => {
      cy.get(`.modal input[name="${field}"], .modal select[name="${field}"], .modal textarea[name="${field}"]`).type(value)
    })
  }
}

/**
 * Table helpers
 */
export const tableHelpers = {
  getRowCount: (tableSelector) => {
    return cy.get(`${tableSelector} tbody tr`).its('length')
  },

  getRowData: (tableSelector, rowIndex) => {
    return cy.get(`${tableSelector} tbody tr`).eq(rowIndex)
  },

  clickRowAction: (tableSelector, rowIndex, action) => {
    cy.get(`${tableSelector} tbody tr`).eq(rowIndex).find(`button[data-bs-target*="${action}"]`).click()
  },

  selectRow: (tableSelector, rowIndex) => {
    cy.get(`${tableSelector} tbody tr`).eq(rowIndex).find('input[type="checkbox"]').check()
  },

  selectAllRows: (tableSelector) => {
    cy.get(`${tableSelector} thead input[type="checkbox"]`).check()
  }
}

/**
 * Search helpers
 */
export const searchHelpers = {
  performSearch: (searchTerm, searchButton = 'button[type="submit"]') => {
    cy.get('input[type="search"]').type(searchTerm)
    cy.get(searchButton).click()
  },

  clearSearch: () => {
    cy.get('input[type="search"]').clear()
    cy.get('button[type="submit"]').click()
  },

  filterBy: (filterSelector, filterValue) => {
    cy.get(filterSelector).select(filterValue)
    cy.get('button[type="submit"]').click()
  },

  sortBy: (columnHeader) => {
    cy.get(`th:contains("${columnHeader}")`).click()
  }
}

/**
 * File upload helpers
 */
export const fileHelpers = {
  uploadImage: (selector, fileName = 'test-image.jpg') => {
    cy.get(selector).selectFile(`cypress/fixtures/${fileName}`)
  },

  uploadInvalidFile: (selector, fileName = 'test.txt') => {
    cy.get(selector).selectFile(`cypress/fixtures/${fileName}`)
  },

  clearFile: (selector) => {
    cy.get(selector).clear()
  }
}

/**
 * Performance helpers
 */
export const performanceHelpers = {
  measurePageLoad: () => {
    cy.window().then((win) => {
      const startTime = win.performance.timing.navigationStart
      const loadTime = win.performance.timing.loadEventEnd - startTime
      cy.log(`Page load time: ${loadTime}ms`)
      expect(loadTime).to.be.lessThan(5000)
    })
  },

  measureFormSubmission: () => {
    const startTime = Date.now()
    cy.get('button[type="submit"]').click()
    cy.url().should('include', 'submit_report.php')
    const endTime = Date.now()
    const submissionTime = endTime - startTime
    cy.log(`Form submission time: ${submissionTime}ms`)
    expect(submissionTime).to.be.lessThan(3000)
  }
}

/**
 * Security helpers
 */
export const securityHelpers = {
  testSQLInjection: (fieldSelector, maliciousInput) => {
    cy.get(fieldSelector).type(maliciousInput)
    cy.get('button[type="submit"]').click()
    cy.get('body').should('not.contain', 'SQL')
    cy.get('body').should('not.contain', 'database')
  },

  testXSS: (fieldSelector, maliciousInput) => {
    cy.get(fieldSelector).type(maliciousInput)
    cy.get('button[type="submit"]').click()
    cy.get('body').should('not.contain', '<script>')
    cy.get('body').should('not.contain', 'alert(')
  },

  testFileUpload: (selector, fileName) => {
    cy.get(selector).selectFile(`cypress/fixtures/${fileName}`)
    cy.get('button[type="submit"]').click()
    cy.get('.invalid-feedback').should('be.visible')
  }
}

/**
 * Accessibility helpers
 */
export const accessibilityHelpers = {
  checkAltText: () => {
    cy.get('img').each(($img) => {
      cy.wrap($img).should('have.attr', 'alt')
    })
  },

  checkFormLabels: () => {
    cy.get('input, select, textarea').each(($input) => {
      const id = $input.attr('id')
      if (id) {
        cy.get(`label[for="${id}"]`).should('exist')
      }
    })
  },

  checkKeyboardNavigation: () => {
    cy.get('body').tab()
    cy.focused().should('be.visible')
  },

  checkARIA: () => {
    cy.get('[role]').should('exist')
    cy.get('[aria-label]').should('exist')
  }
}

/**
 * Responsive helpers
 */
export const responsiveHelpers = {
  testMobile: () => {
    cy.viewport('iphone-6')
    cy.get('body').should('be.visible')
  },

  testTablet: () => {
    cy.viewport('ipad-2')
    cy.get('body').should('be.visible')
  },

  testDesktop: () => {
    cy.viewport('macbook-15')
    cy.get('body').should('be.visible')
  },

  testAllBreakpoints: () => {
    const breakpoints = ['iphone-6', 'ipad-2', 'macbook-15']
    breakpoints.forEach(breakpoint => {
      cy.viewport(breakpoint)
      cy.get('body').should('be.visible')
    })
  }
}
