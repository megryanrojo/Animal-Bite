<?php
require '../conn/conn.php';

if (
  isset($_POST['firstName'], $_POST['lastName'], $_POST['birthDate'], $_POST['sex'], $_POST['address'], $_POST['incidentDate'], $_POST['biteLocation'])
) {
  $firstName = $_POST['firstName'];
  $lastName = $_POST['lastName'];
  $birthDate = $_POST['birthDate'];
  $sex = $_POST['sex'];
  $address = $_POST['address'];
  $contactNumber = $_POST['contactNumber'] ?? null;
  $email = $_POST['email'] ?? null;
  $incidentDate = $_POST['incidentDate'];
  $biteLocation = $_POST['biteLocation'];
  $notes = $_POST['notes'] ?? null;

  $biteImage = null;
  if (isset($_FILES['biteImage']) && $_FILES['biteImage']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/';
    $ext = pathinfo($_FILES['biteImage']['name'], PATHINFO_EXTENSION);
    $newFilename = uniqid('bite_', true) . '.' . $ext;
    $uploadPath = $uploadDir . $newFilename;

    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0777, true);
    }

    if (move_uploaded_file($_FILES['biteImage']['tmp_name'], $uploadPath)) {
      $biteImage = $newFilename;
    }
  }

  $stmt = $pdo->prepare("INSERT INTO reports (
      firstName, lastName, birthDate, sex, address, contactNumber, email, incidentDate, biteLocation, biteImage, notes
    ) VALUES (
      :firstName, :lastName, :birthDate, :sex, :address, :contactNumber, :email, :incidentDate, :biteLocation, :biteImage, :notes
    )");

  $stmt->execute([
    ':firstName' => $firstName,
    ':lastName' => $lastName,
    ':birthDate' => $birthDate,
    ':sex' => $sex,
    ':address' => $address,
    ':contactNumber' => $contactNumber,
    ':email' => $email,
    ':incidentDate' => $incidentDate,
    ':biteLocation' => $biteLocation,
    ':biteImage' => $biteImage,
    ':notes' => $notes,
  ]);

  echo "✅ Report submitted successfully!";
} else {
  echo "❌ Missing required fields.";
}
?>
