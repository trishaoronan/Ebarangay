<?php
// sidebar-requests.php
session_start();
include 'db.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Location: staff-login.html');
    exit;
}

// Fetch request statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM requests
";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Fetch document type breakdown for each status
$breakdownQuery = "
    SELECT 
        document_type,
        status,
        COUNT(*) as count
    FROM requests
    GROUP BY document_type, status
    ORDER BY document_type, status
";
$breakdownResult = $conn->query($breakdownQuery);
$breakdown = [
    'total' => [],
    'pending' => [],
    'processing' => [],
    'approved' => [],
    'rejected' => []
];

if ($breakdownResult) {
    while ($row = $breakdownResult->fetch_assoc()) {
        $docType = $row['document_type'];
        $status = $row['status'];
        $count = $row['count'];
        
        // Add to total
        if (!isset($breakdown['total'][$docType])) {
            $breakdown['total'][$docType] = 0;
        }
        $breakdown['total'][$docType] += $count;
        
        // Add to specific status
        if ($status === 'pending') {
            $breakdown['pending'][$docType] = $count;
        } elseif ($status === 'processing') {
            $breakdown['processing'][$docType] = $count;
        } elseif ($status === 'approved') {
            $breakdown['approved'][$docType] = $count;
        } elseif ($status === 'rejected') {
            $breakdown['rejected'][$docType] = $count;
        }
    }
}

// Ordered document list for popover content (matches desired reference)
$docOrder = [
  'Barangay Clearance',
  'Certificate of Residency',
  'Certificate of Indigency',
  'Certificate of Good Moral Character',
  'Business Clearance / Permit',
  'Certificate of Solo Parent',
  'Certificate of No Derogatory Record',
  'Blotter / Incident Report',
  'Barangay ID',
  'Certificate of Low Income',
  'Certificate of Non-Employment',
  'Certificate for Burial Assistance',
  'Other (Please Specify)'
];
$docListHtml = '<div class="p-1"><ul class="list-unstyled mb-0">' . implode('', array_map(function($n){
  return '<li class="mb-1">' . htmlspecialchars($n, ENT_QUOTES) . '</li>';
}, $docOrder)) . '</ul></div>';

// Some deployments may not have the barangay_id_applications or low_income_certificates tables yet; guard joins to avoid fatal SQL errors.
$hasBarangayId = false;
$tblCheck = $conn->query("SHOW TABLES LIKE 'barangay_id'");
if ($tblCheck && $tblCheck->num_rows > 0) {
  $hasBarangayId = true;
}

$hasLowIncome = false;
$tblCheck2 = $conn->query("SHOW TABLES LIKE 'low_income'");
if ($tblCheck2 && $tblCheck2->num_rows > 0) {
  $hasLowIncome = true;
}

$hasNonEmployment = false;
$tblCheck3 = $conn->query("SHOW TABLES LIKE 'non_employment'");
if ($tblCheck3 && $tblCheck3->num_rows > 0) {
  $hasNonEmployment = true;
}

// Check for other document tables
$hasBarangayClearance = false;
$tblCheckBC = $conn->query("SHOW TABLES LIKE 'barangay_clearance'");
if ($tblCheckBC && $tblCheckBC->num_rows > 0) { $hasBarangayClearance = true; }

$hasCertificateResidency = false;
$tblCheckCR = $conn->query("SHOW TABLES LIKE 'certificate_residency'");
if ($tblCheckCR && $tblCheckCR->num_rows > 0) { $hasCertificateResidency = true; }

$hasCertificateIndigency = false;
$tblCheckCI = $conn->query("SHOW TABLES LIKE 'certificate_indigency'");
if ($tblCheckCI && $tblCheckCI->num_rows > 0) { $hasCertificateIndigency = true; }

$hasGoodMoral = false;
$tblCheckGM = $conn->query("SHOW TABLES LIKE 'good_moral'");
if ($tblCheckGM && $tblCheckGM->num_rows > 0) { $hasGoodMoral = true; }

$hasBurialAssistance = false;
$tblCheckBA = $conn->query("SHOW TABLES LIKE 'burial_assistance'");
if ($tblCheckBA && $tblCheckBA->num_rows > 0) { $hasBurialAssistance = true; }

$hasBlotterReport = false;
$tblCheckBR = $conn->query("SHOW TABLES LIKE 'blotter_report'");
if ($tblCheckBR && $tblCheckBR->num_rows > 0) { $hasBlotterReport = true; }

$hasBusinessPermit = false;
$tblCheckBP = $conn->query("SHOW TABLES LIKE 'business_permit'");
if ($tblCheckBP && $tblCheckBP->num_rows > 0) { $hasBusinessPermit = true; }

$hasSoloParent = false;
$tblCheckSP = $conn->query("SHOW TABLES LIKE 'solo_parent'");
if ($tblCheckSP && $tblCheckSP->num_rows > 0) { $hasSoloParent = true; }

$hasNoDerogatory = false;
$tblCheckND = $conn->query("SHOW TABLES LIKE 'no_derogatory'");
if ($tblCheckND && $tblCheckND->num_rows > 0) { $hasNoDerogatory = true; }

// Check columns for barangay_id table
$bidHasGender = false;
$bidHasOccupation = false;
if ($hasBarangayId) {
  $bidCols = $conn->query("SHOW COLUMNS FROM barangay_id");
  if ($bidCols) {
    while ($col = $bidCols->fetch_assoc()) {
      if ($col['Field'] === 'gender') $bidHasGender = true;
      if ($col['Field'] === 'occupation') $bidHasOccupation = true;
    }
  }
}

// Check columns for low_income table
$liHasMonthlyIncome = false;
$liHasOccupation = false;
$liHasProofResidency = false;
$liHasPurpose = false;
if ($hasLowIncome) {
  $liCols = $conn->query("SHOW COLUMNS FROM low_income");
  if ($liCols) {
    while ($col = $liCols->fetch_assoc()) {
      if ($col['Field'] === 'estimated_monthly_income') $liHasMonthlyIncome = true;
      if ($col['Field'] === 'occupation') $liHasOccupation = true;
      if ($col['Field'] === 'proof_of_residency_path') $liHasProofResidency = true;
      if ($col['Field'] === 'purpose') $liHasPurpose = true;
    }
  }
}

// Check columns for non_employment table
$neHasCivilStatus = false;
$neHasPurpose = false;
if ($hasNonEmployment) {
  $neCols = $conn->query("SHOW COLUMNS FROM non_employment");
  if ($neCols) {
    while ($col = $neCols->fetch_assoc()) {
      if ($col['Field'] === 'civil_status') $neHasCivilStatus = true;
      if ($col['Field'] === 'purpose') $neHasPurpose = true;
    }
  }
}

// Build column aliases for conditional joins
$bidGender           = ($hasBarangayId && $bidHasGender) ? 'bid.gender' : 'NULL';
$bidOccupation       = ($hasBarangayId && $bidHasOccupation) ? 'bid.occupation' : 'NULL';
$liMonthlyIncome     = ($hasLowIncome && $liHasMonthlyIncome) ? 'li.estimated_monthly_income' : 'NULL';
$liOccupation        = ($hasLowIncome && $liHasOccupation) ? 'li.occupation' : 'NULL';
$liProofResidency    = ($hasLowIncome && $liHasProofResidency) ? 'li.proof_of_residency_path' : 'NULL';
$liPurpose           = ($hasLowIncome && $liHasPurpose) ? 'li.purpose' : 'NULL';
$neCivilStatus       = ($hasNonEmployment && $neHasCivilStatus) ? 'ne.civil_status' : 'NULL';
$nePurpose           = ($hasNonEmployment && $neHasPurpose) ? 'ne.purpose' : 'NULL';

$bcPurpose           = $hasBarangayClearance ? 'bc.purpose'           : 'NULL';
$bcLastName          = $hasBarangayClearance ? 'bc.last_name'         : 'NULL';
$bcFirstName         = $hasBarangayClearance ? 'bc.first_name'        : 'NULL';
$bcMiddleName        = $hasBarangayClearance ? 'bc.middle_name'       : 'NULL';
$bcSuffix            = $hasBarangayClearance ? 'bc.suffix'            : 'NULL';
$bcDOB               = $hasBarangayClearance ? 'bc.date_of_birth'     : 'NULL';
$bcCivilStatus       = $hasBarangayClearance ? 'bc.civil_status'      : 'NULL';
$bcAddress           = $hasBarangayClearance ? 'bc.complete_address'  : 'NULL';
$bcContact           = $hasBarangayClearance ? 'bc.contact_number'    : 'NULL';
$bcValidId           = $hasBarangayClearance ? 'bc.valid_id_path'     : 'NULL';

$crPurpose           = $hasCertificateResidency ? 'cr.purpose'              : 'NULL';
$crDateResiding      = $hasCertificateResidency ? 'cr.date_started_residing': 'NULL';
$crHouseholdHead     = $hasCertificateResidency ? 'cr.household_head_name'  : 'NULL';

$ciPurpose           = $hasCertificateIndigency ? 'ci.specific_purpose'         : 'NULL';
$ciMonthlyIncome     = $hasCertificateIndigency ? 'ci.estimated_monthly_income' : 'NULL';
$ciDependents        = $hasCertificateIndigency ? 'ci.number_of_dependents'     : 'NULL';
$ciProofIncome       = $hasCertificateIndigency ? 'ci.proof_of_income_path'     : 'NULL';

$gmPurpose           = $hasGoodMoral ? 'gm.specific_purpose' : 'NULL';

$baDeceasedFirst     = $hasBurialAssistance ? 'ba.deceased_first_name' : 'NULL';
$baDeceasedLast      = $hasBurialAssistance ? 'ba.deceased_last_name'  : 'NULL';
$baRelationship      = $hasBurialAssistance ? 'ba.relationship'        : 'NULL';
$baCauseOfDeath      = $hasBurialAssistance ? 'ba.cause_of_death'      : 'NULL';
$baDateOfDeath       = $hasBurialAssistance ? 'ba.date_of_death'       : 'NULL';
$baPlaceOfDeath      = $hasBurialAssistance ? 'ba.place_of_death'      : 'NULL';

$brIncidentType      = $hasBlotterReport ? 'br.incident_type'     : 'NULL';
$brIncidentDate      = $hasBlotterReport ? 'br.incident_date'     : 'NULL';
$brIncidentTime      = $hasBlotterReport ? 'br.incident_time'     : 'NULL';
$brIncidentLocation  = $hasBlotterReport ? 'br.incident_location' : 'NULL';
$brNarrative         = $hasBlotterReport ? 'br.narrative'         : 'NULL';
$brRespondentName    = $hasBlotterReport ? 'br.respondent_name'   : 'NULL';
$brRespondentAddress = $hasBlotterReport ? 'br.respondent_address': 'NULL';
$brEvidencePaths     = $hasBlotterReport ? 'br.evidence_paths'    : 'NULL';

$bpBusinessName      = $hasBusinessPermit ? 'bp.business_name'        : 'NULL';
$bpBusinessType      = $hasBusinessPermit ? 'bp.business_type'        : 'NULL';
$bpBusinessLocation  = $hasBusinessPermit ? 'bp.business_location'    : 'NULL';
$bpOwnershipProof    = $hasBusinessPermit ? 'bp.ownership_proof_path' : 'NULL';

$spChildrenCount     = $hasSoloParent ? 'sp.children_count'    : 'NULL';
$spChildrenAges      = $hasSoloParent ? 'sp.children_ages'     : 'NULL';
$spReason            = $hasSoloParent ? 'sp.reason'            : 'NULL';
$spSupportingPaths   = $hasSoloParent ? 'sp.supporting_paths'  : 'NULL';

$ndPlaceOfBirth      = $hasNoDerogatory ? 'nd.place_of_birth'      : 'NULL';
$ndPurpose           = $hasNoDerogatory ? 'nd.specific_purpose'    : 'NULL';

// Check if requests table has mode_of_release and payment_status columns
$hasModeOfRelease = false;
$hasPaymentStatus = false;
$columnsCheck = $conn->query("SHOW COLUMNS FROM requests");
if ($columnsCheck) {
  while ($col = $columnsCheck->fetch_assoc()) {
    if ($col['Field'] === 'mode_of_release') $hasModeOfRelease = true;
    if ($col['Field'] === 'payment_status') $hasPaymentStatus = true;
  }
}

$paymentStatusCol = $hasPaymentStatus ? "r.payment_status" : "'Unpaid' AS payment_status";
// Get mode_of_release from requests table (it's now normalized there)
$modeOfReleaseCol = "r.mode_of_release";

// Build LEFT JOIN clauses
$bidJoin = $hasBarangayId ? "LEFT JOIN barangay_id bid ON r.id = bid.request_id" : "";
$liJoin  = $hasLowIncome  ? "LEFT JOIN low_income li  ON r.id = li.request_id"   : "";
$neJoin  = $hasNonEmployment ? "LEFT JOIN non_employment ne ON r.id = ne.request_id" : "";
$bcJoin  = $hasBarangayClearance ? "LEFT JOIN barangay_clearance bc ON r.id = bc.request_id" : "";
$crJoin  = $hasCertificateResidency ? "LEFT JOIN certificate_residency cr ON r.id = cr.request_id" : "";
$ciJoin  = $hasCertificateIndigency ? "LEFT JOIN certificate_indigency ci ON r.id = ci.request_id" : "";
$gmJoin  = $hasGoodMoral ? "LEFT JOIN good_moral gm ON r.id = gm.request_id" : "";
$baJoin  = $hasBurialAssistance ? "LEFT JOIN burial_assistance ba ON r.id = ba.request_id" : "";
$brJoin  = $hasBlotterReport ? "LEFT JOIN blotter_report br ON r.id = br.request_id" : "";
$bpJoin  = $hasBusinessPermit ? "LEFT JOIN business_permit bp ON r.id = bp.request_id" : "";
$spJoin  = $hasSoloParent ? "LEFT JOIN solo_parent sp ON r.id = sp.request_id" : "";
$ndJoin  = $hasNoDerogatory ? "LEFT JOIN no_derogatory nd ON r.id = nd.request_id" : "";

// Fetch all document requests with JOIN to residents
$query = "
  SELECT 
    r.id,
    r.resident_id,
    r.document_type,
    r.status,
    r.requested_at,
    {$paymentStatusCol},
    {$modeOfReleaseCol},
    res.first_name,
    res.last_name,
    res.mobile,
    res.street,
    res.barangay,
    res.municipality,
    res.profile_pic,
    COALESCE({$bcPurpose}, {$crPurpose}, {$ciPurpose}, {$gmPurpose}, {$ndPurpose}, {$brNarrative}, {$liPurpose}, {$nePurpose}) AS purpose,
    {$bcLastName} AS bc_last_name,
    {$bcFirstName} AS bc_first_name,
    {$bcMiddleName} AS middle_name,
    {$bcSuffix} AS suffix,
    {$bcDOB} AS date_of_birth,
    {$bcCivilStatus} AS civil_status,
    {$bcAddress} AS complete_address,
    {$bcContact} AS contact_number,
    {$bcValidId} AS valid_id_path,
    {$gmPurpose} AS gm_purpose,
    {$crDateResiding} AS date_started_residing,
    {$crHouseholdHead} AS household_head_name,
    {$ciMonthlyIncome} AS estimated_monthly_income,
    {$ciDependents} AS number_of_dependents,
    {$ciProofIncome} AS proof_of_income_path,
    {$baDeceasedFirst} AS deceased_first_name,
    {$baDeceasedLast} AS deceased_last_name,
    {$baRelationship} AS relationship,
    {$baCauseOfDeath} AS cause_of_death,
    {$baDateOfDeath} AS date_of_death,
    {$baPlaceOfDeath} AS place_of_death,
    {$brIncidentType} AS incident_type,
    {$brIncidentDate} AS incident_date,
    {$brIncidentTime} AS incident_time,
    {$brIncidentLocation} AS incident_location,
    {$brNarrative} AS narrative,
    {$brRespondentName} AS respondent_name,
    {$brRespondentAddress} AS respondent_address,
    {$brEvidencePaths} AS evidence_paths,
    {$bpBusinessName} AS business_name,
    {$bpBusinessType} AS business_type,
    {$bpBusinessLocation} AS business_location,
    {$bpOwnershipProof} AS ownership_proof_path,
    {$spChildrenCount} AS children_count,
    {$spChildrenAges} AS children_ages,
    {$spReason} AS reason,
    {$spSupportingPaths} AS supporting_paths,
    {$ndPlaceOfBirth} AS place_of_birth,
    {$bidGender} AS gender,
    {$bidOccupation} AS bid_occupation,
    {$liMonthlyIncome} AS li_monthly_income,
    {$liOccupation} AS li_occupation,
    {$liProofResidency} AS li_proof_residency_path,
    {$neCivilStatus} AS ne_civil_status,
    {$nePurpose} AS ne_purpose
  FROM requests r
  LEFT JOIN residents res ON r.resident_id = res.id
  {$bcJoin}
  {$crJoin}
  {$ciJoin}
  {$gmJoin}
  {$baJoin}
  {$brJoin}
  {$bpJoin}
  {$spJoin}
  {$ndJoin}
  {$bidJoin}
  {$liJoin}
  {$neJoin}
  ORDER BY r.requested_at DESC
";

$result = $conn->query($query);
$requests = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>eBarangay | Staff Dashboard - Requests</title>
  <link rel="icon" type="image/jpeg" href="pics/eb-logo.jpg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="style.css">
  <style>
    .bulk-actions {
      display: none;
      margin-bottom: 15px;
      padding: 10px;
      background: #f8f9fa;
      border-radius: 8px;
    }
    .solo-parent-hidden {
      display: none !important;
    }
    .filter-refresh-btn {
      border: 2px solid #4B0082;
      color: #4B0082;
      border-radius: 50%;
      width: 38px;
      height: 38px;
      padding: 0;
      background: transparent;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.18s, color 0.18s, box-shadow 0.18s;
      box-shadow: none;
    }
    .filter-refresh-btn:hover,
    .filter-refresh-btn:focus,
    .filter-refresh-btn.filter-refresh-btn-loading {
      background: #4B0082 !important;
      color: #fff !important;
      box-shadow: 0 4px 12px rgba(106,13,173,0.10);
    }
    .filter-refresh-btn i {
      font-size: 1.25rem;
      transition: color 0.18s;
    }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .spin-animation { animation: spin 0.8s linear infinite; }
    /* Ensure cards receive hover/clicks */
    .stat-card { pointer-events: auto; }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top" style="padding: 0.5rem 0; margin-top: -8px; padding-top: calc(0.5rem + 8px); z-index: 1050;">
    <div class="container-fluid">
      <a class="navbar-brand d-flex align-items-center" href="staff-dashboard.html">
        <img src="pics/eb-logo.jpg" alt="eBarangay Logo" class="me-2" style="height:40px; width:auto" />
        <span>STAFF DASHBOARD</span>
      </a>
      <div class="d-flex align-items-center">
        <div class="dropdown me-2">
          <button class="btn btn-light position-relative" id="notifButton" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
            <i class="bi bi-bell fs-5"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifBadge">0</span>
          </button>
          <div class="dropdown-menu dropdown-menu-end shadow-sm p-0 border-0" id="notifDropdown" style="width:340px;">
            <div class="p-3 border-bottom bg-light">
              <h6 class="fw-bold mb-0">Notifications</h6>
            </div>
            <div class="notification-panel">
              <!-- Notifications will be loaded dynamically via JavaScript -->
            </div>
          </div>
        </div>
        <button class="btn btn-outline-light btn-sm" id="staffLogoutBtn">Logout</button>
      </div>
    </div>
  </nav>

  <div class="container-fluid">
    <div class="row">
      <!-- Sidebar -->
      <nav class="col-md-3 col-lg-2 d-md-block sidebar p-3 shadow-sm" style="position: fixed; top: 65px; left: 0; height: calc(100vh - 65px); overflow-y: auto; z-index: 1000;">
        <div class="nav flex-column">
          <a class="nav-link" href="staff-dashboard.html"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
          <a class="nav-link active" href="sidebar-requests.php"><i class="bi bi-files me-2"></i> Requests</a>
          <a class="nav-link" href="sidebar-residents.php"><i class="bi bi-people me-2"></i> Residents</a>
          <a class="nav-link" href="sidebar-reports.html"><i class="bi bi-clipboard-data me-2"></i> Reports</a>
          <a class="nav-link" href="sidebar-profile.html"><i class="bi bi-card-list me-2"></i> Profile</a>
        </div>
      </nav>

      <!-- Main Content -->
      <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
        <div class="page-header mb-4">
          <h3>Document Requests</h3>
          <small>Manage and process document requests from residents.</small>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
          <div class="col-md-3">
                    <div class="stat-card bg-primary text-white text-center shadow-sm"
                  data-bs-toggle="popover"
                 data-bs-trigger="hover"
                 data-bs-placement="top"
                 data-breakdown-type="total"
                  data-bs-html="true"
                    data-bs-container="body"
                    title="Document Types"
                      data-bs-content="<?php echo htmlspecialchars($docListHtml, ENT_QUOTES); ?>"
                 role="button">
              <h5><?php echo $stats['total'] ?? 0; ?></h5>
              <p>Total Requests</p>
            </div>
          </div>
          <div class="col-md-3">
                    <div class="stat-card bg-warning text-dark text-center shadow-sm"
                  data-bs-toggle="popover"
                 data-bs-trigger="hover"
                 data-bs-placement="top"
                 data-breakdown-type="pending"
                  data-bs-html="true"
                    data-bs-container="body"
                    title="Pending Requests"
                      data-bs-content="<?php echo htmlspecialchars($docListHtml, ENT_QUOTES); ?>"
                 role="button">
              <h5><?php echo $stats['pending'] ?? 0; ?></h5>
              <p>Pending</p>
            </div>
          </div>
          <div class="col-md-3">
                    <div class="stat-card bg-success text-white text-center shadow-sm"
                  data-bs-toggle="popover"
                 data-bs-trigger="hover"
                 data-bs-placement="top"
                 data-breakdown-type="approved"
                  data-bs-html="true"
                    data-bs-container="body"
                    title="Approved Requests"
                      data-bs-content="<?php echo htmlspecialchars($docListHtml, ENT_QUOTES); ?>"
                 role="button">
              <h5><?php echo $stats['approved'] ?? 0; ?></h5>
              <p>Approved</p>
            </div>
          </div>
          <div class="col-md-3">
                    <div class="stat-card bg-danger text-white text-center shadow-sm"
                  data-bs-toggle="popover"
                 data-bs-trigger="hover"
                 data-bs-placement="top"
                 data-breakdown-type="rejected"
                  data-bs-html="true"
                    data-bs-container="body"
                    title="Rejected Requests"
                      data-bs-content="<?php echo htmlspecialchars($docListHtml, ENT_QUOTES); ?>"
                 role="button">
              <h5><?php echo $stats['rejected'] ?? 0; ?></h5>
              <p>Rejected</p>
            </div>
          </div>
        </div>
        <!-- Filters -->
        <div class="card shadow-sm border-0 mb-3">
          <div class="card-body">
            <form id="filterForm" class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                  <input type="text" class="form-control" id="searchName" placeholder="Search by Resident Name" style="height: 38px;" />
                </div>
              <div class="col-6 col-md-2">
                <!-- Request Status filter -->
                <select class="form-select" id="filterStatus" style="height: 38px;">
                  <option value="">All Status</option>
                  <option value="pending">Pending</option>
                  <option value="processing">Processing</option>
                  <option value="approved">Approved</option>
                  <option value="completed">Completed</option>
                  <option value="rejected">Rejected</option>
                </select>
                <script>
                  // Force-correct status options in case cached markup lingers
                  (function() {
                    const sel = document.getElementById('filterStatus');
                    if (sel) {
                      sel.innerHTML = `
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="approved">Approved</option>
                        <option value="completed">Completed</option>
                        <option value="rejected">Rejected</option>
                      `;
                    }
                  })();
                </script>
              </div>
                <div class="col-6 col-md-2">
                  <select class="form-select" id="filterDocType" style="height: 38px;">
                    <option value="">All Documents</option>
                    <option value="barangay clearance">Barangay Clearance</option>
                    <option value="barangay id">Barangay ID</option>
                    <option value="certificate of residency">Certificate of Residency</option>
                    <option value="certificate of indigency">Certificate of Indigency</option>
                    <option value="good moral character certificate">Good Moral</option>
                    <option value="business permit">Business Permit</option>
                    <option value="solo parent certificate">Solo Parent</option>
                    <option value="certificate of no derogatory record">No Derogatory</option>
                    <option value="blotter report">Blotter Report</option>
                    <option value="burial assistance">Burial Assistance</option>
                    <option value="certificate of non-employment">Non-Employment</option>
                    <option value="low income certificate">Low Income</option>
                    <option value="others">Others</option>
                  </select>
                </div>
                <div class="col-6 col-md-2">
                  <input type="date" class="form-control" id="filterDate" style="height: 38px;" />
                </div>
                <div class="col-md-auto d-flex align-items-center" style="height: 100%;">
                  <button type="button" class="filter-refresh-btn d-flex align-items-center justify-content-center ms-2" id="refreshBtn" title="Refresh">
                    <i class="bi bi-arrow-clockwise"></i>
                  </button>
                </div>
              </form>
            </div>
          </div>

        <div class="card shadow-sm border-0">
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover align-middle" id="requestsTable">
                <thead class="table-light">
                  <tr>
                    <th><input type="checkbox" id="selectAll" /></th>
                    <th>Resident</th>
                    <th>Document</th>
                    <th>Date Requested</th>
                    <th>Time Requested</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Release Mode</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($requests as $request): 
                    $paymentStatus = $request['payment_status'] ?? 'Unpaid';
                    $modeOfRelease = $request['mode_of_release'] ?? '';
                    $status = strtolower($request['status'] ?? 'pending');
                  ?>
                  <?php
                    // Use original request statuses
                    $rawStatus = strtolower($request['status'] ?? 'pending');
                    // Map old names if needed, otherwise keep as-is
                    if (!in_array($rawStatus, ['pending', 'processing', 'approved', 'completed', 'rejected'])) {
                      $rawStatus = 'pending';
                    }
                  ?>
                  <tr data-release="<?php echo htmlspecialchars($modeOfRelease); ?>" data-payment="<?php echo $paymentStatus; ?>" data-request-id="<?php echo $request['id']; ?>" data-doc-type="<?php echo htmlspecialchars(strtolower($request['document_type'] ?? '')); ?>" data-status="<?php echo $rawStatus; ?>">
                    <td><input type="checkbox" class="row-check" /></td>
                    <td><?php echo htmlspecialchars(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? 'Unknown')); ?></td>
                    <td><?php echo htmlspecialchars($request['document_type'] ?? 'N/A'); ?></td>
                    <td class="text-center"><?php echo $request['requested_at'] ? date('Y-m-d', strtotime($request['requested_at'])) : 'N/A'; ?></td>
                    <td class="text-center"><?php echo $request['requested_at'] ? date('H:i', strtotime($request['requested_at'])) : 'N/A'; ?></td>
                    <td>
                      <?php 
                      $badgeClass = 'bg-warning text-dark';
                      $statusText = ucfirst($rawStatus);

                      if ($rawStatus === 'completed') {
                        $badgeClass = 'bg-success text-white';
                        $statusText = 'Completed';
                      } elseif ($rawStatus === 'approved') {
                        $badgeClass = 'bg-primary text-white';
                        $statusText = 'Approved';
                      } elseif ($rawStatus === 'processing') {
                        $badgeClass = 'bg-info text-dark';
                        $statusText = 'Processing';
                      } elseif ($rawStatus === 'rejected') {
                        $badgeClass = 'bg-danger text-white';
                        $statusText = 'Rejected';
                      } elseif ($rawStatus === 'pending') {
                        $badgeClass = 'bg-warning text-dark';
                        $statusText = 'Pending';
                      }
                      ?>
                      <span class="badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span>
                    </td>
                    <td>
                      <?php if ($paymentStatus === 'Paid'): ?>
                        <span class="badge payment-badge bg-success" role="button" style="cursor: pointer;" 
                              data-bs-toggle="modal" 
                              data-bs-target="#paymentProofModal"
                              data-request-id="<?php echo $request['id']; ?>"
                              title="View payment proof">
                          <?php echo $paymentStatus; ?>
                        </span>
                      <?php else: ?>
                        <span class="badge payment-badge bg-secondary"><?php echo $paymentStatus; ?></span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($modeOfRelease === 'Download'): ?>
                        <?php 
                        // Check if document is already uploaded (status is Completed)
                        $hasDocument = $status === 'completed';
                        $linkColor = $hasDocument ? 'text-success' : 'text-primary';
                        ?>
                        <?php if ($paymentStatus === 'Paid'): ?>
                          <a href="#" class="<?php echo $linkColor; ?> upload-document-link" data-request-id="<?php echo $request['id']; ?>" data-bs-toggle="modal" data-bs-target="#uploadModal">
                            Download
                          </a>
                        <?php else: ?>
                          <span class="text-muted">Download</span>
                        <?php endif; ?>
                      <?php elseif ($modeOfRelease === 'Pickup'): ?>
                        <?php if ($status === 'approved' || $status === 'processing'): ?>
                          <a href="#" class="text-primary pickup-confirm-link" data-request-id="<?php echo $request['id']; ?>" data-bs-toggle="modal" data-bs-target="#pickupConfirmModal">
                            Pickup
                          </a>
                        <?php elseif ($status === 'completed'): ?>
                          <span class="text-success fw-semibold">Pickup</span>
                        <?php else: ?>
                          <span class="text-muted">Pickup</span>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <button class="btn btn-sm btn-outline-secondary view-request" data-bs-toggle="modal" data-bs-target="#requestModal"
                        data-request-id="<?php echo $request['id']; ?>"
                        data-request="#REQ-<?php echo $request['id']; ?>"
                        data-date="<?php echo $request['requested_at'] ? date('Y-m-d H:i', strtotime($request['requested_at'])) : ''; ?>"
                        data-name="<?php echo htmlspecialchars(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? '')); ?>"
                        data-contact="<?php echo htmlspecialchars($request['mobile'] ?? '—'); ?>"
                        data-address="<?php echo htmlspecialchars($request['street'] ?: 'Pulong Buhangin, Santa Maria, Bulacan'); ?>"
                        data-document="<?php echo htmlspecialchars($request['document_type'] ?? ''); ?>"
                        data-release="<?php echo htmlspecialchars($modeOfRelease); ?>"
                        data-payment="<?php echo $paymentStatus; ?>"
                        data-notes="<?php echo htmlspecialchars($request['notes'] ?? 'N/A'); ?>"
                        data-bc-last-name="<?php echo htmlspecialchars($request['bc_last_name'] ?? ''); ?>"
                        data-bc-first-name="<?php echo htmlspecialchars($request['bc_first_name'] ?? ''); ?>"
                        data-bc-middle-name="<?php echo htmlspecialchars($request['middle_name'] ?? ''); ?>"
                        data-bc-suffix="<?php echo htmlspecialchars($request['suffix'] ?? ''); ?>"
                        data-bc-dob="<?php echo htmlspecialchars($request['date_of_birth'] ?? ''); ?>"
                        data-bc-civil-status="<?php echo htmlspecialchars($request['civil_status'] ?? ''); ?>"
                        data-bc-complete-address="<?php echo htmlspecialchars($request['complete_address'] ?? ''); ?>"
                        data-bc-contact-number="<?php echo htmlspecialchars($request['contact_number'] ?? ''); ?>"
                        data-bc-purpose="<?php echo htmlspecialchars($request['purpose'] ?? ''); ?>"
                        data-bc-valid-id="<?php echo htmlspecialchars($request['valid_id_path'] ?? ''); ?>"
                        data-date-residing="<?php echo htmlspecialchars($request['date_started_residing'] ?? ''); ?>"
                        data-household-head="<?php echo htmlspecialchars($request['household_head_name'] ?? ''); ?>"
                        data-monthly-income="<?php echo htmlspecialchars($request['estimated_monthly_income'] ?? $request['li_monthly_income'] ?? ''); ?>"
                        data-dependents="<?php echo htmlspecialchars($request['number_of_dependents'] ?? ''); ?>"
                        data-occupation="<?php echo htmlspecialchars($request['li_occupation'] ?? ''); ?>"
                        data-deceased-name="<?php echo htmlspecialchars($request['deceased_name'] ?? ''); ?>"
                        data-relationship="<?php echo htmlspecialchars($request['relationship'] ?? ''); ?>"
                        data-cause-of-death="<?php echo htmlspecialchars($request['cause_of_death'] ?? ''); ?>"
                        data-date-of-death="<?php echo htmlspecialchars($request['date_of_death'] ?? ''); ?>"
                        data-place-of-death="<?php echo htmlspecialchars($request['place_of_death'] ?? ''); ?>"
                        data-incident-type="<?php echo htmlspecialchars($request['incident_type'] ?? ''); ?>"
                        data-incident-date="<?php echo htmlspecialchars($request['incident_date'] ?? ''); ?>"
                        data-incident-time="<?php echo htmlspecialchars($request['incident_time'] ?? ''); ?>"
                        data-incident-location="<?php echo htmlspecialchars($request['incident_location'] ?? ''); ?>"
                        data-narrative="<?php echo htmlspecialchars($request['narrative'] ?? ''); ?>"
                        data-respondent-name="<?php echo htmlspecialchars($request['respondent_name'] ?? ''); ?>"
                        data-respondent-address="<?php echo htmlspecialchars($request['respondent_address'] ?? ''); ?>"
                        data-business-name="<?php echo htmlspecialchars($request['business_name'] ?? ''); ?>"
                        data-business-type="<?php echo htmlspecialchars($request['business_type'] ?? ''); ?>"
                        data-business-location="<?php echo htmlspecialchars($request['business_location'] ?? ''); ?>"
                        data-children-count="<?php echo htmlspecialchars($request['children_count'] ?? ''); ?>"
                        data-children-ages="<?php echo htmlspecialchars($request['children_ages'] ?? ''); ?>"
                        data-solo-parent-reason="<?php echo htmlspecialchars($request['reason'] ?? ''); ?>"
                        data-place-of-birth="<?php echo htmlspecialchars($request['place_of_birth'] ?? ''); ?>"
                        data-gender="<?php echo htmlspecialchars($request['gender'] ?? ''); ?>"
                        data-bid-occupation="<?php echo htmlspecialchars($request['bid_occupation'] ?? ''); ?>"
                        data-nonempl-civil-status="<?php echo htmlspecialchars($request['ne_civil_status'] ?? ''); ?>"
                        data-nonempl-purpose="<?php echo htmlspecialchars($request['ne_purpose'] ?? ''); ?>"
                        data-ownership-proof="<?php echo htmlspecialchars($request['ownership_proof_path'] ?? ''); ?>"
                        data-proof-residency="<?php echo htmlspecialchars(($request['proof_of_residency_path'] ?? '') ?: ($request['li_proof_residency_path'] ?? '')); ?>"
                        data-supporting-paths="<?php echo htmlspecialchars($request['supporting_paths'] ?? ''); ?>"
                        data-evidence-paths="<?php echo htmlspecialchars($request['evidence_paths'] ?? ''); ?>"
                        data-profile-pic="<?php echo htmlspecialchars($request['profile_pic'] ?? ''); ?>"
                      >View</button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  
                  <?php if (empty($requests)): ?>
                  <tr>
                    <td colspan="8" class="text-center py-4">
                      <div class="text-muted">
                        <i class="bi bi-inbox me-2"></i>
                        No document requests found
                      </div>
                    </td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Request Details Modal -->
        <div class="modal fade" id="requestModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">

              <!-- Header -->
              <div class="modal-header text-white rounded-top-4" style="background: linear-gradient(135deg, #c5202f, #222c8e, #6a0dad);">
                <h5 class="modal-title fw-bold">
                  <i class="bi bi-file-earmark-text me-2"></i> Request Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>

              <!-- Scrollable body -->
              <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                <div class="d-flex justify-content-between align-items-start mb-3">
                  <!-- Profile Picture -->
                  <div class="me-3" style="flex-shrink: 0;">
                    <div id="modal-profile-pic-container" style="width: 80px; height: 80px; border-radius: 50%; overflow: hidden; border: 3px solid #6a0dad; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                      <img id="modal-profile-pic" src="" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                      <i id="modal-profile-pic-placeholder" class="bi bi-person-circle" style="font-size: 2.5rem; color: #ccc;"></i>
                    </div>
                  </div>
                  <div class="flex-grow-1">
                    <h5 class="fw-bold mb-1 text-dark" id="modal-name">—</h5>
                    <div class="small text-dark" id="modal-contact"><strong>Contact:</strong> <span id="modal-contact-value">—</span></div>
                    <div class="small text-dark" id="modal-address"><strong>Address:</strong> <span id="modal-address-value">—</span></div>
                  </div>
                  <div>
                    <span class="badge bg-info rounded-pill" id="modal-request-pill">Request</span>
                  </div>
                </div>

                <div class="row mb-3">
                  <div class="col-md-6">
                    <div class="small text-muted">Request <strong class="text-dark" id="modal-request">—</strong></div>
                  </div>
                  <div class="col-md-6 text-end small text-muted">Date: <strong class="text-dark" id="modal-date">—</strong></div>
                </div>

                <div class="row mb-3">
                  <div class="col-md-6">
                    <div class="fw-semibold small text-muted">Requested Document</div>
                    <div class="mb-2 text-dark" id="modal-document">—</div>
                  </div>
                  <div class="col-md-6">
                    <div class="fw-semibold small text-muted">Status</div>
                    <div class="mb-2"><strong class="text-dark" id="modal-status">—</strong></div>
                  </div>
                </div>

                <hr class="my-3">

                <!-- Applicant Details Section -->
                <div class="mb-3" id="clearance-details-section" style="display: none;">
                  <h6 class="fw-bold text-dark mb-3"><i class="bi bi-person-lines-fill me-2"></i>Applicant Details</h6>
                  
                  <div class="row mb-2">
                    <div class="col-md-3">
                      <small class="text-muted">First Name:</small>
                      <div class="text-dark" id="modal-first-name">—</div>
                    </div>
                    <div class="col-md-3">
                      <small class="text-muted">Middle Name:</small>
                      <div class="text-dark" id="modal-middle-name">—</div>
                    </div>
                    <div class="col-md-3">
                      <small class="text-muted">Last Name:</small>
                      <div class="text-dark" id="modal-last-name">—</div>
                    </div>
                    <div class="col-md-3">
                      <small class="text-muted">Suffix:</small>
                      <div class="text-dark" id="modal-suffix">—</div>
                    </div>
                  </div>

                  <div class="row mb-2">
                    <div class="col-md-3">
                      <small class="text-muted">Date of Birth:</small>
                      <div class="text-dark" id="modal-dob">—</div>
                    </div>
                    <div class="col-md-3">
                      <small class="text-muted">Contact Number:</small>
                      <div class="text-dark" id="modal-contact-number">—</div>
                    </div>
                    <div class="col-md-6" id="soloparent-reason-fields" style="display: none;">
                      <small class="text-muted">Reason for Solo Parent Status:</small>
                      <div class="text-dark" id="modal-solo-parent-reason">—</div>
                    </div>
                  </div>

                  <div class="row mb-2" id="civil-status-col">
                    <div class="col-md-6">
                      <small class="text-muted">Civil Status:</small>
                      <div class="text-dark" id="modal-civil-status">—</div>
                    </div>
                  </div>

                  <div class="row mb-2">
                    <div class="col-md-12">
                      <small class="text-muted">Complete Address:</small>
                      <div class="text-dark" id="modal-complete-address">—</div>
                    </div>
                  </div>

                  <div class="row mb-2" id="purpose-field-row" style="display: none;">
                    <div class="col-md-12">
                      <small class="text-muted">Purpose:</small>
                      <div class="text-dark" id="modal-purpose">—</div>
                    </div>
                  </div>


                  <div class="row mb-2" id="residency-specific-fields" style="display: none;">
                    <div class="col-md-6">
                      <small class="text-muted">Date Started Residing:</small>
                      <div class="text-dark" id="modal-date-residing">—</div>
                    </div>
                    <div class="col-md-6">
                      <small class="text-muted">Household Head Name:</small>
                      <div class="text-dark" id="modal-household-head">—</div>
                    </div>
                  </div>

                  <div class="row mb-2" id="indigency-specific-fields" style="display: none;">
                    <div class="col-md-6">
                      <small class="text-muted">Occupation / Source of Income:</small>
                      <div class="text-dark" id="modal-occupation">—</div>
                    </div>
                    <div class="col-md-6">
                      <small class="text-muted">Estimated Monthly Income:</small>
                      <div class="text-dark" id="modal-monthly-income">—</div>
                    </div>
                  </div>

                  <div class="row mb-2" id="indigency-household-fields" style="display: none;">
                    <div class="col-md-12">
                      <small class="text-muted">Number of Dependents:</small>
                      <div class="text-dark" id="modal-dependents">—</div>
                    </div>
                  </div>

                  <!-- Burial Assistance Fields -->
                  <div class="row mb-2" id="burial-specific-fields" style="display: none;">
                    <div class="col-md-6">
                      <small class="text-muted">Deceased Name:</small>
                      <div class="text-dark" id="modal-deceased-name">—</div>
                    </div>
                    <div class="col-md-6">
                      <small class="text-muted">Relationship to Deceased:</small>
                      <div class="text-dark" id="modal-relationship">—</div>
                    </div>
                  </div>
                  <div class="row mb-2" id="burial-details-fields" style="display: none;">
                    <div class="col-md-6">
                      <small class="text-muted">Cause of Death:</small>
                      <div class="text-dark" id="modal-cause-of-death">—</div>
                    </div>
                    <div class="col-md-6">
                      <small class="text-muted">Date of Death:</small>
                      <div class="text-dark" id="modal-date-of-death">—</div>
                    </div>
                  </div>
                  <div class="row mb-2" id="burial-place-fields" style="display: none;">
                    <div class="col-md-12">
                      <small class="text-muted">Place of Death:</small>
                      <div class="text-dark" id="modal-place-of-death">—</div>
                    </div>
                  </div>

                  <!-- Blotter Report Fields -->
                  <div class="row mb-2" id="blotter-incident-fields" style="display: none;">
                    <div class="col-md-6">
                      <small class="text-muted">Incident Type:</small>
                      <div class="text-dark" id="modal-incident-type">—</div>
                    </div>
                    <div class="col-md-6">
                      <small class="text-muted">Incident Date:</small>
                      <div class="text-dark" id="modal-incident-date">—</div>
                    </div>
                  </div>
                  <div class="row mb-2" id="blotter-time-fields" style="display: none;">
                    <div class="col-md-6">
                      <small class="text-muted">Incident Time:</small>
                      <div class="text-dark" id="modal-incident-time">—</div>
                    </div>
                    <div class="col-md-6">
                      <small class="text-muted">Incident Location:</small>
                      <div class="text-dark" id="modal-incident-location">—</div>
                    </div>
                  </div>
                  <div class="row mb-2" id="blotter-narrative-fields" style="display: none;">
                    <div class="col-md-12">
                      <small class="text-muted">Narrative:</small>
                      <div class="text-dark" style="max-height: 150px; overflow-y: auto;" id="modal-narrative">—</div>
                    </div>
                  </div>
                  <div class="row mb-2" id="blotter-respondent-fields" style="display: none;">
                    <div class="col-md-6">
                      <small class="text-muted">Respondent Name:</small>
                      <div class="text-dark" id="modal-respondent-name">—</div>
                    </div>
                    <div class="col-md-6">
                      <small class="text-muted">Respondent Address:</small>
                      <div class="text-dark" id="modal-respondent-address">—</div>
                    </div>
                  </div>

                  <!-- Business Permit Fields -->
                  <div class="row mb-2" id="business-fields" style="display: none;">
                    <div class="col-md-4 col-12">
                      <small class="text-muted">Business Name:</small>
                      <div class="text-dark" id="modal-business-name">—</div>
                    </div>
                    <div class="col-md-4 col-12">
                      <small class="text-muted">Business Type:</small>
                      <div class="text-dark" id="modal-business-type">—</div>
                    </div>
                    <div class="col-md-4 col-12">
                      <small class="text-muted">Business Location:</small>
                      <div class="text-dark" id="modal-business-location">—</div>
                    </div>
                  </div>

                  <!-- Solo Parent Fields -->
                  <!-- Solo Parent Fields -->
                  <div class="row mb-2" id="soloparent-fields" style="display: none;">
                    <div class="col-6">
                      <small class="text-muted">Number of Children:</small>
                      <div class="text-dark" id="modal-children-count">—</div>
                    </div>
                    <div class="col-6">
                      <small class="text-muted">Children Ages:</small>
                      <div class="text-dark" id="modal-children-ages">—</div>
                    </div>
                  </div>

                  <!-- Barangay ID Fields -->
                  <div class="row mb-2" id="barangay-id-fields" style="display: none;">
                    <div class="col-md-6">
                      <small class="text-muted">Gender:</small>
                      <div class="text-dark" id="modal-gender">—</div>
                    </div>
                    <div class="col-md-6">
                      <small class="text-muted">Occupation:</small>
                      <div class="text-dark" id="modal-bid-occupation">—</div>
                    </div>
                  </div>

                  <!-- No Derogatory Fields -->
                  <div class="row mb-2" id="noderog-fields" style="display: none;">
                    <div class="col-md-12">
                      <small class="text-muted">Place of Birth:</small>
                      <div class="text-dark" id="modal-place-of-birth">—</div>
                    </div>
                  </div>

                  <!-- Non-Employment Fields -->
                  <div class="row mb-2" id="nonempl-fields" style="display: none;">
                    <div class="col-md-6">
                      <small class="text-muted">Civil Status:</small>
                      <div class="text-dark" id="modal-nonempl-civil-status">—</div>
                    </div>
                    <div class="col-md-6">
                      <small class="text-muted">Purpose:</small>
                      <div class="text-dark" id="modal-nonempl-purpose">—</div>
                    </div>
                  </div>

                  <div class="row mb-3 align-items-center g-3">
                    <div class="col-md-6 col-12 d-flex align-items-center gap-2">
                      <span class="text-muted small mb-0">Valid ID:</span>
                      <div>
                        <button type="button" id="modal-valid-id-btn" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#validIdModal" style="display:none;">
                          <i class="bi bi-eye me-1"></i>View Uploaded ID
                        </button>
                        <span id="modal-no-valid-id" class="text-muted small" style="display:none;">No ID uploaded</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Proof of Income Section (Certificate of Indigency only) -->
                <div class="row mb-3 align-items-center g-3" id="proof-of-income-section" style="display: none;">
                  <div class="col-md-12 d-flex align-items-center gap-2">
                    <span class="text-muted small mb-0">Proof of Income:</span>
                    <div>
                      <button type="button" id="modal-proof-income-btn" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#proofIncomeModal" style="display:none;">
                        <i class="bi bi-eye me-1"></i>View Proof of Income
                      </button>
                      <span id="modal-no-proof-income" class="text-muted small" style="display:none;">No proof uploaded</span>
                    </div>
                  </div>
                </div>

                <!-- Proof of Ownership Section (Business Permit only) -->
                <div class="row mb-3 align-items-center g-3" id="ownership-proof-section" style="display: none;">
                  <div class="col-md-12 d-flex align-items-center gap-2">
                    <span class="text-muted small mb-0">Proof of Ownership / Lease:</span>
                    <div>
                      <button type="button" id="modal-ownership-proof-btn" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#attachmentModal" style="display:none;">
                        <i class="bi bi-eye me-1"></i>View Proof
                      </button>
                      <span id="modal-no-ownership-proof" class="text-muted small" style="display:none;">No proof uploaded</span>
                    </div>
                  </div>
                </div>

                <!-- Proof of Residency Section (Low Income Certificate and Barangay ID only) -->
                <div class="row mb-3 align-items-center g-3" id="proof-residency-section" style="display: none;">
                  <div class="col-md-12 d-flex align-items-center gap-2">
                    <span class="text-muted small mb-0">Proof of Residency:</span>
                    <div>
                      <button type="button" id="modal-proof-residency-btn" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#attachmentModal" style="display:none;">
                        <i class="bi bi-eye me-1"></i>View Proof
                      </button>
                      <span id="modal-no-proof-residency" class="text-muted small" style="display:none;">No proof uploaded</span>
                    </div>
                  </div>
                </div>

                <!-- Additional Attachments List -->
                <div class="row mb-2" id="attachments-section" style="display: none;">
                  <div class="col-md-12">
                    <small class="text-muted" id="attachments-title">Additional Attachments:</small>
                    <div id="attachments-list" class="d-flex flex-wrap gap-2"></div>
                    <span id="attachments-empty" class="text-muted small" style="display:none;">No attachments</span>
                  </div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-semibold text-dark">Release & Payment</div>
                    <div class="small text-dark">
                      Mode: <span id="modal-release">—</span> • 
                      Payment: <span id="modal-payment" class="badge bg-secondary">—</span>
                    </div>
                  </div>
                  <div>
                    <button class="btn btn-sm btn-outline-success" id="mark-as-paid-btn" style="display: none;">
                      <i class="bi bi-cash me-1"></i> Mark as Paid
                    </button>
                  </div>
                </div>
              </div>

              <!-- Footer with action buttons -->
              <div class="modal-footer border-0 d-flex justify-content-between">
                <div>
                  <button class="btn btn-success" id="modal-approve"><i class="bi bi-check-circle me-1"></i> Approve</button>
                  <button class="btn btn-danger ms-2" id="modal-reject"><i class="bi bi-x-circle me-1"></i> Reject</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Valid ID Viewer Modal -->
        <div class="modal fade" id="validIdModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
              <div class="modal-header text-white rounded-top-4" style="background: linear-gradient(135deg, #c5202f, #222c8e, #6a0dad);">
                <h5 class="modal-title fw-bold">
                  <i class="bi bi-card-image me-2"></i> Valid ID
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body p-4 text-center">
                <div id="valid-id-image-container">
                  <img id="valid-id-image" src="" alt="Valid ID" class="img-fluid rounded" style="max-height: 70vh; width: auto;">
                </div>
                <div id="valid-id-pdf-container" style="display: none;">
                  <iframe id="valid-id-pdf" src="" style="width: 100%; height: 70vh; border: none; border-radius: 8px;"></iframe>
                </div>
              </div>
              <div class="modal-footer border-0">
                <a id="valid-id-download" href="" download class="btn btn-primary"><i class="bi bi-download me-1"></i> Download</a>
              </div>
            </div>
          </div>
        </div>

        <!-- Proof of Income Viewer Modal -->
        <div class="modal fade" id="proofIncomeModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
              <div class="modal-header text-white rounded-top-4" style="background: linear-gradient(135deg, #c5202f, #222c8e, #6a0dad);">
                <h5 class="modal-title fw-bold">
                  <i class="bi bi-file-earmark-text me-2"></i> Proof of Income
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body p-4 text-center">
                <div id="proof-income-image-container">
                  <img id="proof-income-image" src="" alt="Proof of Income" class="img-fluid rounded" style="max-height: 70vh; width: auto;">
                </div>
                <div id="proof-income-pdf-container" style="display: none;">
                  <iframe id="proof-income-pdf" src="" style="width: 100%; height: 70vh; border: none; border-radius: 8px;"></iframe>
                </div>
              </div>
              <div class="modal-footer border-0">
                <a id="proof-income-download" href="" download class="btn btn-primary"><i class="bi bi-download me-1"></i> Download</a>
              </div>
            </div>
          </div>
        </div>

        <!-- Generic Attachment Viewer Modal -->
        <div class="modal fade" id="attachmentModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
              <div class="modal-header text-white rounded-top-4" style="background: linear-gradient(135deg, #c5202f, #222c8e, #6a0dad);">
                <h5 class="modal-title fw-bold" id="attachment-modal-title">
                  <i class="bi bi-paperclip me-2"></i> Attachment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body p-4 text-center">
                <div id="attachment-image-container">
                  <img id="attachment-image" src="" alt="Attachment" class="img-fluid rounded" style="max-height: 70vh; width: auto;">
                </div>
                <div id="attachment-pdf-container" style="display: none;">
                  <iframe id="attachment-pdf" src="" style="width: 100%; height: 70vh; border: none; border-radius: 8px;"></iframe>
                </div>
              </div>
              <div class="modal-footer border-0">
                <a id="attachment-download" href="" download class="btn btn-primary"><i class="bi bi-download me-1"></i> Download</a>
              </div>
            </div>
          </div>
        </div>

        <!-- Upload Document Modal -->
        <div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
              <div class="modal-header text-white rounded-top-4" style="background: linear-gradient(135deg, #c5202f, #222c8e, #6a0dad);">
                <h5 class="modal-title fw-bold">
                  <i class="bi bi-cloud-upload me-2"></i> Upload Document
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>

              <div class="modal-body p-4">
                <p class="text-muted mb-3">Upload the processed document for the resident to download.</p>
                
                <form id="uploadDocumentForm" enctype="multipart/form-data">
                  <input type="hidden" id="upload-request-id" name="request_id">
                  
                  <div class="mb-3">
                    <label for="document-file" class="form-label fw-semibold">
                      Select Document <span class="text-danger">*</span>
                    </label>
                    <input type="file" class="form-control" id="document-file" name="document" accept=".pdf,.doc,.docx" required>
                    <div class="form-text">Accepted formats: PDF, DOC, DOCX (Max 10MB)</div>
                  </div>

                  <div class="mb-3">
                    <label for="upload-notes" class="form-label fw-semibold">Notes (Optional)</label>
                    <textarea class="form-control" id="upload-notes" name="notes" rows="3" placeholder="Add any notes for the resident..."></textarea>
                  </div>
                </form>
              </div>

              <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitUpload">
                  <i class="bi bi-upload me-1"></i> Upload Document
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Proof Modal -->
        <div class="modal fade" id="paymentProofModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
              <div class="modal-header text-white rounded-top-4" style="background: linear-gradient(135deg, #c5202f, #222c8e, #6a0dad);">
                <h5 class="modal-title fw-bold">
                  <i class="bi bi-receipt me-2"></i> Payment Proof
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body p-4">
                <div id="paymentProofLoading" class="text-center py-5">
                  <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                  <p class="text-muted mt-3">Loading payment details...</p>
                </div>
                <div id="paymentProofContent" style="display: none;">
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <small class="text-muted">Amount Paid:</small>
                      <div class="fw-bold" id="payment-amount">—</div>
                    </div>
                    <div class="col-md-6">
                      <small class="text-muted">Payment Method:</small>
                      <div class="fw-bold" id="payment-method">—</div>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <small class="text-muted">Reference Number:</small>
                      <div class="fw-bold" id="payment-reference">—</div>
                    </div>
                    <div class="col-md-6">
                      <small class="text-muted">Submitted At:</small>
                      <div class="fw-bold" id="payment-submitted">—</div>
                    </div>
                  </div>
                  <div class="mb-3">
                    <small class="text-muted">Status:</small>
                    <div><span class="badge" id="payment-status-badge">—</span></div>
                  </div>
                  <hr>
                  <div class="text-center">
                    <small class="text-muted d-block mb-2">Payment Proof Image:</small>
                    <img id="payment-proof-image" src="" alt="Payment Proof" class="img-fluid rounded shadow-sm" style="max-height: 60vh; width: auto; cursor: pointer;" onclick="window.open(this.src, '_blank')">
                    <p class="text-muted small mt-2"><i class="bi bi-info-circle me-1"></i>Click image to view full size</p>
                  </div>
                </div>
                <div id="paymentProofError" style="display: none;">
                  <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <span id="payment-error-message">Unable to load payment details</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pickup Confirmation Modal -->
        <div class="modal fade" id="pickupConfirmModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
              <div class="modal-header text-white rounded-top-4" style="background: linear-gradient(135deg, #c5202f, #222c8e, #6a0dad);">
                <h5 class="modal-title fw-bold">
                  <i class="bi bi-check-circle me-2"></i> Confirm Document Pickup
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body p-4">
                <div class="text-center mb-4">
                  <i class="bi bi-box-seam text-primary" style="font-size: 3rem;"></i>
                </div>
                <h6 class="text-center mb-3">Has the resident picked up the document?</h6>
                <p class="text-muted text-center mb-4">Confirming will mark this request as <strong>Completed</strong>.</p>
                <input type="hidden" id="pickup-request-id">
              </div>
              <div class="modal-footer border-0 d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                  <i class="bi bi-x-circle me-1"></i> No
                </button>
                <button type="button" class="btn btn-success px-4" id="confirmPickupBtn">
                  <i class="bi bi-check-circle me-1"></i> Yes, Picked Up
                </button>
              </div>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Initialize with server data
    const requestsData = <?php echo json_encode($requests); ?>;
    const stats = <?php echo json_encode($stats); ?>;

    // Helper function to display file (image or PDF)
    function displayFile(filePath, imgId, pdfId, imgContainerId, pdfContainerId, downloadId) {
      const imgContainer = document.getElementById(imgContainerId);
      const pdfContainer = document.getElementById(pdfContainerId);
      const img = document.getElementById(imgId);
      const pdf = document.getElementById(pdfId);
      const downloadBtn = document.getElementById(downloadId);
      
      const isPdf = filePath.toLowerCase().endsWith('.pdf');
      
      if (isPdf) {
        imgContainer.style.display = 'none';
        pdfContainer.style.display = 'block';
        pdf.src = filePath;
        img.src = '';
      } else {
        imgContainer.style.display = 'block';
        pdfContainer.style.display = 'none';
        img.src = filePath;
        pdf.src = '';
      }
      
      downloadBtn.href = filePath;
    }

    // Filter functionality
    document.addEventListener('DOMContentLoaded', function() {
      console.log('DOMContentLoaded fired');
      
      // Setup filters
      const searchName = document.getElementById('searchName');
      const filterStatus = document.getElementById('filterStatus');
      const filterDocType = document.getElementById('filterDocType');
      const filterDate = document.getElementById('filterDate');
      const table = document.getElementById('requestsTable');

      // Ensure status filter options match request statuses (cache-safe)
      if (filterStatus) {
        filterStatus.innerHTML = `
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="processing">Processing</option>
          <option value="approved">Approved</option>
          <option value="completed">Completed</option>
          <option value="rejected">Rejected</option>
        `;
      }
      
      console.log('Elements found:', { searchName: !!searchName, filterStatus: !!filterStatus, filterDocType: !!filterDocType, filterDate: !!filterDate, table: !!table });
      
      if (table) {
        const tbody = table.querySelector('tbody');
        let noResultsRow = null;

        function applyFilters() {
          console.log('applyFilters called');
          const nameFilter = (searchName?.value || '').toLowerCase().trim();
          const statusFilter = (filterStatus?.value || '').toLowerCase();
          const docTypeFilter = (filterDocType?.value || '').toLowerCase().trim();
          const dateFilter = (filterDate?.value || '').trim();

          console.log('Filters:', { nameFilter, statusFilter, docTypeFilter, dateFilter });

          const rows = Array.from(tbody.querySelectorAll('tr'));
          let matchCount = 0;

          rows.forEach((row) => {
            if (row.dataset.placeholder === 'no-results') return;

            const name = (row.cells[1]?.textContent || '').toLowerCase().trim();
            const statusBadge = row.querySelector('td:nth-child(6) .badge');
            const status = statusBadge ? statusBadge.textContent.trim().toLowerCase() : '';
            const docType = (row.dataset.docType || '').toLowerCase();
            const date = (row.cells[3]?.textContent || '').trim();

            const matchesName = !nameFilter || name.includes(nameFilter);
            const matchesStatus = !statusFilter || status === statusFilter;
            const matchesDocType = !docTypeFilter || docType === docTypeFilter;
            const matchesDate = !dateFilter || date === dateFilter;

            const isMatch = matchesName && matchesStatus && matchesDocType && matchesDate;
            row.style.display = isMatch ? '' : 'none';
            if (isMatch) matchCount++;
          });

          if (noResultsRow?.parentElement) noResultsRow.remove();

          if (matchCount === 0) {
            noResultsRow = document.createElement('tr');
            noResultsRow.dataset.placeholder = 'no-results';
            noResultsRow.innerHTML = '<td colspan="9" class="text-center py-4"><div class="text-muted"><i class="bi bi-search me-2"></i>No requests match your filter criteria</div></td>';
            tbody.appendChild(noResultsRow);
          }
          console.log('Match count:', matchCount);
        }

        if (searchName) {
          console.log('Attaching searchName listener');
          let timer;
          searchName.addEventListener('input', () => {
            console.log('searchName input event fired');
            clearTimeout(timer);
            timer = setTimeout(applyFilters, 200);
          });
        }
        if (filterStatus) {
          console.log('Attaching filterStatus listener');
          filterStatus.addEventListener('change', applyFilters);
        }
        if (filterDocType) {
          console.log('Attaching filterDocType listener');
          filterDocType.addEventListener('change', applyFilters);
        }
        if (filterDate) {
          console.log('Attaching filterDate listener');
          filterDate.addEventListener('change', applyFilters);
        }
      } else {
        console.error('Table not found!');
      }

      // REFRESH BUTTON HANDLER
      const refreshBtn = document.getElementById('refreshBtn');
      if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
          const icon = refreshBtn.querySelector('i');
          refreshBtn.classList.add('filter-refresh-btn-loading');
          icon.classList.add('spin-animation');
          refreshBtn.disabled = true;
          // Clear all filters
          if (searchName) searchName.value = '';
          if (filterStatus) filterStatus.value = '';
          if (filterDocType) filterDocType.value = '';
          if (filterDate) filterDate.value = '';
          // Re-apply filters to show all rows
          applyFilters();
          setTimeout(() => {
            icon.classList.remove('spin-animation');
            refreshBtn.classList.remove('filter-refresh-btn-loading');
            refreshBtn.disabled = false;
          }, 800);
        });
      }

      // ATTACH VIEW BUTTON LISTENERS - moved to after function definition
    });

    // Filter functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Handle Pickup Confirmation Modal
      const pickupConfirmModal = document.getElementById('pickupConfirmModal');
      const pickupLinks = document.querySelectorAll('.pickup-confirm-link');
      
      pickupLinks.forEach(link => {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          const requestId = this.getAttribute('data-request-id');
          document.getElementById('pickup-request-id').value = requestId;
        });
      });

      // Handle Pickup Confirmation
      const confirmPickupBtn = document.getElementById('confirmPickupBtn');
      confirmPickupBtn?.addEventListener('click', async function() {
        const requestId = document.getElementById('pickup-request-id').value;
        
        if (!requestId) {
          alert('Error: Request ID not found');
          return;
        }

        // Disable button and show loading
        confirmPickupBtn.disabled = true;
        confirmPickupBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

        try {
          const response = await fetch('update_request_status.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              request_id: requestId,
              status: 'completed'
            })
          });

          const result = await response.json();

          if (result.success) {
            alert('Document pickup confirmed! Request marked as completed.');
            
            // Close modal
            const bsModal = bootstrap.Modal.getInstance(pickupConfirmModal);
            if (bsModal) bsModal.hide();
            
            // Reload page
            setTimeout(() => location.reload(), 500);
          } else {
            alert('Error: ' + result.message);
            confirmPickupBtn.disabled = false;
            confirmPickupBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Yes, Picked Up';
          }
        } catch (error) {
          console.error('Error:', error);
          alert('An error occurred while confirming pickup');
          confirmPickupBtn.disabled = false;
          confirmPickupBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Yes, Picked Up';
        }
      });

      // Handle Payment Proof Modal
      const paymentProofModal = document.getElementById('paymentProofModal');
      if (paymentProofModal) {
        paymentProofModal.addEventListener('show.bs.modal', async function(event) {
          const button = event.relatedTarget;
          const requestId = button.getAttribute('data-request-id');
          
          // Show loading state
          document.getElementById('paymentProofLoading').style.display = 'block';
          document.getElementById('paymentProofContent').style.display = 'none';
          document.getElementById('paymentProofError').style.display = 'none';
          
          try {
            const response = await fetch(`get_payment_proof.php?request_id=${requestId}`);
            const result = await response.json();
            
            if (result.success && result.data) {
              const data = result.data;
              
              // Populate payment details
              document.getElementById('payment-amount').textContent = '₱' + parseFloat(data.amount).toFixed(2);
              document.getElementById('payment-method').textContent = data.payment_method || '—';
              document.getElementById('payment-reference').textContent = data.reference_number || '—';
              document.getElementById('payment-submitted').textContent = data.submitted_at ? new Date(data.submitted_at).toLocaleString() : '—';
              
              // Set status badge
              const statusBadge = document.getElementById('payment-status-badge');
              statusBadge.textContent = data.status || 'pending';
              statusBadge.className = 'badge ' + (data.status === 'verified' ? 'bg-success' : 'bg-warning text-dark');
              
              // Set payment proof image
              const proofImage = document.getElementById('payment-proof-image');
              proofImage.src = data.proof_path || '';
              
              // Show content
              document.getElementById('paymentProofLoading').style.display = 'none';
              document.getElementById('paymentProofContent').style.display = 'block';
            } else {
              throw new Error(result.message || 'No payment proof found');
            }
          } catch (error) {
            console.error('Error loading payment proof:', error);
            document.getElementById('payment-error-message').textContent = error.message || 'Unable to load payment details';
            document.getElementById('paymentProofLoading').style.display = 'none';
            document.getElementById('paymentProofError').style.display = 'block';
          }
        });
      }

      const searchName = document.getElementById('searchName');
      const filterStatus = document.getElementById('filterStatus');
      const filterDocType = document.getElementById('filterDocType');
      const filterDate = document.getElementById('filterDate');
      const table = document.getElementById('requestsTable');
      
      console.log('Filter Elements:', { searchName, filterStatus, filterDocType, filterDate, table });
      
      if (!table) {
        console.error('Table not found!');
        return;
      }
      
      const tbody = table.querySelector('tbody');
      let noResultsRow = null;

      // Function to apply filters (show/hide rows instead of rebuilding)
      function applyFilters() {
        console.log('applyFilters called!');
        const nameFilter = searchName.value.toLowerCase().trim();
        const statusFilter = filterStatus.value.toLowerCase();
        const docTypeFilter = (filterDocType.value || '').toLowerCase().trim();
        const dateFilter = filterDate.value.trim();

        const rows = Array.from(tbody.querySelectorAll('tr'));
        let matchCount = 0;

        console.log('Filters:', { nameFilter, statusFilter, docTypeFilter, dateFilter });

        rows.forEach((row) => {
          // Skip placeholder row if present
          if (row.dataset.placeholder === 'no-results') return;

          const name = (row.cells[1]?.textContent || '').toLowerCase().trim();
          const statusBadge = row.querySelector('.badge');
          const status = statusBadge ? statusBadge.textContent.trim().toLowerCase() : '';
          const docType = (row.dataset.docType || '').trim().toLowerCase();
          const date = (row.cells[3]?.textContent || '').trim();

          const matchesName = !nameFilter || name.includes(nameFilter);
          const matchesStatus = statusFilter === 'all' || !statusFilter || status === statusFilter;
          const matchesDocType = !docTypeFilter || docType === docTypeFilter;
          const matchesDate = !dateFilter || date === dateFilter;

          const isMatch = matchesName && matchesStatus && matchesDocType && matchesDate;
          
          console.log('Row:', { name, status, docType, date, matchesName, matchesStatus, matchesDocType, matchesDate, isMatch });
          
          row.style.display = isMatch ? '' : 'none';
          if (isMatch) matchCount++;
        });

        // Remove existing placeholder
        if (noResultsRow && noResultsRow.parentElement) {
          noResultsRow.remove();
        }

        // Show no results message if needed
        if (matchCount === 0) {
          noResultsRow = document.createElement('tr');
          noResultsRow.dataset.placeholder = 'no-results';
          noResultsRow.innerHTML = `
            <td colspan="8" class="text-center py-4">
              <div class="text-muted">
                <i class="bi bi-search me-2"></i>
                No requests match your filter criteria
              </div>
            </td>
          `;
          tbody.appendChild(noResultsRow);
        }
        
        console.log('Match count:', matchCount);
      }

      // Modal element references
      const requestModalEl = document.getElementById('requestModal');
      const modalName = document.getElementById('modal-name');
      const modalRequest = document.getElementById('modal-request');
      const modalDate = document.getElementById('modal-date');
      const modalDocument = document.getElementById('modal-document');
      const modalStatus = document.getElementById('modal-status');
      const modalRelease = document.getElementById('modal-release');

      function attachViewButtonListeners() {
        const viewButtons = document.querySelectorAll('.view-request');
        viewButtons.forEach(btn => {
          btn.addEventListener('click', async function () {
            const get = (k) => this.getAttribute(k) || '';
            const requestLabel = get('data-request') || '—';
            const requestId = get('data-request-id') || this.closest('tr')?.dataset.requestId || (requestLabel.match(/\d+/) || [requestLabel])[0] || requestLabel;
            
            // Set basic info immediately
            modalName.textContent = get('data-name') || this.closest('tr').cells[1].textContent;
            document.getElementById('modal-contact-value').textContent = get('data-contact') || '—';
            document.getElementById('modal-address-value').textContent = get('data-address') || '—';
            modalRequest.textContent = requestLabel;
            modalDate.textContent = get('data-date') || this.closest('tr').cells[3].textContent;
            modalDocument.textContent = get('data-document') || this.closest('tr').cells[2].textContent;
            
            // Handle profile picture display
            const profilePicData = get('data-profile-pic');
            const profilePicImg = document.getElementById('modal-profile-pic');
            const profilePicPlaceholder = document.getElementById('modal-profile-pic-placeholder');
            if (profilePicData && profilePicData.trim() !== '') {
              profilePicImg.src = profilePicData + '?t=' + Date.now();
              profilePicImg.style.display = 'block';
              profilePicPlaceholder.style.display = 'none';
            } else {
              profilePicImg.style.display = 'none';
              profilePicImg.src = '';
              profilePicPlaceholder.style.display = 'block';
            }
            
            const row = this.closest('tr');
            const statusBadge = row.querySelector('td:nth-child(6) .badge');
            const badgeStatus = statusBadge ? statusBadge.textContent.trim() : '—';
            modalStatus.textContent = badgeStatus;
            
            const releaseMode = get('data-release') || row.dataset.release || '—';
            const paymentStatus = get('data-payment') || row.dataset.payment || '—';
            
            modalRelease.textContent = releaseMode;
            const modalPaymentBadge = document.getElementById('modal-payment');
            modalPaymentBadge.textContent = paymentStatus;
            modalPaymentBadge.className = 'badge ' + (paymentStatus === 'Paid' ? 'bg-success' : 'bg-secondary');
            
            // Show/hide Mark as Paid button
            const markAsPaidBtn = document.getElementById('mark-as-paid-btn');
            if (paymentStatus !== 'Paid') {
              markAsPaidBtn.style.display = 'inline-block';
              markAsPaidBtn.onclick = () => markPaymentAsPaid(requestId, row);
            } else {
              markAsPaidBtn.style.display = 'none';
            }

            requestModalEl.dataset.currentRequest = requestId;
            requestModalEl.dataset.currentRowIndex = Array.from(row.parentElement.children).indexOf(row);

            // Reset: hide all document-specific sections to avoid mixing
            document.getElementById('burial-specific-fields').style.display = 'none';
            document.getElementById('burial-details-fields').style.display = 'none';
            document.getElementById('burial-place-fields').style.display = 'none';
            document.getElementById('blotter-incident-fields').style.display = 'none';
            document.getElementById('blotter-time-fields').style.display = 'none';
            document.getElementById('blotter-narrative-fields').style.display = 'none';
            document.getElementById('blotter-respondent-fields').style.display = 'none';
            document.getElementById('business-fields').style.display = 'none';
            document.getElementById('soloparent-fields').style.display = 'none';
            document.getElementById('soloparent-reason-fields').style.display = 'none';
            document.getElementById('barangay-id-fields').style.display = 'none';
            document.getElementById('noderog-fields').style.display = 'none';
            document.getElementById('residency-specific-fields').style.display = 'none';
            document.getElementById('indigency-specific-fields').style.display = 'none';
            document.getElementById('indigency-household-fields').style.display = 'none';
            document.getElementById('nonempl-fields').style.display = 'none';
            document.getElementById('proof-of-income-section').style.display = 'none';
            document.getElementById('ownership-proof-section').style.display = 'none';
            document.getElementById('proof-residency-section').style.display = 'none';
            document.getElementById('attachments-section').style.display = 'none';
            document.getElementById('purpose-field-row').style.display = 'none';
            document.getElementById('modal-purpose').textContent = '—';
            // Reset valid ID visibility
            document.getElementById('modal-valid-id-btn').style.display = 'none';
            document.getElementById('modal-no-valid-id').style.display = 'inline';
            // Reset hidden global fields
            const civilCol2 = document.getElementById('civil-status-col');
            if (civilCol2) {
              civilCol2.classList.remove('solo-parent-hidden');
            }
            // Purpose field removed from modal

            // Fetch detailed clearance data
            // Helper (inner scope): populate from attributes
            const populateFromAttributesInner = () => {
              const getInner = (k) => this.getAttribute(k) || '';
              const docTypeLocal = getInner('data-document') || '';
              const isBarangayClearance = docTypeLocal === 'Barangay Clearance';
              const hasAnyApplicantField = (
                getInner('data-bc-last-name') || getInner('data-bc-first-name') || getInner('data-bc-middle-name') || getInner('data-bc-suffix') || getInner('data-bc-dob') || getInner('data-bc-civil-status') || getInner('data-bc-complete-address') || getInner('data-bc-contact-number')
              );
              // Only show for Barangay Clearance
              document.getElementById('clearance-details-section').style.display = (isBarangayClearance && hasAnyApplicantField) ? 'block' : 'none';
              document.getElementById('modal-last-name').textContent = getInner('data-bc-last-name') || '—';
              document.getElementById('modal-first-name').textContent = getInner('data-bc-first-name') || '—';
              document.getElementById('modal-middle-name').textContent = getInner('data-bc-middle-name') || '—';
              document.getElementById('modal-suffix').textContent = getInner('data-bc-suffix') || '—';
              document.getElementById('modal-dob').textContent = getInner('data-bc-dob') || '—';
              document.getElementById('modal-civil-status').textContent = getInner('data-bc-civil-status') || '—';
              document.getElementById('modal-complete-address').textContent = getInner('data-bc-complete-address') || '—';
              document.getElementById('modal-contact-number').textContent = getInner('data-bc-contact-number') || '—';
              
              // Show purpose for Barangay Clearance
              if (isBarangayClearance) {
                const purposeRow = document.getElementById('purpose-field-row');
                purposeRow.style.display = 'block';
                document.getElementById('modal-purpose').textContent = getInner('data-bc-purpose') || '—';
              }

              if (docTypeLocal === 'Burial Assistance') {
                document.getElementById('burial-specific-fields').style.display = 'block';
                document.getElementById('burial-details-fields').style.display = 'block';
                document.getElementById('burial-place-fields').style.display = 'block';
                document.getElementById('modal-deceased-name').textContent = getInner('data-deceased-name') || '—';
                document.getElementById('modal-relationship').textContent = getInner('data-relationship') || '—';
                document.getElementById('modal-cause-of-death').textContent = getInner('data-cause-of-death') || '—';
                document.getElementById('modal-date-of-death').textContent = getInner('data-date-of-death') || '—';
                document.getElementById('modal-place-of-death').textContent = getInner('data-place-of-death') || '—';
              }

              if (docTypeLocal === 'Business Permit') {
                document.getElementById('business-fields').style.display = 'block';
                document.getElementById('modal-business-name').textContent = getInner('data-business-name') || '—';
                document.getElementById('modal-business-type').textContent = getInner('data-business-type') || '—';
                document.getElementById('modal-business-location').textContent = getInner('data-business-location') || '—';
              }

              if (docTypeLocal === 'No Derogatory Certificate') {
                document.getElementById('noderog-fields').style.display = 'block';
                document.getElementById('modal-place-of-birth').textContent = getInner('data-place-of-birth') || '—';
              }

              const validIdBtn = document.getElementById('modal-valid-id-btn');
              const noValidId = document.getElementById('modal-no-valid-id');
              const validIdCol = validIdBtn.parentElement.parentElement;
              const idDocTypes = ['Barangay Clearance','Certificate of Residency','Certificate of Indigency','Good Moral Character Certificate','Solo Parent Certificate','Certificate of Non-Employment','Barangay ID','Low Income Certificate','No Derogatory Certificate','Business Permit'];
              const requiresId = idDocTypes.includes(docTypeLocal);
              const validIdPath = getInner('data-bc-valid-id');
              if (!requiresId) {
                validIdCol.style.display = 'none';
              } else {
                validIdCol.style.display = 'block';
                if (validIdPath) {
                  validIdBtn.style.display = 'inline-block';
                  noValidId.style.display = 'none';
                  validIdBtn.onclick = function() {
                    document.getElementById('valid-id-image').src = validIdPath;
                  };
                } else {
                  validIdBtn.style.display = 'none';
                  noValidId.style.display = 'inline';
                }
              }
            };

            try {
              const response = await fetch(`get_clearance_details.php?request_id=${encodeURIComponent(requestId)}`);
              let result;
              try {
                result = await response.json();
              } catch (parseErr) {
                console.warn('JSON parse error, falling back to attributes', parseErr);
                populateFromAttributesInner();
                return;
              }
              console.log('Fetched data:', result);

              if (!result.success || !result.data) {
                console.warn('Falling back to data attributes - fetch returned no data');
                populateFromAttributesInner();
                return;
              }

              if (result.success && result.data) {
                const data = result.data;
                console.log('Full data received:', data);
                console.log('Document type:', data.document_type);
                console.log('Mode of release:', data.mode_of_release);
                console.log('Estimated monthly income:', data.estimated_monthly_income);
                console.log('Number of dependents:', data.number_of_dependents);
                console.log('Proof of income path:', data.proof_of_income_path);
                
                // Update resident info from database
                if (data.resident_first_name && data.resident_last_name) {
                  modalName.textContent = `${data.resident_first_name} ${data.resident_last_name}`;
                }
                if (data.resident_mobile) {
                  document.getElementById('modal-contact-value').textContent = data.resident_mobile;
                }
                if (data.street || data.barangay || data.municipality) {
                  const address = [data.street, data.barangay, data.municipality].filter(Boolean).join(', ');
                  document.getElementById('modal-address-value').textContent = address || '—';
                }
                
                // Update release mode from database
                if (data.mode_of_release) {
                  modalRelease.textContent = data.mode_of_release;
                }
                
                // Show Applicant Details for ALL document types that have form data
                {
                  const docTypesWithApplicantDetails = [
                    'Barangay Clearance',
                    'Certificate of Residency', 
                    'Certificate of Indigency',
                    'Good Moral Character Certificate',
                    'Solo Parent Certificate',
                    'Certificate of Non-Employment',
                    'Barangay ID',
                    'Low Income Certificate',
                    'No Derogatory Certificate',
                    'Business Permit',
                    'Burial Assistance',
                    'Blotter Report'
                  ];
                  const shouldShowApplicant = docTypesWithApplicantDetails.includes(data.document_type);
                  const hasAnyApplicantField = (
                    (data.last_name || data.first_name || data.middle_name || data.suffix || data.date_of_birth || data.civil_status || data.complete_address || data.contact_number)
                    || (data.resident_first_name || data.resident_last_name)
                  );

                  // Show for all document types with applicant data
                  document.getElementById('clearance-details-section').style.display = (shouldShowApplicant && hasAnyApplicantField) ? 'block' : 'none';

                  document.getElementById('modal-last-name').textContent = (data.last_name || data.resident_last_name) || '—';
                  document.getElementById('modal-first-name').textContent = (data.first_name || data.resident_first_name) || '—';
                  document.getElementById('modal-middle-name').textContent = data.middle_name || '—';
                  document.getElementById('modal-suffix').textContent = data.suffix || '—';
                  document.getElementById('modal-dob').textContent = data.date_of_birth || '—';
                  // Handle civil status - hide for Solo Parent Certificate
                  const civilStatusCol = document.getElementById('civil-status-col');
                  if (data.document_type === 'Solo Parent Certificate') {
                    civilStatusCol.style.display = 'none';
                  } else {
                    civilStatusCol.style.display = 'block';
                    document.getElementById('modal-civil-status').textContent = data.civil_status || '—';
                  }
                  document.getElementById('modal-complete-address').textContent = data.complete_address || '—';
                  document.getElementById('modal-contact-number').textContent = data.contact_number || '—';

                  // Show/hide indigency-specific fields
                  const indigencyFields = document.getElementById('indigency-specific-fields');
                  const proofIncomeSection = document.getElementById('proof-of-income-section');
                  const purposeRow = document.getElementById('purpose-field-row');
                  
                  console.log('Checking indigency fields...');
                  console.log('indigency-specific-fields element:', indigencyFields);
                  console.log('proof-of-income-section element:', proofIncomeSection);
                  
                  // Check if this is an indigency certificate (has estimated_monthly_income field)
                  if (data.estimated_monthly_income !== undefined && data.estimated_monthly_income !== null) {
                    console.log('Showing indigency fields');
                    indigencyFields.style.display = 'block';
                    document.getElementById('indigency-household-fields').style.display = 'block';
                    document.getElementById('modal-monthly-income').textContent = data.estimated_monthly_income || '—';
                    document.getElementById('modal-dependents').textContent = data.number_of_dependents || 'Not specified';
                    
                    // Show proof of income section for indigency certificates
                    proofIncomeSection.style.display = 'block';
                    const proofIncomeBtn = document.getElementById('modal-proof-income-btn');
                    const noProofIncome = document.getElementById('modal-no-proof-income');
                    
                    if (data.proof_of_income_path) {
                      proofIncomeBtn.style.display = 'inline-block';
                      noProofIncome.style.display = 'none';
                      
                      // Set proof of income - support both images and PDFs
                      proofIncomeBtn.onclick = function() {
                        displayFile(data.proof_of_income_path, 'proof-income-image', 'proof-income-pdf', 'proof-income-image-container', 'proof-income-pdf-container', 'proof-income-download');
                      };
                    } else {
                      proofIncomeBtn.style.display = 'none';
                      noProofIncome.style.display = 'inline';
                    }
                  } else {
                    indigencyFields.style.display = 'none';
                    proofIncomeSection.style.display = 'none';
                  }

                  // Show purpose for document types that have it
                  const docTypesWithPurpose = [
                    'Barangay Clearance',
                    'Certificate of Residency',
                    'Certificate of Indigency',
                    'Good Moral Character Certificate',
                    'Low Income Certificate',
                    'No Derogatory Certificate'
                  ];
                  if (docTypesWithPurpose.includes(data.document_type) && (data.purpose || data.specific_purpose)) {
                    purposeRow.style.display = 'block';
                    document.getElementById('modal-purpose').textContent = data.purpose || data.specific_purpose || '—';
                  } else {
                    purposeRow.style.display = 'none';
                  }

                  // Set valid ID button
                  const validIdBtn = document.getElementById('modal-valid-id-btn');
                  const noValidId = document.getElementById('modal-no-valid-id');
                  
                  if (data.valid_id_path) {
                    validIdBtn.style.display = 'inline-block';
                    noValidId.style.display = 'none';
                    
                    // Set valid ID - support both images and PDFs
                    validIdBtn.onclick = function() {
                      displayFile(data.valid_id_path, 'valid-id-image', 'valid-id-pdf', 'valid-id-image-container', 'valid-id-pdf-container', 'valid-id-download');
                    };
                  } else {
                    validIdBtn.style.display = 'none';
                    noValidId.style.display = 'inline';
                  }

                  // Ownership / Lease Proof for Business Permit
                  const ownershipSection = document.getElementById('ownership-proof-section');
                  const ownershipBtn = document.getElementById('modal-ownership-proof-btn');
                  const ownershipNone = document.getElementById('modal-no-ownership-proof');
                  if (data.document_type === 'Business Permit') {
                    ownershipSection.style.display = 'block';
                    if (data.ownership_proof_path) {
                      ownershipBtn.style.display = 'inline-block';
                      ownershipNone.style.display = 'none';
                      ownershipBtn.onclick = function() {
                        displayFile(data.ownership_proof_path, 'attachment-image', 'attachment-pdf', 'attachment-image-container', 'attachment-pdf-container', 'attachment-download');
                        document.getElementById('attachment-modal-title').innerHTML = '<i class="bi bi-paperclip me-2"></i> Proof of Ownership / Lease';
                      };
                    } else {
                      ownershipBtn.style.display = 'none';
                      ownershipNone.style.display = 'inline';
                    }
                  } else {
                    ownershipSection.style.display = 'none';
                  }

                  // Proof of Residency for Low Income / Barangay ID
                  const proofResSection = document.getElementById('proof-residency-section');
                  const proofResBtn = document.getElementById('modal-proof-residency-btn');
                  const proofResNone = document.getElementById('modal-no-proof-residency');
                  const proofResPath = data.proof_of_residency_path || data.proof_residency_path;
                  if (data.document_type === 'Low Income Certificate' || data.document_type === 'Barangay ID') {
                    proofResSection.style.display = 'block';
                    if (proofResPath) {
                      proofResBtn.style.display = 'inline-block';
                      proofResNone.style.display = 'none';
                      proofResBtn.onclick = function() {
                        displayFile(proofResPath, 'attachment-image', 'attachment-pdf', 'attachment-image-container', 'attachment-pdf-container', 'attachment-download');
                        document.getElementById('attachment-modal-title').innerHTML = '<i class="bi bi-paperclip me-2"></i> Proof of Residency';
                      };
                    } else {
                      proofResBtn.style.display = 'none';
                      proofResNone.style.display = 'inline';
                    }
                  } else {
                    proofResSection.style.display = 'none';
                  }

                  // Additional attachments (Solo Parent supporting docs, Blotter evidence)
                  const attachmentsSection = document.getElementById('attachments-section');
                  const attachmentsList = document.getElementById('attachments-list');
                  const attachmentsEmpty = document.getElementById('attachments-empty');
                  attachmentsList.innerHTML = '';
                  let files = [];
                  try {
                    if (data.supporting_paths) {
                      const arr = JSON.parse(data.supporting_paths || '[]');
                      if (Array.isArray(arr)) files = files.concat(arr);
                    }
                  } catch {}
                  try {
                    if (data.evidence_paths) {
                      const arr2 = JSON.parse(data.evidence_paths || '[]');
                      if (Array.isArray(arr2)) files = files.concat(arr2);
                    }
                  } catch {}
                  if (files.length) {
                    attachmentsSection.style.display = 'block';
                    // Set appropriate title based on document type
                    const attachmentsTitle = document.getElementById('attachments-title');
                    if (data.document_type === 'Solo Parent Certificate') {
                      attachmentsTitle.textContent = 'Supporting Documents:';
                    } else if (data.document_type === 'Blotter Report') {
                      attachmentsTitle.textContent = 'Evidence Files:';
                    } else {
                      attachmentsTitle.textContent = 'Additional Attachments:';
                    }
                    files.forEach((p, idx) => {
                      const btn = document.createElement('button');
                      btn.type = 'button';
                      btn.className = 'btn btn-sm btn-outline-primary';
                      const isPdf = p.toLowerCase().endsWith('.pdf');
                      btn.innerHTML = isPdf ? '<i class="bi bi-file-pdf me-1"></i>Attachment ' + (idx + 1) : '<i class="bi bi-eye me-1"></i>Attachment ' + (idx + 1);
                      btn.setAttribute('data-bs-toggle', 'modal');
                      btn.setAttribute('data-bs-target', '#attachmentModal');
                      btn.onclick = function() {
                        displayFile(p, 'attachment-image', 'attachment-pdf', 'attachment-image-container', 'attachment-pdf-container', 'attachment-download');
                        document.getElementById('attachment-modal-title').innerHTML = '<i class="bi bi-paperclip me-2"></i> Attachment ' + (idx + 1);
                      };
                      attachmentsList.appendChild(btn);
                    });
                    attachmentsEmpty.style.display = 'none';
                  } else {
                    attachmentsSection.style.display = 'none';
                    attachmentsEmpty.style.display = 'none';
                  }
                }

                // Document-type specific sections
                if (data.document_type === 'Burial Assistance') {
                  document.getElementById('burial-specific-fields').style.display = 'block';
                  document.getElementById('burial-details-fields').style.display = 'block';
                  document.getElementById('burial-place-fields').style.display = 'block';
                  document.getElementById('modal-deceased-name').textContent = `${data.deceased_first_name || ''} ${data.deceased_last_name || ''}`.trim() || '—';
                  document.getElementById('modal-relationship').textContent = data.relationship || '—';
                  document.getElementById('modal-cause-of-death').textContent = data.cause_of_death || '—';
                  document.getElementById('modal-date-of-death').textContent = data.date_of_death || '—';
                  document.getElementById('modal-place-of-death').textContent = data.place_of_death || '—';
                }

                if (data.document_type === 'Blotter Report') {
                  document.getElementById('blotter-incident-fields').style.display = 'block';
                  document.getElementById('blotter-time-fields').style.display = 'block';
                  document.getElementById('blotter-narrative-fields').style.display = 'block';
                  document.getElementById('blotter-respondent-fields').style.display = 'block';
                  document.getElementById('modal-incident-type').textContent = data.incident_type || '—';
                  document.getElementById('modal-incident-date').textContent = data.incident_date || '—';
                  document.getElementById('modal-incident-time').textContent = data.incident_time || '—';
                  document.getElementById('modal-incident-location').textContent = data.incident_location || '—';
                  document.getElementById('modal-narrative').textContent = data.narrative || '—';
                  document.getElementById('modal-respondent-name').textContent = data.respondent_name || '—';
                  document.getElementById('modal-respondent-address').textContent = data.respondent_address || '—';
                }

                if (data.document_type === 'Business Permit') {
                  document.getElementById('business-fields').style.display = 'block';
                  document.getElementById('modal-business-name').textContent = data.business_name || '—';
                  document.getElementById('modal-business-type').textContent = data.business_type || '—';
                  document.getElementById('modal-business-location').textContent = data.business_location || '—';
                }

                if (data.document_type === 'Solo Parent Certificate') {
                  document.getElementById('soloparent-fields').style.display = 'block';
                  document.getElementById('soloparent-reason-fields').style.display = 'block';
                  document.getElementById('modal-children-count').textContent = data.children_count || '—';
                  document.getElementById('modal-children-ages').textContent = data.children_ages || '—';
                  document.getElementById('modal-solo-parent-reason').textContent = data.reason || '—';
                }

                if (data.document_type === 'Certificate of Residency') {
                  document.getElementById('residency-specific-fields').style.display = 'flex';
                  document.getElementById('modal-date-residing').textContent = data.date_started_residing || '—';
                  document.getElementById('modal-household-head').textContent = data.household_head_name || '—';
                }

                if (data.document_type === 'Certificate of Non-Employment') {
                  document.getElementById('nonempl-fields').style.display = 'block';
                  document.getElementById('modal-nonempl-civil-status').textContent = data.civil_status || '—';
                  document.getElementById('modal-nonempl-purpose').textContent = data.purpose || '—';
                }

                if (data.document_type === 'No Derogatory Certificate') {
                  document.getElementById('noderog-fields').style.display = 'block';
                  document.getElementById('modal-place-of-birth').textContent = data.place_of_birth || '—';
                }
              }
            } catch (error) {
              console.error('Error fetching clearance details:', error);
              // Fallback from attributes
              populateFromAttributesInner();
            }
          });
        });
      }

      // ATTACH VIEW BUTTON LISTENERS
      attachViewButtonListeners();

      // Mark as Paid function
      async function markPaymentAsPaid(requestId, row) {
        if (!confirm('Mark this payment as paid?')) return;
        
        try {
          const response = await fetch('mark_as_paid.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: requestId })
          });
          
          const result = await response.json();
          
          if (result.success) {
            alert('Payment marked as paid successfully!');
            // Update the row's payment badge
            const paymentBadge = row.querySelector('.payment-badge');
            if (paymentBadge) {
              paymentBadge.textContent = 'Paid';
              paymentBadge.className = 'badge payment-badge bg-success';
            }
            // Update modal
            const modalPaymentBadge = document.getElementById('modal-payment');
            if (modalPaymentBadge) {
              modalPaymentBadge.textContent = 'Paid';
              modalPaymentBadge.className = 'badge bg-success';
            }
            // Hide Mark as Paid button
            document.getElementById('mark-as-paid-btn').style.display = 'none';
            // Close and reopen modal to refresh
            const bsModal = bootstrap.Modal.getInstance(requestModalEl);
            if (bsModal) {
              bsModal.hide();
              setTimeout(() => location.reload(), 500);
            }
          } else {
            alert('Error: ' + result.message);
          }
        } catch (error) {
          console.error('Error marking payment as paid:', error);
          alert('An error occurred while marking payment as paid');
        }
      }

      // Handle upload document modal
      const uploadLinks = document.querySelectorAll('.upload-document-link');
      uploadLinks.forEach(link => {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          const requestId = this.getAttribute('data-request-id');
          document.getElementById('upload-request-id').value = requestId;
          // Reset form
          document.getElementById('uploadDocumentForm').reset();
        });
      });

      // Handle Approve/Reject buttons in Request Details Modal
      const approveBtn = document.getElementById('modal-approve');
      const rejectBtn = document.getElementById('modal-reject');
      
      async function updateRequestStatus(status) {
        const requestId = requestModalEl.dataset.currentRequest;
        if (!requestId) {
          alert('Error: Request ID not found');
          return;
        }

        const btn = status === 'approved' ? approveBtn : rejectBtn;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

        try {
          const response = await fetch('update_request_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: requestId, status: status })
          });

          const result = await response.json();

          if (result.success) {
            alert(`Request ${status} successfully!`);
            // Close modal
            const bsModal = bootstrap.Modal.getInstance(requestModalEl);
            if (bsModal) bsModal.hide();
            // Reload page
            setTimeout(() => location.reload(), 500);
          } else {
            alert('Error: ' + result.message);
            btn.disabled = false;
            btn.innerHTML = originalText;
          }
        } catch (error) {
          console.error('Error updating status:', error);
          alert('An error occurred while updating the request');
          btn.disabled = false;
          btn.innerHTML = originalText;
        }
      }

      if (approveBtn) {
        approveBtn.addEventListener('click', () => updateRequestStatus('approved'));
      }
      if (rejectBtn) {
        rejectBtn.addEventListener('click', () => updateRequestStatus('rejected'));
      }

      // Handle document upload
      const submitUploadBtn = document.getElementById('submitUpload');
      submitUploadBtn.addEventListener('click', async function() {
        const form = document.getElementById('uploadDocumentForm');
        const fileInput = document.getElementById('document-file');
        
        if (!fileInput.files.length) {
          alert('Please select a document to upload');
          return;
        }

        const formData = new FormData(form);
        
        // Disable button and show loading
        submitUploadBtn.disabled = true;
        submitUploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Uploading...';

        try {
          const response = await fetch('upload_document.php', {
            method: 'POST',
            body: formData
          });

          const result = await response.json();

          if (result.success) {
            alert('Document uploaded successfully!');
            
            // Close modal
            const uploadModal = bootstrap.Modal.getInstance(document.getElementById('uploadModal'));
            if (uploadModal) uploadModal.hide();

            // Reload page
            setTimeout(() => location.reload(), 500);
          } else {
            alert('Error: ' + result.message);
            submitUploadBtn.disabled = false;
            submitUploadBtn.innerHTML = '<i class="bi bi-upload me-1"></i> Upload Document';
          }
        } catch (error) {
          console.error('Upload error:', error);
          alert('An error occurred while uploading the document');
          submitUploadBtn.disabled = false;
          submitUploadBtn.innerHTML = '<i class="bi bi-upload me-1"></i> Upload Document';
        }
      });
    });

    // Staff logout functionality
    document.getElementById('staffLogoutBtn')?.addEventListener('click', async function() {
      try {
        const response = await fetch('log_logout.php', {
          method: 'POST',
          cache: 'no-store'
        });
        
        const result = await response.json();
        window.location.href = 'staff-login.html';
      } catch (error) {
        console.error('Logout error:', error);
        window.location.href = 'staff-login.html';
      }
    });

    // Initialize all popovers for stats cards
    document.addEventListener('DOMContentLoaded', function() {
      // Notification handling
      const notifBadge = document.getElementById('notifBadge');

      // Get icon and color based on notification type
      function getNotificationIcon(type) {
        const icons = {
          'document_request': { icon: 'bi-file-earmark-text', color: 'text-primary' },
          'payment_sent': { icon: 'bi-credit-card', color: 'text-success' },
          'new_registration': { icon: 'bi-person-plus', color: 'text-info' },
          'profile_update': { icon: 'bi-pencil-square', color: 'text-warning' },
          'default': { icon: 'bi-bell-fill', color: 'text-primary' }
        };
        return icons[type] || icons['default'];
      }

      // Format time ago
      function timeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
        if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
        return date.toLocaleDateString();
      }

      async function fetchNotifications() {
        try {
          const res = await fetch('get_notifications.php', { cache: 'no-store' });
          const data = await res.json();
          const panel = document.querySelector('.notification-panel');
          if (!panel) return;
          
          panel.innerHTML = '';

          // Normalize response shape - handle both formats
          let notifs = [];
          let unreadCount = 0;
          
          if (data && data.success) {
            notifs = data.data || [];
            unreadCount = data.unread_count || 0;
          } else if (Array.isArray(data)) {
            notifs = data;
            unreadCount = data.filter(n => !n.is_read).length;
          }

          const badge = document.getElementById('notifBadge');
          if (badge) {
            badge.textContent = unreadCount;
            badge.style.display = unreadCount > 0 ? 'inline-block' : 'none';
          }

          if (notifs.length === 0) {
            panel.innerHTML = '<div class="p-3 text-center text-muted"><i class="bi bi-bell-slash me-2"></i>No notifications yet</div>';
            return;
          }

          notifs.forEach(n => {
            const item = document.createElement('div');
            const isRead = Number(n.is_read ?? n.read ?? 0) === 1;
            const iconInfo = getNotificationIcon(n.type || n.notification_type);
            const timeStr = n.created_at ? timeAgo(n.created_at) : '';
            
            item.className = 'notif-item p-3 border-bottom' + (isRead ? '' : ' unread');
            item.style.cursor = 'pointer';
            item.innerHTML = `
              <div class="d-flex align-items-start">
                <i class="bi ${iconInfo.icon} me-2 ${iconInfo.color} fs-5"></i>
                <div class="flex-grow-1">
                  <div class="d-flex justify-content-between align-items-start">
                    <h6 class="mb-1 fw-semibold text-dark" style="font-size: 0.9rem;">${escapeHtml(n.title || 'Notification')}</h6>
                    <small class="text-muted ms-2" style="white-space: nowrap;">${timeStr}</small>
                  </div>
                  <p class="mb-0 small text-secondary">${escapeHtml(n.message || '')}</p>
                </div>
              </div>
            `;

            item.addEventListener('click', async () => {
              if (!isRead) {
                try {
                  await fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: n.id })
                  });
                } catch (err) {
                  console.error('mark read failed', err);
                }
              }
              // Navigate based on notification type
              if (n.type === 'document_request' || n.type === 'payment_sent') {
                window.location.reload();
              } else if (n.type === 'new_registration' || n.type === 'profile_update') {
                window.location.href = 'sidebar-residents.php';
              } else {
                window.location.reload();
              }
            });

            panel.appendChild(item);
          });
        } catch (err) {
          console.error('Failed to fetch notifications', err);
          const panel = document.querySelector('.notification-panel');
          if (panel) {
            panel.innerHTML = '<div class="p-3 text-center text-muted"><i class="bi bi-exclamation-circle me-2"></i>Failed to load notifications</div>';
          }
        }
      }

      function escapeHtml(str) {
        if (!str && str !== 0) return '';
        return String(str)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#39;');
      }

      // Initial load and polling
      fetchNotifications();
      setInterval(fetchNotifications, 60000); // Poll every 1 minute

      // Breakdown data and popover functionality
      const breakdownData = <?php echo json_encode($breakdown); ?>;

      // Desired ordering for document types (matches database names exactly)
      const docOrder = [
        'Barangay Clearance',
        'Certificate of Residency',
        'Certificate of Indigency',
        'Good Moral Character Certificate',
        'Business Permit',
        'Solo Parent Certificate',
        'Certificate of No Derogatory Record',
        'Blotter Report',
        'Barangay ID',
        'Low Income Certificate',
        'Certificate of Non-Employment',
        'Burial Assistance',
        'Other (Please Specify)'
      ];
      
      function getDocumentIcon(docType) {
        switch(docType) {
          case 'Barangay Clearance':
            return 'bi-file-earmark-check';
          case 'Certificate of Residency':
            return 'bi-house-door';
          case 'Certificate of Indigency':
            return 'bi-currency-dollar';
          case 'Good Moral Character Certificate':
            return 'bi-award';
          case 'Business Permit':
            return 'bi-shop';
          case 'Solo Parent Certificate':
            return 'bi-people';
          case 'Certificate of No Derogatory Record':
            return 'bi-shield-check';
          case 'Blotter Report':
            return 'bi-exclamation-triangle';
          case 'Barangay ID':
            return 'bi-person-badge';
          case 'Low Income Certificate':
            return 'bi-wallet2';
          case 'Certificate of Non-Employment':
            return 'bi-briefcase';
          case 'Burial Assistance':
            return 'bi-heart';
          case 'Other (Please Specify)':
            return 'bi-file-earmark-text';
          default:
            return 'bi-file-earmark';
        }
      }

      // Initialize popovers on the cards
      const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
      console.log('Initializing stat card popovers:', popoverTriggerList.length);
    });
  </script>
  <!-- Robust popover + logout init (independent of other JS) -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      console.log('=== FINAL INIT SCRIPT STARTING ===');
      
      // Breakdown data
      const breakdownData = <?php echo json_encode($breakdown); ?>;
      console.log('Breakdown data loaded:', breakdownData);
      
      const docOrder = [
        'Barangay Clearance',
        'Certificate of Residency',
        'Certificate of Indigency',
        'Good Moral Character Certificate',
        'Business Permit',
        'Solo Parent Certificate',
        'Certificate of No Derogatory Record',
        'Blotter Report',
        'Barangay ID',
        'Low Income Certificate',
        'Certificate of Non-Employment',
        'Burial Assistance',
        'Other (Please Specify)'
      ];

      function getDocumentIcon(docType) {
        switch(docType) {
          case 'Barangay Clearance':
            return 'bi-file-earmark-check';
          case 'Certificate of Residency':
            return 'bi-house-door';
          case 'Certificate of Indigency':
            return 'bi-currency-dollar';
          case 'Good Moral Character Certificate':
            return 'bi-award';
          case 'Business Permit':
            return 'bi-shop';
          case 'Solo Parent Certificate':
            return 'bi-people';
          case 'Certificate of No Derogatory Record':
            return 'bi-shield-check';
          case 'Blotter Report':
            return 'bi-exclamation-triangle';
          case 'Barangay ID':
            return 'bi-person-badge';
          case 'Low Income Certificate':
            return 'bi-wallet2';
          case 'Certificate of Non-Employment':
            return 'bi-briefcase';
          case 'Burial Assistance':
            return 'bi-heart';
          case 'Other (Please Specify)':
            return 'bi-file-earmark-text';
          default:
            return 'bi-file-earmark';
        }
      }

      function generateBreakdownContent(type) {
        const data = breakdownData[type] || {};
        console.log('Generating content for:', type, data);
        
        let header = '';
        let iconClass = '';
        let headerColor = '';
        
        if (type === 'total') {
          header = 'Total Document Requests';
          iconClass = 'bi-folder2-open';
          headerColor = '#3b82f6';
        } else if (type === 'pending') {
          header = 'Pending Requests';
          iconClass = 'bi-hourglass-split';
          headerColor = '#f59e0b';
        } else if (type === 'approved') {
          header = 'Approved Requests';
          iconClass = 'bi-check-circle-fill';
          headerColor = '#10b981';
        } else if (type === 'rejected') {
          header = 'Rejected Requests';
          iconClass = 'bi-x-circle-fill';
          headerColor = '#ef4444';
        }

        let html = `
          <div style="width: 240px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
            <div style="display: flex; flex-direction: column; gap: 3px;">`;
        
        // Filter logic: for pending/approved/rejected, only show items with counts > 0
        const itemsToShow = (type === 'total') 
          ? docOrder 
          : docOrder.filter(docName => (data[docName] || 0) > 0);
        
        if (itemsToShow.length === 0) {
          html += `
            <div style="text-align: center; padding: 10px; color: #9ca3af;">
              <i class="bi bi-inbox" style="font-size: 1rem; display: block; margin-bottom: 4px; opacity: 0.4;"></i>
              <span style="font-size: 0.65rem;">No ${type} requests</span>
            </div>`;
        } else {
          itemsToShow.forEach(docName => {
            const count = data[docName] || 0;
            const displayCount = count > 0 ? count : '-';
            const icon = getDocumentIcon(docName);
            
            html += `
              <div style="display: flex; align-items: center; gap: 6px; padding: 2px 0;">
                <i class="${icon}" style="color: #374151; font-size: 0.7rem; flex-shrink: 0;"></i>
                <span style="font-size: 0.875rem; color: #000000;">${docName}: ${displayCount}</span>
              </div>`;
          });
        }

        html += `
            </div>
          </div>`;
        
        return html;
      }

      // Initialize popovers
      try {
        const els = document.querySelectorAll('[data-bs-toggle="popover"]');
        console.log('Initializing popovers on elements:', els.length);
        
        els.forEach(el => {
          const breakdownType = el.getAttribute('data-breakdown-type');
          let titleText = '';
          switch (breakdownType) {
            case 'total': titleText = 'Total Document Requests'; break;
            case 'pending': titleText = 'Pending Requests'; break;
            case 'approved': titleText = 'Approved Requests'; break;
            case 'rejected': titleText = 'Rejected Requests'; break;
          }
          
          const content = generateBreakdownContent(breakdownType);
          console.log('Creating popover for:', breakdownType, 'with content length:', content.length);
          
          new bootstrap.Popover(el, {
            title: titleText,
            content: content,
            html: true,
            placement: 'top',
            trigger: 'hover',
            container: 'body'
          });
        });
        
        console.log('Popovers initialized successfully!');
      } catch (e) {
        console.error('Popover init failed:', e);
      }

      // Logout handler
      async function handleLogout() {
        try {
          await fetch('log_logout.php', { method: 'POST', cache: 'no-store' });
        } catch (err) {
          console.warn('Logout fetch failed, proceeding to redirect', err);
        } finally {
          window.location.href = 'staff-login.html';
        }
      }
      const logoutBtn = document.getElementById('staffLogoutBtn');
      if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
          e.preventDefault();
          handleLogout();
        });
      }
      document.addEventListener('click', function(e) {
        const targetBtn = e.target.closest('#staffLogoutBtn');
        if (targetBtn) {
          e.preventDefault();
          handleLogout();
        }
      });
    });
  </script>
</body>
</html>