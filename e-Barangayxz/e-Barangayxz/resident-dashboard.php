 <?php
// resident-dashboard.php
include 'auth_check.php';
include 'db.php';

$resident = [
  'first_name' => '',
  'last_name' => '',
  'email' => $_SESSION['resident_email'] ?? '',
  'mobile' => '',
  'created_at' => '',
  'gender' => null,
  'birthday' => null,
  'age' => null,
  'street' => null,
  'barangay' => null,
  'municipality' => null,
  'profile_pic' => null
];

// Fetch resident status and restriction info
$residentStatus = 'active';
$statusExpiresAt = null;
$restrictedDocs = [];

  if (!empty($resident_id)) {
    // Ensure common resident columns exist (automatic migration for older DBs)
    $requiredColumns = [
      'gender' => "VARCHAR(30) DEFAULT NULL",
      'birthday' => "DATE DEFAULT NULL",
      'age' => "INT DEFAULT NULL",
      'barangay' => "VARCHAR(255) DEFAULT NULL",
      'municipality' => "VARCHAR(255) DEFAULT NULL"
    ];
    foreach ($requiredColumns as $col => $definition) {
      $colRes = $conn->query("SHOW COLUMNS FROM residents LIKE '$col'");
      if (!($colRes && $colRes->num_rows > 0)) {
        // try to add column, ignore errors
        $conn->query("ALTER TABLE residents ADD COLUMN $col $definition");
        
      }

    }

    // Build SELECT dynamically to support older DBs that may lack some columns
    $baseCols = ['first_name','last_name','email','mobile','created_at'];
    $optional = ['gender','birthday','age','street','barangay','municipality','profile_pic'];
    foreach ($optional as $col) {
      $colRes = $conn->query("SHOW COLUMNS FROM residents LIKE '$col'");
      if ($colRes && $colRes->num_rows > 0) $baseCols[] = $col;
    }
    $selectCols = implode(', ', $baseCols);
    $sql = "SELECT $selectCols FROM residents WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
      $stmt->bind_param('i', $resident_id);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $resident = array_merge($resident, $row);
      }
      $stmt->close();
    }
    
    // Fetch status and restriction info
    $statusCols = [];
    $colRes = $conn->query("SHOW COLUMNS FROM residents LIKE 'status'");
    if ($colRes && $colRes->num_rows > 0) $statusCols[] = 'status';
    $colRes = $conn->query("SHOW COLUMNS FROM residents LIKE 'status_expires_at'");
    if ($colRes && $colRes->num_rows > 0) $statusCols[] = 'status_expires_at';
    $colRes = $conn->query("SHOW COLUMNS FROM residents LIKE 'restricted_documents'");
    if ($colRes && $colRes->num_rows > 0) $statusCols[] = 'restricted_documents';
    
    if (count($statusCols) > 0) {
      $statusSelect = implode(', ', $statusCols);
      $statusSql = "SELECT $statusSelect FROM residents WHERE id = ? LIMIT 1";
      $statusStmt = $conn->prepare($statusSql);
      if ($statusStmt) {
        $statusStmt->bind_param('i', $resident_id);
        $statusStmt->execute();
        $statusRes = $statusStmt->get_result();
        if ($statusRes && $statusRes->num_rows > 0) {
          $statusRow = $statusRes->fetch_assoc();
          if (isset($statusRow['status'])) $residentStatus = $statusRow['status'];
          if (isset($statusRow['status_expires_at'])) $statusExpiresAt = $statusRow['status_expires_at'];
          if (isset($statusRow['restricted_documents'])) {
            $restrictedDocs = json_decode($statusRow['restricted_documents'], true) ?: [];
          }
        }
        $statusStmt->close();
      }
    }
  }

// Load resident requests (if requests table exists)
$requests = [];
$checkReq = $conn->query("SHOW TABLES LIKE 'requests'");
if ($checkReq && $checkReq->num_rows > 0 && !empty($resident_id)) {
  // Some local/dev DBs may have an older requests table without 'given_at' or 'notes'.
  // Detect which columns exist and build the SELECT accordingly to avoid SQL errors.
  $has_given_at = false;
  $has_notes = false;
  $colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'given_at'");
  if ($colRes && $colRes->num_rows > 0) $has_given_at = true;
  $colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'notes'");
  if ($colRes && $colRes->num_rows > 0) $has_notes = true;

  $selectCols = "id, document_type, status, requested_at";
  if ($has_given_at) $selectCols .= ", given_at";
  if ($has_notes) $selectCols .= ", COALESCE(notes,'') AS notes";
  
  // Check if payment_status column exists
  $has_payment_status = false;
  $colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'payment_status'");
  if ($colRes && $colRes->num_rows > 0) {
    $has_payment_status = true;
    $selectCols .= ", COALESCE(payment_status,'Unpaid') AS payment_status";
  }
  
  // Check if payment_proof column exists
  $has_payment_proof = false;
  $colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'payment_proof'");
  if ($colRes && $colRes->num_rows > 0) {
    $has_payment_proof = true;
    $selectCols .= ", payment_proof";
  }
  
  // Check if document_path column exists
  $has_document_path = false;
  $colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'document_path'");
  if ($colRes && $colRes->num_rows > 0) {
    $has_document_path = true;
    $selectCols .= ", document_path";
  }
  
  // Check if mode_of_payment column exists
  $has_mode_of_payment = false;
  $colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'mode_of_payment'");
  if ($colRes && $colRes->num_rows > 0) {
    $has_mode_of_payment = true;
    $selectCols .= ", mode_of_payment";
  }

  $sql = "SELECT " . $selectCols . " FROM requests WHERE resident_id = ? ORDER BY requested_at DESC";
  $q = $conn->prepare($sql);
  if ($q) {
    $q->bind_param('i', $resident_id);
    $q->execute();
    $resq = $q->get_result();
    while ($r = $resq->fetch_assoc()) {
      // normalize keys so client code can rely on them
      if (!array_key_exists('given_at', $r)) $r['given_at'] = null;
      if (!array_key_exists('notes', $r)) $r['notes'] = '';
      if (!array_key_exists('payment_status', $r)) $r['payment_status'] = 'Unpaid';
      if (!array_key_exists('payment_proof', $r)) $r['payment_proof'] = null;
      if (!array_key_exists('document_path', $r)) $r['document_path'] = null;
      if (!array_key_exists('mode_of_payment', $r)) $r['mode_of_payment'] = 'GCash';
      $requests[] = $r;
    }
    $q->close();
  }
}

function initials($first, $last) {
  $f = trim($first);
  $l = trim($last);
  $initials = '';
  if ($f !== '') $initials .= strtoupper($f[0]);
  if ($l !== '') $initials .= strtoupper($l[0]);
  if ($initials === '') $initials = 'R';
  return $initials;
}

function format_mobile_dashed($raw) {
    if (!$raw) return '—';
    // strip non-digits
    $d = preg_replace('/\D+/', '', $raw);
    if (!$d) return '—';
    // common PH mobile format: 11 digits starting with 09 -> 09XX-XXX-XXXX
    if (preg_match('/^09\d{9}$/', $d)) {
        return substr($d,0,4) . '-' . substr($d,4,3) . '-' . substr($d,7,4);
    }
    // fallback: group by 3s from left
    return implode('-', str_split($d, 3));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Resident Dashboard | eBarangay</title>
  <link rel="icon" type="image/jpeg" href="pics/eb-logo.jpg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
      :root {
        --gradient-start: #b31217;
        --gradient-mid: #6a0572;
        --gradient-end: #2d0b8c;
        --text-dark: #232347;
      }

      body {
        background-color: #f8f9fc;
        font-family: 'Segoe UI', sans-serif;
        color: var(--text-dark);
        display: flex;
        flex-direction: column;
        min-height: 100vh;
      }

      /* Navbar */
      .navbar {
        background: linear-gradient(90deg, var(--gradient-start) 0%, var(--gradient-mid) 50%, var(--gradient-end) 100%);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
      }

      .navbar-brand, .navbar .nav-link, .navbar .btn {
        color: #fff !important;
      }

      /* Logout button hover effect */
      .navbar .btn-outline-light {
        border: 2px solid #fff !important;
        transition: all 0.3s ease;
      }

      .navbar .btn-outline-light:hover {
        background-color: #fff !important;
        color: var(--gradient-mid) !important;
        border: 2px solid #fff !important;
      }

      /* Ensure resident dashboard topbar logout matches sidebar nav-link sizing */
      .resident-topbar .logout-box {
        background: transparent !important;
        padding: 0 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
      }
      .resident-topbar .topbar-action,
      .resident-topbar .logout-box .topbar-action {
        padding: 0.45rem 0.85rem !important; /* smaller button */
        font-weight: 600 !important;
        border-radius: 8px !important;
        font-size: 0.9rem !important;
        min-height: 34px !important;
        line-height: 1 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.2s ease, color 0.2s ease;
      }

      /* interactive hover/active states */
      .resident-topbar .topbar-action:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(255,255,255,0.18); }
      .resident-topbar .topbar-action:active { transform: translateY(0); box-shadow: 0 2px 6px rgba(0,0,0,0.25) inset; }
      .resident-topbar .topbar-action:focus-visible { outline: 2px solid #ffffff; outline-offset: 2px; }

      main {
        flex: 1;
        padding: 0;
        overflow: hidden;
      }

      /* --- Panel Container --- */
      .panel-card {
        border-radius: 16px;
        background-color: #fff;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 20px;
        border-top: 5px solid transparent;
        border-image: linear-gradient(to right, var(--gradient-start), var(--gradient-mid), var(--gradient-end)) 1;
      }

      .panel-card h5 {
        color: var(--gradient-mid);
        font-weight: 700;
      }

      /* Tabs */
      .nav-tabs {
        border-bottom: none;
        gap: 6px;
      }

      .nav-tabs .nav-link {
        border: none;
        border-radius: 10px 10px 0 0;
        background-color: #f1f2f8;
        color: var(--text-dark);
        font-weight: 500;
        transition: all 0.3s ease;
      }

      .nav-tabs .nav-link.active {
        background: linear-gradient(90deg, var(--gradient-start) 0%, var(--gradient-mid) 50%, var(--gradient-end) 100%);
        color: #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      }

      /* Scrollable Request List */
      #request-status .list-group {
        max-height: 400px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #bbbbbbff #f8f9fc;
      }

      /* Scrollable Pending Requests */
      #activeRequests {
        max-height: 350px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #bbbbbbff #f8f9fc;
      }

      /* Scrollable Completed Documents */
      #completedDocs {
        max-height: 350px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #bbbbbbff #f8f9fc;
      }

      /* Scrollable Payment List */
      #paymentList {
        max-height: 350px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #bbbbbbff #f8f9fc;
      }

      /* Scrollable Recent Activity */
      #recentActivity {
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #bbbbbbff #f8f9fc;
      }

      /* Custom Scrollbar for Webkit browsers (Chrome, Safari, Edge) */
      #request-status .list-group::-webkit-scrollbar,
      #activeRequests::-webkit-scrollbar,
      #completedDocs::-webkit-scrollbar,
      #paymentList::-webkit-scrollbar,
      #recentActivity::-webkit-scrollbar,
      .dashboard-col-left::-webkit-scrollbar,
      .dashboard-col-middle::-webkit-scrollbar {
        width: 8px;
      }

      #request-status .list-group::-webkit-scrollbar-track,
      #activeRequests::-webkit-scrollbar-track,
      #completedDocs::-webkit-scrollbar-track,
      #paymentList::-webkit-scrollbar-track,
      #recentActivity::-webkit-scrollbar-track,
      .dashboard-col-left::-webkit-scrollbar-track,
      .dashboard-col-middle::-webkit-scrollbar-track {
        background: #f8f9fc;
        border-radius: 10px;
      }

      #request-status .list-group::-webkit-scrollbar-thumb,
      #activeRequests::-webkit-scrollbar-thumb,
      #completedDocs::-webkit-scrollbar-thumb,
      #paymentList::-webkit-scrollbar-thumb,
      #recentActivity::-webkit-scrollbar-thumb,
      .dashboard-col-left::-webkit-scrollbar-thumb,
      .dashboard-col-middle::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #b31217, #6a0572);
        border-radius: 10px;
      }

      #request-status .list-group::-webkit-scrollbar-thumb:hover,
      #activeRequests::-webkit-scrollbar-thumb:hover,
      #completedDocs::-webkit-scrollbar-thumb:hover,
      #paymentList::-webkit-scrollbar-thumb:hover,
      #recentActivity::-webkit-scrollbar-thumb:hover,
      .dashboard-col-left::-webkit-scrollbar-thumb:hover,
      .dashboard-col-middle::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(180deg, #8e2222, #4a0552);
      }

      /* Activity Items */
      .activity-item {
        transition: background-color 0.15s ease;
      }
      .activity-item:hover {
        background-color: #fafafa;
      }
      .activity-item:last-child {
        border-bottom: none !important;
      }
      .activity-icon {
        min-width: 36px;
      }
      .activity-content {
        min-width: 0;
      }
      .activity-title {
        color: #232347;
      }

      /* Buttons */
      .btn-gradient {
        background: linear-gradient(90deg, var(--gradient-start) 0%, var(--gradient-mid) 50%, var(--gradient-end) 100%);
        border: none;
        color: white;
        font-weight: 600;
        transition: 0.3s ease;
      }

      .btn-gradient:hover {
        opacity: 0.9;
        transform: scale(1.01);
      }

      /* Make list items feel clickable */
      .list-group-item.request-item {
        cursor: pointer;
        transition: background-color 0.12s ease;
      }
      .list-group-item.request-item:hover {
        background-color: #fafafa;
      }

      /* Profile Card */
      .profile-card {
        border-radius: 16px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        overflow: hidden;
      }

      .profile-header {
        background: linear-gradient(90deg, var(--gradient-start) 0%, var(--gradient-mid) 50%, var(--gradient-end) 100%);
        color: white;
        text-align: center;
        padding: 15px 10px;
      }

      .profile-avatar {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background-color: #ffffff33;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: bold;
        margin: 0 auto 8px auto;
        border: 3px solid #fff;
      }

      .profile-body {
        padding: 12px;
        background: #fff;
        font-size: 0.85rem;
      }

      .profile-body p {
        margin-bottom: 0.3rem;
      }

      .status-box {
        background-color: #f3f0ff;
        border-left: 5px solid var(--gradient-start);
        border-radius: 12px;
        padding: 15px;
      }

      /* Status box when placed inside the profile header */
      .profile-header .status-box {
        background-color: rgba(255,255,255,0.12);
        border-left: none;
        border: 1px solid rgba(255,255,255,0.12);
        padding: 8px 12px;
        display: inline-block;
        border-radius: 10px;
        margin-top: 10px;
        color: #fff;
      }

      .profile-header .status-box h6,
      .profile-header .status-box small {
        color: rgba(255,255,255,0.95);
      }

      /* small check icon beside name */
      .profile-header .bi-check-circle-fill {
        font-size: 0.95rem;
        vertical-align: middle;
      }

      /* Badges */
      .badge.bg-warning {
        background-color: #ffd95e !important;
        color: #5a4a00 !important;
      }
      .badge.bg-success {
        background: linear-gradient(90deg, #43e97b, #38f9d7);
        color: #fff !important;
      }
      .badge.bg-info {
        background: linear-gradient(90deg, #a1c4fd, #c2e9fb);
        color: #333 !important;
      }
      .badge.bg-primary {
        background: linear-gradient(90deg, #4facfe, #00f2fe);
        color: #fff !important;
      }

      /* Footer */
      footer {
        margin-top: auto;
        width: 100%;
      }

      footer small {
        color: #ddd;
      }

      @media (max-width: 768px) {
        main {
          padding: 25px 0;
        }
      }

      /* 3-column dashboard layout */
      .dashboard-row {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        max-height: calc(100vh - 120px);
        overflow: hidden;
        padding: 16px;
      }

      /* Left column: Profile + Completed Documents */
      .dashboard-col-left {
        flex: 0 0 420px;
        max-width: 420px;
        display: flex;
        flex-direction: column;
        gap: 20px;
        max-height: calc(100vh - 120px);
        overflow: hidden;
        scrollbar-width: thin;
        scrollbar-color: #ffffffff #f8f9fc;
      }

      /* Profile card should not shrink */
      .dashboard-col-left .profile-card {
        flex-shrink: 0;
      }

      /* Completed Documents panel styling */
      #completedPanel {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
      }

      #completedPanel h6 {
        flex-shrink: 0;
      }

      #completedDocs {
        flex: 1;
        overflow-y: auto;
        min-height: 0;
      }

      /* Download icon styling for completed documents */
      .download-indicator {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.75rem;
        color: #198754;
        background-color: #d1e7dd;
        padding: 2px 8px;
        border-radius: 12px;
        margin-left: 8px;
      }

      .download-indicator i {
        font-size: 0.8rem;
      }

      .completed-doc-header {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
      }

      /* Middle column: Request Document + Payment */
      .dashboard-col-middle {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 16px;
        max-height: calc(100vh - 120px);
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #bbbbbbff #f8f9fc;
      }

      /* Right column: Recent Activity */
      .dashboard-col-right {
        flex: 0 0 300px;
        max-width: 300px;
        display: flex;
        flex-direction: column;
        max-height: calc(100vh - 120px);
      }

      /* Ensure panels fill their containers */
      .dashboard-col-left .panel-card,
      .dashboard-col-left .profile-card,
      .dashboard-col-middle .panel-card,
      .dashboard-col-right .panel-card {
        width: 100%;
        box-sizing: border-box;
      }

      /* Recent Activity panel should stretch to fill available height */
      .dashboard-col-right .panel-card {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
      }

      /* Make Recent Activity heading fixed and content scrollable */
      .dashboard-col-right .panel-card h6 {
        flex-shrink: 0;
      }

      .dashboard-col-right #recentActivity,
      .dashboard-col-right #noRecentActivityMessage {
        flex: 1;
        overflow-y: auto;
        max-height: none;
      }

      /* Responsive: stack on medium screens */
      @media (max-width: 1199px) {
        .dashboard-col-left {
          flex: 0 0 360px;
          max-width: 360px;
        }
        .dashboard-col-right {
          flex: 0 0 280px;
          max-width: 280px;
        }
      }

      /* Responsive: 2 columns on tablet */
      @media (max-width: 991px) {
        .dashboard-row {
          flex-direction: column;
        }
        .dashboard-col-left,
        .dashboard-col-middle,
        .dashboard-col-right {
          flex: 1 1 100%;
          max-width: 100%;
        }
      }
      /* Modal Close button gradient (match login button) - affects only footer "Close" buttons */
      .btn-close-gradient,
      #privacyModal .modal-footer .btn[data-bs-dismiss="modal"],
      #contactModal .modal-footer .btn[data-bs-dismiss="modal"] {
        background: linear-gradient(135deg, #8e2222, #6a0dad);
        color: #fff;
        border: none;
        box-shadow: 0 6px 18px rgba(43, 6, 82, 0.12);
        transition: transform 0.18s ease, background 0.18s ease;
      }
      .btn-close-gradient:hover,
      #privacyModal .modal-footer .btn[data-bs-dismiss="modal"]:hover,
      #contactModal .modal-footer .btn[data-bs-dismiss="modal"]:hover {
        background: linear-gradient(135deg, #222c8e, #6a0dad);
        transform: translateY(-1px);
        color: #fff;
      }
      .btn-close-gradient:focus,
      #privacyModal .modal-footer .btn[data-bs-dismiss="modal"]:focus,
      #contactModal .modal-footer .btn[data-bs-dismiss="modal"]:focus {
        outline: 3px solid rgba(197, 32, 47, 0.12);
        outline-offset: 2px;
      }
      /* General layout adjustments ------------------------------------------------- */
      /* Reserve scrollbar to avoid page width jump when modals open */
      html { overflow-y: scroll; }

      /* Prevent Bootstrap from adding padding to body when modal opens */
      .modal-open { padding-right: 0 !important; }
      /* ----------------------------------------------------------------------------------------- */
    </style>
</head>
<body>
  <!-- HEADER -->
  <nav class="navbar navbar-expand-lg px-4 py-2 sticky-top resident-topbar" style="background:linear-gradient(90deg,#b31217 0%,#6a0572 50%,#2d0b8c 100%);">
    <a class="navbar-brand" href="resident-dashboard.php">
      <img src="pics/eb-logo.jpg" alt="eBarangay Logo" class="me-2" style="height: 55px; width: auto;">
    <strong>eBarangay</strong> | Resident Portal
    </a>
    <div class="ms-auto d-flex align-items-center">
      <div class="logout-box">
        <a id="logoutBtn" href="#" class="btn btn-outline-light topbar-action">Logout</a>
      </div>
    </div>
  </nav>

  <main class="container-fluid py-3 px-3">
    <div class="dashboard-row">
      <!-- LEFT COLUMN: Profile + Completed Documents -->
      <div class="dashboard-col-left">
        <div class="profile-card">
          <div class="profile-header">
            <div class="profile-avatar" id="dashboardProfileAvatar">
              <?php if (!empty($resident['profile_pic']) && file_exists($resident['profile_pic'])): ?>
                <img src="<?php echo htmlspecialchars($resident['profile_pic']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
              <?php else: ?>
                <span id="dashboardProfileInitials"><?php echo initials($resident['first_name'], $resident['last_name']); ?></span>
              <?php endif; ?>
            </div>
            <h6 class="fw-bold mb-0"><?php echo htmlspecialchars(trim(($resident['first_name'].' '.$resident['last_name'])) ?: ($_SESSION['resident_name'] ?? 'Resident')); ?> <i class="bi bi-check-circle-fill text-success ms-2" id="accountStatusIcon" data-bs-toggle="tooltip" data-bs-placement="top" title="Your account is active and verified."></i></h6>
          </div>
          <div class="profile-body">
            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($resident['email'] ?: '—'); ?></p>
            <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars(format_mobile_dashed($resident['mobile'] ?? '')); ?></p>
            <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars(($resident['street'] ? $resident['street'] . ', ' : '') . 'Pulong Buhangin, Santa Maria, Bulacan'); ?></p>
            <p class="mb-1"><strong>Gender:</strong> <?php echo htmlspecialchars($resident['gender'] ?: '—'); ?></p>
            <p class="mb-1"><strong>Birthday:</strong> <?php echo $resident['birthday'] ? date('M j, Y', strtotime($resident['birthday'])) : '—'; ?></p>
            <p class="mb-1"><strong>Age:</strong> <?php echo ($resident['age'] !== null && $resident['age'] !== '') ? intval($resident['age']) : '—'; ?></p>
            <p><strong>Registered Since:</strong> <?php echo $resident['created_at'] ? date('M Y', strtotime($resident['created_at'])) : '—'; ?></p>
            <div class="d-grid"><a href="resident-profile.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-pencil-square me-1"></i>Edit Profile</a></div>
          </div>
        </div>
        
        <!-- Completed Documents -->
        <div class="panel-card" id="completedPanel">
          <h6 class="fw-bold mb-2"><i class="bi bi-file-earmark-check me-2"></i>Completed Documents</h6>
          <ul id="completedDocs" class="list-group shadow-sm">
            <!-- Completed items (moved by JS) -->
          </ul>
          <div id="noCompletedDocsMessage" class="text-center text-muted py-3" style="display: none;">
            <i class="bi bi-file-earmark-check fs-4 mb-2 d-block"></i>
            <small>No documents</small>
          </div>
        </div>
      </div>

      <!-- MIDDLE COLUMN: Request Document + Payment -->
      <div class="dashboard-col-middle">
        <div class="panel-card">
          <ul class="nav nav-tabs mb-4" id="requestTabs">
            <li class="nav-item">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#request-doc">Request Document</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#request-status">Request Status</button>
            </li>
          </ul>

          <div class="tab-content">
            <!-- Request Document -->
            <div class="tab-pane fade show active" id="request-doc">
              <h5 class="fw-bold mb-3"><i class="bi bi-file-earmark-text me-2"></i>Request a Document</h5>
              <p class="text-muted">Select the document you want to request and click “Submit Request.”</p>

              <form id="requestForm">
                <div class="mb-3">
                  <label class="form-label fw-semibold">Document Type</label>
                  <select class="form-select" id="documentSelect" required>
                    <option value="">-- Select Document --</option>
                    <option value="barangay-clearance.php">Barangay Clearance</option>
                    <option value="certificate-residency.php">Certificate of Residency</option>
                    <option value="indigency-certificate.php">Certificate of Indigency</option>
                    <option value="goodmoral-certificate.php">Certificate of Good Moral Character</option>
                    <option value="business-permit.php">Business Clearance / Permit</option>
                    <option value="soloparent-certificate.php">Certificate of Solo Parent</option>
                    <option value="no-derogatory.php">Certificate of No Derogatory Record</option>
                    <option value="blotter-report.php">Blotter / Incident Report</option>
                    <option value="barangay-id.php">Barangay ID</option>
                    <option value="low-income-certificate.php">Certificate of Low Income</option>
                    <option value="non-employment.php">Certificate of Non-Employment</option>
                    <option value="burial-assistance.php">Certificate for Burial Assistance</option>
                    <option value="others.php">Other (Please Specify)</option>
                  </select>
                </div>

                <button type="submit" class="btn btn-gradient w-100 mt-2">Confirm</button>
              </form>
            </div>

            <!-- Request Status -->
            <div class="tab-pane fade" id="request-status">
              <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2"></i>Request Status</h5>
              <p class="text-muted">View the progress of your submitted document requests.</p>

              <div class="mb-3">
                <div class="panel-card">
                  <h6 class="fw-bold mb-2">Pending Requests</h6>
                  <ul id="activeRequests" class="list-group shadow-sm">
                    <!-- Active requests will be loaded here from the server -->
                  </ul>
                </div>
              </div>

            </div>
          </div>
        </div>
        
        <!-- Payment Submission -->
        <div class="panel-card">
          <h6 class="fw-bold mb-3"><i class="bi bi-credit-card me-2"></i>Payment</h6>
          <p class="small text-muted mb-3">Submit proof of payment for your document request.</p>
          
          <div id="paymentList">
            <!-- Pending payments will be loaded here -->
          </div>

          <div id="noPaymentsMessage" class="text-center text-muted py-3" style="display: none;">
            <i class="bi bi-cash-coin fs-4 mb-2 d-block"></i>
            <small>No pending payments</small>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN: Recent Activity -->
      <div class="dashboard-col-right">
        <div class="panel-card">
          <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2"></i>Recent Activity</h6>
          <div id="recentActivity">
            <!-- Recent activity items will be populated here -->
          </div>
          <div id="noRecentActivityMessage" class="text-center text-muted py-3" style="display: none;">
            <i class="bi bi-clock-history fs-4 mb-2 d-block"></i>
            <small>No recent activity</small>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Expose initial requests loaded server-side
    const residentId = <?php echo json_encode($resident_id ?? 'null'); ?>;
    const initialRequests = <?php echo json_encode($requests, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?> || [];
    const residentStatus = <?php echo json_encode($residentStatus); ?>;
    const statusExpiresAt = <?php echo json_encode($statusExpiresAt); ?>;
    const restrictedDocuments = <?php echo json_encode($restrictedDocs); ?>;
    
    // Debug: Show what data PHP loaded
    console.log('=== PHP DATA DEBUG ===');
    console.log('Logged in as resident_id:', residentId);
    console.log('Total requests from PHP:', initialRequests.length);
    console.log('Full requests array:', initialRequests);

    document.getElementById('logoutBtn').addEventListener('click', function(e){
      e.preventDefault();
      fetch('resident_logout.php', { method: 'POST' }).then(r => r.json()).then(j => {
        window.location.href = (j && j.data && j.data.redirect) ? j.data.redirect : 'login-register.html';
      }).catch(()=> window.location.href = 'login-register.html');
    });

    // Helper to format date
    function formatDateTime(dt) {
      if (!dt) return '—';
      try { const d = new Date(dt); return d.toLocaleString(); } catch(e) { return dt; }
    }

    // Render request list items into the correct container
    function renderRequests(list) {
      const activeList = document.getElementById('activeRequests');
      const completedList = document.getElementById('completedDocs');
      if (!activeList || !completedList) return;
      activeList.innerHTML = '';
      completedList.innerHTML = '';

      list.forEach(r => {
        const li = document.createElement('li');
        li.className = 'list-group-item request-item';
        li.dataset.title = r.document_type || 'Document';
        li.dataset.status = r.status || 'Pending';
        li.dataset.requested = r.requested_at ? formatDateTime(r.requested_at) : '—';
        li.dataset.given = r.given_at ? formatDateTime(r.given_at) : '—';
        li.dataset.notes = r.notes || '';
        li.dataset.documentPath = r.document_path || '';
        li.dataset.givenAt = r.given_at || '';

        const inner = document.createElement('div');
        inner.className = 'd-flex justify-content-between align-items-center';
        
        // Create left side with document name and download indicator
        const leftSide = document.createElement('div');
        leftSide.className = 'completed-doc-header';
        
        const docName = document.createElement('span');
        docName.innerHTML = `<strong>${escapeHtml(r.document_type || 'Document')}</strong>`;
        leftSide.appendChild(docName);
        
        // Add download indicator for completed documents with document_path
        const isCompleted = ['ready', 'released', 'completed'].includes((r.status || '').toLowerCase());
        if (isCompleted && r.document_path && r.document_path.trim() !== '') {
          const downloadIndicator = document.createElement('span');
          downloadIndicator.className = 'download-indicator';
          downloadIndicator.innerHTML = '<i class="bi bi-download"></i> Download';
          leftSide.appendChild(downloadIndicator);
        }
        
        inner.appendChild(leftSide);

        const badge = document.createElement('span');
        const statusLower = (r.status || 'pending').toLowerCase();
        if (statusLower === 'ready' || statusLower === 'released' || statusLower === 'completed') {
          badge.className = 'badge bg-success';
          badge.textContent = 'Completed';
        } else if (statusLower === 'approved') {
          badge.className = 'badge bg-info text-dark';
          badge.textContent = 'On Process';
        } else if (statusLower === 'processing') {
          badge.className = 'badge bg-info text-dark';
          badge.textContent = 'Processing';
        } else if (statusLower === 'rejected') {
          badge.className = 'badge bg-danger';
          badge.textContent = 'Rejected';
        } else {
          badge.className = 'badge bg-warning text-dark';
          badge.textContent = 'Pending';
        }
        inner.appendChild(badge);

        li.appendChild(inner);
        const small1 = document.createElement('small');
        small1.className = 'text-muted d-block mt-1';
        small1.textContent = 'Date Requested: ' + (li.dataset.requested || '—');
        const small2 = document.createElement('small');
        small2.className = 'text-muted d-block';
        small2.textContent = 'Date Given: ' + (li.dataset.given || '—');
        li.appendChild(small1);
        li.appendChild(small2);

        // decide where to append
        const dest = (r.status || '').toLowerCase();
        if (dest === 'ready' || dest === 'released' || dest === 'completed') {
          // Only attach click event to completed documents
          li.addEventListener('click', function() {
            const title = this.dataset.title || 'Document';
            const status = this.dataset.status || '';
            const requested = this.dataset.requested || '';
            const given = this.dataset.given || '';
            const notes = this.dataset.notes || '';
            const documentPath = this.dataset.documentPath || null;
            const givenAt = this.dataset.givenAt || null;

            console.log('Modal Data:', { title, status, documentPath, givenAt });

            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalStatus').textContent = status;
            document.getElementById('modalRequested').textContent = requested;
            document.getElementById('modalGiven').textContent = given;
            document.getElementById('modalNotes').textContent = notes || 'No additional notes from staff.';

            const actionBtn = document.getElementById('modalAction');
            const expiryWarning = document.getElementById('downloadExpiryWarning');
            
            console.log('Document Path:', documentPath);
            console.log('Document Path type:', typeof documentPath);
            console.log('Document Path length:', documentPath ? documentPath.length : 'null');
            
            // Show download button if document path exists
            if (documentPath && documentPath.trim() !== '') {
              console.log('Showing download button for:', documentPath);
              // Set button appearance
              actionBtn.classList.remove('btn-secondary');
              actionBtn.classList.add('btn-gradient');
              // Set download attributes
              actionBtn.href = documentPath;
              actionBtn.setAttribute('download', documentPath.split('/').pop());
              actionBtn.setAttribute('target', '_blank');
              // Force show the button with important priority
              actionBtn.style.cssText = 'display: inline-block !important; color: white;';
              expiryWarning.style.display = 'none';
              console.log('Button display style set:', actionBtn.style.display);
              console.log('Button computed style:', window.getComputedStyle(actionBtn).display);
            } else {
              console.log('Hiding download button - no document path');
              actionBtn.style.display = 'none';
              expiryWarning.style.display = 'none';
            }

            const modalEl = document.getElementById('requestDetailModal');
            let bsModal = bootstrap.Modal.getInstance(modalEl);
            if (!bsModal) {
              bsModal = new bootstrap.Modal(modalEl);
            }
            bsModal.show();
          });
          completedList.appendChild(li);
        } else {
          activeList.appendChild(li);
        }
      });

      // Show/hide empty state messages
      const noCompletedMsg = document.getElementById('noCompletedDocsMessage');
      if (noCompletedMsg) {
        if (completedList.children.length === 0) {
          completedList.style.display = 'none';
          noCompletedMsg.style.display = 'block';
        } else {
          completedList.style.display = 'block';
          noCompletedMsg.style.display = 'none';
        }
      }
    }

    // Simple HTML escaper
    function escapeHtml(s) {
      if (!s) return '';
      return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // Initialize with server-provided requests
    renderRequests(initialRequests);

    // Load and display pending payments
    function loadPendingPayments() {
      const paymentList = document.getElementById('paymentList');
      const noPaymentsMsg = document.getElementById('noPaymentsMessage');
      
      if (!paymentList) return;

      console.log('All requests:', initialRequests);

      // Filter APPROVED or COMPLETED requests that have payment (unpaid, pending verification, or paid)
      const pendingPayments = initialRequests.filter(r => {
        const status = (r.status || '').toLowerCase();
        const paymentStatus = (r.payment_status || '').toLowerCase();
        const matches = (status === 'approved' || status === 'completed') && (paymentStatus === 'unpaid' || paymentStatus === 'pending verification' || paymentStatus === 'paid');
        console.log(`Request #${r.id}: status="${status}", payment_status="${paymentStatus}", matches=${matches}`);
        return matches;
      });

      console.log('Filtered pending payments:', pendingPayments);

      if (pendingPayments.length === 0) {
        paymentList.style.display = 'none';
        noPaymentsMsg.style.display = 'block';
        return;
      }

      paymentList.style.display = 'block';
      noPaymentsMsg.style.display = 'none';
      paymentList.innerHTML = '';

      pendingPayments.forEach(payment => {
        const paymentStatus = (payment.payment_status || '').toLowerCase();
        const hasProof = payment.payment_proof && payment.payment_proof !== '';
        const isPendingVerification = paymentStatus === 'pending verification';
        const isPaid = paymentStatus === 'paid';
        const modeOfPayment = (payment.mode_of_payment || 'GCash').toLowerCase();
        const isCashPayment = modeOfPayment === 'cash';
        
        const item = document.createElement('div');
        item.className = 'card mb-2 shadow-sm';
        item.style.transition = 'all 0.2s ease';
        
        // Different badge colors based on payment status
        let badgeClass = 'bg-warning text-dark';
        let badgeText = 'Unpaid';
        let buttonHtml = '';
        
        if (isPaid) {
          badgeClass = 'bg-success text-white';
          badgeText = 'Paid';
          item.style.cursor = 'default';
          buttonHtml = '<small class="text-success d-block mt-2"><i class="bi bi-check-circle me-1"></i>Your document will be released soon.</small>';
        } else if (isPendingVerification || hasProof) {
          badgeClass = 'bg-info text-white';
          badgeText = 'Pending Verification';
          item.style.cursor = 'default';
          buttonHtml = '<small class="text-muted d-block mt-2"><i class="bi bi-clock-history me-1"></i>Payment submitted, awaiting staff verification</small>';
        } else if (isCashPayment) {
          item.style.cursor = 'default';
          buttonHtml = `<div class="alert alert-warning p-2 mt-2 mb-0" role="alert" style="font-size: 0.9rem; border-left: 4px solid #ffc107;">
              <i class="bi bi-exclamation-triangle-fill me-2"></i>
              <strong>Please pay at the barangay cashier within 5 working days or the request will be rejected.</strong>
            </div>`;
        } else {
          item.style.cursor = 'pointer';
          buttonHtml = `<button class="btn btn-sm btn-gradient w-100 mt-2 submit-payment-btn" data-request-id="${payment.id}" data-document="${escapeHtml(payment.document_type)}">
              <i class="bi bi-upload me-1"></i> Submit Payment
            </button>`;
        }
        
        item.innerHTML = `
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <h6 class="mb-1 fw-semibold">${escapeHtml(payment.document_type || 'Document')}</h6>
                <small class="text-muted d-block">Request #${payment.id}</small>
                <small class="text-muted d-block">Amount: ₱25.00</small>
                <small class="text-muted d-block">Payment Mode: <span class="${isCashPayment ? 'text-warning fw-semibold' : 'text-primary fw-semibold'}">${isCashPayment ? 'Cash' : 'GCash'}</span></small>
              </div>
              <span class="badge ${badgeClass}">${badgeText}</span>
            </div>
            ${buttonHtml}
          </div>
        `;
        
        // Only add hover effects for GCash payments that are unpaid
        if (!isPendingVerification && !hasProof && !isPaid && !isCashPayment) {
          item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
          });
          
          item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
          });
        }

        paymentList.appendChild(item);
      });

      // Attach event listeners to submit payment buttons
      document.querySelectorAll('.submit-payment-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.stopPropagation();
          const requestId = this.getAttribute('data-request-id');
          const documentName = this.getAttribute('data-document');
          openPaymentModal(requestId, documentName);
        });
      });
    }

    // Open payment submission modal
    function openPaymentModal(requestId, documentName) {
      document.getElementById('payment-request-id').value = requestId;
      document.getElementById('payment-document-name').textContent = documentName;
      document.getElementById('paymentProofForm').reset();
      
      // Reset validation display
      const validationDisplay = document.getElementById('gcash-validation-display');
      if (validationDisplay) {
        validationDisplay.style.display = 'none';
        validationDisplay.innerHTML = '';
      }
      
      // Reset submit button state
      const submitBtn = document.getElementById('submitPaymentBtn');
      if (submitBtn) {
        submitBtn.disabled = false;
      }
      
      const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
      modal.show();
    }

    // Initialize payments on page load
    loadPendingPayments();

    // GCash Receipt AI Validation - Initialize after DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
      console.log('Initializing GCash validation...');
      
      let gcashValidationState = {
        isValidating: false,
        isValid: false,
        referenceNumber: null
      };

      // Validate GCash receipt with AI
      async function validateGCashReceipt(file) {
        console.log('validateGCashReceipt called with file:', file.name);
        const validationDisplay = document.getElementById('gcash-validation-display');
        const referenceInput = document.getElementById('payment-reference');
        const submitBtn = document.getElementById('submitPaymentBtn');
        
        if (!validationDisplay) {
          console.error('Validation display element not found!');
          return;
        }
        
        // Show validating status
        gcashValidationState.isValidating = true;
        gcashValidationState.isValid = false;
        if (submitBtn) submitBtn.disabled = true;
        
        validationDisplay.innerHTML = `
          <div class="alert alert-info d-flex align-items-center mb-0 py-2">
            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
            <span>Validating GCash receipt...</span>
          </div>
        `;
        validationDisplay.style.display = 'block';
        
        try {
          const formData = new FormData();
          formData.append('receipt', file);
          
          console.log('Sending request to validate_gcash_receipt.php...');
          
          const response = await fetch('validate_gcash_receipt.php', {
            method: 'POST',
            body: formData
          });
          
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          
          const result = await response.json();
          console.log('Validation result:', result);
          
          if (result.success && result.validation) {
            const validation = result.validation;
            
            if (validation.is_valid) {
              // Valid GCash receipt
              gcashValidationState.isValid = true;
              gcashValidationState.referenceNumber = validation.reference_number;
              
              // Auto-populate reference number
              if (validation.reference_number && referenceInput) {
                referenceInput.value = validation.reference_number;
                referenceInput.classList.add('is-valid');
                referenceInput.classList.remove('is-invalid');
              }
              
              validationDisplay.innerHTML = `
                <div class="alert alert-success mb-0 py-2">
                  <i class="bi bi-check-circle-fill me-2"></i>
                  <strong>Valid GCash Receipt!</strong>
                  <br><small class="text-muted">
                    Recipient: ${validation.recipient_name || 'ER***A O.'} | Amount: ₱${validation.amount || '25.00'}
                    <br>Reference: ${validation.reference_number}
                  </small>
                </div>
              `;
              if (submitBtn) submitBtn.disabled = false;
            } else {
              // Invalid receipt but might have extracted reference
              gcashValidationState.isValid = false;
              
              // Still auto-populate reference if found
              if (validation.reference_number && referenceInput) {
                referenceInput.value = validation.reference_number;
                gcashValidationState.referenceNumber = validation.reference_number;
              }
              
              let issuesList = '';
              if (validation.issues && validation.issues.length > 0) {
                issuesList = '<ul class="mb-0 mt-2 small">' + 
                  validation.issues.map(issue => `<li>${escapeHtml(issue)}</li>`).join('') + 
                  '</ul>';
              }
              
              validationDisplay.innerHTML = `
                <div class="alert alert-danger mb-0 py-2">
                  <i class="bi bi-x-circle-fill me-2"></i>
                  <strong>Invalid GCash Receipt</strong>
                  <br><small>${escapeHtml(validation.message || 'Receipt does not meet requirements')}</small>
                  ${issuesList}
                  <br><small class="text-muted"><strong>Required:</strong> Recipient ER***A O., Phone +63 965 721 4742, Amount ₱25.00</small>
                </div>
              `;
              // Allow submission with warning
              if (submitBtn) submitBtn.disabled = false;
            }
          } else {
            // API error
            validationDisplay.innerHTML = `
              <div class="alert alert-warning mb-0 py-2">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <span>Could not validate receipt. ${escapeHtml(result.error || 'Please ensure it\'s a valid GCash receipt.')}</span>
              </div>
            `;
            if (submitBtn) submitBtn.disabled = false;
          }
        } catch (error) {
          console.error('GCash validation error:', error);
          validationDisplay.innerHTML = `
            <div class="alert alert-warning mb-0 py-2">
              <i class="bi bi-exclamation-triangle-fill me-2"></i>
              <span>Validation service unavailable. Please verify your receipt manually.</span>
              <br><small class="text-muted">Error: ${escapeHtml(error.message)}</small>
            </div>
          `;
          if (submitBtn) submitBtn.disabled = false;
        }
        
        gcashValidationState.isValidating = false;
      }

      // Add file change listener for GCash receipt validation
      const paymentProofFile = document.getElementById('payment-proof-file');
      if (paymentProofFile) {
        console.log('Attaching change listener to payment-proof-file');
        paymentProofFile.addEventListener('change', function(e) {
          console.log('File changed:', this.files.length);
          const file = this.files[0];
          if (file) {
            console.log('File selected:', file.name, 'Type:', file.type);
            // Only validate images (not PDFs) as AI needs images
            if (file.type.startsWith('image/')) {
              validateGCashReceipt(file);
            } else {
              // For PDF files, show manual entry message
              const validationDisplay = document.getElementById('gcash-validation-display');
              if (validationDisplay) {
                validationDisplay.innerHTML = `
                  <div class="alert alert-info mb-0 py-2">
                    <i class="bi bi-info-circle me-2"></i>
                    <span>PDF uploaded. Please enter the reference number manually.</span>
                  </div>
                `;
                validationDisplay.style.display = 'block';
              }
            }
          }
        });
      } else {
        console.error('payment-proof-file element not found!');
      }
    });

    // GCash Receipt AI Validation
    let gcashValidationState = {
      isValidating: false,
      isValid: false,
      referenceNumber: null
    };

    // Validate GCash receipt with AI
    async function validateGCashReceipt(file) {
      const validationDisplay = document.getElementById('gcash-validation-display');
      const referenceInput = document.getElementById('payment-reference');
      const submitBtn = document.getElementById('submitPaymentBtn');
      
      if (!file || !validationDisplay) return;
      
      // Show validating status
      gcashValidationState.isValidating = true;
      gcashValidationState.isValid = false;
      submitBtn.disabled = true;
      
      validationDisplay.innerHTML = `
        <div class="alert alert-info d-flex align-items-center mb-0 py-2">
          <span class="spinner-border spinner-border-sm me-2" role="status"></span>
          <span>Validating GCash receipt...</span>
        </div>
      `;
      validationDisplay.style.display = 'block';
      
      try {
        const formData = new FormData();
        formData.append('receipt', file);
        
        const response = await fetch('validate_gcash_receipt.php', {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        
        if (result.success && result.validation) {
          const validation = result.validation;
          
          if (validation.is_valid) {
            // Valid GCash receipt
            gcashValidationState.isValid = true;
            gcashValidationState.referenceNumber = validation.reference_number;
            
            // Auto-populate reference number
            if (validation.reference_number && referenceInput) {
              referenceInput.value = validation.reference_number;
              referenceInput.classList.add('is-valid');
            }
            
            validationDisplay.innerHTML = `
              <div class="alert alert-success mb-0 py-2">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>Valid GCash Receipt!</strong>
                <br><small class="text-muted">
                  Recipient: ${validation.recipient_name || 'ER***A O.'} | Amount: ₱${validation.amount || '25.00'}
                  <br>Reference: ${validation.reference_number}
                </small>
              </div>
            `;
            submitBtn.disabled = false;
          } else {
            // Invalid receipt - don't allow submission
            gcashValidationState.isValid = false;
            
            // Don't auto-populate reference for invalid receipts
            
            let issuesList = '';
            if (validation.issues && validation.issues.length > 0) {
              issuesList = '<ul class="mb-0 mt-2 small">' + 
                validation.issues.map(issue => `<li>${issue}</li>`).join('') + 
                '</ul>';
            }
            
            validationDisplay.innerHTML = `
              <div class="alert alert-danger mb-0 py-2">
                <i class="bi bi-x-circle-fill me-2"></i>
                <strong>Invalid GCash Receipt</strong>
                <br><small>${validation.message || 'Receipt does not meet requirements'}</small>
                ${issuesList}
                <br><small class="text-muted"><strong>Required:</strong> Recipient ER***A O., Phone +63 965 721 4742, Amount ₱25.00</small>
              </div>
            `;
            // Don't allow submission if invalid
            submitBtn.disabled = true;
          }
        } else {
          // API error
          validationDisplay.innerHTML = `
            <div class="alert alert-warning mb-0 py-2">
              <i class="bi bi-exclamation-triangle-fill me-2"></i>
              <span>Could not validate receipt. ${result.error || 'Please ensure it\'s a valid GCash receipt.'}</span>
            </div>
          `;
          submitBtn.disabled = false;
        }
      } catch (error) {
        console.error('GCash validation error:', error);
        validationDisplay.innerHTML = `
          <div class="alert alert-warning mb-0 py-2">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <span>Validation service unavailable. Please verify your receipt manually.</span>
          </div>
        `;
        submitBtn.disabled = false;
      }
      
      gcashValidationState.isValidating = false;
    }

    // Add input validation for reference number - only allow digits
    const referenceInput = document.getElementById('payment-reference');
    if (referenceInput) {
      referenceInput.addEventListener('input', function(e) {
        // Remove any non-digit characters
        this.value = this.value.replace(/[^0-9]/g, '');
        // Limit to 14 digits
        if (this.value.length > 14) {
          this.value = this.value.slice(0, 14);
        }
      });
    }
    
    // Add file change listener for GCash receipt validation
    const paymentProofFile = document.getElementById('payment-proof-file');
    if (paymentProofFile) {
      paymentProofFile.addEventListener('change', function(e) {
        const file = this.files[0];
        if (file) {
          // Only validate images (not PDFs) as AI needs images
          if (file.type.startsWith('image/')) {
            validateGCashReceipt(file);
          } else {
            // For PDF files, show manual entry message
            const validationDisplay = document.getElementById('gcash-validation-display');
            if (validationDisplay) {
              validationDisplay.innerHTML = `
                <div class="alert alert-info mb-0 py-2">
                  <i class="bi bi-info-circle me-2"></i>
                  <span>PDF uploaded. Please enter the reference number manually.</span>
                </div>
              `;
              validationDisplay.style.display = 'block';
            }
          }
        }
      });
    }

    // Show empty state for Recent Activity if no content
    (function() {
      const recentActivity = document.getElementById('recentActivity');
      const noActivityMsg = document.getElementById('noRecentActivityMessage');
      if (recentActivity && noActivityMsg) {
        // Check if recentActivity div is empty or only has whitespace
        if (!recentActivity.textContent.trim()) {
          recentActivity.style.display = 'none';
          noActivityMsg.style.display = 'block';
        }
      }
    })();

    // Load recent activities from API
    async function loadRecentActivities() {
      const activityContainer = document.getElementById('recentActivity');
      const noActivityMsg = document.getElementById('noRecentActivityMessage');
      
      if (!activityContainer) return;

      try {
        const response = await fetch('get_resident_activities.php');
        const result = await response.json();
        
        if (!result.success || !result.activities || result.activities.length === 0) {
          activityContainer.style.display = 'none';
          noActivityMsg.style.display = 'block';
          return;
        }

        activityContainer.innerHTML = '';
        activityContainer.style.display = 'block';
        noActivityMsg.style.display = 'none';

        result.activities.forEach(activity => {
          // Skip login and logout activities
          if (activity.activity_type === 'resident_login' || activity.activity_type === 'resident_logout') {
            return;
          }
          
          const item = document.createElement('div');
          item.className = 'activity-item d-flex align-items-start p-2 border-bottom';
          
          // Determine icon and color based on activity type
          let icon = 'bi-file-text';
          let iconColor = '#6a0572';
          
          switch(activity.activity_type) {
            case 'request_submitted':
              icon = 'bi-file-earmark-plus';
              iconColor = '#0d6efd'; // blue
              break;
            case 'request_approved':
              icon = 'bi-check-circle';
              iconColor = '#198754'; // green
              break;
            case 'request_rejected':
              icon = 'bi-x-circle';
              iconColor = '#dc3545'; // red
              break;
            case 'request_processing':
              icon = 'bi-gear';
              iconColor = '#fd7e14'; // orange
              break;
            case 'payment_submitted':
              icon = 'bi-credit-card';
              iconColor = '#0dcaf0'; // cyan
              break;
            case 'payment_confirmed':
              icon = 'bi-check2-circle';
              iconColor = '#20c997'; // teal
              break;
            case 'document_ready':
              icon = 'bi-file-earmark-check';
              iconColor = '#6f42c1'; // purple
              break;
            case 'document_released':
              icon = 'bi-file-earmark-arrow-down';
              iconColor = '#198754'; // green
              break;
            case 'profile_updated':
              icon = 'bi-person-gear';
              iconColor = '#6f42c1'; // purple
              break;
          }

          // Format the date
          const activityDate = new Date(activity.created_at);
          const dateStr = activityDate.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
          });
          const timeStr = activityDate.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit'
          });

          // Staff name display
          const staffDisplay = activity.staff_name ? 
            `<small class="text-muted d-block"><i class="bi bi-person me-1"></i>Processed by: ${escapeHtml(activity.staff_name)}</small>` : '';

          item.innerHTML = `
            <div class="activity-icon me-3 d-flex align-items-center justify-content-center rounded-circle" 
                 style="width: 36px; height: 36px; background-color: ${iconColor}15; color: ${iconColor}; flex-shrink: 0;">
              <i class="bi ${icon}"></i>
            </div>
            <div class="activity-content flex-grow-1">
              <div class="d-flex justify-content-between align-items-start">
                <strong class="activity-title" style="font-size: 0.85rem;">${escapeHtml(activity.title)}</strong>
              </div>
              <small class="text-muted d-block" style="font-size: 0.75rem;">${escapeHtml(activity.message)}</small>
              ${staffDisplay}
              <small class="text-muted" style="font-size: 0.7rem;"><i class="bi bi-clock me-1"></i>${dateStr} at ${timeStr}</small>
            </div>
          `;

          activityContainer.appendChild(item);
        });

      } catch (error) {
        console.error('Error loading recent activities:', error);
        activityContainer.style.display = 'none';
        noActivityMsg.style.display = 'block';
      }
    }

    // Load activities on page load
    loadRecentActivities();

    // Handle payment submission - Global function
    async function handlePaymentSubmit(e) {
      if (e) e.preventDefault();
      
      console.log('Payment submit function called');
      
      const form = document.getElementById('paymentProofForm');
      const fileInput = document.getElementById('payment-proof-file');
      const referenceInput = document.getElementById('payment-reference');
      const submitBtn = document.getElementById('submitPaymentBtn');
      
      // Validate reference number is exactly 14 digits
      if (referenceInput.value.length !== 14 || !/^[0-9]{14}$/.test(referenceInput.value)) {
        alert('Reference number must be exactly 14 digits');
        referenceInput.focus();
        return;
      }
      
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      if (!fileInput.files.length) {
        alert('Please upload proof of payment');
        return;
      }

      const formData = new FormData(form);
      
      // Disable button and show loading
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting...';

      try {
        const response = await fetch('submit_payment_proof.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          // Close modal
          const modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
          if (modal) modal.hide();

          // Reload page to show updated status
          location.reload();
        } else {
          alert('Error: ' + result.message);
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Submit Payment';
        }
      } catch (error) {
        console.error('Payment submission error:', error);
        alert('An error occurred while submitting payment');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Submit Payment';
      }
    }
  </script>
  
  <!-- Restriction Modal -->
  <div class="modal fade" id="restrictionModal" tabindex="-1" aria-labelledby="restrictionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-warning">
          <h5 class="modal-title" id="restrictionModalLabel">
            <i class="bi bi-exclamation-triangle me-2"></i>Account Restricted
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning mb-0">
            <i class="bi bi-shield-exclamation me-2"></i>
            <span id="restrictionMessage"></span>
          </div>
          <p class="mt-3 mb-0 small text-muted">
            <strong>Note:</strong> You can still request other available documents during this restriction period.
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-close-gradient" data-bs-dismiss="modal">
            <i class="bi bi-check-circle me-1"></i> I Understand
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Request Details Modal (centered, responsive, organized) -->
  <div class="modal fade" id="requestDetailModal" tabindex="-1" aria-labelledby="requestDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="requestDetailModalLabel">Request Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="container-fluid">
            <div class="row mb-3">
              <div class="col-12">
                <h5 id="modalTitle" class="mb-0">Document</h5>
              </div>
            </div>

            <dl class="row">
              <dt class="col-sm-4">Status</dt>
              <dd class="col-sm-8"><span id="modalStatus"></span></dd>

              <dt class="col-sm-4">Date Requested</dt>
              <dd class="col-sm-8"><span id="modalRequested"></span></dd>

              <dt class="col-sm-4">Date Given</dt>
              <dd class="col-sm-8"><span id="modalGiven"></span></dd>
            </dl>

            <hr />

            <div class="mb-0">
              <h6 class="mb-2">Notes</h6>
              <p id="modalNotes" class="mb-0 small text-muted"></p>
            </div>
            
            <!-- Download Expiry Warning -->
            <div id="downloadExpiryWarning" class="alert" style="display:none;"></div>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end">
          <a id="modalAction" class="btn btn-gradient" style="display:none;"><i class="bi bi-download me-1"></i>Download Document</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Payment Submission Modal -->
  <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="paymentModalLabel">
            <i class="bi bi-credit-card me-2"></i>Submit Payment Proof
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <small>Please submit proof of payment to process your document request.</small>
          </div>

          <div class="mb-3">
            <strong>Document:</strong> <span id="payment-document-name"></span>
          </div>

          <form id="paymentProofForm" enctype="multipart/form-data">
            <input type="hidden" id="payment-request-id" name="request_id">
            <input type="hidden" name="payment_method" value="GCash">
            
            <div class="mb-3">
              <label class="form-label fw-semibold">Amount Paid <span class="text-danger">*</span></label>
              <div class="form-control-plaintext fw-bold fs-6">₱ 25.00</div>
              <input type="hidden" name="amount" value="25">
              <div class="form-text">Fixed fee for all documents</div>
            </div>

            <div class="mb-3">
              <label for="payment-proof-file" class="form-label fw-semibold">
                Upload Proof <span class="text-danger">*</span>
              </label>
              <input type="file" class="form-control" id="payment-proof-file" name="payment_proof" accept="image/*,.pdf" required>
              <div class="form-text">Accepted: Images or PDF (Max 5MB). GCash receipts will be auto-validated.</div>
              <!-- AI Validation Display -->
              <div id="gcash-validation-display" class="mt-2" style="display: none;"></div>
            </div>

            <div class="mb-3">
              <label for="payment-reference" class="form-label fw-semibold">
                Reference Number <span class="text-danger">*</span>
              </label>
              <input type="text" class="form-control" id="payment-reference" name="reference_number" placeholder="Enter 14-digit reference number" required pattern="[0-9]{14}" maxlength="14" title="Please enter exactly 14 digits">
              <div class="form-text">Must be exactly 14 digits (will auto-fill from GCash receipt)</div>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-gradient" id="submitPaymentBtn" onclick="handlePaymentSubmit(event)">
            <i class="bi bi-check-circle me-1"></i> Submit Payment
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Additional scripts: request handling, modal wiring, tooltips, PDF generation -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script>
    // Restore last selected document on page load
    document.addEventListener('DOMContentLoaded', function() {
      const select = document.getElementById("documentSelect");
      const lastDocument = localStorage.getItem('lastSelectedDocument');
      if (lastDocument && select) {
        select.value = lastDocument;
      }
    });

    // Submit request to server then optionally navigate to document page
    document.getElementById("requestForm").addEventListener("submit", function(e) {
      e.preventDefault();
      const select = document.getElementById("documentSelect");
      const selectedValue = select.value;
      const selectedText = select.options[select.selectedIndex].text;

      if (!selectedValue) {
        alert("Please select a document type before submitting.");
        return;
      }
      
      // Check if account is restricted and trying to request restricted documents
      if (residentStatus === 'restricted') {
        const restrictedTypes = [
          'Barangay ID',
          'Barangay Clearance',
          'Certificate of Good Moral Character',
          'Business Clearance / Permit'
        ];
        
        if (restrictedTypes.some(type => selectedText.includes(type))) {
          // Calculate days remaining
          let daysRemaining = 0;
          if (statusExpiresAt) {
            const expiryDate = new Date(statusExpiresAt);
            const now = new Date();
            const timeDiff = expiryDate - now;
            daysRemaining = Math.max(0, Math.ceil(timeDiff / (1000 * 60 * 60 * 24)));
          }
          
          // Show restriction modal
          const restrictionModal = new bootstrap.Modal(document.getElementById('restrictionModal'));
          document.getElementById('restrictionMessage').textContent = 
            `Your account is currently restricted. You cannot request "${selectedText}" for ${daysRemaining} more day${daysRemaining !== 1 ? 's' : ''}.`;
          restrictionModal.show();
          return;
        }
      }

      // Save the selected document to localStorage before navigating
      if (selectedValue) {
        localStorage.setItem('lastSelectedDocument', selectedValue);
      }

      // Check if this document requires a detailed form (has .html or .php extension)
      if (selectedValue && (selectedValue.endsWith('.html') || selectedValue.endsWith('.php'))) {
        // Before navigating, verify current authentication via AJAX so the form page
        // won't immediately redirect to the registration page for unauthenticated users.
        fetch('auth_check.php', { method: 'GET', headers: { 'Accept': 'application/json' }, cache: 'no-store' })
          .then(response => {
            if (response.ok) {
              // authenticated, go to the form page
              window.location.href = selectedValue;
            } else {
              // not authenticated — send user to login/register page intentionally
              window.location.href = 'login-register.html';
            }
          })
          .catch(() => {
            // network error or auth_check unavailable — fall back to login/register
            window.location.href = 'login-register.html';
          });

        return;
      }

      // For simple requests without forms, submit directly to server
      fetch('submit_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ document_type: selectedText, notes: '' })
      }).then(r => r.json()).then(j => {
        if (j && j.success) {
          // append to UI as pending
          const newItem = [{ id: j.request_id, document_type: selectedText, status: 'Pending', requested_at: new Date().toISOString(), given_at: null, notes: '' }];
          // merge into initialRequests for in-memory state
          initialRequests.unshift(newItem[0]);
          renderRequests(initialRequests);

          alert('Request submitted successfully.');
        } else {
          alert((j && j.message) ? j.message : 'Could not submit request');
        }
      }).catch(err => {
        console.error(err);
        alert('Network or server error while submitting request.');
      });
    });

    // Modal handling is now done in renderRequests() function - removed duplicate code

    // Initialize Bootstrap tooltips (for account status icon)
    (function() {
      const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tooltipTriggerList.forEach(t => new bootstrap.Tooltip(t));
    })();

    // Move completed requests into the Completed Documents box
    (function() {
      const activeList = document.getElementById('activeRequests');
      const completedList = document.getElementById('completedDocs');
      if (!activeList || !completedList) return;

      // consider 'Released' and 'Ready' as completed
      const items = Array.from(activeList.querySelectorAll('.request-item'));
      items.forEach(item => {
        const status = (item.dataset.status || '').toLowerCase();
        if (status === 'released' || status === 'ready' || status === 'completed') {
          const badge = item.querySelector('.badge');
          if (badge) {
            badge.className = 'badge bg-success';
            badge.textContent = 'Completed';
          }
          completedList.appendChild(item);
        }
      });
    })();

    // Old PDF generation and duplicate modal handling code has been removed
    // Download functionality is now handled directly in the renderRequests() function
    // which sets the download button's href to the document_path from the database
  </script>
  
</body>
</html>
