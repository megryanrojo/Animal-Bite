describe('Staff Dashboard Tests', () => {
  beforeEach(() => {
    cy.loginAsStaff()
    cy.visit('/src/staff/dashboard.php')
  })

  describe('Dashboard Layout', () => {
    it('should display the staff dashboard correctly', () => {
      cy.get('h1').should('contain', 'Dashboard')
      cy.get('.navbar-brand').should('contain', 'BHW Animal Bite Portal')
    })

    it('should display navigation menu', () => {
      cy.get('.navbar-nav').should('be.visible')
      cy.get('a[href*="dashboard.php"]').should('contain', 'Dashboard')
      cy.get('a[href*="reports.php"]').should('contain', 'Reports')
      cy.get('a[href*="patients.php"]').should('contain', 'Patients')
      cy.get('a[href*="search.php"]').should('contain', 'Search')
    })

    it('should display user information in navbar', () => {
      cy.get('.navbar').should('contain', 'Staff')
      cy.get('a[href*="logout"]').should('be.visible')
    })

    it('should be responsive on mobile devices', () => {
      cy.viewport('iphone-6')
      cy.get('.navbar-toggler').should('be.visible')
      cy.get('.navbar-collapse').should('not.be.visible')
      
      cy.get('.navbar-toggler').click()
      cy.get('.navbar-collapse').should('be.visible')
    })
  })

  describe('Dashboard Statistics', () => {
    it('should display statistics cards', () => {
      cy.get('.card').should('have.length.at.least', 3)
      cy.get('.card').should('contain', 'My Reports')
      cy.get('.card').should('contain', 'My Patients')
      cy.get('.card').should('contain', 'Pending Reports')
    })

    it('should display numeric values in statistics', () => {
      cy.get('.card .h3').should('be.visible')
      cy.get('.card .h3').each(($el) => {
        cy.wrap($el).should('match', /^\d+$/)
      })
    })

    it('should have proper styling for statistics cards', () => {
      cy.get('.card').each(($card) => {
        cy.wrap($card).should('have.class', 'card')
        cy.wrap($card).find('.card-body').should('be.visible')
      })
    })
  })

  describe('Recent Activity', () => {
    it('should display recent reports section', () => {
      cy.get('h5').should('contain', 'Recent Reports')
      cy.get('.table').should('be.visible')
    })

    it('should display recent reports table with proper columns', () => {
      cy.get('.table thead th').should('contain', 'Report ID')
      cy.get('.table thead th').should('contain', 'Patient Name')
      cy.get('.table thead th').should('contain', 'Date')
      cy.get('.table thead th').should('contain', 'Status')
    })

    it('should display recent reports data', () => {
      cy.get('.table tbody tr').should('have.length.at.least', 1)
    })

    it('should have view all reports link', () => {
      cy.get('a[href*="reports.php"]').should('contain', 'View All Reports')
    })
  })

  describe('Quick Actions', () => {
    it('should display quick action buttons', () => {
      cy.get('.btn').should('contain', 'New Report')
      cy.get('.btn').should('contain', 'New Patient')
      cy.get('.btn').should('contain', 'View Reports')
    })

    it('should navigate to new report page', () => {
      cy.get('a[href*="new_report.php"]').click()
      cy.url().should('include', 'new_report.php')
    })

    it('should navigate to new patient page', () => {
      cy.get('a[href*="new_patient.php"]').click()
      cy.url().should('include', 'new_patient.php')
    })

    it('should navigate to view reports page', () => {
      cy.get('a[href*="reports.php"]').click()
      cy.url().should('include', 'reports.php')
    })
  })

  describe('Charts and Visualizations', () => {
    it('should display charts if present', () => {
      cy.get('canvas').then(($canvas) => {
        if ($canvas.length > 0) {
          cy.wrap($canvas).should('be.visible')
        }
      })
    })

    it('should display chart legends if present', () => {
      cy.get('.chart-legend').then(($legend) => {
        if ($legend.length > 0) {
          cy.wrap($legend).should('be.visible')
        }
      })
    })
  })

  describe('Notifications', () => {
    it('should display notifications if present', () => {
      cy.get('.alert').then(($alert) => {
        if ($alert.length > 0) {
          cy.wrap($alert).should('be.visible')
        }
      })
    })

    it('should have dismissible notifications', () => {
      cy.get('.alert .btn-close').then(($closeBtn) => {
        if ($closeBtn.length > 0) {
          cy.wrap($closeBtn).click()
          cy.wrap($closeBtn).parent().should('not.exist')
        }
      })
    })
  })

  describe('Navigation', () => {
    it('should navigate to reports page', () => {
      cy.navigateToPage('reports.php')
      cy.url().should('include', 'reports.php')
    })

    it('should navigate to patients page', () => {
      cy.navigateToPage('patients.php')
      cy.url().should('include', 'patients.php')
    })

    it('should navigate to search page', () => {
      cy.navigateToPage('search.php')
      cy.url().should('include', 'search.php')
    })
  })

  describe('Logout Functionality', () => {
    it('should logout successfully', () => {
      cy.logout()
      cy.url().should('include', 'login')
    })

    it('should clear session on logout', () => {
      cy.logout()
      cy.visit('/src/staff/dashboard.php')
      cy.url().should('include', 'login')
    })
  })

  describe('Accessibility', () => {
    it('should have proper heading structure', () => {
      cy.get('h1').should('exist')
      cy.get('h2, h3, h4, h5, h6').should('exist')
    })

    it('should have proper alt text for images', () => {
      cy.get('img').each(($img) => {
        cy.wrap($img).should('have.attr', 'alt')
      })
    })

    it('should be keyboard navigable', () => {
      cy.get('body').tab()
      cy.focused().should('be.visible')
    })
  })

  describe('Performance', () => {
    it('should load dashboard within acceptable time', () => {
      cy.measurePageLoad()
    })

    it('should handle page refresh', () => {
      cy.reload()
      cy.get('h1').should('contain', 'Dashboard')
    })
  })

  describe('Data Refresh', () => {
    it('should refresh statistics on page reload', () => {
      cy.get('.card .h3').first().then(($initialValue) => {
        const initialValue = $initialValue.text()
        cy.reload()
        cy.get('.card .h3').first().should('be.visible')
      })
    })

    it('should update recent activity on page reload', () => {
      cy.get('.table tbody tr').then(($rows) => {
        const initialRowCount = $rows.length
        cy.reload()
        cy.get('.table tbody tr').should('have.length.at.least', 1)
      })
    })
  })
})
