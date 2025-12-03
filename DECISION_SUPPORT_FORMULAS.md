# Decision Support System - Formulas and Calculations Documentation

## Overview

The Decision Support System in the Animal Bite Reporting and Management System uses various statistical calculations and formulas to provide insights into animal bite patterns, treatment effectiveness, and risk assessment. This document details all formulas and calculations used in the system.

## 1. Core Statistical Formulas

### 1.1 Percentage Calculation (General Formula)
```
Percentage = (Part / Total) × 100
```
**Used for**: All percentage calculations throughout the system

### 1.2 Percentage Change Calculation
```
Percentage Change = ((Current Value - Previous Value) / Previous Value) × 100
```

**Implementation**:
```php
$percentChange = (($totalCases - $compareCases) / $compareCases) * 100;
```

**Special Cases**:
- If previous value = 0 and current > 0: Returns 100%
- If both values = 0: Returns 0%

## 2. Case Analysis Formulas

### 2.1 Category III Percentage (Critical Cases)
```
Category III % = (Category III Cases / Total Cases) × 100
```

**Implementation**:
```php
$categoryIIIPercent = $totalCases > 0 ? ($categoryIIICount / $totalCases) * 100 : 0;
```

**Purpose**: Identifies proportion of severe cases requiring immediate attention
**Threshold**: >30% triggers emergency response recommendations

### 2.2 Treatment Compliance Rates

#### Wound Washing Compliance
```
Wound Washing % = (Cases with Soap Washing / Total Cases) × 100
```

#### Rabies Vaccination Coverage
```
Vaccination Rate % = (Rabies Vaccine Administered / Total Cases) × 100
```

#### Tetanus Prophylaxis Rate
```
Tetanus Rate % = (Tetanus Shots Given / Total Cases) × 100
```

#### Antibiotic Usage Rate
```
Antibiotic Rate % = (Antibiotics Prescribed / Total Cases) × 100
```

#### Hospital Referral Rate
```
Referral Rate % = (Hospital Referrals / Total Cases) × 100
```

**Implementation**:
```php
$treatmentQuery = "SELECT
    SUM(CASE WHEN r.washWithSoap = 1 THEN 1 ELSE 0 END) as wash_count,
    SUM(CASE WHEN r.rabiesVaccine = 1 THEN 1 ELSE 0 END) as rabies_count,
    SUM(CASE WHEN r.antiTetanus = 1 THEN 1 ELSE 0 END) as tetanus_count,
    SUM(CASE WHEN r.antibiotics = 1 THEN 1 ELSE 0 END) as antibiotics_count,
    SUM(CASE WHEN r.referredToHospital = 1 THEN 1 ELSE 0 END) as referred_count,
    COUNT(*) as total_count
FROM reports r JOIN patients p ON r.patientId = p.patientId $treatmentWhere";
```

## 3. Animal Ownership Analysis

### 3.1 Stray Animal Percentage
```
Stray Animal % = (Stray Animal Bites / Total Ownership Cases) × 100
```

**Implementation**:
```php
$strayPct = ($strayCount / $totalOwn) * 100;
```

**Threshold**: >50% triggers animal control recommendations

### 3.2 Owned Animal Categories
- Owned by patient
- Owned by neighbor
- Owned by unknown person

```
Owned Animals % = (Owned Animal Bites / Total Ownership Cases) × 100
```

## 4. Animal Vaccination Status

### 4.1 Unvaccinated Animal Percentage
```
Unvaccinated % = (Bites from Unvaccinated Animals / Total Vaccination Cases) × 100
```

**Implementation**:
```php
$unvacPct = ($unvaccinatedCount / $totalVac) * 100;
```

**Threshold**: >50% triggers vaccination campaign recommendations

### 4.2 Vaccination Status Categories
- Yes: Animal was vaccinated
- No: Animal was not vaccinated
- Unknown: Vaccination status unclear

## 5. Demographic Analysis

### 5.1 Age Group Distribution
**Age Group Classification**:
```
Age at Bite = TIMESTAMPDIFF(YEAR, dateOfBirth, biteDate)
```

**Age Groups**:
- Under 5: Age < 5
- 5-12: Age 5-12
- 13-18: Age 13-18
- 19-30: Age 19-30
- 31-50: Age 31-50
- 51-65: Age 51-65
- Over 65: Age > 65
- Unknown: Age cannot be calculated

**Implementation**:
```sql
SELECT CASE
    WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.biteDate) < 5 THEN 'Under 5'
    WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.biteDate) BETWEEN 5 AND 12 THEN '5-12'
    WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.biteDate) BETWEEN 13 AND 18 THEN '13-18'
    WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.biteDate) BETWEEN 19 AND 30 THEN '19-30'
    WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.biteDate) BETWEEN 31 AND 50 THEN '31-50'
    WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.biteDate) BETWEEN 51 AND 65 THEN '51-65'
    WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.biteDate) > 65 THEN 'Over 65'
    ELSE 'Unknown'
END as age_group, COUNT(*) as count
```

### 5.2 Gender Distribution
```
Gender % = (Cases by Gender / Total Gender Cases) × 100
```

**Categories**: Male, Female, Other

## 6. Geographic Analysis

### 6.1 Barangay Risk Ranking
```
Barangay Case Count = COUNT(*) GROUP BY barangay ORDER BY count DESC
```

**Top Areas**: First 3 barangays with highest case counts

### 6.2 Geographic Risk Heat Map
- Uses barangay coordinates for mapping
- Color-coded by case density
- Interactive filtering by date range

## 7. Temporal Analysis

### 7.1 Monthly Trend Analysis
```
Monthly Cases = COUNT(*) GROUP BY DATE_FORMAT(biteDate, '%Y-%m')
```

### 7.2 Trend Change Calculation
```
Trend Change % = ((Recent Period Average - Earlier Period Average) / Earlier Period Average) × 100
```

**Implementation**:
```php
$firstHalf = array_slice($trendCounts, 0, floor($trendCount / 2));
$secondHalf = array_slice($trendCounts, floor($trendCount / 2));
$firstHalfAvg = count($firstHalf) > 0 ? array_sum($firstHalf) / count($firstHalf) : 0;
$secondHalfAvg = count($secondHalf) > 0 ? array_sum($secondHalf) / count($secondHalf) : 0;
$trendChange = $firstHalfAvg > 0 ? (($secondHalfAvg - $firstHalfAvg) / $firstHalfAvg) * 100 : 0;
```

**Trend Interpretation**:
- >10%: Increasing trend → Enhanced preventive measures
- <-10%: Decreasing trend → Current interventions effective
- -10% to 10%: Stable trend

### 7.3 Period Comparison
```
Period Comparison % = ((Current Period Cases - Previous Period Cases) / Previous Period Cases) × 100
```

**Implementation**:
```sql
-- Compare current date range with same-length previous period
DATE_SUB(?, INTERVAL DATEDIFF(?, ?) DAY)
```

## 8. Animal Type Analysis

### 8.1 Animal Type Distribution
```
Animal Type % = (Cases by Animal Type / Total Cases) × 100
```

### 8.2 Most Common Animal
```
Top Animal = MAX(COUNT(*)) GROUP BY animalType
Top Animal % = (Top Animal Cases / Total Cases) × 100
```

## 9. Bite Category Analysis (WHO Classification)

### 9.1 Bite Severity Distribution
**Categories**:
- **Category I**: Touching/feeding animals, licks on intact skin
- **Category II**: Minor scratches, nibbling without bleeding
- **Category III**: Deep bites, multiple wounds, mucous membrane exposure

```
Category % = (Cases in Category / Total Cases) × 100
```

**Severity Ordering**:
```sql
ORDER BY CASE
    WHEN r.biteType = 'Category I' THEN 1
    WHEN r.biteType = 'Category II' THEN 2
    WHEN r.biteType = 'Category III' THEN 3
    ELSE 4
END
```

## 10. Data Quality and Completeness

### 10.1 Data Completeness Score
```
Completeness % = (Records with Data / Total Records) × 100
```

**Applied to**:
- Date of birth completeness for age analysis
- Gender data completeness
- Animal ownership information
- Vaccination status data

## 11. Recommendation Engine Formulas

### 11.1 Emergency Response Trigger
```
IF Category III % > 30% THEN "Establish rapid response teams"
```

### 11.2 Vaccination Campaign Trigger
```
IF Vaccination Rate < 80% THEN "Deploy mobile vaccination units"
```

### 11.3 Animal Control Trigger
```
IF Stray Animal % > 50% THEN "Partner for animal population management"
```

### 11.4 Geographic Targeting
```
Top Risk Areas = TOP 3 barangays by case count
Recommendation = "Establish health outposts in [Area1, Area2, Area3]"
```

## 12. Statistical Aggregation Functions

### 12.1 Array Sum Operations
```php
// Total cases across periods
$totalCases = array_sum($trendCounts);

// Average calculations
$firstHalfAvg = array_sum($firstHalf) / count($firstHalf);
$secondHalfAvg = array_sum($secondHalf) / count($secondHalf);
```

### 12.2 Array Slicing for Period Analysis
```php
// Split data into equal periods for trend analysis
$firstHalf = array_slice($trendCounts, 0, floor($trendCount / 2));
$secondHalf = array_slice($trendCounts, floor($trendCount / 2));
```

## 13. Data Filtering Logic

### 13.1 Date Range Filtering
```
WHERE r.biteDate BETWEEN ? AND ?
```

### 13.2 Multi-dimensional Filtering
```sql
WHERE r.biteDate BETWEEN ? AND ?
  AND r.animalType = ? (optional)
  AND p.barangay = ? (optional)
```

### 13.3 Null Data Handling
```sql
AND p.dateOfBirth IS NOT NULL  -- For age calculations
AND r.animalOwnership IS NOT NULL  -- For ownership analysis
AND r.animalVaccinated IS NOT NULL  -- For vaccination analysis
```

## 14. Output Formatting

### 14.1 Percentage Display
```php
number_format($percentage, 1) . '%'  // One decimal place
```

### 14.2 Number Formatting
```php
number_format($number)  // Add thousand separators
```

### 14.3 Absolute Value for Changes
```php
number_format(abs($percentChange), 1)  // Remove negative sign for display
```

---

## Formula Validation and Error Handling

### Error Prevention
- Division by zero checks: `$total > 0 ? ($part / $total) * 100 : 0`
- Null data handling: `IS NOT NULL` conditions in queries
- Array bounds checking: `count($array) > 0` before operations
- Default values: Fallback to 0 for missing data

### Data Consistency
- All percentages calculated from filtered datasets
- Consistent date range application across all metrics
- Filtered results maintain referential integrity

---

## Performance Considerations

### Query Optimization
- Single-pass aggregations using SQL SUM/COUNT/CASE
- Indexed date columns for range queries
- LIMIT clauses for top-N queries (e.g., top 10 barangays)

### Memory Management
- Array operations on pre-filtered result sets
- Chunked processing for large datasets
- Efficient JSON encoding for chart data

---

## Future Formula Extensions

### Planned Calculations
1. **Risk Scoring Algorithm**: Weighted risk factors for geographic areas
2. **Seasonal Trend Analysis**: Fourier analysis for cyclical patterns
3. **Predictive Modeling**: Time-series forecasting for case projections
4. **Cost-Benefit Analysis**: Economic impact of prevention programs
5. **Vaccine Efficacy Metrics**: Post-vaccination infection rates

---

*This document provides comprehensive coverage of all formulas and calculations used in the Decision Support System. Formulas are implemented in PHP with MySQL database queries and are designed for real-time analytics and decision-making support.*
