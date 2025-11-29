<?php
// Lightweight endpoint to return automated classification JSON
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/conn/conn.php';

function classifyIncidentAjax($pdo, $patientId, $input) {
    $biteLocation = strtolower(trim($input['bite_location'] ?? ''));
    $multiple = !empty($input['multiple_bites']) || (isset($input['multiple_bites']) && $input['multiple_bites'] === 'on');
    $ownership = strtolower($input['animal_ownership'] ?? '');
    $animalVaccinated = strtolower($input['animal_vaccinated'] ?? '');
    $provoked = isset($input['provoked']) && $input['provoked'] !== '' ? $input['provoked'] : null;

    // Determine patient age (if available)
    $age = null;
    if ($patientId) {
        try {
            $stmt = $pdo->prepare("SELECT dateOfBirth FROM patients WHERE patientId = ?");
            $stmt->execute([$patientId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['dateOfBirth'])) {
                $dob = new DateTime($row['dateOfBirth']);
                $now = new DateTime();
                $age = (int)$now->diff($dob)->y;
            }
        } catch (PDOException $e) { /* ignore */ }
    }

    $highRiskLocations = ['face','head','neck','scalp','eyes','mouth','lips','nose','ear','hands','fingers'];
    $isHighLocation = false;
    foreach ($highRiskLocations as $frag) {
        if ($frag !== '' && strpos($biteLocation, $frag) !== false) { $isHighLocation = true; break; }
    }

    $score = 0;
    if ($isHighLocation) $score += 3;
    if ($multiple) $score += 3;
    if ($ownership === 'stray' || $ownership === 'unknown') $score += 2;
    if ($animalVaccinated === 'no' || $animalVaccinated === 'unknown' || $animalVaccinated === '') $score += 2;
    if ($provoked === '0' || $provoked === 'no' || $provoked === '') $score += 1;
    if ($age !== null && $age <= 5) $score += 2;

    if ($score >= 6) {
        $category = 'Category III';
        $severity = 'High';
    } elseif ($score >= 3) {
        $category = 'Category II';
        $severity = 'Moderate';
    } else {
        $category = 'Category I';
        $severity = 'Low';
    }

    $rationale = "Auto-classified as $category (Severity: $severity) — score=$score;";
    $rationale .= $isHighLocation ? " location_high;" : " location_low;";
    $rationale .= $multiple ? " multiple_bites;" : " single_bite;";
    $rationale .= " ownership={$ownership}; vaccinated={$animalVaccinated};";
    if ($age !== null) $rationale .= " age={$age};";

    return [ 'biteType' => $category, 'severity' => $severity, 'rationale' => $rationale, 'score' => $score ];
}

$patientId = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : null;
$input = $_POST;

$result = classifyIncidentAjax($pdo, $patientId, $input);
echo json_encode(['success' => true, 'data' => $result]);
exit;
