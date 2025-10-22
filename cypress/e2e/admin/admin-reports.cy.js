describe('Admin Reports Management Tests', () => {
  beforeEach(() => {
    cy.loginAsAdmin()
    cy.visit('/src/admin/view_reports.php')
  })

  describe('Reports List Page', () => {
    it('should display the reports page correctly', () => {
      cy.get('h1').should('contain', 'Reports')
      cy.get('.table').should('be.visible')
    })

    it('should display reports table with proper columns', () => {
      cy.verifyTableHeaders('.table', [
        'Report ID',
        'Patient Name',
        'Animal Type',
        'Bite Date',
        'Status',
        'Urgency',
        'Actions'
      ])
    })

    it('should display reports data', () => {
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
    it('should search reports by patient name', () => {
      cy.performSearch('John')
      cy.get('tbody tr').should('have.length.at.least', 1)
    })

    it('should search reports by report ID', () => {
      cy.performSearch('1')
      cy.get('tbody tr').should('have.length.at.least', 1)
    })

    it('should filter reports by status', () => {
      cy.testFiltering('select[name="status"]', 'Pending')
      cy.get('tbody tr').should('have.length.at.least', 1)
    })

    it('should filter reports by urgency', () => {
      cy.testFiltering('select[name="urgency"]', 'High')
      cy.get('tbody tr').should('have.length.at.least', 1)
    })

    it('should clear search results', () => {
      cy.performSearch('test')
      cy.clearSearch()
      cy.get('tbody tr').should('have.length.at.least', 1)
    })
  })

  describe('Report Actions', () => {
    it('should view report details', () => {
      cy.get('button[data-bs-target*="view"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Report Details')
    })

    it('should edit report', () => {
      cy.get('button[data-bs-target*="edit"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Edit Report')
    })

    it('should delete report', () => {
      cy.get('button[data-bs-target*="delete"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-body').should('contain', 'Are you sure')
    })

    it('should print report', () => {
      cy.get('button[data-bs-target*="print"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Print Report')
    })
  })

  describe('Report Details Modal', () => {
    beforeEach(() => {
      cy.get('button[data-bs-target*="view"]').first().click()
    })

    it('should display report details correctly', () => {
      cy.get('.modal').should('be.visible')
      cy.get('.modal-body').should('contain', 'Patient Information')
      cy.get('.modal-body').should('contain', 'Bite Information')
    })

    it('should display patient information', () => {
      cy.get('.modal-body').should('contain', 'Name:')
      cy.get('.modal-body').should('contain', 'Contact:')
      cy.get('.modal-body').should('contain', 'Address:')
    })

    it('should display bite information', () => {
      cy.get('.modal-body').should('contain', 'Animal Type:')
      cy.get('.modal-body').should('contain', 'Bite Date:')
      cy.get('.modal-body').should('contain', 'Bite Location:')
    })

    it('should close modal when close button is clicked', () => {
      cy.get('.modal .btn-close').click()
      cy.get('.modal').should('not.exist')
    })

    it('should close modal when backdrop is clicked', () => {
      cy.get('.modal-backdrop').click({ force: true })
      cy.get('.modal').should('not.exist')
    })
  })

  describe('Edit Report Modal', () => {
    beforeEach(() => {
      cy.get('button[data-bs-target*="edit"]').first().click()
    })

    it('should display edit form correctly', () => {
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Edit Report')
      cy.get('form').should('be.visible')
    })

    it('should populate form with existing data', () => {
      cy.get('input[name="firstName"]').should('have.value')
      cy.get('input[name="lastName"]').should('have.value')
      cy.get('select[name="status"]').should('have.value')
    })

    it('should update report status', () => {
      cy.get('select[name="status"]').select('Reviewed')
      cy.get('button[type="submit"]').click()
      cy.get('.modal').should('not.exist')
      cy.checkNotification('Report updated successfully', 'success')
    })

    it('should update report urgency', () => {
      cy.get('select[name="urgency"]').select('High')
      cy.get('button[type="submit"]').click()
      cy.get('.modal').should('not.exist')
      cy.checkNotification('Report updated successfully', 'success')
    })

    it('should validate required fields', () => {
      cy.get('input[name="firstName"]').clear()
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
    })

    it('should close modal on cancel', () => {
      cy.get('button').contains('Cancel').click()
      cy.get('.modal').should('not.exist')
    })
  })

  describe('Delete Report Modal', () => {
    beforeEach(() => {
      cy.get('button[data-bs-target*="delete"]').first().click()
    })

    it('should display delete confirmation', () => {
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Delete Report')
      cy.get('.modal-body').should('contain', 'Are you sure')
    })

    it('should confirm deletion', () => {
      cy.get('button').contains('Yes, delete it!').click()
      cy.get('.modal').should('not.exist')
      cy.checkNotification('Report deleted successfully', 'success')
    })

    it('should cancel deletion', () => {
      cy.get('button').contains('Cancel').click()
      cy.get('.modal').should('not.exist')
    })
  })

  describe('Print Report Modal', () => {
    beforeEach(() => {
      cy.get('button[data-bs-target*="print"]').first().click()
    })

    it('should display print preview', () => {
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Print Report')
      cy.get('.modal-body').should('contain', 'Report Preview')
    })

    it('should have print button', () => {
      cy.get('button').contains('Print').should('be.visible')
    })

    it('should test print functionality', () => {
      cy.testPrint()
    })

    it('should close print modal', () => {
      cy.get('.modal .btn-close').click()
      cy.get('.modal').should('not.exist')
    })
  })

  describe('Export Functionality', () => {
    it('should have export button', () => {
      cy.get('button').contains('Export').should('be.visible')
    })

    it('should export reports to CSV', () => {
      cy.get('button').contains('Export').click()
      cy.get('a[href*="export_reports.php"]').should('be.visible')
    })

    it('should export reports to Excel', () => {
      cy.get('button').contains('Export').click()
      cy.get('a[href*="export_reports.php?format=excel"]').should('be.visible')
    })
  })

  describe('Pagination', () => {
    it('should display pagination if there are many reports', () => {
      cy.get('.pagination').then(($pagination) => {
        if ($pagination.length > 0) {
          cy.wrap($pagination).should('be.visible')
          cy.testPagination()
        }
      })
    })
  })

  describe('Sorting', () => {
    it('should sort reports by date', () => {
      cy.testSorting('Bite Date')
    })

    it('should sort reports by patient name', () => {
      cy.testSorting('Patient Name')
    })

    it('should sort reports by status', () => {
      cy.testSorting('Status')
    })
  })

  describe('Bulk Actions', () => {
    it('should have select all checkbox', () => {
      cy.get('input[type="checkbox"]').first().should('be.visible')
    })

    it('should select individual reports', () => {
      cy.get('input[type="checkbox"]').eq(1).check()
      cy.get('input[type="checkbox"]').eq(1).should('be.checked')
    })

    it('should select all reports', () => {
      cy.get('input[type="checkbox"]').first().check()
      cy.get('input[type="checkbox"]').should('be.checked')
    })

    it('should have bulk action buttons', () => {
      cy.get('input[type="checkbox"]').eq(1).check()
      cy.get('button').contains('Bulk Actions').should('be.visible')
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
    it('should load reports page within acceptable time', () => {
      cy.measurePageLoad()
    })

    it('should handle large number of reports', () => {
      cy.get('.table tbody tr').should('have.length.at.least', 1)
    })
  })
})
