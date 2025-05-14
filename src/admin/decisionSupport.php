<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.html");
    exit;
}

require_once '../conn/conn.php';

// Get admin information
try {
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM admin WHERE adminId = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $admin = ['firstName' => 'Admin', 'lastName' => ''];
}

// Initialize filter variables
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-90 days'));
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$compareFrom = isset($_GET['compare_from']) ? $_GET['compare_from'] : date('Y-m-d', strtotime('-180 days'));
$compareTo = isset($_GET['compare_to']) ? $_GET['compare_to'] : date('Y-m-d', strtotime('-91 days'));
$animalType = isset($_GET['animal_type']) ? $_GET['animal_type'] : '';
$barangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';

// Get unique animal types for filter dropdown
try {
    $animalTypesStmt = $pdo->query("SELECT DISTINCT animalType FROM reports WHERE animalType IS NOT NULL ORDER BY animalType");
    $animalTypes = $animalTypesStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $animalTypes = [];
}

// Get unique barangays for filter dropdown
try {
    $barangaysStmt = $pdo->query("SELECT DISTINCT barangay FROM patients WHERE barangay IS NOT NULL ORDER BY barangay");
    $barangays = $barangaysStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $barangays = [];
}

// DSS ANALYTICS FUNCTIONS
// These functions perform actual data analysis and generate insights

/**
 * Calculate statistical significance using Chi-Square test
 * @param int $observed Observed value
 * @param int $expected Expected value
 * @return float p-value (lower = more significant)
 */
function calculateSignificance($observed, $expected) {
    if ($expected == 0) return 1.0; // No significance if expected is zero
    
    $chiSquare = pow($observed - $expected, 2) / $expected;
    
    // Approximate p-value for chi-square with 1 degree of freedom
    // This is a simplified calculation for demonstration
    $p = exp(-0.5 * $chiSquare);
    
    return $p;
}

/**
 * Generate trend description based on time series data
 * @param array $data Array of data points
 * @return array Trend description and metrics
 */
function analyzeTrend($data) {
    if (empty($data) || count($data) < 2) {
        return [
            'description' => 'Insufficient data to analyze trends.',
            'direction' => 'neutral',
            'magnitude' => 0,
            'significance' => 0
        ];
    }
    
    // Calculate linear regression
    $n = count($data);
    $sumX = 0;
    $sumY = 0;
    $sumXY = 0;
    $sumX2 = 0;
    
    foreach ($data as $i => $value) {
        $sumX += $i;
        $sumY += $value;
        $sumXY += $i * $value;
        $sumX2 += $i * $i;
    }
    
    $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    
    // Calculate average for percentage change
    $avg = $sumY / $n;
    $percentChange = ($slope * $n / $avg) * 100;
    
    // Calculate R-squared (coefficient of determination)
    $meanY = $sumY / $n;
    $totalVariation = 0;
    $explainedVariation = 0;
    
    foreach ($data as $i => $value) {
        $predicted = ($slope * $i) + (($sumY - $slope * $sumX) / $n);
        $totalVariation += pow($value - $meanY, 2);
        $explainedVariation += pow($predicted - $meanY, 2);
    }
    
    $rSquared = ($totalVariation > 0) ? $explainedVariation / $totalVariation : 0;
    
    // Determine trend direction and magnitude
    $direction = ($slope > 0) ? 'increasing' : (($slope < 0) ? 'decreasing' : 'stable');
    $magnitude = abs($percentChange);
    
    // Generate description
    $description = '';
    if ($magnitude < 5) {
        $description = "Cases have remained relatively stable over the period.";
    } else {
        $intensifier = ($magnitude > 20) ? 'significantly ' : (($magnitude > 10) ? 'moderately ' : 'slightly ');
        $description = "Cases are {$intensifier}{$direction} at a rate of " . number_format(abs($percentChange), 1) . "% over the period.";
        
        if ($rSquared > 0.7) {
            $description .= " This trend is statistically significant and likely to continue.";
        } elseif ($rSquared > 0.3) {
            $description .= " This trend shows moderate statistical significance.";
        } else {
            $description .= " This trend shows weak statistical significance and may be due to random variation.";
        }
    }
    
    return [
        'description' => $description,
        'direction' => $direction,
        'magnitude' => $magnitude,
        'significance' => $rSquared
    ];
}

/**
 * Generate seasonal pattern analysis
 * @param array $monthlyData Monthly data for multiple years
 * @return array Seasonal insights
 */
function analyzeSeasonality($monthlyData) {
    if (empty($monthlyData) || count($monthlyData) < 12) {
        return [
            'description' => 'Insufficient data to analyze seasonality.',
            'peak_months' => [],
            'low_months' => []
        ];
    }
    
    // Group by month across years
    $monthlyAverages = [];
    $monthCounts = [];
    
    foreach ($monthlyData as $item) {
        $month = date('n', strtotime($item['month'] . '-01'));
        
        if (!isset($monthlyAverages[$month])) {
            $monthlyAverages[$month] = 0;
            $monthCounts[$month] = 0;
        }
        
        $monthlyAverages[$month] += $item['count'];
        $monthCounts[$month]++;
    }
    
    // Calculate averages
    foreach ($monthlyAverages as $month => $total) {
        if ($monthCounts[$month] > 0) {
            $monthlyAverages[$month] = $total / $monthCounts[$month];
        }
    }
    
    // Calculate overall average
    $overallAverage = array_sum($monthlyAverages) / count($monthlyAverages);
    
    // Find peak and low months (>20% deviation from average)
    $peakMonths = [];
    $lowMonths = [];
    
    foreach ($monthlyAverages as $month => $avg) {
        $percentDiff = (($avg - $overallAverage) / $overallAverage) * 100;
        
        if ($percentDiff > 20) {
            $peakMonths[$month] = $percentDiff;
        } elseif ($percentDiff < -20) {
            $lowMonths[$month] = $percentDiff;
        }
    }
    
    // Generate description
    $description = '';
    
    if (empty($peakMonths) && empty($lowMonths)) {
        $description = "No significant seasonal pattern detected. Cases are distributed relatively evenly throughout the year.";
    } else {
        if (!empty($peakMonths)) {
            arsort($peakMonths);
            $monthNames = [];
            foreach (array_keys($peakMonths) as $month) {
                $monthNames[] = date('F', mktime(0, 0, 0, $month, 10));
            }
            
            $description = "Peak months for animal bite cases are " . implode(', ', $monthNames) . 
                           ", with " . number_format(reset($peakMonths), 1) . "% more cases than average.";
        }
        
        if (!empty($lowMonths)) {
            asort($lowMonths);
            $monthNames = [];
            foreach (array_keys($lowMonths) as $month) {
                $monthNames[] = date('F', mktime(0, 0, 0, $month, 10));
            }
            
            if (!empty($description)) {
                $description .= " ";
            }
            
            $description .= "Lowest incidence occurs in " . implode(', ', $monthNames) . 
                           ", with " . number_format(abs(reset($lowMonths)), 1) . "% fewer cases than average.";
        }
    }
    
    return [
        'description' => $description,
        'peak_months' => array_keys($peakMonths),
        'low_months' => array_keys($lowMonths)
    ];
}

/**
 * Analyze demographic patterns to identify at-risk groups
 * @param array $ageData Age distribution data
 * @param array $genderData Gender distribution data
 * @return array Risk group insights
 */
function analyzeRiskGroups($ageData, $genderData) {
    $insights = [];
    
    // Analyze age distribution
    if (!empty($ageData)) {
        // Find age group with highest incidence
        $maxCount = 0;
        $maxAgeGroup = '';
        $totalAgeCases = 0;
        
        foreach ($ageData as $age) {
            $totalAgeCases += $age['count'];
            if ($age['count'] > $maxCount) {
                $maxCount = $age['count'];
                $maxAgeGroup = $age['age_group'];
            }
        }
        
        // Calculate percentage for highest group
        $maxAgePercent = ($totalAgeCases > 0) ? ($maxCount / $totalAgeCases) * 100 : 0;
        
        // Check if there's a significant concentration in one age group (>30%)
        if ($maxAgePercent > 30) {
            $insights[] = "The {$maxAgeGroup} age group is significantly overrepresented, accounting for " . 
                         number_format($maxAgePercent, 1) . "% of all cases.";
        }
        
        // Check for children specifically
        $childrenCount = 0;
        foreach ($ageData as $age) {
            if ($age['age_group'] === 'Under 5' || $age['age_group'] === '5-12') {
                $childrenCount += $age['count'];
            }
        }
        
        $childrenPercent = ($totalAgeCases > 0) ? ($childrenCount / $totalAgeCases) * 100 : 0;
        
        if ($childrenPercent > 25) {
            $insights[] = "Children under 12 account for " . number_format($childrenPercent, 1) . 
                         "% of cases, suggesting a need for targeted education in schools and for parents.";
        }
    }
    
    // Analyze gender distribution
    if (!empty($genderData)) {
        $maleCount = 0;
        $femaleCount = 0;
        
        foreach ($genderData as $gender) {
            if ($gender['gender'] === 'Male') {
                $maleCount = $gender['count'];
            } elseif ($gender['gender'] === 'Female') {
                $femaleCount = $gender['count'];
            }
        }
        
        $totalGenderCases = $maleCount + $femaleCount;
        
        if ($totalGenderCases > 0) {
            $malePercent = ($maleCount / $totalGenderCases) * 100;
            $femalePercent = ($femaleCount / $totalGenderCases) * 100;
            
            // Check for significant gender disparity (>60%)
            if ($malePercent > 60) {
                $insights[] = "Males are significantly overrepresented at " . number_format($malePercent, 1) . 
                             "% of cases, suggesting gender-specific risk factors.";
            } elseif ($femalePercent > 60) {
                $insights[] = "Females are significantly overrepresented at " . number_format($femalePercent, 1) . 
                             "% of cases, suggesting gender-specific risk factors.";
            }
        }
    }
    
    // If no specific insights, provide a general statement
    if (empty($insights)) {
        $insights[] = "No significant demographic patterns detected. Cases appear to be distributed across age groups and genders.";
    }
    
    return $insights;
}

/**
 * Generate data-driven recommendations based on analysis
 * @param array $analysisResults Various analysis results
 * @return array Actionable recommendations
 */
function generateRecommendations($analysisResults) {
    $recommendations = [];
    
    // Extract analysis components
    $trendAnalysis = $analysisResults['trend'] ?? null;
    $seasonalAnalysis = $analysisResults['seasonality'] ?? null;
    $riskGroups = $analysisResults['risk_groups'] ?? [];
    $highRiskAreas = $analysisResults['high_risk_areas'] ?? [];
    $animalDistribution = $analysisResults['animal_distribution'] ?? [];
    $vaccinationRate = $analysisResults['vaccination_rate'] ?? 0;
    $reportingDelay = $analysisResults['reporting_delay'] ?? 0;
    
    // Recommendations based on trend
    if ($trendAnalysis && isset($trendAnalysis['direction'])) {
        if ($trendAnalysis['direction'] === 'increasing' && $trendAnalysis['magnitude'] > 10) {
            $recommendations[] = [
                'title' => 'Address Rising Case Numbers',
                'description' => "Implement immediate intervention measures to address the " . 
                                number_format($trendAnalysis['magnitude'], 1) . "% increase in cases. " .
                                "Consider allocating additional resources for prevention and treatment."
            ];
        } elseif ($trendAnalysis['direction'] === 'decreasing' && $trendAnalysis['magnitude'] > 10) {
            $recommendations[] = [
                'title' => 'Maintain Successful Strategies',
                'description' => "Continue current prevention strategies that have contributed to a " . 
                                number_format($trendAnalysis['magnitude'], 1) . "% decrease in cases. " .
                                "Document successful approaches for future reference."
            ];
        }
    }
    
    // Recommendations based on seasonality
    if ($seasonalAnalysis && !empty($seasonalAnalysis['peak_months'])) {
        $monthNames = [];
        foreach ($seasonalAnalysis['peak_months'] as $month) {
            $monthNames[] = date('F', mktime(0, 0, 0, $month, 10));
        }
        
        $recommendations[] = [
            'title' => 'Prepare for Seasonal Peaks',
            'description' => "Increase preparedness and resources during " . implode(', ', $monthNames) . 
                            ", when cases historically peak. Consider seasonal awareness campaigns before these months."
        ];
    }
    
    // Recommendations based on risk groups
    foreach ($riskGroups as $insight) {
        if (strpos($insight, 'Children under 12') !== false) {
            $recommendations[] = [
                'title' => 'School-Based Education',
                'description' => "Implement animal bite prevention education in schools, targeting children under 12 " .
                                "who are disproportionately affected. Include parent education components."
            ];
        }
        
        if (strpos($insight, 'Males are significantly') !== false) {
            $recommendations[] = [
                'title' => 'Target High-Risk Demographics',
                'description' => "Develop targeted prevention messages for males, who show higher risk. " .
                                "Focus on common activities or behaviors that may increase exposure risk."
            ];
        } elseif (strpos($insight, 'Females are significantly') !== false) {
            $recommendations[] = [
                'title' => 'Target High-Risk Demographics',
                'description' => "Develop targeted prevention messages for females, who show higher risk. " .
                                "Focus on common activities or behaviors that may increase exposure risk."
            ];
        }
    }
    
    // Recommendations based on high-risk areas
    if (!empty($highRiskAreas)) {
        $areaNames = array_column($highRiskAreas, 'barangay');
        $topArea = $areaNames[0] ?? '';
        
        if (!empty($topArea)) {
            $recommendations[] = [
                'title' => 'Focus on High-Risk Areas',
                'description' => "Prioritize prevention and response resources in {$topArea} and other high-incidence barangays. " .
                                "Consider targeted vaccination drives and stray animal control in these areas."
            ];
        }
    }
    
    // Recommendations based on animal distribution
    if (!empty($animalDistribution)) {
        $topAnimal = '';
        $topCount = 0;
        
        foreach ($animalDistribution as $animal) {
            if ($animal['count'] > $topCount) {
                $topCount = $animal['count'];
                $topAnimal = $animal['animalType'];
            }
        }
        
        if (!empty($topAnimal)) {
            $recommendations[] = [
                'title' => 'Animal-Specific Interventions',
                'description' => "Focus prevention efforts on {$topAnimal} bites, which account for the highest percentage of cases. " .
                                "Develop specific guidelines for {$topAnimal} owners and handling encounters."
            ];
        }
    }
    
    // Recommendations based on vaccination rate
    if ($vaccinationRate < 50) {
        $recommendations[] = [
            'title' => 'Improve Animal Vaccination',
            'description' => "The low animal vaccination rate of " . number_format($vaccinationRate, 1) . "% is concerning. " .
                            "Implement community vaccination drives and consider subsidized or free rabies vaccines for pets."
        ];
    }
    
    // Recommendations based on reporting delay
    if ($reportingDelay > 24) {
        $recommendations[] = [
            'title' => 'Address Reporting Delays',
            'description' => "The average reporting delay of {$reportingDelay} hours is concerning. " .
                            "Launch public awareness campaigns about the importance of immediate reporting after animal bites."
        ];
    }
    
    // If no specific recommendations, provide general ones
    if (empty($recommendations)) {
        $recommendations[] = [
            'title' => 'Maintain Prevention Programs',
            'description' => "Continue current prevention and education programs. Monitor trends closely for any changes."
        ];
        
        $recommendations[] = [
            'title' => 'Improve Data Collection',
            'description' => "Enhance data collection practices to gather more detailed information about cases, " .
                            "which will enable more specific recommendations in the future."
        ];
    }
    
    return $recommendations;
}

// Get total cases for the selected period
try {
    $totalQuery = "
        SELECT COUNT(*) as total_cases
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        WHERE r.reportDate BETWEEN ? AND ?
    ";
    
    $params = [$dateFrom, $dateTo];
    
    if (!empty($animalType)) {
        $totalQuery .= " AND r.animalType = ?";
        $params[] = $animalType;
    }
    
    if (!empty($barangay)) {
        $totalQuery .= " AND p.barangay = ?";
        $params[] = $barangay;
    }
    
    $totalStmt = $pdo->prepare($totalQuery);
    $totalStmt->execute($params);
    $totalCases = $totalStmt->fetchColumn();
    
    // Get total cases for comparison period
    $compareParams = [$compareFrom, $compareTo];
    
    if (!empty($animalType)) {
        $compareParams[] = $animalType;
    }
    
    if (!empty($barangay)) {
        $compareParams[] = $barangay;
    }
    
    $compareStmt = $pdo->prepare($totalQuery);
    $compareStmt->execute($compareParams);
    $compareCases = $compareStmt->fetchColumn();
    
    // Calculate percentage change
    if ($compareCases > 0) {
        $percentChange = (($totalCases - $compareCases) / $compareCases) * 100;
    } else {
        $percentChange = $totalCases > 0 ? 100 : 0;
    }
    
} catch (PDOException $e) {
    $totalCases = 0;
    $compareCases = 0;
    $percentChange = 0;
}

// Get cases by animal type
try {
    $animalQuery = "
        SELECT r.animalType, COUNT(*) as count
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        WHERE r.reportDate BETWEEN ? AND ?
    ";
    
    $animalParams = [$dateFrom, $dateTo];
    
    if (!empty($animalType)) {
        $animalQuery .= " AND r.animalType = ?";
        $animalParams[] = $animalType;
    }
    
    if (!empty($barangay)) {
        $animalQuery .= " AND p.barangay = ?";
        $animalParams[] = $barangay;
    }
    
    $animalQuery .= " GROUP BY r.animalType ORDER BY count DESC";
    
    $animalStmt = $pdo->prepare($animalQuery);
    $animalStmt->execute($animalParams);
    $animalData = $animalStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $animalData = [];
}

// Get cases by bite category
try {
    $categoryQuery = "
        SELECT r.biteType, COUNT(*) as count
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        WHERE r.reportDate BETWEEN ? AND ?
    ";
    
    $categoryParams = [$dateFrom, $dateTo];
    
    if (!empty($animalType)) {
        $categoryQuery .= " AND r.animalType = ?";
        $categoryParams[] = $animalType;
    }
    
    if (!empty($barangay)) {
        $categoryQuery .= " AND p.barangay = ?";
        $categoryParams[] = $barangay;
    }
    
    $categoryQuery .= " GROUP BY r.biteType ORDER BY 
        CASE 
            WHEN r.biteType = 'Category I' THEN 1
            WHEN r.biteType = 'Category II' THEN 2
            WHEN r.biteType = 'Category III' THEN 3
            ELSE 4
        END";
    
    $categoryStmt = $pdo->prepare($categoryQuery);
    $categoryStmt->execute($categoryParams);
    $categoryData = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $categoryData = [];
}

// Get cases by barangay (top 10)
try {
    $barangayQuery = "
        SELECT p.barangay, COUNT(*) as count
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        WHERE r.reportDate BETWEEN ? AND ? AND p.barangay IS NOT NULL
    ";
    
    $barangayParams = [$dateFrom, $dateTo];
    
    if (!empty($animalType)) {
        $barangayQuery .= " AND r.animalType = ?";
        $barangayParams[] = $animalType;
    }
    
    if (!empty($barangay)) {
        $barangayQuery .= " AND p.barangay = ?";
        $barangayParams[] = $barangay;
    }
    
    $barangayQuery .= " GROUP BY p.barangay ORDER BY count DESC LIMIT 10";
    
    $barangayStmt = $pdo->prepare($barangayQuery);
    $barangayStmt->execute($barangayParams);
    $barangayData = $barangayStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $barangayData = [];
}

// Get trend data (cases by month)
try {
    $trendQuery = "
        SELECT 
            DATE_FORMAT(r.reportDate, '%Y-%m') as month,
            COUNT(*) as count
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        WHERE r.reportDate BETWEEN ? AND ?
    ";
    
    $trendParams = [$dateFrom, $dateTo];
    
    if (!empty($animalType)) {
        $trendQuery .= " AND r.animalType = ?";
        $trendParams[] = $animalType;
    }
    
    if (!empty($barangay)) {
        $trendQuery .= " AND p.barangay = ?";
        $trendParams[] = $barangay;
    }
    
    $trendQuery .= " GROUP BY DATE_FORMAT(r.reportDate, '%Y-%m') ORDER BY month";
    
    $trendStmt = $pdo->prepare($trendQuery);
    $trendStmt->execute($trendParams);
    $trendData = $trendStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format trend data for chart
    $trendLabels = [];
    $trendCounts = [];
    
    foreach ($trendData as $data) {
        $date = DateTime::createFromFormat('Y-m', $data['month']);
        $trendLabels[] = $date->format('M Y');
        $trendCounts[] = $data['count'];
    }
    
    // Get monthly data for the past 2 years for seasonality analysis
    $seasonalQuery = "
        SELECT 
            DATE_FORMAT(r.reportDate, '%Y-%m') as month,
            COUNT(*) as count
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        WHERE r.reportDate >= DATE_SUB(CURDATE(), INTERVAL 2 YEAR)
    ";
    
    $seasonalParams = [];
    
    if (!empty($animalType)) {
        $seasonalQuery .= " AND r.animalType = ?";
        $seasonalParams[] = $animalType;
    }
    
    if (!empty($barangay)) {
        $seasonalQuery .= " AND p.barangay = ?";
        $seasonalParams[] = $barangay;
    }
    
    $seasonalQuery .= " GROUP BY DATE_FORMAT(r.reportDate, '%Y-%m') ORDER BY month";
    
    $seasonalStmt = $pdo->prepare($seasonalQuery);
    $seasonalStmt->execute($seasonalParams);
    $seasonalData = $seasonalStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $trendData = [];
    $trendLabels = [];
    $trendCounts = [];
    $seasonalData = [];
}

// Get cases by age group
try {
    $ageQuery = "
        SELECT 
            CASE
                WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.reportDate) < 5 THEN 'Under 5'
                WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.reportDate) BETWEEN 5 AND 12 THEN '5-12'
                WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.reportDate) BETWEEN 13 AND 18 THEN '13-18'
                WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.reportDate) BETWEEN 19 AND 30 THEN '19-30'
                WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.reportDate) BETWEEN 31 AND 50 THEN '31-50'
                WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.reportDate) BETWEEN 51 AND 65 THEN '51-65'
                WHEN TIMESTAMPDIFF(YEAR, p.dateOfBirth, r.reportDate) > 65 THEN 'Over 65'
                ELSE 'Unknown'
            END as age_group,
            COUNT(*) as count
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        WHERE r.reportDate BETWEEN ? AND ? AND p.dateOfBirth IS NOT NULL
    ";
    
    $ageParams = [$dateFrom, $dateTo];
    
    if (!empty($animalType)) {
        $ageQuery .= " AND r.animalType = ?";
        $ageParams[] = $animalType;
    }
    
    if (!empty($barangay)) {
        $ageQuery .= " AND p.barangay = ?";
        $ageParams[] = $barangay;
    }
    
    $ageQuery .= " GROUP BY age_group ORDER BY 
        CASE 
            WHEN age_group = 'Under 5' THEN 1
            WHEN age_group = '5-12' THEN 2
            WHEN age_group = '13-18' THEN 3
            WHEN age_group = '19-30' THEN 4
            WHEN age_group = '31-50' THEN 5
            WHEN age_group = '51-65' THEN 6
            WHEN age_group = 'Over 65' THEN 7
            ELSE 8
        END";
    
    $ageStmt = $pdo->prepare($ageQuery);
    $ageStmt->execute($ageParams);
    $ageData = $ageStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $ageData = [];
}

// Get cases by gender
try {
    $genderQuery = "
        SELECT 
            p.gender,
            COUNT(*) as count
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        WHERE r.reportDate BETWEEN ? AND ? AND p.gender IS NOT NULL
    ";
    
    $genderParams = [$dateFrom, $dateTo];
    
    if (!empty($animalType)) {
        $genderQuery .= " AND r.animalType = ?";
        $genderParams[] = $animalType;
    }
    
    if (!empty($barangay)) {
        $genderQuery .= " AND p.barangay = ?";
        $genderParams[] = $barangay;
    }
    
    $genderQuery .= " GROUP BY p.gender";
    
    $genderStmt = $pdo->prepare($genderQuery);
    $genderStmt->execute($genderParams);
    $genderData = $genderStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $genderData = [];
}

// Get treatment statistics
try {
    $treatmentQuery = "
        SELECT 
            SUM(CASE WHEN r.washWithSoap = 1 THEN 1 ELSE 0 END) as wash_count,
            SUM(CASE WHEN r.rabiesVaccine = 1 THEN 1 ELSE 0 END) as rabies_count,
            SUM(CASE WHEN r.antiTetanus = 1 THEN 1 ELSE 0 END) as tetanus_count,
            SUM(CASE WHEN r.antibiotics = 1 THEN 1 ELSE 0 END) as antibiotics_count,
            SUM(CASE WHEN r.referredToHospital = 1 THEN 1 ELSE 0 END) as referred_count,
            COUNT(*) as total_count
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        WHERE r.reportDate BETWEEN ? AND ?
    ";
    
    $treatmentParams = [$dateFrom, $dateTo];
    
    if (!empty($animalType)) {
        $treatmentQuery .= " AND r.animalType = ?";
        $treatmentParams[] = $animalType;
    }
    
    if (!empty($barangay)) {
        $treatmentQuery .= " AND p.barangay = ?";
        $treatmentParams[] = $barangay;
    }
    
    $treatmentStmt = $pdo->prepare($treatmentQuery);
    $treatmentStmt->execute($treatmentParams);
    $treatmentData = $treatmentStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $treatmentData = [
        'wash_count' => 0,
        'rabies_count' => 0,
        'tetanus_count' => 0,
        'antibiotics_count' => 0,
        'referred_count' => 0,
        'total_count' => 0
    ];
}

// Get animal ownership data
try {
    $ownershipQuery = "
        SELECT 
            r.animalOwnership,
            COUNT(*) as count
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        WHERE r.reportDate BETWEEN ? AND ? AND r.animalOwnership IS NOT NULL
    ";
    
    $ownershipParams = [$dateFrom, $dateTo];
    
    if (!empty($animalType)) {
        $ownershipQuery .= " AND r.animalType = ?";
        $ownershipParams[] = $animalType;
    }
    
    if (!empty($barangay)) {
        $ownershipQuery .= " AND p.barangay = ?";
        $ownershipParams[] = $barangay;
    }
    
    $ownershipQuery .= " GROUP BY r.animalOwnership";
    
    $ownershipStmt = $pdo->prepare($ownershipQuery);
    $ownershipStmt->execute($ownershipParams);
    $ownershipData = $ownershipStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $ownershipData = [];
}

// Get vaccination status data
try {
    $vaccinationQuery = "
        SELECT 
            r.animalVaccinated,
            COUNT(*) as count
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        WHERE r.reportDate BETWEEN ? AND ? AND r.animalVaccinated IS NOT NULL
    ";
    
    $vaccinationParams = [$dateFrom, $dateTo];
    
    if (!empty($animalType)) {
        $vaccinationQuery .= " AND r.animalType = ?";
        $vaccinationParams[] = $animalType;
    }
    
    if (!empty($barangay)) {
        $vaccinationQuery .= " AND p.barangay = ?";
        $vaccinationParams[] = $barangay;
    }
    
    $vaccinationQuery .= " GROUP BY r.animalVaccinated";
    
    $vaccinationStmt = $pdo->prepare($vaccinationQuery);
    $vaccinationStmt->execute($vaccinationParams);
    $vaccinationData = $vaccinationStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate vaccination rate
    $vaccinatedCount = 0;
    $totalVaccinationCount = 0;
    
    foreach ($vaccinationData as $data) {
        $totalVaccinationCount += $data['count'];
        if ($data['animalVaccinated'] === 'Yes') {
            $vaccinatedCount = $data['count'];
        }
    }
    
    $vaccinationRate = ($totalVaccinationCount > 0) ? ($vaccinatedCount / $totalVaccinationCount) * 100 : 0;
    
} catch (PDOException $e) {
    $vaccinationData = [];
    $vaccinationRate = 0;
}

// Get reporting delay data
try {
    $delayQuery = "
        SELECT 
            AVG(TIMESTAMPDIFF(HOUR, biteDate, reportDate)) as avg_delay
        FROM reports
        WHERE reportDate BETWEEN ? AND ? AND biteDate IS NOT NULL
    ";
    
    $delayParams = [$dateFrom, $dateTo];
    
    if (!empty($animalType)) {
        $delayQuery .= " AND animalType = ?";
        $delayParams[] = $animalType;
    }
    
    $delayStmt = $pdo->prepare($delayQuery);
    $delayStmt->execute($delayParams);
    $avgDelay = $delayStmt->fetchColumn();
    
} catch (PDOException $e) {
    $avgDelay = 0;
}

// Get high-risk areas (barangays with high Category III bites)
try {
    $riskAreasQuery = "
        SELECT 
            p.barangay,
            COUNT(*) as total_cases,
            SUM(CASE WHEN r.biteType = 'Category III' THEN 1 ELSE 0 END) as high_risk_cases,
            ROUND((SUM(CASE WHEN r.biteType = 'Category III' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as high_risk_percentage
        FROM reports r
        JOIN patients p ON r.patientId = p.patientId
        WHERE r.reportDate BETWEEN ? AND ? AND p.barangay IS NOT NULL
    ";
    
    $riskAreasParams = [$dateFrom, $dateTo];
    
    if (!empty($animalType)) {
        $riskAreasQuery .= " AND r.animalType = ?";
        $riskAreasParams[] = $animalType;
    }
    
    if (!empty($barangay)) {
        $riskAreasQuery .= " AND p.barangay = ?";
        $riskAreasParams[] = $barangay;
    }
    
    $riskAreasQuery .= " GROUP BY p.barangay HAVING COUNT(*) >= 5 ORDER BY high_risk_percentage DESC LIMIT 5";
    
    $riskAreasStmt = $pdo->prepare($riskAreasQuery);
    $riskAreasStmt->execute($riskAreasParams);
    $highRiskAreas = $riskAreasStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $highRiskAreas = [];
}

// Perform trend analysis
$trendAnalysis = analyzeTrend($trendCounts);

// Perform seasonality analysis
$seasonalAnalysis = analyzeSeasonality($seasonalData);

// Analyze risk groups
$riskGroupInsights = analyzeRiskGroups($ageData, $genderData);

// Compile analysis results
$analysisResults = [
    'trend' => $trendAnalysis,
    'seasonality' => $seasonalAnalysis,
    'risk_groups' => $riskGroupInsights,
    'high_risk_areas' => $highRiskAreas,
    'animal_distribution' => $animalData,
    'vaccination_rate' => $vaccinationRate,
    'reporting_delay' => $avgDelay
];

// Generate recommendations
$recommendations = generateRecommendations($analysisResults);

// Function to build URL with updated parameters
function buildUrl($newParams = []) {
    $params = $_GET;
    
    foreach ($newParams as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    
    return 'analytics.php?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Analytics Dashboard | Animal Bite Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bs-primary: #0d6efd;
            --bs-primary-rgb: 13, 110, 253;
            --bs-secondary: #f8f9fa;
            --bs-secondary-rgb: 248, 249, 250;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
        }
        
        .navbar-brand i {
            color: var(--bs-primary);
            margin-right: 0.5rem;
            font-size: 1.5rem;
        }
        
        .nav-link.active {
            color: var(--bs-primary) !important;
            font-weight: 500;
        }
        
        .btn-primary {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        
        .btn-outline-primary {
            color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        
        .analytics-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
            flex-grow: 1;
        }
        
        .content-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .content-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            background-color: rgba(var(--bs-primary-rgb), 0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .content-card-body {
            padding: 1.5rem;
        }
        
        .filter-form {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .stats-card {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            height: 100%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0.5rem 0;
            color: var(--bs-primary);
        }
        
        .card-icon {
            font-size: 2.5rem;
            color: rgba(var(--bs-primary-rgb), 0.2);
        }
        
        .trend-indicator {
            display: inline-flex;
            align-items: center;
            font-weight: 500;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        
        .trend-up {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .trend-down {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .trend-neutral {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table th {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
            font-weight: 600;
            border-top: none;
        }
        
        .table td, .table th {
            padding: 1rem;
            vertical-align: middle;
        }
        
        .table tbody tr {
            transition: all 0.2s;
        }
        
        .table tbody tr:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
        }
        
        .footer {
            background-color: white;
            padding: 1rem 0;
            margin-top: auto;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .kpi-card {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            height: 100%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .kpi-icon {
            font-size: 2rem;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-bottom: 1rem;
        }
        
        .kpi-icon-primary {
            background-color: rgba(var(--bs-primary-rgb), 0.1);
            color: var(--bs-primary);
        }
        
        .kpi-icon-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .kpi-icon-warning {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .kpi-icon-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .kpi-icon-info {
            background-color: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
        }
        
        .kpi-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .kpi-label {
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .kpi-percent {
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .analytics-container {
                padding: 1rem;
            }
            
            .content-card-header, .content-card-body {
                padding: 1rem;
            }
            
            .filter-form {
                padding: 1rem;
            }
            
            .chart-container {
                height: 250px;
            }
        }
        
        @media print {
            .navbar, .filter-form, .footer, .no-print {
                display: none !important;
            }
            
            .analytics-container {
                padding: 0;
                width: 100%;
                max-width: 100%;
            }
            
            .content-card {
                break-inside: avoid;
                margin-bottom: 1rem;
                box-shadow: none;
                border: 1px solid #dee2e6;
            }
            
            .content-card-header {
                background-color: #f8f9fa;
            }
            
            body {
                background-color: white;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top mb-4">
    <div class="container">
      <a class="navbar-brand fw-bold" href="index.php">BHW Admin Portal</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="admin_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="geomapping.php"><i class="bi bi-geo-alt me-1"></i> Geomapping</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="view_reports.php"><i class="bi bi-file-earmark-text"></i> Reports</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="decisionSupport.php"><i class="bi bi-graph-up me-1"></i> Decision Support</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="view_staff.php"><i class="bi bi-people"></i> Staff</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="admin_settings.php"><i class="bi bi-gear"></i> Settings</a>
          </li>
          <li class="nav-item">
            <a class="nav-link btn-logout ms-2" href="../logout/admin_logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

    <div class="analytics-container">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Analytics Dashboard</h2>
                <p class="text-muted mb-0">Comprehensive analysis of animal bite cases and trends</p>
            </div>
            <div>
                <button class="btn btn-outline-primary me-2" onclick="window.print()">
                    <i class="bi bi-printer me-2"></i>Print Report
                </button>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download me-2"></i>Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="export_analytics.php?format=pdf&<?php echo http_build_query($_GET); ?>"><i class="bi bi-file-pdf me-2"></i>Export as PDF</a></li>
                        <li><a class="dropdown-item" href="export_analytics.php?format=excel&<?php echo http_build_query($_GET); ?>"><i class="bi bi-file-excel me-2"></i>Export as Excel</a></li>
                        <li><a class="dropdown-item" href="export_analytics.php?format=csv&<?php echo http_build_query($_GET); ?>"><i class="bi bi-file-csv me-2"></i>Export as CSV</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-form">
            <form method="GET" action="analytics.php">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h5 class="mb-3">Primary Period</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="date_from" class="form-label">From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="date_to" class="form-label">To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h5 class="mb-3">Comparison Period</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="compare_from" class="form-label">From</label>
                                <input type="date" class="form-control" id="compare_from" name="compare_from" value="<?php echo htmlspecialchars($compareFrom); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="compare_to" class="form-label">To</label>
                                <input type="date" class="form-control" id="compare_to" name="compare_to" value="<?php echo htmlspecialchars($compareTo); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="animal_type" class="form-label">Animal Type</label>
                        <select class="form-select" id="animal_type" name="animal_type">
                            <option value="">All Types</option>
                            <?php foreach ($animalTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $animalType === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="barangay" class="form-label">Barangay</label>
                        <select class="form-select" id="barangay" name="barangay">
                            <option value="">All Barangays</option>
                            <?php foreach ($barangays as $brgy): ?>
                            <option value="<?php echo htmlspecialchars($brgy); ?>" <?php echo $barangay === $brgy ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($brgy); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="d-grid gap-2 d-md-flex w-100">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-search me-2"></i>Apply Filters
                            </button>
                            <a href="analytics.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-2"></i>Reset
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Key Performance Indicators -->
        <div class="content-card">
            <div class="content-card-header">
                <h5 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>Key Performance Indicators</h5>
                <span class="badge bg-primary">
                    <?php echo date('M d, Y', strtotime($dateFrom)) . ' - ' . date('M d, Y', strtotime($dateTo)); ?>
                </span>
            </div>
            <div class="content-card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="kpi-card">
                            <div class="kpi-icon kpi-icon-primary">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>
                            <div class="kpi-value"><?php echo $totalCases; ?></div>
                            <div class="kpi-label">Total Cases</div>
                            <?php if ($percentChange > 0): ?>
                            <div class="kpi-percent text-success">
                                <i class="bi bi-arrow-up-right"></i> <?php echo number_format(abs($percentChange), 1); ?>% increase
                            </div>
                            <?php elseif ($percentChange < 0): ?>
                            <div class="kpi-percent text-danger">
                                <i class="bi bi-arrow-down-right"></i> <?php echo number_format(abs($percentChange), 1); ?>% decrease
                            </div>
                            <?php else: ?>
                            <div class="kpi-percent text-secondary">
                                <i class="bi bi-dash"></i> No change
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="kpi-card">
                            <div class="kpi-icon kpi-icon-danger">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <?php
                                $categoryIIICount = 0;
                                foreach ($categoryData as $category) {
                                    if ($category['biteType'] === 'Category III') {
                                        $categoryIIICount = $category['count'];
                                        break;
                                    }
                                }
                                $categoryIIIPercent = $totalCases > 0 ? ($categoryIIICount / $totalCases) * 100 : 0;
                            ?>
                            <div class="kpi-value"><?php echo $categoryIIICount; ?></div>
                            <div class="kpi-label">Category III Cases</div>
                            <div class="kpi-percent">
                                <?php echo number_format($categoryIIIPercent, 1); ?>% of total cases
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="kpi-card">
                            <div class="kpi-icon kpi-success">
                                <i class="bi bi-bandaid"></i>
                            </div>
                            <?php
                                $treatmentRate = $treatmentData['total_count'] > 0 ? 
                                    ($treatmentData['rabies_count'] / $treatmentData['total_count']) * 100 : 0;
                            ?>
                            <div class="kpi-value"><?php echo number_format($treatmentRate, 1); ?>%</div>
                            <div class="kpi-label">Rabies Vaccination Rate</div>
                            <div class="kpi-percent">
                                <?php echo $treatmentData['rabies_count']; ?> out of <?php echo $treatmentData['total_count']; ?> cases
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Trend Analysis -->
        <div class="content-card">
            <div class="content-card-header">
                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Case Trend Analysis</h5>
                <div class="btn-group btn-group-sm no-print">
                    <button type="button" class="btn btn-outline-primary active" id="lineChartBtn">Line</button>
                    <button type="button" class="btn btn-outline-primary" id="barChartBtn">Bar</button>
                </div>
            </div>
            <div class="content-card-body">
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
                <div class="mt-4">
                    <h6>Trend Analysis:</h6>
                    <p>
                        <?php echo $trendAnalysis['description']; ?>
                    </p>
                    <?php if (!empty($seasonalAnalysis['description'])): ?>
                    <h6 class="mt-3">Seasonal Patterns:</h6>
                    <p>
                        <?php echo $seasonalAnalysis['description']; ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Distribution Analysis -->
        <div class="row g-4">
            <!-- Animal Type Distribution -->
            <div class="col-md-6">
                <div class="content-card h-100">
                    <div class="content-card-header">
                        <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Animal Type Distribution</h5>
                    </div>
                    <div class="content-card-body">
                        <div class="chart-container">
                            <canvas id="animalChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <h6>Key Insights:</h6>
                            <ul>
                                <?php
                                    if (count($animalData) > 0) {
                                        $topAnimal = $animalData[0];
                                        $topAnimalPercent = ($topAnimal['count'] / $totalCases) * 100;
                                        
                                        echo "<li><strong>" . htmlspecialchars($topAnimal['animalType']) . "</strong> accounts for " . number_format($topAnimalPercent, 1) . "% of all cases, making it the most common source of animal bites.</li>";
                                        
                                        if (count($animalData) > 1) {
                                            $secondAnimal = $animalData[1];
                                            $secondAnimalPercent = ($secondAnimal['count'] / $totalCases) * 100;
                                            
                                            echo "<li><strong>" . htmlspecialchars($secondAnimal['animalType']) . "</strong> is the second most common at " . number_format($secondAnimalPercent, 1) . "% of cases.</li>";
                                        }
                                        
                                        echo "<li>Focus prevention efforts on " . htmlspecialchars($topAnimal['animalType']) . " bite prevention to have the greatest impact.</li>";
                                    } else {
                                        echo "<li>No data available for the selected filters.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bite Category Distribution -->
            <div class="col-md-6">
                <div class="content-card h-100">
                    <div class="content-card-header">
                        <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Bite Category Distribution</h5>
                    </div>
                    <div class="content-card-body">
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <h6>Key Insights:</h6>
                            <ul>
                                <?php
                                    $categoryICount = 0;
                                    $categoryIICount = 0;
                                    $categoryIIICount = 0;
                                    
                                    foreach ($categoryData as $category) {
                                        if ($category['biteType'] === 'Category I') {
                                            $categoryICount = $category['count'];
                                        } elseif ($category['biteType'] === 'Category II') {
                                            $categoryIICount = $category['count'];
                                        } elseif ($category['biteType'] === 'Category III') {
                                            $categoryIIICount = $category['count'];
                                        }
                                    }
                                    
                                    $categoryIPercent = $totalCases > 0 ? ($categoryICount / $totalCases) * 100 : 0;
                                    $categoryIIPercent = $totalCases > 0 ? ($categoryIICount / $totalCases) * 100 : 0;
                                    $categoryIIIPercent = $totalCases > 0 ? ($categoryIIICount / $totalCases) * 100 : 0;
                                    
                                    if ($totalCases > 0) {
                                        echo "<li><strong>Category III</strong> (severe) cases account for " . number_format($categoryIIIPercent, 1) . "% of all cases, requiring immediate medical attention.</li>";
                                        echo "<li><strong>Category II</strong> (moderate) cases represent " . number_format($categoryIIPercent, 1) . "% of all cases.</li>";
                                        echo "<li><strong>Category I</strong> (mild) cases make up " . number_format($categoryIPercent, 1) . "% of all cases.</li>";
                                        
                                        if ($categoryIIIPercent > 30) {
                                            echo "<li>The high proportion of Category III cases indicates a need for improved prevention and early intervention strategies.</li>";
                                        }
                                    } else {
                                        echo "<li>No data available for the selected filters.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Demographic Analysis -->
        <div class="row g-4 mt-2">
            <!-- Age Group Distribution -->
            <div class="col-md-6">
                <div class="content-card h-100">
                    <div class="content-card-header">
                        <h5 class="mb-0"><i class="bi bi-people me-2"></i>Age Group Distribution</h5>
                    </div>
                    <div class="content-card-body">
                        <div class="chart-container">
                            <canvas id="ageChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <h6>Key Insights:</h6>
                            <ul>
                                <?php
                                    if (count($ageData) > 0) {
                                        // Find the age group with the highest count
                                        $maxCount = 0;
                                        $maxAgeGroup = '';
                                        
                                        foreach ($ageData as $age) {
                                            if ($age['count'] > $maxCount) {
                                                $maxCount = $age['count'];
                                                $maxAgeGroup = $age['age_group'];
                                            }
                                        }
                                        
                                        $maxAgePercent = $totalCases > 0 ? ($maxCount / $totalCases) * 100 : 0;
                                        
                                        echo "<li>The <strong>" . htmlspecialchars($maxAgeGroup) . "</strong> age group has the highest incidence at " . number_format($maxAgePercent, 1) . "% of all cases.</li>";
                                        
                                        // Check if children are significantly affected
                                        $childrenCount = 0;
                                        foreach ($ageData as $age) {
                                            if ($age['age_group'] === 'Under 5' || $age['age_group'] === '5-12') {
                                                $childrenCount += $age['count'];
                                            }
                                        }
                                        
                                        $childrenPercent = $totalCases > 0 ? ($childrenCount / $totalCases) * 100 : 0;
                                        
                                        if ($childrenPercent > 30) {
                                            echo "<li>Children under 12 account for " . number_format($childrenPercent, 1) . "% of cases, suggesting a need for targeted education in schools and for parents.</li>";
                                        }
                                        
                                        echo "<li>Educational campaigns should be tailored to the most affected age groups.</li>";
                                    } else {
                                        echo "<li>No age data available for the selected filters.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gender Distribution -->
            <div class="col-md-6">
                <div class="content-card h-100">
                    <div class="content-card-header">
                        <h5 class="mb-0"><i class="bi bi-gender-ambiguous me-2"></i>Gender Distribution</h5>
                    </div>
                    <div class="content-card-body">
                        <div class="chart-container">
                            <canvas id="genderChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <h6>Key Insights:</h6>
                            <ul>
                                <?php
                                    $maleCount = 0;
                                    $femaleCount = 0;
                                    $otherCount = 0;
                                    
                                    foreach ($genderData as $gender) {
                                        if ($gender['gender'] === 'Male') {
                                            $maleCount = $gender['count'];
                                        } elseif ($gender['gender'] === 'Female') {
                                            $femaleCount = $gender['count'];
                                        } else {
                                            $otherCount += $gender['count'];
                                        }
                                    }
                                    
                                    $totalGenderCount = $maleCount + $femaleCount + $otherCount;
                                    $malePercent = $totalGenderCount > 0 ? ($maleCount / $totalGenderCount) * 100 : 0;
                                    $femalePercent = $totalGenderCount > 0 ? ($femaleCount / $totalGenderCount) * 100 : 0;
                                    
                                    if ($totalGenderCount > 0) {
                                        if ($maleCount > $femaleCount) {
                                            echo "<li><strong>Males</strong> account for " . number_format($malePercent, 1) . "% of cases, while <strong>females</strong> account for " . number_format($femalePercent, 1) . "%.</li>";
                                            
                                            if ($malePercent > 60) {
                                                echo "<li>Males are significantly more affected, possibly due to occupational exposure or behavioral factors.</li>";
                                            }
                                        } elseif ($femaleCount > $maleCount) {
                                            echo "<li><strong>Females</strong> account for " . number_format($femalePercent, 1) . "% of cases, while <strong>males</strong> account for " . number_format($malePercent, 1) . "%.</li>";
                                            
                                            if ($femalePercent > 60) {
                                                echo "<li>Females are significantly more affected, which may be related to specific risk factors or behaviors.</li>";
                                            }
                                        } else {
                                            echo "<li>Cases are equally distributed between males and females.</li>";
                                        }
                                        
                                        echo "<li>Gender-specific prevention strategies may be beneficial based on this distribution.</li>";
                                    } else {
                                        echo "<li>No gender data available for the selected filters.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Geographic Distribution -->
        <div class="content-card mt-4">
            <div class="content-card-header">
                <h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Geographic Distribution</h5>
                <a href="geomapping.php?date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&animal_type=<?php echo urlencode($animalType); ?>" class="btn btn-sm btn-outline-primary no-print">
                    <i class="bi bi-map me-2"></i>View Heatmap
                </a>
            </div>
            <div class="content-card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <canvas id="barangayChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Top Affected Barangays:</h6>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Barangay</th>
                                        <th>Cases</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        $rank = 1;
                                        foreach ($barangayData as $data) {
                                            $percentage = $totalCases > 0 ? ($data['count'] / $totalCases) * 100 : 0;
                                            echo "<tr>";
                                            echo "<td>" . $rank++ . "</td>";
                                            echo "<td>" . htmlspecialchars($data['barangay']) . "</td>";
                                            echo "<td>" . $data['count'] . "</td>";
                                            echo "<td>" . number_format($percentage, 1) . "%</td>";
                                            echo "</tr>";
                                            
                                            if ($rank > 5) break; // Show only top 5
                                        }
                                        
                                        if (count($barangayData) === 0) {
                                            echo "<tr><td colspan='4' class='text-center'>No data available</td></tr>";
                                        }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <h6>Key Insights:</h6>
                            <ul>
                                <?php
                                    if (count($barangayData) > 0) {
                                        $topBarangay = $barangayData[0];
                                        $topBarangayPercent = $totalCases > 0 ? ($topBarangay['count'] / $totalCases) * 100 : 0;
                                        
                                        echo "<li><strong>" . htmlspecialchars($topBarangay['barangay']) . "</strong> has the highest number of cases at " . number_format($topBarangayPercent, 1) . "% of all reported incidents.</li>";
                                        
                                        if (count($barangayData) > 1) {
                                            $secondBarangay = $barangayData[1];
                                            $secondBarangayPercent = $totalCases > 0 ? ($secondBarangay['count'] / $totalCases) * 100 : 0;
                                            
                                            echo "<li><strong>" . htmlspecialchars($secondBarangay['barangay']) . "</strong> follows with " . number_format($secondBarangayPercent, 1) . "% of cases.</li>";
                                        }
                                        
                                        echo "<li>Targeted interventions should be prioritized in these high-incidence areas.</li>";
                                        echo "<li>Consider investigating environmental or socioeconomic factors that may contribute to the higher incidence in these areas.</li>";
                                    } else {
                                        echo "<li>No geographic data available for the selected filters.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Risk Factor Analysis -->
        <div class="row g-4 mt-2">
            <!-- Animal Ownership -->
            <div class="col-md-6">
                <div class="content-card h-100">
                    <div class="content-card-header">
                        <h5 class="mb-0"><i class="bi bi-shield-exclamation me-2"></i>Animal Ownership</h5>
                    </div>
                    <div class="content-card-body">
                        <div class="chart-container">
                            <canvas id="ownershipChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <h6>Key Insights:</h6>
                            <ul>
                                <?php
                                    $strayCount = 0;
                                    $ownedCount = 0;
                                    
                                    foreach ($ownershipData as $ownership) {
                                        if ($ownership['animalOwnership'] === 'Stray') {
                                            $strayCount = $ownership['count'];
                                        } elseif (strpos($ownership['animalOwnership'], 'Owned') !== false) {
                                            $ownedCount += $ownership['count'];
                                        }
                                    }
                                    
                                    $totalOwnershipCount = array_sum(array_column($ownershipData, 'count'));
                                    $strayPercent = $totalOwnershipCount > 0 ? ($strayCount / $totalOwnershipCount) * 100 : 0;
                                    $ownedPercent = $totalOwnershipCount > 0 ? ($ownedCount / $totalOwnershipCount) * 100 : 0;
                                    
                                    if ($totalOwnershipCount > 0) {
                                        if ($strayCount > $ownedCount) {
                                            echo "<li><strong>Stray animals</strong> account for " . number_format($strayPercent, 1) . "% of bite cases, while <strong>owned animals</strong> account for " . number_format($ownedPercent, 1) . "%.</li>";
                                            echo "<li>The high proportion of stray animal bites suggests a need for improved animal control measures.</li>";
                                        } else {
                                            echo "<li><strong>Owned animals</strong> account for " . number_format($ownedPercent, 1) . "% of bite cases, while <strong>stray animals</strong> account for " . number_format($strayPercent, 1) . "%.</li>";
                                            echo "<li>The high proportion of owned animal bites suggests a need for better pet owner education.</li>";
                                        }
                                        
                                        echo "<li>Targeted interventions should address the predominant source of animal bites.</li>";
                                    } else {
                                        echo "<li>No animal ownership data available for the selected filters.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Animal Vaccination Status -->
            <div class="col-md-6">
                <div class="content-card h-100">
                    <div class="content-card-header">
                        <h5 class="mb-0"><i class="bi bi-virus me-2"></i>Animal Vaccination Status</h5>
                    </div>
                    <div class="content-card-body">
                        <div class="chart-container">
                            <canvas id="vaccinationChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <h6>Key Insights:</h6>
                            <ul>
                                <?php
                                    $vaccinatedCount = 0;
                                    $unvaccinatedCount = 0;
                                    $unknownCount = 0;
                                    
                                    foreach ($vaccinationData as $vaccination) {
                                        if ($vaccination['animalVaccinated'] === 'Yes') {
                                            $vaccinatedCount = $vaccination['count'];
                                        } elseif ($vaccination['animalVaccinated'] === 'No') {
                                            $unvaccinatedCount = $vaccination['count'];
                                        } else {
                                            $unknownCount += $vaccination['count'];
                                        }
                                    }
                                    
                                    $totalVaccinationCount = $vaccinatedCount + $unvaccinatedCount + $unknownCount;
                                    $vaccinatedPercent = $totalVaccinationCount > 0 ? ($vaccinatedCount / $totalVaccinationCount) * 100 : 0;
                                    $unvaccinatedPercent = $totalVaccinationCount > 0 ? ($unvaccinatedCount / $totalVaccinationCount) * 100 : 0;
                                    $unknownPercent = $totalVaccinationCount > 0 ? ($unknownCount / $totalVaccinationCount) * 100 : 0;
                                    
                                    if ($totalVaccinationCount > 0) {
                                        echo "<li>Only <strong>" . number_format($vaccinatedPercent, 1) . "%</strong> of biting animals were confirmed to be vaccinated against rabies.</li>";
                                        
                                        if ($unvaccinatedPercent > 30) {
                                            echo "<li>A concerning " . number_format($unvaccinatedPercent, 1) . "% of animals were confirmed to be unvaccinated, highlighting a significant public health risk.</li>";
                                        }
                                        
                                        if ($unknownPercent > 30) {
                                            echo "<li>The vaccination status was unknown for " . number_format($unknownPercent, 1) . "% of cases, indicating a need for improved animal bite investigation protocols.</li>";
                                        }
                                        
                                        echo "<li>Increased pet vaccination campaigns and enforcement of vaccination requirements are recommended.</li>";
                                    } else {
                                        echo "<li>No vaccination status data available for the selected filters.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Treatment Analysis -->
        <div class="content-card mt-4">
            <div class="content-card-header">
                <h5 class="mb-0"><i class="bi bi-bandaid me-2"></i>Treatment Analysis</h5>
            </div>
            <div class="content-card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <canvas id="treatmentChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Treatment Statistics:</h6>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Treatment</th>
                                        <th>Count</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        $totalCount = $treatmentData['total_count'] > 0 ? $treatmentData['total_count'] : 1;
                                        
                                        echo "<tr>";
                                        echo "<td>Wound Washing</td>";
                                        echo "<td>" . $treatmentData['wash_count'] . "</td>";
                                        echo "<td>" . number_format(($treatmentData['wash_count'] / $totalCount) * 100, 1) . "%</td>";
                                        echo "</tr>";
                                        
                                        echo "<tr>";
                                        echo "<td>Rabies Vaccine</td>";
                                        echo "<td>" . $treatmentData['rabies_count'] . "</td>";
                                        echo "<td>" . number_format(($treatmentData['rabies_count'] / $totalCount) * 100, 1) . "%</td>";
                                        echo "</tr>";
                                        
                                        echo "<tr>";
                                        echo "<td>Anti-Tetanus</td>";
                                        echo "<td>" . $treatmentData['tetanus_count'] . "</td>";
                                        echo "<td>" . number_format(($treatmentData['tetanus_count'] / $totalCount) * 100, 1) . "%</td>";
                                        echo "</tr>";
                                        
                                        echo "<tr>";
                                        echo "<td>Antibiotics</td>";
                                        echo "<td>" . $treatmentData['antibiotics_count'] . "</td>";
                                        echo "<td>" . number_format(($treatmentData['antibiotics_count'] / $totalCount) * 100, 1) . "%</td>";
                                        echo "</tr>";
                                        
                                        echo "<tr>";
                                        echo "<td>Hospital Referral</td>";
                                        echo "<td>" . $treatmentData['referred_count'] . "</td>";
                                        echo "<td>" . number_format(($treatmentData['referred_count'] / $totalCount) * 100, 1) . "%</td>";
                                        echo "</tr>";
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <h6>Key Insights:</h6>
                            <ul>
                                <?php
                                    if ($treatmentData['total_count'] > 0) {
                                        $washPercent = ($treatmentData['wash_count'] / $totalCount) * 100;
                                        $rabiesPercent = ($treatmentData['rabies_count'] / $totalCount) * 100;
                                        $tetanusPercent = ($treatmentData['tetanus_count'] / $totalCount) * 100;
                                        
                                        if ($washPercent < 90) {
                                            echo "<li>Only " . number_format($washPercent, 1) . "% of cases received proper wound washing, which is below the recommended 100% for all bite cases.</li>";
                                        } else {
                                            echo "<li>Proper wound washing was administered in " . number_format($washPercent, 1) . "% of cases, which is excellent.</li>";
                                        }
                                        
                                        if ($rabiesPercent < 70) {
                                            echo "<li>Rabies vaccination rate of " . number_format($rabiesPercent, 1) . "% is concerning and should be improved for all eligible cases.</li>";
                                        } else {
                                            echo "<li>Rabies vaccination was administered in " . number_format($rabiesPercent, 1) . "% of cases, showing good protocol adherence.</li>";
                                        }
                                        
                                        echo "<li>Hospital referrals were made in " . number_format(($treatmentData['referred_count'] / $totalCount) * 100, 1) . "% of cases, typically for more severe bites or complications.</li>";
                                    } else {
                                        echo "<li>No treatment data available for the selected filters.</li>";
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recommendations -->
        <div class="content-card mt-4">
            <div class="content-card-header">
                <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Recommendations & Action Items</h5>
            </div>
            <div class="content-card-body">
                <div class="alert alert-primary">
                    <h6><i class="bi bi-info-circle me-2"></i>Based on this analysis, we recommend the following actions:</h6>
                    <ol>
                        <?php
                            // Output data-driven recommendations
                            foreach ($recommendations as $recommendation) {
                                echo "<li><strong>" . htmlspecialchars($recommendation['title']) . ":</strong> " . 
                                     htmlspecialchars($recommendation['description']) . "</li>";
                            }
                        ?>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">&copy; <?php echo date('Y'); ?> Barangay Health Workers Management System</small>
                </div>
                <div>
                    <small><a href="help.php" class="text-decoration-none">Help & Support</a></small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prepare chart data
        const trendLabels = <?php echo json_encode($trendLabels); ?>;
        const trendCounts = <?php echo json_encode($trendCounts); ?>;
        
        // Animal type data
        const animalLabels = [];
        const animalCounts = [];
        <?php foreach ($animalData as $animal): ?>
        animalLabels.push('<?php echo addslashes($animal['animalType']); ?>');
        animalCounts.push(<?php echo $animal['count']; ?>);
        <?php endforeach; ?>
        
        // Bite category data
        const categoryLabels = [];
        const categoryCounts = [];
        <?php foreach ($categoryData as $category): ?>
        categoryLabels.push('<?php echo addslashes($category['biteType']); ?>');
        categoryCounts.push(<?php echo $category['count']; ?>);
        <?php endforeach; ?>
        
        // Age group data
        const ageLabels = [];
        const ageCounts = [];
        <?php foreach ($ageData as $age): ?>
        ageLabels.push('<?php echo addslashes($age['age_group']); ?>');
        ageCounts.push(<?php echo $age['count']; ?>);
        <?php endforeach; ?>
        
        // Gender data
        const genderLabels = [];
        const genderCounts = [];
        <?php foreach ($genderData as $gender): ?>
        genderLabels.push('<?php echo addslashes($gender['gender']); ?>');
        genderCounts.push(<?php echo $gender['count']; ?>);
        <?php endforeach; ?>
        
        // Barangay data
        const barangayLabels = [];
        const barangayCounts = [];
        <?php 
        $count = 0;
        foreach ($barangayData as $barangay): 
            if ($count < 10) { // Limit to top 10
        ?>
        barangayLabels.push('<?php echo addslashes($barangay['barangay']); ?>');
        barangayCounts.push(<?php echo $barangay['count']; ?>);
        <?php 
            }
            $count++;
        endforeach; 
        ?>
        
        // Ownership data
        const ownershipLabels = [];
        const ownershipCounts = [];
        <?php foreach ($ownershipData as $ownership): ?>
        ownershipLabels.push('<?php echo addslashes($ownership['animalOwnership']); ?>');
        ownershipCounts.push(<?php echo $ownership['count']; ?>);
        <?php endforeach; ?>
        
        // Vaccination data
        const vaccinationLabels = [];
        const vaccinationCounts = [];
        <?php foreach ($vaccinationData as $vaccination): ?>
        vaccinationLabels.push('<?php echo addslashes($vaccination['animalVaccinated']); ?>');
        vaccinationCounts.push(<?php echo $vaccination['count']; ?>);
        <?php endforeach; ?>
        
        // Treatment data
        const treatmentLabels = ['Wound Washing', 'Rabies Vaccine', 'Anti-Tetanus', 'Antibiotics', 'Hospital Referral'];
        const treatmentCounts = [
            <?php echo $treatmentData['wash_count']; ?>,
            <?php echo $treatmentData['rabies_count']; ?>,
            <?php echo $treatmentData['tetanus_count']; ?>,
            <?php echo $treatmentData['antibiotics_count']; ?>,
            <?php echo $treatmentData['referred_count']; ?>
        ];
        
        // Chart colors
        const colors = [
            'rgba(54, 162, 235, 0.7)',
            'rgba(255, 99, 132, 0.7)',
            'rgba(255, 206, 86, 0.7)',
            'rgba(75, 192, 192, 0.7)',
            'rgba(153, 102, 255, 0.7)',
            'rgba(255, 159, 64, 0.7)',
            'rgba(199, 199, 199, 0.7)',
            'rgba(83, 102, 255, 0.7)',
            'rgba(40, 167, 69, 0.7)',
            'rgba(220, 53, 69, 0.7)'
        ];
        
        // Create trend chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Number of Cases',
                    data: trendCounts,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Cases'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Case Trend Over Time'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            }
        });
        
        // Toggle between line and bar chart
        document.getElementById('lineChartBtn').addEventListener('click', function() {
            trendChart.config.type = 'line';
            trendChart.update();
            this.classList.add('active');
            document.getElementById('barChartBtn').classList.remove('active');
        });
        
        document.getElementById('barChartBtn').addEventListener('click', function() {
            trendChart.config.type = 'bar';
            trendChart.update();
            this.classList.add('active');
            document.getElementById('lineChartBtn').classList.remove('active');
        });
        
        // Create animal type chart
        const animalCtx = document.getElementById('animalChart').getContext('2d');
        new Chart(animalCtx, {
            type: 'pie',
            data: {
                labels: animalLabels,
                datasets: [{
                    data: animalCounts,
                    backgroundColor: colors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Cases by Animal Type'
                    },
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        
        // Create bite category chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: categoryLabels,
                datasets: [{
                    label: 'Number of Cases',
                    data: categoryCounts,
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(255, 193, 7, 0.7)',
                        'rgba(220, 53, 69, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Cases'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Cases by Bite Category'
                    }
                }
            }
        });
        
        // Create age group chart
        const ageCtx = document.getElementById('ageChart').getContext('2d');
        new Chart(ageCtx, {
            type: 'bar',
            data: {
                labels: ageLabels,
                datasets: [{
                    label: 'Number of Cases',
                    data: ageCounts,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Cases'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Cases by Age Group'
                    }
                }
            }
        });
        
        // Create gender chart
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        new Chart(genderCtx, {
            type: 'doughnut',
            data: {
                labels: genderLabels,
                datasets: [{
                    data: genderCounts,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Cases by Gender'
                    },
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        
        // Create barangay chart
        const barangayCtx = document.getElementById('barangayChart').getContext('2d');
        new Chart(barangayCtx, {
            type: 'bar',
            data: {
                labels: barangayLabels,
                datasets: [{
                    label: 'Number of Cases',
                    data: barangayCounts,
                    backgroundColor: colors,
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Cases'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Top Barangays by Case Count'
                    }
                }
            }
        });
        
        // Create ownership chart
        const ownershipCtx = document.getElementById('ownershipChart').getContext('2d');
        new Chart(ownershipCtx, {
            type: 'pie',
            data: {
                labels: ownershipLabels,
                datasets: [{
                    data: ownershipCounts,
                    backgroundColor: colors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Cases by Animal Ownership'
                    },
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        
        // Create vaccination chart
        const vaccinationCtx = document.getElementById('vaccinationChart').getContext('2d');
        new Chart(vaccinationCtx, {
            type: 'pie',
            data: {
                labels: vaccinationLabels,
                datasets: [{
                    data: vaccinationCounts,
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(220, 53, 69, 0.7)',
                        'rgba(108, 117, 125, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Cases by Animal Vaccination Status'
                    },
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        
        // Create treatment chart
        const treatmentCtx = document.getElementById('treatmentChart').getContext('2d');
        new Chart(treatmentCtx, {
            type: 'bar',
            data: {
                labels: treatmentLabels,
                datasets: [{
                    label: 'Number of Cases',
                    data: treatmentCounts,
                    backgroundColor: colors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Cases'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Treatment Measures Applied'
                    }
                }
            }
        });
    </script>
</body>
</html>
