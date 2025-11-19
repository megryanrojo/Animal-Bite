<?php
if (!isset($staff) && isset($_SESSION['staffId'])) {
    require_once '../conn/conn.php';
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM staff WHERE staffId = ?");
    $stmt->execute([$_SESSION['staffId']]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
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
          <a class="nav-link<?php if(isset($activePage) && $activePage=="dashboard") echo " active fw-bold"; ?>" href="dashboard.php"><i class="bi bi-house-door me-1"></i> Dashboard</a>
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
<style>
.navbar {
    border-bottom: 1px solid rgba(0,0,0,.1);
}
.navbar .nav-link {
    color: #495057;
    padding: 1rem 1rem;
    transition: all 0.2s;
}
.navbar .nav-link:hover {
    color: #28a745;
}
.navbar .nav-link.active {
    color: #28a745;
    background-color: rgba(40,167,69,0.1);
    border-radius: 0.25rem;
}
.navbar-brand {
    color: #28a745;
    font-size: 1.25rem;
}
.navbar-brand i {
    margin-right: 0.5rem;
}
.dropdown-menu {
    border: none;
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15);
}
.dropdown-item:hover {
    background-color: rgba(40,167,69,0.1);
}
</style> 
<link rel="stylesheet" href="../css/system-theme.css">