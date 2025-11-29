<?php
/**
 * Vaccination Scheduling Helper Functions
 * Handles dose intervals, workload calculation, validation, and auto-lock logic
 */

// Get PEP dose interval from bite date (max 3 doses)
function getPEPDoseInterval($doseNumber) {
    $intervals = [
        1 => 0,
        2 => 7,
        3 => 21
    ];
    return isset($intervals[$doseNumber]) ? $intervals[$doseNumber] : null;
}

// Calculate suggested next dose date based on PEP schedule
function calculateSuggestedDoseDate($biteDate, $doseNumber) {
    if (!$biteDate || !$doseNumber) return null;
    
    $interval = getPEPDoseInterval($doseNumber);
    if ($interval === null) return null;
    
    $date = new DateTime($biteDate);
    $date->modify("+{$interval} days");
    return $date->format('Y-m-d');
}

// Calculate workload (number of doses scheduled on a given date)
function getWorkloadLevel($pdo, $date) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as dose_count FROM vaccination_records WHERE DATE(dateGiven) = ?");
        $stmt->execute([$date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)$result['dose_count'];
        
        if ($count >= 0 && $count <= 10) {
            $level = 'LOW';
            $color = 'success';
        } elseif ($count >= 11 && $count <= 25) {
            $level = 'MODERATE';
            $color = 'warning';
        } elseif ($count >= 26 && $count <= 40) {
            $level = 'HIGH';
            $color = 'danger';
        } else {
            $level = 'VERY HIGH';
            $color = 'dark';
        }
        
        return [
            'count' => $count,
            'level' => $level,
            'color' => $color,
            'message' => $count . ' doses scheduled (' . $level . ' workload)'
        ];
    } catch (PDOException $e) {
        return [
            'count' => 0,
            'level' => 'UNKNOWN',
            'color' => 'secondary',
            'message' => 'Unable to calculate workload'
        ];
    }
}

// Check if category requires PEP vaccination
function requiresPEP($pdo, $reportId) {
    try {
        $stmt = $pdo->prepare("SELECT r.category FROM reports r WHERE r.reportId = ?");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$report) return false;
        
        $category = strtoupper(trim($report['category']));
        return ($category !== 'CATEGORY I' && $category !== 'CAT I');
    } catch (PDOException $e) {
        return false;
    }
}

// Get existing dose numbers for a report
function getExistingDoseNumbers($pdo, $reportId) {
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT doseNumber FROM vaccination_records WHERE reportId = ? AND exposureType = 'PEP' ORDER BY doseNumber ASC");
        $stmt->execute([$reportId]);
        $doses = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $doses);
    } catch (PDOException $e) {
        return [];
    }
}

// Validate dose sequence
function validateDoseSequence($pdo, $reportId, $doseNumber, $action = 'add') {
    $doseNumber = (int)$doseNumber;
    
    if ($doseNumber < 1 || $doseNumber > 3) {
        return ['valid' => false, 'message' => 'Dose number must be between 1 and 3'];
    }
    
    $existingDoses = getExistingDoseNumbers($pdo, $reportId);
    
    if ($action === 'add') {
        if ($doseNumber > 1 && !in_array($doseNumber - 1, $existingDoses)) {
            $msg = "Cannot add Dose $doseNumber: Dose " . ($doseNumber - 1) . " must be completed first";
            return ['valid' => false, 'message' => $msg];
        }
        if (in_array($doseNumber, $existingDoses)) {
            return ['valid' => false, 'message' => "Dose $doseNumber already exists for this report"];
        }
    }
    
    return ['valid' => true, 'message' => 'Valid dose sequence'];
}

// Check if a vaccination date is in the past
function isPastDate($dateGiven) {
    if (!$dateGiven) return false;
    
    $given = new DateTime($dateGiven);
    $now = new DateTime();
    $now->setTime(0, 0, 0);
    
    return $given < $now;
}

// Check if record should be locked
function isRecordLocked($pdo, $reportId) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as dose_count FROM vaccination_records WHERE reportId = ? AND exposureType = 'PEP'");
        $stmt->execute([$reportId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)$result['dose_count'];
        
        return $count >= 3;
    } catch (PDOException $e) {
        return false;
    }
}

// Get lock status for display
function getLockStatus($pdo, $reportId) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as dose_count FROM vaccination_records WHERE reportId = ? AND exposureType = 'PEP'");
        $stmt->execute([$reportId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)$result['dose_count'];
        
        return [
            'completed_doses' => $count,
            'is_locked' => $count >= 3,
            'message' => $count >= 3 ? 'All 3 doses completed - Record is locked' : "Progress: $count/3 doses"
        ];
    } catch (PDOException $e) {
        return [
            'completed_doses' => 0,
            'is_locked' => false,
            'message' => 'Unable to determine status'
        ];
    }
}

// Get next recommended dose number
function getNextDoseNumber($pdo, $reportId) {
    $existingDoses = getExistingDoseNumbers($pdo, $reportId);
    
    for ($i = 1; $i <= 3; $i++) {
        if (!in_array($i, $existingDoses)) {
            return $i;
        }
    }
    
    return null;
}

// Format vaccination record display with workload indicator
function formatVaccinationRow($pdo, $vaccination) {
    $row = [
        'id' => $vaccination['vaccinationId'],
        'dose' => $vaccination['doseNumber'],
        'date' => $vaccination['dateGiven'],
        'name' => $vaccination['vaccineName'] ?? 'N/A',
        'by' => $vaccination['administeredBy'] ?? 'N/A',
        'remarks' => $vaccination['remarks'] ?? '',
        'can_edit' => !isPastDate($vaccination['dateGiven']),
    ];
    
    if ($vaccination['dateGiven']) {
        $row['workload'] = getWorkloadLevel($pdo, $vaccination['dateGiven']);
    } else {
        $row['workload'] = ['count' => 0, 'level' => 'N/A', 'color' => 'secondary', 'message' => 'No date set'];
    }
    
    return $row;
}

// Get all validation messages for adding a dose
function getAddDoseValidations($pdo, $reportId, $doseNumber, $dateGiven) {
    $messages = [];
    
    if (!requiresPEP($pdo, $reportId)) {
        $messages[] = [
            'type' => 'danger',
            'text' => 'This report category does not require PEP vaccination'
        ];
    }
    
    $seqCheck = validateDoseSequence($pdo, $reportId, $doseNumber, 'add');
    if (!$seqCheck['valid']) {
        $messages[] = [
            'type' => 'warning',
            'text' => $seqCheck['message']
        ];
    }
    
    if ($dateGiven) {
        $workload = getWorkloadLevel($pdo, $dateGiven);
        if ($workload['level'] !== 'LOW' && $workload['level'] !== 'UNKNOWN') {
            $messages[] = [
                'type' => 'info',
                'text' => 'Workload: ' . $workload['message'] . ' (you can still schedule here)'
            ];
        }
    }
    
    if ($dateGiven && isPastDate($dateGiven)) {
        $messages[] = [
            'type' => 'info',
            'text' => 'This dose date is in the past (already administered)'
        ];
    }
    
    return $messages;
}

// Get all validation messages for editing a dose
function getEditDoseValidations($pdo, $reportId, $doseNumber, $vaccinationId, $newDateGiven) {
    $messages = [];
    
    if (isRecordLocked($pdo, $reportId)) {
        $messages[] = [
            'type' => 'danger',
            'text' => 'This record is locked (all 3 doses completed). Cannot edit.'
        ];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT dateGiven FROM vaccination_records WHERE vaccinationId = ?");
        $stmt->execute([$vaccinationId]);
        $vax = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vax && isPastDate($vax['dateGiven'])) {
            $messages[] = [
                'type' => 'danger',
                'text' => 'Cannot edit: This dose was already administered in the past'
            ];
        }
    } catch (PDOException $e) {
    }
    
    if ($newDateGiven) {
        $workload = getWorkloadLevel($pdo, $newDateGiven);
        if ($workload['level'] !== 'LOW' && $workload['level'] !== 'UNKNOWN') {
            $messages[] = [
                'type' => 'info',
                'text' => 'New date workload: ' . $workload['message']
            ];
        }
    }
    
    return $messages;
}

?>
