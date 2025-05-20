<?php
if (!isset($staff) && isset($_SESSION['staffId'])) {
    require_once '../conn/conn.php';
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM staff WHERE staffId = ?");
    $stmt->execute([$_SESSION['staffId']]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<nav class="navbar navbar-expand-lg navbar-light sticky-top">
  <div class="container">
    <a class="navbar-brand" href="dashboard.php">
      <i class="bi bi-heart-pulse"></i>
      <span class="fw-bold">BHW Animal Bite Portal</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link<?php if(isset($activePage) && $activePage=="dashboard") echo " active"; ?>" href="dashboard.php"><i class="bi bi-house-door me-1"></i> Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?php if(isset($activePage) && $activePage=="reports") echo " active"; ?>" href="reports.php"><i class="bi bi-file-earmark-text me-1"></i> Reports</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?php if(isset($activePage) && $activePage=="patients") echo " active"; ?>" href="patients.php"><i class="bi bi-people me-1"></i> Patients</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?php if(isset($activePage) && $activePage=="search") echo " active"; ?>" href="search.php"><i class="bi bi-search me-1"></i> Search</a>
        </li>
      </ul>
      <div class="d-flex align-items-center">
        <div class="dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle me-1"></i> <?php echo isset($staff) ? htmlspecialchars($staff['firstName'] . ' ' . $staff['lastName']) : 'Staff'; ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
            <li><a class="dropdown-item text-danger" href="../logout/staff_logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</nav> 