describe('Admin Patients Management Tests', () => {
  beforeEach(() => {
    cy.loginAsAdmin()
    cy.visit('/src/admin/view_patients.php')
  })

  describe('Patients List Page', () => {
    it('should display the patients page correctly', () => {
      cy.get('h1').should('contain', 'Patients')
      cy.get('.table').should('be.visible')
    })

    it('should display patients table with proper columns', () => {
      cy.verifyTableHeaders('.table', [
        'Patient ID',
        'Name',
        'Contact',
        'Address',
        'Date of Birth',
        'Gender',
        'Actions'
      ])
    })

    it('should display patients data', () => {
      cy.get('.table tbody tr').should('have.length.at.least', 1)
    })

    it('should have search functionality', () => {
      cy.get('input[type="search"]').should('be.visible')
      cy.get('button[type="submit"]').should('be.visible')
    })

    it('should have filter options', () => {
      cy.get('select').should('be.visible')
    })
  })

  describe('Search and Filter', () => {
    it('should search patients by name', () => {
      cy.performSearch('John')
      cy.get('tbody tr').should('have.length.at.least', 1)
    })

    it('should search patients by contact number', () => {
      cy.performSearch('09123456789')
      cy.get('tbody tr').should('have.length.at.least', 1)
    })

    it('should filter patients by gender', () => {
      cy.testFiltering('select[name="gender"]', 'Male')
      cy.get('tbody tr').should('have.length.at.least', 1)
    })

    it('should filter patients by age range', () => {
      cy.testFiltering('select[name="age_range"]', '18-30')
      cy.get('tbody tr').should('have.length.at.least', 1)
    })

    it('should clear search results', () => {
      cy.performSearch('test')
      cy.clearSearch()
      cy.get('tbody tr').should('have.length.at.least', 1)
    })
  })

  describe('Patient Actions', () => {
    it('should view patient details', () => {
      cy.get('button[data-bs-target*="view"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Patient Details')
    })

    it('should edit patient', () => {
      cy.get('button[data-bs-target*="edit"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Edit Patient')
    })

    it('should delete patient', () => {
      cy.get('button[data-bs-target*="delete"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-body').should('contain', 'Are you sure')
    })

    it('should view patient reports', () => {
      cy.get('button[data-bs-target*="reports"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Patient Reports')
    })
  })

  describe('Patient Details Modal', () => {
    beforeEach(() => {
      cy.get('button[data-bs-target*="view"]').first().click()
    })

    it('should display patient details correctly', () => {
      cy.get('.modal').should('be.visible')
      cy.get('.modal-body').should('contain', 'Personal Information')
      cy.get('.modal-body').should('contain', 'Contact Information')
    })

    it('should display personal information', () => {
      cy.get('.modal-body').should('contain', 'Name:')
      cy.get('.modal-body').should('contain', 'Date of Birth:')
      cy.get('.modal-body').should('contain', 'Gender:')
    })

    it('should display contact information', () => {
      cy.get('.modal-body').should('contain', 'Contact Number:')
      cy.get('.modal-body').should('contain', 'Email:')
      cy.get('.modal-body').should('contain', 'Address:')
    })

    it('should close modal when close button is clicked', () => {
      cy.get('.modal .btn-close').click()
      cy.get('.modal').should('not.exist')
    })
  })

  describe('Edit Patient Modal', () => {
    beforeEach(() => {
      cy.get('button[data-bs-target*="edit"]').first().click()
    })

    it('should display edit form correctly', () => {
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Edit Patient')
      cy.get('form').should('be.visible')
    })

    it('should populate form with existing data', () => {
      cy.get('input[name="firstName"]').should('have.value')
      cy.get('input[name="lastName"]').should('have.value')
      cy.get('input[name="contactNumber"]').should('have.value')
    })

    it('should update patient information', () => {
      cy.get('input[name="contactNumber"]').clear().type('09123456789')
      cy.get('button[type="submit"]').click()
      cy.get('.modal').should('not.exist')
      cy.checkNotification('Patient updated successfully', 'success')
    })

    it('should validate required fields', () => {
      cy.get('input[name="firstName"]').clear()
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
    })

    it('should validate email format', () => {
      cy.get('input[name="email"]').clear().type('invalid-email')
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
    })

    it('should close modal on cancel', () => {
      cy.get('button').contains('Cancel').click()
      cy.get('.modal').should('not.exist')
    })
  })

  describe('Delete Patient Modal', () => {
    beforeEach(() => {
      cy.get('button[data-bs-target*="delete"]').first().click()
    })

    it('should display delete confirmation', () => {
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Delete Patient')
      cy.get('.modal-body').should('contain', 'Are you sure')
    })

    it('should confirm deletion', () => {
      cy.get('button').contains('Yes, delete it!').click()
      cy.get('.modal').should('not.exist')
      cy.checkNotification('Patient deleted successfully', 'success')
    })

    it('should cancel deletion', () => {
      cy.get('button').contains('Cancel').click()
      cy.get('.modal').should('not.exist')
    })
  })

  describe('Patient Reports Modal', () => {
    beforeEach(() => {
      cy.get('button[data-bs-target*="reports"]').first().click()
    })

    it('should display patient reports', () => {
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Patient Reports')
      cy.get('.table').should('be.visible')
    })

    it('should display reports table', () => {
      cy.get('.table thead th').should('contain', 'Report ID')
      cy.get('.table thead th').should('contain', 'Animal Type')
      cy.get('.table thead th').should('contain', 'Bite Date')
      cy.get('.table thead th').should('contain', 'Status')
    })

    it('should display reports data', () => {
      cy.get('.table tbody tr').should('have.length.at.least', 1)
    })

    it('should close modal', () => {
      cy.get('.modal .btn-close').click()
      cy.get('.modal').should('not.exist')
    })
  })

  describe('Export Functionality', () => {
    it('should have export button', () => {
      cy.get('button').contains('Export').should('be.visible')
    })

    it('should export patients to CSV', () => {
      cy.get('button').contains('Export').click()
      cy.get('a[href*="export_patients.php"]').should('be.visible')
    })

    it('should export patients to Excel', () => {
      cy.get('button').contains('Export').click()
      cy.get('a[href*="export_patients.php?format=excel"]').should('be.visible')
    })
  })

  describe('Pagination', () => {
    it('should display pagination if there are many patients', () => {
      cy.get('.pagination').then(($pagination) => {
        if ($pagination.length > 0) {
          cy.wrap($pagination).should('be.visible')
          cy.testPagination()
        }
      })
    })
  })

  describe('Sorting', () => {
    it('should sort patients by name', () => {
      cy.testSorting('Name')
    })

    it('should sort patients by date of birth', () => {
      cy.testSorting('Date of Birth')
    })

    it('should sort patients by contact number', () => {
      cy.testSorting('Contact')
    })
  })

  describe('Bulk Actions', () => {
    it('should have select all checkbox', () => {
      cy.get('input[type="checkbox"]').first().should('be.visible')
    })

    it('should select individual patients', () => {
      cy.get('input[type="checkbox"]').eq(1).check()
      cy.get('input[type="checkbox"]').eq(1).should('be.checked')
    })

    it('should select all patients', () => {
      cy.get('input[type="checkbox"]').first().check()
      cy.get('input[type="checkbox"]').should('be.checked')
    })

    it('should have bulk action buttons', () => {
      cy.get('input[type="checkbox"]').eq(1).check()
      cy.get('button').contains('Bulk Actions').should('be.visible')
    })
  })

  describe('Patient Statistics', () => {
    it('should display patient statistics', () => {
      cy.get('.card').should('contain', 'Total Patients')
      cy.get('.card').should('contain', 'Male Patients')
      cy.get('.card').should('contain', 'Female Patients')
    })

    it('should display numeric values in statistics', () => {
      cy.get('.card .h3').should('be.visible')
      cy.get('.card .h3').each(($el) => {
        cy.wrap($el).should('match', /^\d+$/)
      })
    })
  })

  describe('Responsive Design', () => {
    it('should be responsive on mobile devices', () => {
      cy.viewport('iphone-6')
      cy.get('.table-responsive').should('be.visible')
      cy.get('.table').should('be.visible')
    })

    it('should maintain functionality on tablet', () => {
      cy.viewport('ipad-2')
      cy.get('.table').should('be.visible')
      cy.get('button[data-bs-target*="view"]').first().click()
      cy.get('.modal').should('be.visible')
    })
  })

  describe('Accessibility', () => {
    it('should have proper table headers', () => {
      cy.get('.table thead th').should('have.attr', 'scope', 'col')
    })

    it('should have proper form labels', () => {
      cy.get('label').should('be.visible')
    })

    it('should be keyboard navigable', () => {
      cy.get('body').tab()
      cy.focused().should('be.visible')
    })
  })

  describe('Performance', () => {
    it('should load patients page within acceptable time', () => {
      cy.measurePageLoad()
    })

    it('should handle large number of patients', () => {
      cy.get('.table tbody tr').should('have.length.at.least', 1)
    })
  })
})
