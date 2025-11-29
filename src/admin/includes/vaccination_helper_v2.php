<?php
/**
 * ====================================================================
 * ENHANCED VACCINATION SCHEDULING & DISPLAY HELPER
 * ====================================================================
 * 
 * Centralized functions for:
 * - PEP (Post-Exposure Prophylaxis) scheduling & validation
 * - PrEP (Pre-Exposure Prophylaxis) scheduling & validation
 * - Dose status tracking (Completed, Upcoming, Missed, Overdue, NotScheduled)
 * - Workload awareness (no hard limits, informational only)
 * - Category-based requirements enforcement
 * - Sequential dose validation
 * - Auto-lock on final dose completion
 * 
 * SCHEDULES:
 * PEP: Days 0, 3, 7 (3 doses max) - Post-bite emergency
 * PrEP: Custom intervals - Preventive vaccination
 * ====================================================================
 */

// ====================================================================
// SCHEDULE DEFINITIONS
// ====================================================================

/**
 * Get PEP dose interval from bite date
 * Standard WHO rabies PEP: Days 0, 3, 7 (3 doses total)
 * 
 * @param int $doseNumber The dose number (1-3)
 * @return int|null Days offset from bite date, or null if invalid
 */
function getPEPDoseInterval($doseNumber) {
    $intervals = [
        1 => 0,    // Day 0 - immediately
        2 => 3,    // Day 3
        3 => 7     // Day 7
    ];
    return isset($intervals[$doseNumber]) ? $intervals[$doseNumber] : null;
}

/**
 * Get PrEP dose interval (customizable preventive schedule)
 * Default: Annual single dose or custom interval
 * 
 * @param int $doseNumber The dose number
 * @return int|null Days offset, or null if invalid
 */
function getPrEPDoseInterval($doseNumber) {
    // PrEP typically doesn't follow strict intervals like PEP
    // Can be annual renewal or custom based on health worker discretion
    $intervals = [
        1 => 0,    // First dose can be anytime
        2 => 365,  // Next renewal at 1 year
        3 => 730,  // 2 years
        4 => 1095  // 3 years
    ];
    return isset($intervals[$doseNumber]) ? $intervals[$doseNumber] : null;
}

// ====================================================================
// DOSE CALCULATION & SCHEDULING
// ====================================================================

/**
 * Calculate suggested next dose date based on vaccine type
 * 
 * @param string $baseDate The bite date (PEP) or last dose date (PrEP)
 * @param int $doseNumber Current dose number
 * @param string $exposureType 'PEP' or 'PrEP'
 * @return string|null Suggested date in YYYY-MM-DD format
 */
function calculateSuggestedDoseDate($baseDate, $doseNumber, $exposureType = 'PEP') {
    if (!$baseDate || !$doseNumber) return null;
    
    if ($exposureType === 'PEP') {
        $interval = getPEPDoseInterval($doseNumber);
    } else {
        $interval = getPrEPDoseInterval($doseNumber);
    }
    
    if ($interval === null) return null;
    
    $date = new DateTime($baseDate);
    $date->modify("+{$interval} days");
    return $date->format('Y-m-d');
}

/**
 * Get next dose number for a report/patient
 * Checks for existing doses and returns next sequential number
 * 
 * @param PDO $pdo Database connection
 * @param int $reportId Report ID (for PEP)
 * @param string $exposureType 'PEP' or 'PrEP'
 * @param int $maxDoses Maximum doses allowed (3 for PEP, unlimited for PrEP)
 * @return int|null Next dose number, or null if complete
 */
function getNextDoseNumber($pdo, $reportId = null, $exposureType = 'PEP', $maxDoses = 3) {
    try {
        if ($exposureType === 'PEP' && $reportId) {
            $stmt = $pdo->prepare("
                SELECT DISTINCT doseNumber 
                FROM vaccination_records 
                WHERE reportId = ? AND exposureType = 'PEP' 
                ORDER BY doseNumber ASC
            ");
            $stmt->execute([$reportId]);
        } elseif ($exposureType === 'PrEP' && $reportId) {
            $stmt = $pdo->prepare("
                SELECT DISTINCT doseNumber 
                FROM vaccination_records 
                WHERE patientId = ? AND reportId IS NULL AND exposureType = 'PrEP' 
                ORDER BY doseNumber ASC
            ");
            $stmt->execute([$reportId]);
        } else {
            return null;
        }
        
        $doses = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $doses = array_map('intval', $doses);
        
        for ($i = 1; $i <= $maxDoses; $i++) {
            if (!in_array($i, $doses)) {
                return $i;
            }
        }
        
        return null; // All doses completed
    } catch (PDOException $e) {
        return null;
    }
}

// ====================================================================
// DOSE STATUS & TRACKING
// ====================================================================

/**
 * Calculate dose status based on date and completion
 * 
 * @param string|null $dateGiven The date the dose was given
 * @param string|null $nextScheduledDate When the dose should be given
 * @return string Status: 'Completed', 'Upcoming', 'Missed', 'Overdue', 'NotScheduled'
 */
function calculateDoseStatus($dateGiven, $nextScheduledDate) {
    $now = new DateTime();
    
    // Dose already given
    if ($dateGiven) {
        return 'Completed';
    }
    
    // No scheduled date
    if (!$nextScheduledDate) {
        return 'NotScheduled';
    }
    
    $scheduled = new DateTime($nextScheduledDate);
    
    // Future date
    if ($scheduled > $now) {
        return 'Upcoming';
    }
    
    // Past date without completion
    $interval = new DateInterval('P7D'); // 7-day grace period
    $gracePeriod = clone $scheduled;
    $gracePeriod->add($interval);
    
    if ($now <= $gracePeriod) {
        return 'Missed';
    }
    
    return 'Overdue';
}

/**
 * Get dose status with styling info for display
 * 
 * @param string $status The dose status
 * @return array Associative array with status, color, icon, label
 */
function getDoseStatusInfo($status) {
    $statusMap = [
        'Completed' => [
            'color' => 'success',
            'icon' => 'check-circle-fill',
            'label' => 'Completed',
            'badge_class' => 'bg-success'
        ],
        'Upcoming' => [
            'color' => 'info',
            'icon' => 'calendar-event',
            'label' => 'Upcoming',
            'badge_class' => 'bg-info'
        ],
        'Missed' => [
            'color' => 'warning',
            'icon' => 'exclamation-triangle',
            'label' => 'Missed',
            'badge_class' => 'bg-warning'
        ],
        'Overdue' => [
            'color' => 'danger',
            'icon' => 'exclamation-circle-fill',
            'label' => 'OVERDUE',
            'badge_class' => 'bg-danger'
        ],
        'NotScheduled' => [
            'color' => 'secondary',
            'icon' => 'question-circle',
            'label' => 'Not Scheduled',
            'badge_class' => 'bg-secondary'
        ]
    ];
    
    return isset($statusMap[$status]) ? $statusMap[$status] : $statusMap['NotScheduled'];
}

// ====================================================================
// WORKLOAD CALCULATION & DISPLAY
// ====================================================================

/**
 * Calculate workload (doses scheduled) on a given date
 * Returns informational data - never blocks scheduling
 * 
 * @param PDO $pdo Database connection
 * @param string|null $date The date to check (YYYY-MM-DD)
 * @param string $exposureType 'PEP', 'PrEP', or 'all'
 * @return array Workload data with count, level, color, message
 */
function getWorkloadLevel($pdo, $date, $exposureType = 'all') {
    try {
        if (!$date) {
            return [
                'count' => 0,
                'level' => 'N/A',
                'color' => 'secondary',
                'message' => 'No date specified',
                'badge_css' => 'bg-secondary'
            ];
        }
        
        $query = "SELECT COUNT(*) as dose_count FROM vaccination_records WHERE DATE(dateGiven) = ?";
        $params = [$date];
        
        if ($exposureType !== 'all') {
            $query .= " AND exposureType = ?";
            $params[] = $exposureType;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)($result['dose_count'] ?? 0);
        
        // Workload classification
        if ($count <= 10) {
            $level = 'LOW';
            $color = 'success';
            $badge = 'bg-success';
        } elseif ($count <= 25) {
            $level = 'MODERATE';
            $color = 'warning';
            $badge = 'bg-warning';
        } elseif ($count <= 40) {
            $level = 'HIGH';
            $color = 'danger';
            $badge = 'bg-danger';
        } else {
            $level = 'VERY HIGH';
            $color = 'dark';
            $badge = 'bg-dark';
        }
        
        return [
            'count' => $count,
            'level' => $level,
            'color' => $color,
            'badge_css' => $badge,
            'message' => $count . ' doses scheduled (' . $level . ' workload)'
        ];
    } catch (PDOException $e) {
        return [
            'count' => 0,
            'level' => 'ERROR',
            'color' => 'secondary',
            'badge_css' => 'bg-secondary',
            'message' => 'Unable to calculate workload'
        ];
    }
}

// ====================================================================
// VALIDATION & BUSINESS RULES
// ====================================================================

/**
 * Check if report category requires PEP vaccination
 * Category I: No PEP required
 * Categories II & III: PEP required
 * 
 * @param PDO $pdo Database connection
 * @param int $reportId Report ID
 * @return bool True if PEP is required
 */
function requiresPEP($pdo, $reportId) {
    try {
        $stmt = $pdo->prepare("
            SELECT biteType 
            FROM reports 
            WHERE reportId = ?
        ");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$report) return false;
        
        $biteType = strtoupper(trim($report['biteType']));
        
        // Category I: No PEP required
        return !(stripos($biteType, 'I') !== false && stripos($biteType, 'II') === false);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Validate dose sequence rules
 * - Cannot skip doses (must add 1, then 2, then 3, etc.)
 * - Cannot add duplicate dose
 * - Cannot exceed maximum doses
 * 
 * @param PDO $pdo Database connection
 * @param int $reportId Report ID
 * @param int $doseNumber Dose number to validate
 * @param string $action 'add' or 'edit'
 * @param int $maxDoses Maximum allowed doses
 * @return array ['valid' => bool, 'message' => string]
 */
function validateDoseSequence($pdo, $reportId, $doseNumber, $action = 'add', $maxDoses = 3) {
    $doseNumber = (int)$doseNumber;
    
    if ($doseNumber < 1 || $doseNumber > $maxDoses) {
        return [
            'valid' => false,
            'message' => "Dose number must be between 1 and $maxDoses"
        ];
    }
    
    try {
        // Get existing doses
        $stmt = $pdo->prepare("
            SELECT DISTINCT doseNumber 
            FROM vaccination_records 
            WHERE reportId = ? AND exposureType = 'PEP' 
            ORDER BY doseNumber ASC
        ");
        $stmt->execute([$reportId]);
        $existingDoses = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        
        if ($action === 'add') {
            // Check for sequencing: Dose 2 requires Dose 1, etc.
            if ($doseNumber > 1 && !in_array($doseNumber - 1, $existingDoses)) {
                return [
                    'valid' => false,
                    'message' => "Cannot add Dose $doseNumber: Dose " . ($doseNumber - 1) . " must be completed first"
                ];
            }
            
            // Check for duplicates
            if (in_array($doseNumber, $existingDoses)) {
                return [
                    'valid' => false,
                    'message' => "Dose $doseNumber already exists for this report"
                ];
            }
        }
        
        return ['valid' => true, 'message' => 'Valid dose sequence'];
    } catch (PDOException $e) {
        return ['valid' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Check if a date is in the past (dose already administered)
 * 
 * @param string|null $dateGiven The date to check
 * @return bool True if date is in the past
 */
function isPastDate($dateGiven) {
    if (!$dateGiven) return false;
    
    $given = new DateTime($dateGiven);
    $now = new DateTime();
    $now->setTime(0, 0, 0);
    
    return $given < $now;
}

/**
 * Check if vaccination record is locked
 * Locked when all doses completed for PEP
 * 
 * @param PDO $pdo Database connection
 * @param int $reportId Report ID
 * @param int $maxDoses Maximum doses for completion
 * @return bool True if locked
 */
function isRecordLocked($pdo, $reportId, $maxDoses = 3) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as dose_count 
            FROM vaccination_records 
            WHERE reportId = ? AND exposureType = 'PEP'
        ");
        $stmt->execute([$reportId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)($result['dose_count'] ?? 0);
        
        return $count >= $maxDoses;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get lock status for display in UI
 * 
 * @param PDO $pdo Database connection
 * @param int $reportId Report ID
 * @param int $maxDoses Maximum doses
 * @return array Lock status data
 */
function getLockStatus($pdo, $reportId, $maxDoses = 3) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as dose_count 
            FROM vaccination_records 
            WHERE reportId = ? AND exposureType = 'PEP'
        ");
        $stmt->execute([$reportId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)($result['dose_count'] ?? 0);
        
        $isLocked = $count >= $maxDoses;
        $message = $isLocked 
            ? "All $maxDoses doses completed - Record is locked"
            : "Progress: $count/$maxDoses doses";
        
        return [
            'completed_doses' => $count,
            'is_locked' => $isLocked,
            'message' => $message,
            'remaining' => max(0, $maxDoses - $count)
        ];
    } catch (PDOException $e) {
        return [
            'completed_doses' => 0,
            'is_locked' => false,
            'message' => 'Unable to determine status',
            'remaining' => $maxDoses
        ];
    }
}

// ====================================================================
// BATCH OPERATIONS & DISPLAY
// ====================================================================

/**
 * Get all PEP vaccinations for a report
 * 
 * @param PDO $pdo Database connection
 * @param int $reportId Report ID
 * @return array List of PEP vaccination records
 */
function getPEPVaccinations($pdo, $reportId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * 
            FROM vaccination_records 
            WHERE reportId = ? AND exposureType = 'PEP'
            ORDER BY doseNumber ASC, dateGiven DESC
        ");
        $stmt->execute([$reportId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get all PrEP vaccinations for a patient
 * 
 * @param PDO $pdo Database connection
 * @param int $patientId Patient ID
 * @return array List of PrEP vaccination records
 */
function getPrEPVaccinations($pdo, $patientId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * 
            FROM vaccination_records 
            WHERE patientId = ? AND reportId IS NULL AND exposureType = 'PrEP'
            ORDER BY dateGiven DESC, doseNumber DESC
        ");
        $stmt->execute([$patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Format vaccination record for display with enhanced data
 * 
 * @param PDO $pdo Database connection
 * @param array $vaccination Vaccination record
 * @param string $exposureType 'PEP' or 'PrEP'
 * @return array Formatted display data
 */
function formatVaccinationRow($pdo, $vaccination, $exposureType = 'PEP') {
    $dateGiven = $vaccination['dateGiven'] ?? null;
    $nextScheduledDate = $vaccination['nextScheduledDate'] ?? null;
    $status = calculateDoseStatus($dateGiven, $nextScheduledDate);
    $statusInfo = getDoseStatusInfo($status);
    
    $row = [
        'id' => $vaccination['vaccinationId'],
        'dose' => $vaccination['doseNumber'],
        'dateGiven' => $dateGiven,
        'nextScheduledDate' => $nextScheduledDate,
        'vaccineName' => $vaccination['vaccineName'] ?? 'Not specified',
        'batchNumber' => $vaccination['batchNumber'] ?? '-',
        'administeredBy' => $vaccination['administeredBy'] ?? 'Not specified',
        'remarks' => $vaccination['remarks'] ?? '',
        'status' => $status,
        'statusInfo' => $statusInfo,
        'canEdit' => !isPastDate($dateGiven) && !$vaccination['isLocked'],
        'canDelete' => !$vaccination['isLocked'],
        'isLocked' => $vaccination['isLocked'] ?? false
    ];
    
    // Add workload info if date exists
    if ($dateGiven) {
        $row['workload'] = getWorkloadLevel($pdo, $dateGiven, $exposureType);
    } else {
        $row['workload'] = [
            'count' => 0,
            'level' => 'N/A',
            'color' => 'secondary',
            'badge_css' => 'bg-secondary',
            'message' => 'No date set'
        ];
    }
    
    return $row;
}

/**
 * Get all validation messages for adding a dose
 * 
 * @param PDO $pdo Database connection
 * @param int $reportId Report ID
 * @param int $doseNumber Dose number
 * @param string|null $dateGiven Date given
 * @return array Array of validation messages
 */
function getAddDoseValidations($pdo, $reportId, $doseNumber, $dateGiven) {
    $messages = [];
    
    // Check if PEP is required
    if (!requiresPEP($pdo, $reportId)) {
        $messages[] = [
            'type' => 'danger',
            'icon' => 'exclamation-circle',
            'text' => 'This report category does not require PEP vaccination'
        ];
    }
    
    // Check dose sequence
    $seqCheck = validateDoseSequence($pdo, $reportId, $doseNumber, 'add');
    if (!$seqCheck['valid']) {
        $messages[] = [
            'type' => 'warning',
            'icon' => 'exclamation-triangle',
            'text' => $seqCheck['message']
        ];
    }
    
    // Check workload
    if ($dateGiven) {
        $workload = getWorkloadLevel($pdo, $dateGiven, 'PEP');
        if ($workload['level'] !== 'LOW' && $workload['level'] !== 'N/A') {
            $messages[] = [
                'type' => 'info',
                'icon' => 'info-circle',
                'text' => '<strong>Workload Alert:</strong> ' . $workload['message'] . ' (you can still schedule here)'
            ];
        }
    }
    
    // Check if past date
    if ($dateGiven && isPastDate($dateGiven)) {
        $messages[] = [
            'type' => 'info',
            'icon' => 'calendar-check',
            'text' => 'This dose date is in the past (already administered)'
        ];
    }
    
    return $messages;
}

/**
 * Get all validation messages for editing a dose
 * 
 * @param PDO $pdo Database connection
 * @param int $reportId Report ID
 * @param int $doseNumber Dose number
 * @param int $vaccinationId Vaccination ID
 * @param string|null $newDateGiven New date given
 * @return array Array of validation messages
 */
function getEditDoseValidations($pdo, $reportId, $doseNumber, $vaccinationId, $newDateGiven) {
    $messages = [];
    
    // Check if record is locked
    if (isRecordLocked($pdo, $reportId)) {
        $messages[] = [
            'type' => 'danger',
            'icon' => 'lock-fill',
            'text' => 'This record is locked (all 3 doses completed). Cannot edit.'
        ];
    }
    
    // Check if past dose
    try {
        $stmt = $pdo->prepare("SELECT dateGiven FROM vaccination_records WHERE vaccinationId = ?");
        $stmt->execute([$vaccinationId]);
        $vax = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vax && isPastDate($vax['dateGiven'])) {
            $messages[] = [
                'type' => 'danger',
                'icon' => 'clock-history',
                'text' => 'Cannot edit: This dose was already administered in the past'
            ];
        }
    } catch (PDOException $e) {
        // Ignore database errors
    }
    
    // Check new date workload
    if ($newDateGiven) {
        $workload = getWorkloadLevel($pdo, $newDateGiven, 'PEP');
        if ($workload['level'] !== 'LOW' && $workload['level'] !== 'N/A') {
            $messages[] = [
                'type' => 'info',
                'icon' => 'info-circle',
                'text' => '<strong>New Date Workload:</strong> ' . $workload['message']
            ];
        }
    }
    
    return $messages;
}

/**
 * Separate and organize vaccinations for display
 * Returns organized structure with PEP and PrEP separated
 * 
 * @param PDO $pdo Database connection
 * @param int $patientId Patient ID
 * @param int|null $reportId Report ID (for PEP filtering)
 * @return array Organized vaccination data with PEP and PrEP sections
 */
function getOrganizedVaccinations($pdo, $patientId, $reportId = null) {
    $result = [
        'pep' => [
            'vaccinations' => [],
            'completed' => 0,
            'upcoming' => 0,
            'overdue' => 0,
            'lockStatus' => null
        ],
        'prep' => [
            'vaccinations' => [],
            'completed' => 0,
            'upcoming' => 0,
            'overdue' => 0
        ]
    ];
    
    // Get PEP vaccinations (linked to report)
    if ($reportId) {
        $pepVax = getPEPVaccinations($pdo, $reportId);
        foreach ($pepVax as $vax) {
            $formatted = formatVaccinationRow($pdo, $vax, 'PEP');
            $result['pep']['vaccinations'][] = $formatted;
            
            // Count statuses
            switch ($formatted['status']) {
                case 'Completed':
                    $result['pep']['completed']++;
                    break;
                case 'Upcoming':
                    $result['pep']['upcoming']++;
                    break;
                case 'Overdue':
                case 'Missed':
                    $result['pep']['overdue']++;
                    break;
            }
        }
        $result['pep']['lockStatus'] = getLockStatus($pdo, $reportId);
    }
    
    // Get PrEP vaccinations (patient-level)
    $prepVax = getPrEPVaccinations($pdo, $patientId);
    foreach ($prepVax as $vax) {
        $formatted = formatVaccinationRow($pdo, $vax, 'PrEP');
        $result['prep']['vaccinations'][] = $formatted;
        
        // Count statuses
        switch ($formatted['status']) {
            case 'Completed':
                $result['prep']['completed']++;
                break;
            case 'Upcoming':
                $result['prep']['upcoming']++;
                break;
            case 'Overdue':
            case 'Missed':
                $result['prep']['overdue']++;
                break;
        }
    }
    
    return $result;
}

?>
