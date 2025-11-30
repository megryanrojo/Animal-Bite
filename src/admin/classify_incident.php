<?php
// Enhanced AI-powered classification endpoint
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/conn/conn.php';

function classifyIncidentAjax($pdo, $patientId, $input) {
    $biteLocation = strtolower(trim($input['bite_location'] ?? ''));
    $multiple = !empty($input['multiple_bites']) || (isset($input['multiple_bites']) && $input['multiple_bites'] === 'on');
    $ownership = strtolower($input['animal_ownership'] ?? '');
    $animalVaccinated = strtolower($input['animal_vaccinated'] ?? '');
    $provoked = isset($input['provoked']) && $input['provoked'] !== '' ? $input['provoked'] : null;
    $animalType = strtolower($input['animal_type'] ?? '');
    $animalStatus = strtolower($input['animal_status'] ?? '');

    // Enhanced patient risk factors
    $age = null;
    $ageRisk = 'normal';
    if ($patientId) {
        try {
            $stmt = $pdo->prepare("SELECT dateOfBirth, gender FROM patients WHERE patientId = ?");
            $stmt->execute([$patientId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['dateOfBirth'])) {
                $dob = new DateTime($row['dateOfBirth']);
                $now = new DateTime();
                $age = (int)$now->diff($dob)->y;
                // Age-based risk assessment
                if ($age <= 5) $ageRisk = 'very_high'; // Children very vulnerable
                elseif ($age <= 12) $ageRisk = 'high'; // School age children
                elseif ($age >= 65) $ageRisk = 'moderate'; // Elderly more susceptible
            }
        } catch (PDOException $e) { /* ignore */ }
    }

    // Comprehensive location risk assessment
    $highRiskLocations = [
        'face', 'head', 'neck', 'scalp', 'eyes', 'mouth', 'lips', 'nose', 'ear',
        'hands', 'fingers', 'feet', 'toes', 'genitals', 'perineum'
    ];
    $moderateRiskLocations = [
        'arms', 'legs', 'torso', 'chest', 'back', 'shoulders'
    ];

    $locationRisk = 'low';
    foreach ($highRiskLocations as $frag) {
        if (strpos($biteLocation, $frag) !== false) {
            $locationRisk = 'high';
            break;
        }
    }
    if ($locationRisk === 'low') {
        foreach ($moderateRiskLocations as $frag) {
            if (strpos($biteLocation, $frag) !== false) {
                $locationRisk = 'moderate';
                break;
            }
        }
    }

    // Animal type risk assessment
    $highRiskAnimals = ['dog', 'cat', 'monkey', 'bat', 'raccoon', 'skunk'];
    $moderateRiskAnimals = ['pig', 'cow', 'horse', 'sheep', 'goat'];

    $animalRisk = 'low';
    foreach ($highRiskAnimals as $animal) {
        if (strpos($animalType, $animal) !== false) {
            $animalRisk = 'high';
            break;
        }
    }
    if ($animalRisk === 'low') {
        foreach ($moderateRiskAnimals as $animal) {
            if (strpos($animalType, $animal) !== false) {
                $animalRisk = 'moderate';
                break;
            }
        }
    }

    // Enhanced scoring system with weighted factors
    $score = 0;
    $riskFactors = [];

    // Location risk (highest weight)
    if ($locationRisk === 'high') {
        $score += 4;
        $riskFactors[] = "High-risk location";
    } elseif ($locationRisk === 'moderate') {
        $score += 2;
        $riskFactors[] = "Moderate-risk location";
    }

    // Multiple bites (high weight)
    if ($multiple) {
        $score += 3;
        $riskFactors[] = "Multiple bites";
    }

    // Animal ownership and vaccination (high weight)
    if ($ownership === 'stray' || $ownership === 'unknown') {
        $score += 3;
        $riskFactors[] = "Stray/unknown animal";
    }
    if ($animalVaccinated === 'no' || $animalVaccinated === 'unknown' || $animalVaccinated === '') {
        $score += 2;
        $riskFactors[] = "Unvaccinated animal";
    }

    // Animal type and status
    if ($animalRisk === 'high') {
        $score += 2;
        $riskFactors[] = "High-risk animal type";
    } elseif ($animalRisk === 'moderate') {
        $score += 1;
        $riskFactors[] = "Moderate-risk animal type";
    }

    if ($animalStatus === 'dead') {
        $score += 1;
        $riskFactors[] = "Dead animal (potential rabies)";
    }

    // Provocation and patient factors
    if ($provoked === 'no' || $provoked === '0' || $provoked === '') {
        $score += 1;
        $riskFactors[] = "Unprovoked bite";
    }

    // Age-based risk
    if ($ageRisk === 'very_high') {
        $score += 3;
        $riskFactors[] = "Very young patient (≤5 years)";
    } elseif ($ageRisk === 'high') {
        $score += 2;
        $riskFactors[] = "Young patient (6-12 years)";
    } elseif ($ageRisk === 'moderate') {
        $score += 1;
        $riskFactors[] = "Elderly patient (≥65 years)";
    }

    // Determine final category with improved thresholds
    if ($score >= 8) {
        $category = 'Category III';
        $severity = 'Critical';
        $recommendation = 'Immediate PEP required. Hospital referral strongly recommended.';
    } elseif ($score >= 5) {
        $category = 'Category II';
        $severity = 'High';
        $recommendation = 'PEP recommended. Close monitoring advised.';
    } else {
        $category = 'Category I';
        $severity = 'Low';
        $recommendation = 'PEP may not be required. Clinical assessment advised.';
    }

    return [
        'biteType' => $category,
        'severity' => $severity,
        'score' => $score,
        'maxScore' => 15,
        'riskFactors' => $riskFactors,
        'recommendation' => $recommendation,
        'rationale' => "Risk Score: {$score}/15 | Factors: " . implode(', ', $riskFactors)
    ];
}

try {
    $patientId = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : null;
    $input = $_POST;

    $result = classifyIncidentAjax($pdo, $patientId, $input);
    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
