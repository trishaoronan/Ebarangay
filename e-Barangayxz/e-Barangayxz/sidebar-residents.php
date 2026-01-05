<?php
// sidebar-residents.php
session_start();
include 'db.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Location: staff-login.html');
    exit;
}

// Fetch all residents with their latest request status
$residentsQuery = "
    SELECT 
        r.id,
        r.first_name,
        r.last_name,
        r.email,
        r.mobile,
        r.street,
        r.barangay,
        r.municipality,
        r.gender,
        r.birthday,
        r.civil_status,
        r.created_at,
        r.is_active,
        IFNULL(r.status, 'active') AS status,
        r.date_of_death,
        r.death_remarks,
        r.profile_pic,
        TIMESTAMPDIFF(YEAR, r.birthday, CURDATE()) AS age,
        (SELECT req.document_type 
         FROM requests req 
         WHERE req.resident_id = r.id 
         ORDER BY req.requested_at DESC 
         LIMIT 1) AS latest_document_type,
        (SELECT req.status 
         FROM requests req 
         WHERE req.resident_id = r.id 
         ORDER BY req.requested_at DESC 
         LIMIT 1) AS latest_status,
        (SELECT req.requested_at 
         FROM requests req 
         WHERE req.resident_id = r.id 
         ORDER BY req.requested_at DESC 
         LIMIT 1) AS latest_request_date
    FROM residents r
    ORDER BY r.created_at DESC
";
$residentsResult = $conn->query($residentsQuery);
$residents = [];
if ($residentsResult) {
    while ($row = $residentsResult->fetch_assoc()) {
        $residents[] = $row;
    }
}

// Fetch all requests for each resident to use in request history modal
$allRequests = [];
if (!empty($residents)) {
    $residentIds = array_column($residents, 'id');
    $idsPlaceholder = implode(',', array_fill(0, count($residentIds), '?'));
    $requestsQuery = "SELECT id, resident_id, document_type, status, requested_at, given_at, notes 
                      FROM requests 
                      WHERE resident_id IN ($idsPlaceholder) 
                      ORDER BY requested_at DESC";
    $stmt = $conn->prepare($requestsQuery);
    if ($stmt) {
        $types = str_repeat('i', count($residentIds));
        $stmt->bind_param($types, ...$residentIds);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($req = $result->fetch_assoc()) {
            $allRequests[] = $req;
        }
        $stmt->close();
    }
}

// Calculate statistics
$totalResidents = count($residents);
$minors = 0;
$adults = 0;
$seniors = 0;

foreach ($residents as $resident) {
    $age = $resident['age'];
    if ($age < 18) {
        $minors++;
    } elseif ($age >= 60) {
        $seniors++;
    } else {
        $adults++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>eBarangay | Staff Dashboard - Residents</title>
  <link rel="icon" type="image/jpeg" href="pics/eb-logo.jpg"> <!--FAVICON-->
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <style>
    /* Residents Refresh Button - match Requests section */
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
    /* --- Notification Fix --- */
    .notification-panel {
      max-height: 300px;
      overflow-y: auto;
    }
    .extra-notifs {
      display: none;
      transition: all 0.3s ease;
    }
    .extra-notifs.show {
      display: block;
    }
    .notif-item:hover {
      background-color: #f8f9fa;
    }
    /* Resident Modal Action Buttons - responsive and polished */
    .modal .action-buttons { display: flex; gap: 0.5rem; align-items: center; }
    .modal .action-buttons .btn { min-width: 120px; }
    /* On very small screens stack buttons vertically and make them full-width */
    @media (max-width: 575px) {
      .modal .action-buttons { flex-direction: column; }
      .modal .action-buttons .btn { width: 100%; min-width: 0; }
    }
    /* Confirmation modal actions - responsive */
    .confirm-actions { display: flex; gap: 0.5rem; }
    .confirm-actions .btn { min-width: 120px; }
    @media (max-width: 575px) {
      .confirm-actions { flex-direction: column; }
      .confirm-actions .btn { width: 100%; min-width: 0; }
    }
    /* Ensure Add Resident modal header uses the same gradient and fits perfectly */
    #addResidentModal .modal-header {
      background: linear-gradient(135deg, #c5202f, #222c8e, #6a0dad) !important;
      color: #fff !important;
      border-top-left-radius: 0.75rem !important;
      border-top-right-radius: 0.75rem !important;
    }
    /* Gradient button matching header gradient */
    .gradient-btn {
      background: linear-gradient(135deg, #c5202f, #222c8e, #6a0dad);
      color: #fff;
      border: 0;
      box-shadow: 0 6px 18px rgba(33, 37, 41, 0.08);
      transition: transform 120ms ease, filter 120ms ease, box-shadow 120ms ease;
    }
    .gradient-btn:hover { filter: brightness(0.95); }
    .gradient-btn:active { transform: translateY(1px); }
    .gradient-btn:focus { outline: none; box-shadow: 0 0 0 0.25rem rgba(106,13,173,0.18); }
    /* Make gradient buttons adapt on small screens inside modals */
    @media (max-width: 575px) {
      .modal-footer .gradient-btn { width: 100%; }
    }
    /* Close button in gradient header (circular white-on-gradient) */
    .close-gradient-btn {
      width: 38px;
      height: 38px;
      padding: 0.25rem;
      border-radius: 50%;
      background: rgba(255,255,255,0.12);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: background 120ms ease, transform 80ms ease;
      border: 0;
    }
    .close-gradient-btn:hover { background: rgba(255,255,255,0.18); transform: translateY(-1px); }
    .close-gradient-btn:active { transform: translateY(0); }
    /* Make sure the white close icon remains visible */
    .close-gradient-btn.btn-close-white { filter: none; }
    
    /* Bootstrap validation styling fix - ensure invalid-feedback shows */
    .form-control.is-invalid,
    .form-select.is-invalid {
      border-color: #dc3545;
      padding-right: calc(1.5em + 0.75rem);
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right calc(0.375em + 0.1875rem) center;
      background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }
    .form-control.is-invalid:focus,
    .form-select.is-invalid:focus {
      border-color: #dc3545;
      box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
    }
    .invalid-feedback {
      display: none;
      width: 100%;
      margin-top: 0.25rem;
      font-size: 0.875em;
      color: #dc3545;
    }
    .is-invalid ~ .invalid-feedback {
      display: block;
    }
    
    /* Red asterisk for required fields */
    .text-danger {
      color: #dc3545 !important;
    }
    
    /* Filter and Refresh Button Styling - consistent across all sections */
    #filterBtn {
      background-color: #007bff;
      color: #fff;
      border: none;
      transition: all 120ms ease;
    }
    #filterBtn:hover {
      background-color: #0056b3;
      transform: translateY(-1px);
      box-shadow: 0 6px 12px rgba(0, 123, 255, 0.2);
    }
    #filterBtn:active {
      transform: translateY(0);
    }
    
    #refreshBtn {
      background-color: transparent;
      color: #4B0082;
      border: 2px solid #4B0082;
      transition: all 120ms ease;
    }
    #refreshBtn:hover {
      background-color: #4B0082;
      color: #fff;
      transform: translateY(-1px);
      box-shadow: 0 6px 12px rgba(75, 0, 130, 0.2);
    }
    #refreshBtn:active {
      transform: translateY(0);
    }
    
    /* Password toggle button styling */
    .password-toggle {
      border-color: #ced4da;
      background: #fff;
    }
    .password-toggle:hover {
      background: #f8f9fa;
      border-color: #ced4da;
    }
    .password-toggle:focus {
      box-shadow: none;
      border-color: #ced4da;
    }
    .password-info-btn {
      border-color: #dc3545;
    }
    .password-info-btn:hover {
      background: #dc3545;
      color: #fff;
    }
    .input-group .form-control.is-invalid {
      border-color: #dc3545;
    }
    .input-group .form-control.is-invalid ~ .password-toggle {
      border-color: #dc3545;
    }
  </style>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top" style="padding: 0.5rem 0; margin-top: -8px; padding-top: calc(0.5rem + 8px); z-index: 1050;">
    <div class="container-fluid ">
      <a class="navbar-brand d-flex align-items-center" href="staff-dashboard.html">
        <img src="pics/eb-logo.jpg" alt="eBarangay Logo" class="me-2" style="height:40px; width:auto" />
        <span>STAFF DASHBOARD</span>
      </a>


      <div class="d-flex align-items-center position-relative">
        <!-- Notification Dropdown -->
        <div class="dropdown me-2">
          <button class="btn btn-light position-relative" id="notifButton" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
            <i class="bi bi-bell fs-5"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifBadge">1</span>
          </button>

          <div class="dropdown-menu dropdown-menu-end shadow-sm p-0 border-0" id="notifDropdown" style="width:340px;">
            <div class="p-3 border-bottom bg-light">
              <h6 class="fw-bold mb-0">Notifications</h6>
            </div>

            <!-- Main notifications -->
            <div class="notification-panel">
              <div class="notif-item p-3 border-bottom unread">
                <div class="d-flex align-items-start">
                  <i class="bi bi-bell-fill me-2 text-primary fs-5"></i>
                  <div>
                    <h6 class="mb-1 fw-semibold text-dark">Document Request</h6>
                    <p class="mb-0 small text-secondary">Juan Dela Cruz requested a Barangay Clearance document.</p>
                  </div>
                </div>
              </div>

              <div class="notif-item p-3 border-bottom">
                <div class="d-flex align-items-start">
                  <i class="bi bi-file-earmark-text me-2 text-primary fs-5"></i>
                  <div>
                    <h6 class="mb-1 fw-semibold text-dark">Document Submitted</h6>
                    <p class="mb-0 small text-secondary">Donny Pangilinan submitted a Barangay Clearance request.</p>
                  </div>
                </div>
              </div>

              <div class="notif-item p-3 border-bottom">
                <div class="d-flex align-items-start">
                  <i class="bi bi-hourglass-split me-2 text-primary fs-5"></i>
                  <div>
                    <h6 class="mb-1 fw-semibold text-dark">Approval Pending</h6>
                    <p class="mb-0 small text-secondary">Waiting for approval of Maria Santos' Business Permit request.</p>
                  </div>
                </div>
              </div>

              <!-- Hidden extra notifications -->
              <div class="extra-notifs mt-0">
                <div class="notif-item p-3 border-bottom">
                  <div class="d-flex align-items-start">
                    <i class="bi bi-calendar-event me-2 text-primary fs-5"></i>
                    <div>
                      <h6 class="mb-1 fw-semibold text-dark">Reminder</h6>
                      <p class="mb-0 small text-secondary">Barangay meeting scheduled for October 25.</p>
                    </div>
                  </div>
                </div>

                <div class="notif-item p-3 border-bottom">
                  <div class="d-flex align-items-start">
                    <i class="bi bi-info-circle me-2 text-primary fs-5"></i>
                    <div>
                      <h6 class="mb-1 fw-semibold text-dark">System Update</h6>
                      <p class="mb-0 small text-secondary">New staff portal features have been added.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <button class="btn btn-outline-light btn-sm" id="staffLogoutBtn">Logout</button>
      </div>
    </div>
  </nav>

  <!-- First Login Password Change Modal -->
  <div class="modal fade" id="firstLoginPasswordModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="firstLoginPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-warning">
          <h5 class="modal-title" id="firstLoginPasswordModalLabel">
            <i class="bi bi-shield-exclamation me-2"></i>Password Change Required
          </h5>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning mb-3">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Security Notice:</strong> You are using the default password. For your account's security, you must change your password before continuing.
          </div>
          
          <form id="firstLoginPasswordForm">
            <div class="mb-3">
              <label for="firstLoginOldPassword" class="form-label fw-semibold">Old Password</label>
              <input type="password" class="form-control" id="firstLoginOldPassword" placeholder="Enter current password" required>
            </div>
            <div class="mb-3">
              <label for="firstLoginNewPassword" class="form-label fw-semibold">New Password</label>
              <input type="password" class="form-control" id="firstLoginNewPassword" placeholder="Enter new password" required>
            </div>
            
            <div class="mb-3">
              <label for="firstLoginConfirmPassword" class="form-label fw-semibold">Confirm New Password</label>
              <input type="password" class="form-control" id="firstLoginConfirmPassword" placeholder="Confirm new password" required>
            </div>

            <div class="small text-muted mb-3">
              <strong>Password requirements:</strong>
              <ul class="mb-0 mt-1">
                <li id="firstLogin-rule-length" class="text-muted">At least 8 characters</li>
                <li id="firstLogin-rule-uppercase" class="text-muted">Contains an uppercase letter (A-Z)</li>
                <li id="firstLogin-rule-lowercase" class="text-muted">Contains a lowercase letter (a-z)</li>
                <li id="firstLogin-rule-number" class="text-muted">Contains a number (0-9)</li>
                <li id="firstLogin-rule-special" class="text-muted">Contains a special character (!@#$%^&*)</li>
              </ul>
            </div>

            <div id="firstLoginPasswordError" class="alert alert-danger small d-none"></div>
            <div id="firstLoginPasswordSuccess" class="alert alert-success small d-none"></div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" id="firstLoginSubmitBtn">
            <i class="bi bi-check-circle me-1"></i>Change Password
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="container-fluid">
    <div class="row">
      <!-- Sidebar -->
      <nav class="col-md-3 col-lg-2 d-md-block sidebar p-3 shadow-sm" style="position: fixed; top: 65px; left: 0; height: calc(100vh - 65px); overflow-y: auto; z-index: 1000;">
        <div class="nav flex-column">
          <a class="nav-link" href="staff-dashboard.html"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
          <a class="nav-link" href="sidebar-requests.php"><i class="bi bi-files me-2"></i> Requests</a>
          <a class="nav-link active" href="sidebar-residents.php"><i class="bi bi-people me-2"></i> Residents</a>
          <a class="nav-link" href="sidebar-reports.html"><i class="bi bi-clipboard-data me-2"></i> Reports</a>
          <a class="nav-link" href="sidebar-profile.html"><i class="bi bi-card-list me-2"></i> Profile</a>
        </div>
      </nav>

      <!-- Main Content -->
      <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
        <div class="page-header mb-4">
          <h3>Registered Residents</h3>
          <small>Manage records and demographics of residents within your barangay.</small>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
          <div class="col-md-3">
            <div class="stat-card bg-success text-white text-center shadow-sm" 
                 data-bs-toggle="popover" 
                 data-bs-trigger="hover"
                 data-bs-placement="bottom"
                 data-bs-title="Total Residents"
                 data-bs-content="Total number of registered residents in the barangay. This includes all age groups and both permanent and temporary residents."
                 data-bs-html="true">
              <h5><?php echo number_format($totalResidents); ?></h5>
              <p>Total Residents <i class="bi bi-info-circle-fill ms-1 small"></i></p>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-card bg-primary text-white text-center shadow-sm"
                 data-bs-toggle="popover" 
                 data-bs-trigger="hover"
                 data-bs-placement="bottom"
                 data-bs-title="Minors Residents"
                 data-bs-content="Total Minor population: <?php echo number_format($minors); ?> (<?php echo $totalResidents > 0 ? round(($minors/$totalResidents)*100) : 0; ?>% of total residents)"
                 data-bs-html="true">
              <h5><?php echo number_format($minors); ?></h5>
              <p>Minors <i class="bi bi-info-circle-fill ms-1 small"></i></p>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-card bg-pink text-white text-center shadow-sm"
                 style="background-color:#e83e8c;"
                 data-bs-toggle="popover" 
                 data-bs-trigger="hover"
                 data-bs-placement="bottom"
                 data-bs-title="Adults Residents"
                 data-bs-content="Total Adults population: <?php echo number_format($adults); ?> (<?php echo $totalResidents > 0 ? round(($adults/$totalResidents)*100) : 0; ?>% of total residents)"
                 data-bs-html="true">
              <h5><?php echo number_format($adults); ?></h5>
              <p>Adults <i class="bi bi-info-circle-fill ms-1 small"></i></p>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-card bg-warning text-dark text-center shadow-sm"
                 data-bs-toggle="popover" 
                 data-bs-trigger="hover"
                 data-bs-placement="bottom"
                 data-bs-title="Senior Residents (60+ years)"
                 data-bs-content="Total senior citizens: <?php echo number_format($seniors); ?> (<?php echo $totalResidents > 0 ? round(($seniors/$totalResidents)*100) : 0; ?>% of total residents)"
                 data-bs-html="true">
              <h5><?php echo number_format($seniors); ?></h5>
              <p>Seniors (60+) <i class="bi bi-info-circle-fill ms-1 small"></i></p>
            </div>
          </div>
        </div>

        <script>
          // Initialize all popovers
          document.addEventListener('DOMContentLoaded', function() {
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            const popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
              return new bootstrap.Popover(popoverTriggerEl, {
                container: 'body',
                delay: { show: 100, hide: 100 }
              });
            });

            // Close popover when clicking outside
            document.addEventListener('click', function(e) {
              if (!e.target.closest('[data-bs-toggle="popover"]')) {
                popoverList.forEach(popover => {
                  popover._element.blur();
                });
              }
            });
          });
        </script>

        <script>
        // Phone input formatting and validation for Add Resident modal
        document.addEventListener('DOMContentLoaded', function() {
          const contactInput = document.getElementById('add-contact');
          if (!contactInput) return;

          // Format as 09XX-XXX-XXXX while typing
          function formatPHMobile(value) {
            const digits = value.replace(/\D/g, '').slice(0, 11); // limit to 11 digits
            if (digits.length <= 4) return digits;
            if (digits.length <= 7) return digits.slice(0,4) + '-' + digits.slice(4);
            return digits.slice(0,4) + '-' + digits.slice(4,7) + '-' + digits.slice(7);
          }

          // Validate final value against pattern
          const pattern = /^09\d{2}-\d{3}-\d{4}$/; 

          contactInput.addEventListener('input', function(e) {
            const start = contactInput.selectionStart;
            const oldLen = contactInput.value.length;
            contactInput.value = formatPHMobile(contactInput.value);
            // try to keep caret near the end (best-effort)
            const newLen = contactInput.value.length;
            const diff = newLen - oldLen;
            try {
              contactInput.setSelectionRange(start + (diff > 0 ? diff : 0), start + (diff > 0 ? diff : 0));
            } catch (err) {
              // ignore selection errors
            }
            // clear any custom validity while typing
            contactInput.setCustomValidity('');
          });

          contactInput.addEventListener('blur', function() {
            const val = contactInput.value;
            if (val.length === 0) {
              contactInput.setCustomValidity('');
              return;
            }
            if (!pattern.test(val)) {
              contactInput.setCustomValidity('Please enter a valid number in the format 09XX-XXX-XXXX');
              contactInput.reportValidity();
            } else {
              contactInput.setCustomValidity('');
            }
          });

          // Also validate on form submission (extra safety)
          const addForm = document.getElementById('addResidentForm');
          if (addForm) {
            addForm.addEventListener('submit', function(e) {
              const val = contactInput.value;
              if (val && !pattern.test(val)) {
                e.preventDefault();
                contactInput.setCustomValidity('Please enter a valid number in the format 09XX-XXX-XXXX');
                contactInput.reportValidity();
              }
            });
          }
        });
        </script>

        <!-- Search / Category / Refresh / Add New (fills the space) -->
        <div class="card shadow-sm border-0 mb-3">
          <div class="card-body">
            <form class="row g-2 align-items-center">
              <div class="col-md-4">
                <!-- Quick inline search to filter the residents table (debounced) -->
                <input id="quickSearch" type="search" class="form-control" placeholder="Search" aria-label="Quick search">
              </div>

              <div class="col-md-2">
                <!-- Gender filter -->
                <select id="genderFilter" class="form-select" aria-label="Filter by gender">
                  <option value="">All Genders</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </select>
              </div>

              <div class="col-md-2">
                <!-- Resident activity status filter -->
                <select id="statusFilter" class="form-select" aria-label="Filter by status">
                  <option value="">All Status</option>
                  <option value="active">Active</option>
                  <option value="suspended">Suspended</option>
                  <option value="restricted">Restricted</option>
                  <option value="deceased">Deceased</option>
                </select>
              </div>

              <div class="col-md-2 text-end">
                <!-- Add New button -->
                <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addResidentModal">
                  <i class="bi bi-person-plus"></i> Add New
                </button>
              </div>
              <div class="col-md-auto">
                <button type="button" class="filter-refresh-btn d-flex align-items-center justify-content-center" id="refreshBtn" title="Refresh"
                  style="border: 2px solid #4B0082; color: #4B0082; border-radius: 50%; width: 38px; height: 38px; padding: 0; background: transparent;">
                  <i class="bi bi-arrow-clockwise"></i>
                </button>
              </div>
            </form>
          </div>
        </div>

        <script>
        // Wire the new filters and Refresh button
        document.addEventListener('DOMContentLoaded', function () {
          // Notification handling
          const notifBadge = document.getElementById('notifBadge');
          const toggleExtraBtn = document.getElementById('toggleExtraNotifs');
          const extraNotifs = document.querySelector('.extra-notifs');

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

              if (notifBadge) {
                notifBadge.textContent = unreadCount;
                notifBadge.style.display = unreadCount > 0 ? 'inline-block' : 'none';
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

                item.addEventListener('click', async (e) => {
                  e.stopPropagation();
                  if (!isRead) {
                    try {
                      await fetch('mark_notification_read.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: n.id })
                      });
                      item.classList.remove('unread');
                      const cur = parseInt(notifBadge.textContent || '0', 10);
                      if (cur > 0) {
                        notifBadge.textContent = cur - 1;
                        if (cur - 1 === 0) notifBadge.style.display = 'none';
                      }
                    } catch (err) {
                      console.error('mark read failed', err);
                    }
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

          function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
          }

          // Initial load and polling
          fetchNotifications();
          setInterval(fetchNotifications, 60000); // Poll every 1 minute

          // Filter functionality for residents
          const quickSearch = document.getElementById('quickSearch');
          const genderFilter = document.getElementById('genderFilter');
          const statusFilter = document.getElementById('statusFilter');
          const filterBtn = document.getElementById('filterBtn');
          const refreshBtn = document.getElementById('refreshBtn');

          // Helper to show/hide rows based on all filters
          function applyFilters() {
            const q = (quickSearch && quickSearch.value || '').trim().toLowerCase();
            const gender = (genderFilter && genderFilter.value || '').trim().toLowerCase();
            const status = (statusFilter && statusFilter.value || '').trim().toLowerCase();
            
            const rows = document.querySelectorAll('table tbody tr');
            let visibleCount = 0;
            
            rows.forEach(r => {
              const text = (r.textContent || '').toLowerCase();
              
              // Get data attributes for precise filtering
              const rowGender = (r.getAttribute('data-gender') || '').toLowerCase();
              const rowStatus = (r.getAttribute('data-status') || '').toLowerCase();

              // Match query AND all filters (if provided)
              const matchesQuery = q === '' || text.includes(q);
              const matchesGender = gender === '' || rowGender === gender;
              const matchesStatus = status === '' || rowStatus === status;

              const isVisible = matchesQuery && matchesGender && matchesStatus;
              r.style.display = isVisible ? '' : 'none';
              if (isVisible) visibleCount++;
            });
            
            // Update filter summary (optional - you can add a status display)
            console.log(`Showing ${visibleCount} of ${rows.length} residents`);
          }

          // Debounced quick search
          if (quickSearch) {
            let qsTimer = null;
            quickSearch.addEventListener('input', function () {
              if (qsTimer) clearTimeout(qsTimer);
              qsTimer = setTimeout(() => applyFilters(), 180);
            });
          }

          // Gender filter change
          if (genderFilter) {
            genderFilter.addEventListener('change', function () {
              applyFilters();
            });
          }

          // Status filter change
          if (statusFilter) {
            statusFilter.addEventListener('change', function () {
              applyFilters();
            });
          }

          // Refresh button: clear all filters and show all rows
          if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
              const icon = refreshBtn.querySelector('i');
              refreshBtn.classList.add('filter-refresh-btn-loading');
              icon.classList.add('spin-animation');
              refreshBtn.disabled = true;
              if (quickSearch) quickSearch.value = '';
              if (genderFilter) genderFilter.selectedIndex = 0;
              if (statusFilter) statusFilter.selectedIndex = 0;
              applyFilters();
              setTimeout(() => {
                icon.classList.remove('spin-animation');
                refreshBtn.classList.remove('filter-refresh-btn-loading');
                refreshBtn.disabled = false;
              }, 800);
            });
          }
            <!-- style moved to top for global effect -->
        });
        </script>

        <!-- Filter Modal -->
        <div class="modal fade" id="filterModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
              <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                  <i class="bi bi-funnel me-2"></i>Advanced Filter
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body p-4">
                <form id="advancedFilterForm">
                  <div class="mb-3">
                    <label class="form-label">Age Range</label>
                    <div class="row g-2">
                      <div class="col-6">
                        <input type="number" class="form-control" placeholder="Min Age" min="0" max="120">
                      </div>
                      <div class="col-6">
                        <input type="number" class="form-control" placeholder="Max Age" min="0" max="120">
                      </div>
                    </div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Gender</label>
                    <select class="form-select">
                      <option value="">All</option>
                      <option value="male">Male</option>
                      <option value="female">Female</option>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Civil Status</label>
                    <select class="form-select">
                      <option value="">All</option>
                      <option value="single">Single</option>
                      <option value="married">Married</option>
                      <option value="widowed">Widowed</option>
                      <option value="divorced">Divorced</option>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Resident Type</label>
                    <select class="form-select">
                      <option value="">All</option>
                      <option value="permanent">Permanent</option>
                      <option value="temporary">Temporary</option>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Registration Date</label>
                    <div class="row g-2">
                      <div class="col-6">
                        <input type="date" class="form-control" placeholder="From">
                      </div>
                      <div class="col-6">
                        <input type="date" class="form-control" placeholder="To">
                      </div>
                    </div>
                  </div>
                </form>
              </div>
              <div class="modal-footer border-0">
                <button type="button" class="btn btn-success" id="applyFilterBtn">Apply Filter</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Add New Resident Modal -->
        <div class="modal fade" id="addResidentModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
              <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                  <i class="bi bi-person-plus me-2"></i>Add New Resident
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                <form id="addResidentForm" novalidate>
                  <!-- Name Fields -->
                  <div class="row g-2 mb-3">
                    <div class="col-md-4">
                      <label for="add-first" class="form-label">First Name <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="add-first" name="first" placeholder="Juan" required>
                      <div class="invalid-feedback">First name is required.</div>
                    </div>
                    <div class="col-md-4">
                      <label for="add-middle" class="form-label">Middle Name</label>
                      <input type="text" class="form-control" id="add-middle" name="middle" placeholder="Ponce">
                    </div>
                    <div class="col-md-4">
                      <label for="add-last" class="form-label">Last Name <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="add-last" name="last" placeholder="Dela Cruz" required>
                      <div class="invalid-feedback">Last name is required.</div>
                    </div>
                  </div>

                  <!-- Gender and Civil Status -->
                  <div class="row g-2 mb-3">
                    <div class="col-md-6">
                      <label for="add-gender" class="form-label">Gender</label>
                      <select id="add-gender" name="gender" class="form-select">
                        <option value="">Select gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                        <option value="Prefer not to say">Prefer not to say</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label for="add-civil" class="form-label">Civil Status</label>
                      <select id="add-civil" name="civil" class="form-select">
                        <option value="">Select civil status</option>
                        <option value="Single">Single</option>
                        <option value="Married">Married</option>
                        <option value="Widowed">Widowed</option>
                        <option value="Divorced">Divorced</option>
                      </select>
                    </div>
                  </div>

                  <!-- Email -->
                  <div class="mb-3">
                    <label for="add-email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="add-email" name="email" placeholder="name@example.com" required>
                    <div class="invalid-feedback">Please enter a valid email address.</div>
                  </div>

                  <!-- Complete Address -->
                  <div class="mb-3">
                    <label class="form-label">Complete Address <span class="text-danger">*</span></label>
                    <div class="row g-2">
                      <div class="col-md-6">
                        <label for="add-municipality" class="form-label small text-muted">Municipality/City</label>
                        <input type="text" class="form-control" id="add-municipality" name="municipality" value="Santa Maria" readonly style="background-color: #f8f9fa; cursor: not-allowed;">
                      </div>
                      <div class="col-md-6">
                        <label for="add-barangay" class="form-label small text-muted">Barangay</label>
                        <input type="text" class="form-control" id="add-barangay" name="barangay" value="Pulong Buhangin" readonly style="background-color: #f8f9fa; cursor: not-allowed;">
                      </div>
                    </div>
                    <div class="mt-2">
                      <label for="add-street" class="form-label small text-muted">Street, House/Building Number, Unit</label>
                      <input type="text" class="form-control" id="add-street" name="street" placeholder="Enter your street and house number" required>
                      <div class="invalid-feedback">Please enter your street and house number.</div>
                    </div>
                  </div>

                  <!-- Contact Number -->
                  <div class="mb-3">
                    <label for="add-contact" class="form-label">Contact Number <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="add-contact" name="contact" placeholder="09XX-XXX-XXXX" inputmode="numeric" autocomplete="tel" required>
                    <div class="invalid-feedback">Please enter a valid number in the format 09XX-XXX-XXXX.</div>
                  </div>

                  <!-- Birthday and Age -->
                  <div class="row g-2 mb-3">
                    <div class="col-md-6">
                      <label for="add-dob" class="form-label">Birthday</label>
                      <input type="date" class="form-control" id="add-dob" name="dob">
                      <div class="form-text small text-muted">Enter date of birth.</div>
                    </div>
                    <div class="col-md-6">
                      <label for="add-age" class="form-label">Age</label>
                      <input type="number" class="form-control" id="add-age" name="age" min="0" max="130" readonly>
                      <div class="form-text small text-muted">Age is auto-calculated from birthday.</div>
                    </div>
                  </div>

                  <!-- Password Fields -->
                  <div class="row g-2 mb-3">
                    <div class="col-md-6">
                      <label for="add-password" class="form-label">Password <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <input type="password" class="form-control" id="add-password" name="password" placeholder="Enter password" required>
                        <button class="btn btn-outline-secondary password-toggle" type="button" data-target="add-password" tabindex="-1">
                          <i class="bi bi-eye-slash"></i>
                        </button>
                      </div>
                      <div class="password-strength-text small mt-1 text-danger d-none" id="password-strength-msg">Password must include: At least 8 characters, An uppercase letter, A lowercase letter, A number, A special character</div>
                      <div class="invalid-feedback">Password is required.</div>
                    </div>
                    <div class="col-md-6">
                      <label for="add-confirm-password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <input type="password" class="form-control" id="add-confirm-password" name="confirmPassword" placeholder="Confirm password" required>
                        <button class="btn btn-outline-secondary password-toggle" type="button" data-target="add-confirm-password" tabindex="-1">
                          <i class="bi bi-eye-slash"></i>
                        </button>
                      </div>
                      <div class="invalid-feedback">Passwords must match.</div>
                    </div>
                  </div>
                </form>
              </div>
              <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn gradient-btn" id="saveResidentBtn">
                  <i class="bi bi-person-plus me-1"></i>Register Resident
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Add JavaScript for form handling -->
        <script>
          document.addEventListener('DOMContentLoaded', function() {
            // Handle Filter Form Submission
            const applyFilterBtn = document.getElementById('applyFilterBtn');
            applyFilterBtn.addEventListener('click', function() {
              // Get all filter values
              const filterForm = document.getElementById('advancedFilterForm');
              const formData = new FormData(filterForm);
              
              // Here you would normally process the filter
              console.log('Applying filters...');
              
              // Close the modal
              const filterModal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
              filterModal.hide();
              
              // You can add logic here to refresh the table with filtered data
            });

            // Handle New Resident Form Submission
            const saveResidentBtn = document.getElementById('saveResidentBtn');
            const emailInput = document.getElementById('add-email');
            const addResidentForm = document.getElementById('addResidentForm');
            const dobInput = document.getElementById('add-dob');
            const ageInput = document.getElementById('add-age');

            // Contact number auto-formatting
            const contactInput = document.getElementById('add-contact');
            if (contactInput) {
              contactInput.addEventListener('input', function(e) {
                let value = this.value.replace(/\D/g, ''); // Remove all non-digits
                if (value.length > 11) value = value.slice(0, 11); // Limit to 11 digits
                
                // Format as 09XX-XXX-XXXX
                if (value.length >= 4) {
                  value = value.slice(0, 4) + '-' + value.slice(4);
                }
                if (value.length >= 8) {
                  value = value.slice(0, 8) + '-' + value.slice(8);
                }
                
                this.value = value;
                
                // Clear validation on input
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
              });
            }

            // Password visibility toggle
            document.querySelectorAll('.password-toggle').forEach(btn => {
              btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                if (input.type === 'password') {
                  input.type = 'text';
                  icon.classList.remove('bi-eye-slash');
                  icon.classList.add('bi-eye');
                } else {
                  input.type = 'password';
                  icon.classList.remove('bi-eye');
                  icon.classList.add('bi-eye-slash');
                }
              });
            });

            // Password strength validation
            function validatePasswordStrength(password) {
              const rules = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
              };
              return {
                isValid: Object.values(rules).every(Boolean),
                rules: rules
              };
            }

            // Password input event listener for strength validation
            const addPasswordInput = document.getElementById('add-password');
            const passwordStrengthMsg = document.getElementById('password-strength-msg');
            const addConfirmPasswordInput = document.getElementById('add-confirm-password');

            if (addPasswordInput) {
              addPasswordInput.addEventListener('input', function() {
                const result = validatePasswordStrength(this.value);
                if (this.value && !result.isValid) {
                  passwordStrengthMsg.classList.remove('d-none');
                  this.classList.add('is-invalid');
                } else {
                  passwordStrengthMsg.classList.add('d-none');
                  this.classList.remove('is-invalid');
                }
                
                // Also check confirm password match when password changes
                if (addConfirmPasswordInput && addConfirmPasswordInput.value) {
                  if (this.value !== addConfirmPasswordInput.value) {
                    addConfirmPasswordInput.classList.add('is-invalid');
                    addConfirmPasswordInput.setCustomValidity('Passwords do not match');
                  } else {
                    addConfirmPasswordInput.classList.remove('is-invalid');
                    addConfirmPasswordInput.setCustomValidity('');
                  }
                }
              });
            }

            // Confirm password validation
            if (addConfirmPasswordInput) {
              addConfirmPasswordInput.addEventListener('input', function() {
                if (addPasswordInput && this.value) {
                  if (this.value !== addPasswordInput.value) {
                    this.classList.add('is-invalid');
                    this.setCustomValidity('Passwords do not match');
                  } else {
                    this.classList.remove('is-invalid');
                    this.setCustomValidity('');
                  }
                } else {
                  this.classList.remove('is-invalid');
                  this.setCustomValidity('');
                }
              });
            }

            // Auto-calculate age from birthday
            if (dobInput && ageInput) {
              dobInput.addEventListener('change', function() {
                if (this.value) {
                  const birthDate = new Date(this.value);
                  const today = new Date();
                  let age = today.getFullYear() - birthDate.getFullYear();
                  const monthDiff = today.getMonth() - birthDate.getMonth();
                  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                  }
                  ageInput.value = age >= 0 ? age : '';
                } else {
                  ageInput.value = '';
                }
              });
            }

            // Email validation helper (simple, practical regex)
            function isValidEmail(value) {
              if (!value) return true; // allow empty if not required
              // Basic validation: local@domain.tld (no spaces) and TLD >= 2 chars
              return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value.trim());
            }

            if (emailInput) {
              emailInput.addEventListener('input', function () {
                emailInput.setCustomValidity('');
                emailInput.classList.remove('is-invalid');
              });

              emailInput.addEventListener('blur', function () {
                const val = emailInput.value.trim();
                if (val && !isValidEmail(val)) {
                  emailInput.setCustomValidity('Please enter a valid email address (e.g. name@example.com)');
                  // show message
                  try { emailInput.reportValidity(); } catch (e) {}
                  emailInput.classList.add('is-invalid');
                } else {
                  emailInput.setCustomValidity('');
                  emailInput.classList.remove('is-invalid');
                }
              });
            }

            saveResidentBtn.addEventListener('click', function() {
              // Clear previous invalid markers
              const invalidEls = addResidentForm.querySelectorAll('.is-invalid');
              invalidEls.forEach(el => el.classList.remove('is-invalid'));

              // Get inputs
              const firstInput = document.getElementById('add-first');
              const lastInput = document.getElementById('add-last');
              const dobInput = document.getElementById('add-dob');
              const genderInput = document.getElementById('add-gender');
              const civilInput = document.getElementById('add-civil');
              const contactInputEl = document.getElementById('add-contact');
              const emailInputEl = document.getElementById('add-email');
              const streetInput = document.getElementById('add-street');
              const passwordInput = document.getElementById('add-password');
              const confirmPasswordInput = document.getElementById('add-confirm-password');

              const phonePattern = /^09\d{2}-\d{3}-\d{4}$/;

              // Clear all previous validation states
              addResidentForm.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
                el.setCustomValidity('');
              });

              // Helper to mark invalid and set focus if first invalid
              let firstInvalid = null;
              function markInvalid(el, message) {
                if (!el) return;
                el.classList.add('is-invalid');
                // Find the invalid-feedback sibling and update its text
                const feedback = el.parentElement.querySelector('.invalid-feedback') || el.nextElementSibling;
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                  feedback.textContent = message || 'Invalid field';
                }
                if (!firstInvalid) firstInvalid = el;
              }

              // Required checks
              if (!firstInput || !firstInput.value.trim()) markInvalid(firstInput, 'First name is required');
              if (!lastInput || !lastInput.value.trim()) markInvalid(lastInput, 'Last name is required');

              // DOB: required and must not be in the future and age reasonable
              if (!dobInput || !dobInput.value) {
                markInvalid(dobInput, 'Date of birth is required');
              } else {
                const dob = new Date(dobInput.value);
                const today = new Date();
                if (isNaN(dob.getTime()) || dob > today) {
                  markInvalid(dobInput, 'Please enter a valid date of birth (not in the future)');
                } else {
                  const age = Math.floor((today - dob) / (365.25 * 24 * 60 * 60 * 1000));
                  if (age < 0 || age > 120) {
                    markInvalid(dobInput, 'Please enter a realistic age');
                  }
                }
              }

              // Gender and Civil Status - check select element value
              if (!genderInput || genderInput.value === '' || genderInput.value === null) {
                markInvalid(genderInput, 'Gender is required');
              }
              if (!civilInput || civilInput.value === '' || civilInput.value === null) {
                markInvalid(civilInput, 'Civil status is required');
              }

              // Email - required
              const emailVal = (emailInputEl && emailInputEl.value || '').trim();
              if (!emailVal) {
                markInvalid(emailInputEl, 'Email is required');
              } else if (!isValidEmail(emailVal)) {
                markInvalid(emailInputEl, 'Please enter a valid email address (e.g. name@example.com)');
              }

              // Address
              if (!streetInput || !streetInput.value.trim()) markInvalid(streetInput, 'Street address is required');

              // Contact number (required) - validate pattern
              const contactVal = (contactInputEl && contactInputEl.value || '').trim();
              if (!contactVal) {
                markInvalid(contactInputEl, 'Contact number is required');
              } else if (!phonePattern.test(contactVal)) {
                markInvalid(contactInputEl, 'Please enter a valid number in the format 09XX-XXX-XXXX');
              }

              // Password validation - required, strong password policy, and must match
              const passwordVal = (passwordInput && passwordInput.value || '');
              const confirmPasswordVal = (confirmPasswordInput && confirmPasswordInput.value || '');
              
              if (!passwordVal) {
                markInvalid(passwordInput, 'Password is required');
              } else {
                const strengthResult = validatePasswordStrength(passwordVal);
                if (!strengthResult.isValid) {
                  markInvalid(passwordInput, 'Password must include: 8+ chars, uppercase, lowercase, number, special character');
                }
              }
              
              if (!confirmPasswordVal) {
                markInvalid(confirmPasswordInput, 'Please confirm your password');
              } else if (passwordVal !== confirmPasswordVal) {
                markInvalid(confirmPasswordInput, 'Passwords do not match');
              }

              // If any invalid found, focus first and stop
              if (firstInvalid) {
                try { firstInvalid.focus(); } catch (e) {}
                return;
              }

              // Final HTML5 validity check as safety
              if (!addResidentForm.checkValidity()) {
                addResidentForm.reportValidity();
                return;
              }

              // All validations passed — proceed to save
              const formData = new FormData(addResidentForm);
              console.log('Saving new resident...', Object.fromEntries(formData.entries()));

              // Disable save button and show loading state
              saveResidentBtn.disabled = true;
              saveResidentBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Registering...';

              // Send data to server
              fetch('add_resident.php', {
                method: 'POST',
                body: formData
              })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  // Close the modal
                  const addModal = bootstrap.Modal.getInstance(document.getElementById('addResidentModal'));
                  if (addModal) addModal.hide();

                  // Show success message
                  if (typeof window.showTransientAlert === 'function') {
                    window.showTransientAlert('Resident registered successfully!', 'success');
                  } else {
                    alert('Resident registered successfully!');
                  }

                  // Reset form and clear custom validity
                  addResidentForm.reset();
                  addResidentForm.querySelectorAll('input,select,textarea').forEach(el => {
                    try { el.setCustomValidity(''); } catch (e) {}
                    el.classList.remove('is-invalid');
                  });

                  // Reload page to show new resident
                  setTimeout(() => {
                    location.reload();
                  }, 1500);
                } else {
                  // Show error message
                  if (typeof window.showTransientAlert === 'function') {
                    window.showTransientAlert(data.message || 'Failed to register resident', 'danger');
                  } else {
                    alert('Error: ' + (data.message || 'Failed to register resident'));
                  }
                }
              })
              .catch(error => {
                console.error('Error:', error);
                if (typeof window.showTransientAlert === 'function') {
                  window.showTransientAlert('An error occurred. Please try again.', 'danger');
                } else {
                  alert('An error occurred. Please try again.');
                }
              })
              .finally(() => {
                // Re-enable save button
                saveResidentBtn.disabled = false;
                saveResidentBtn.innerHTML = '<i class="bi bi-person-plus me-1"></i>Register Resident';
              });
            });

            // Quick inline search (replaces the previous Filter button behavior)
            const quickSearch = document.getElementById('quickSearch');
            if (quickSearch) {
              let qsTimer = null;
              quickSearch.addEventListener('input', function () {
                if (qsTimer) clearTimeout(qsTimer);
                qsTimer = setTimeout(() => {
                  const q = (quickSearch.value || '').trim().toLowerCase();
                  const rows = document.querySelectorAll('table tbody tr');
                  rows.forEach(r => {
                    // Match against visible row text (name, id, address, etc.)
                    const text = (r.textContent || '').toLowerCase();
                    r.style.display = q === '' || text.includes(q) ? '' : 'none';
                  });
                }, 180);
              });
            }
          });
        </script>

        <!-- Residents Table -->
        <div class="card shadow-sm border-0">
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Barangay ID</th>
                    <th>Resident Name</th>
                    <th>Address</th>
                    <th>Gender</th>
                    <th>Age</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($residents as $resident): 
                    // Normalize resident activity status
                    $rawStatus = strtolower($resident['status'] ?? 'active');
                    if (!in_array($rawStatus, ['active', 'deceased', 'suspended', 'restricted'])) {
                      $rawStatus = 'active';
                    }
                    $statusDisplay = strtoupper($rawStatus);
                    $statusBadgeClass = 'bg-success';
                    if ($rawStatus === 'deceased') {
                      $statusBadgeClass = 'bg-dark text-white';
                    } elseif ($rawStatus === 'suspended') {
                      $statusBadgeClass = 'bg-danger';
                    } elseif ($rawStatus === 'restricted') {
                      $statusBadgeClass = 'bg-warning text-dark';
                    }
                    
                    // Also keep a small note of latest request status if needed (optional)
                    $latest = strtolower($resident['latest_status'] ?? '');
                    $latestDisplay = $latest ? strtoupper($latest) : 'NO REQUEST YET';
                    
                    $fullName = trim(($resident['first_name'] ?? '') . ' ' . ($resident['last_name'] ?? ''));
                    
                    // Build address with proper comma handling
                    $addressParts = array_filter([
                        $resident['street'] ?? '',
                        $resident['barangay'] ?? '',
                        'Pulong Buhangin',
                        'Santa Maria',
                        'Bulacan'
                    ], function($part) {
                        return !empty(trim($part));
                    });
                    $address = implode(', ', $addressParts);
                  ?>
                  <tr data-gender="<?php echo strtolower($resident['gender'] ?? ''); ?>" 
                      data-status="<?php echo $rawStatus; ?>" 
                      data-resident-type="permanent">
                    <td>
                      <div class="small">ID No. <?php echo $resident['id']; ?></div>
                      <div class="text-muted small"><?php echo $resident['created_at'] ? date('M d, Y • g:i A', strtotime($resident['created_at'])) : 'N/A'; ?></div>
                    </td>
                    <td>
                      <div class="fw-semibold"><?php echo htmlspecialchars($fullName); ?></div>
                      <div class="text-muted small">Latest request: <?php echo htmlspecialchars($latestDisplay); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($address); ?></td>
                    <td><?php echo htmlspecialchars($resident['gender'] ?? 'N/A'); ?></td>
                    <td><?php echo $resident['age'] ?? 'N/A'; ?></td>
                    <td><span class="badge status-badge <?php echo $statusBadgeClass; ?>" data-resident-id="r<?php echo $resident['id']; ?>"><?php echo $statusDisplay; ?></span></td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-secondary" 
                              data-bs-toggle="modal" 
                              data-bs-target="#residentModal"
                              data-resident-id="r<?php echo $resident['id']; ?>"
                              data-date="<?php echo $resident['latest_request_date'] ? date('M d, Y • g:i A', strtotime($resident['latest_request_date'])) : 'N/A'; ?>"
                              data-name="<?php echo htmlspecialchars($fullName); ?>"
                              data-age="<?php echo $resident['age']; ?>"
                              data-address="<?php echo htmlspecialchars($address); ?>"
                              data-gender="<?php echo htmlspecialchars($resident['gender'] ?? 'N/A'); ?>"
                              data-document="<?php echo htmlspecialchars($resident['latest_document_type'] ?? 'No request yet'); ?>"
                              data-status="<?php echo $statusDisplay; ?>"
                              data-contact="<?php echo htmlspecialchars($resident['mobile'] ?? 'N/A'); ?>"
                              data-registered="<?php echo $resident['created_at'] ? date('F d, Y', strtotime($resident['created_at'])) : 'N/A'; ?>"
                              data-barangay-id="<?php echo $resident['id']; ?>"
                              data-civil-status="<?php echo htmlspecialchars($resident['civil_status'] ?? 'N/A'); ?>"
                              data-email="<?php echo htmlspecialchars($resident['email'] ?? 'N/A'); ?>"
                              data-resident-type="Permanent"
                              data-profile-pic="<?php echo htmlspecialchars($resident['profile_pic'] ?? ''); ?>">
                          View
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  
                  <?php if (empty($residents)): ?>
                  <tr>
                    <td colspan="7" class="text-center py-4">
                      <div class="text-muted">
                        <i class="bi bi-people fs-1"></i>
                        <p class="mt-2">No registered residents found</p>
                      </div>
                    </td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>



 <!-- ===== Scrollable Resident Details Modal ===== -->
<div class="modal fade" id="residentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      
      <!-- Header -->
      <div class="modal-header text-white rounded-top-4" 
           style="background: linear-gradient(135deg, #c5202f, #222c8e, #6a0dad);">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-person-badge me-2"></i> Resident Information
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <!-- Scrollable Body -->
      <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
        <div class="row mb-3">
          <!-- Profile Picture -->
          <div class="col-md-3 text-center mb-3 mb-md-0">
            <div id="modal-profile-pic-container" class="mx-auto" style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; border: 3px solid #6a0dad; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
              <img id="modal-profile-pic" src="" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover; display: none;">
              <i id="modal-profile-pic-placeholder" class="bi bi-person-circle" style="font-size: 4rem; color: #ccc;"></i>
            </div>
          </div>
          <div class="col-md-6">
            <h5 class="fw-bold mb-1" id="modal-name">—</h5>
            <div class="small text-muted mb-1" id="modal-age">Age: —</div>
            <div class="small text-muted mb-1" id="modal-address">Address: —</div>
            <div class="small text-muted mb-1" id="modal-gender">Gender: —</div>
            <div class="small text-muted mb-1" id="modal-contact">Contact: —</div>
            <div class="small text-muted mb-1" id="modal-registered">Registered: —</div>
            <div class="small text-muted mb-1" id="modal-barangay-id">Barangay ID: —</div>
            <div class="small text-muted mb-1" id="modal-civil-status">Civil Status: —</div>
            <div class="small text-muted mb-1" id="modal-resident-type">Resident Type: —</div>
          </div>
          <div class="col-md-3 text-end">
            <span class="badge bg-success rounded-pill px-3 py-2" id="modal-active-pill">Active</span>
          </div>
        </div>

        <hr>

        <div class="card bg-light border-0 shadow-sm mb-3">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="fw-semibold small text-muted">Latest Requested Document</div>
                <div id="modal-document">—</div>
              </div>
              <div class="col-md-6">
                <div class="fw-semibold small text-muted">Request Status</div>
                <div><strong id="modal-status" class="text-dark">—</strong></div>
              </div>
            </div>
            <!-- Date of death and remarks (shown when applicable) -->
            <div id="modal-date-of-death" class="small text-muted mt-2" style="display:none;">Date of Death: —</div>
            <div id="modal-deceased-remarks" class="small text-secondary mt-1" style="display:none;">Remarks: —</div>
          </div>
        </div>

        <div class="card border-0 bg-white shadow-sm mb-3">
          <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div class="w-100">
              <div class="fw-semibold">Resident Condition</div>
              <!-- View Mode -->
              <div id="condition-view-mode">
                <div class="small text-muted" id="condition-text">
                  Active resident — can request documents without restrictions.
                </div>
              </div>
              <!-- Edit Mode -->
              <div id="condition-edit-mode" style="display: none;">
                <select class="form-select form-select-sm mt-2" id="condition-select">
                  <option value="active">Active resident — can request documents without restrictions.</option>
                  <option value="restricted">Restricted — cannot request certain documents for 2 weeks.</option>
                  <option value="suspended">Suspended — cannot request any documents for 2 weeks.</option>
                </select>
                <div class="small text-muted mt-1" id="condition-info"></div>
                <div class="mt-2">
                  <button class="btn btn-primary btn-sm me-2" id="save-condition-btn">
                    <i class="bi bi-check-circle me-1"></i> Save
                  </button>
                  <button class="btn btn-outline-secondary btn-sm" id="cancel-condition-btn">
                    <i class="bi bi-x-circle me-1"></i> Cancel
                  </button>
                </div>
              </div>
            </div>
            <div class="action-buttons">
              <!-- Edit Button: toggles inline edit mode -->
              <button class="btn btn-outline-secondary btn-sm" id="modal-edit-btn" aria-pressed="false" aria-controls="condition-edit-mode">
                <i class="bi bi-pencil-square me-1"></i>
                <span class="d-none d-sm-inline">Edit</span>
              </button>

              <!-- Request History Button: opens the request history modal -->
              <button class="btn btn-outline-primary btn-sm" id="modal-history-btn" type="button" data-bs-toggle="modal" data-bs-target="#requestHistoryModal">
                <i class="bi bi-clock-history me-1"></i>
                <span class="d-none d-sm-inline">History</span>
              </button>
            </div>
          </div>
        </div>
      </div>
      
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          const editBtn = document.getElementById('modal-edit-btn');
          const viewMode = document.getElementById('condition-view-mode');
          const editMode = document.getElementById('condition-edit-mode');
          const saveBtn = document.getElementById('save-condition-btn');
          const cancelBtn = document.getElementById('cancel-condition-btn');
          const conditionSelect = document.getElementById('condition-select');
          const conditionText = document.getElementById('condition-text');
          const conditionInfo = document.getElementById('condition-info');
          const residentModalEl = document.getElementById('residentModal');

          // Show info based on selection
          conditionSelect.addEventListener('change', function() {
            const val = this.value;
            if (val === 'suspended') {
              conditionInfo.innerHTML = '<i class="bi bi-exclamation-triangle text-warning me-1"></i>Resident will be unable to request <strong>any documents</strong> for 2 weeks.';
            } else if (val === 'restricted') {
              conditionInfo.innerHTML = '<i class="bi bi-exclamation-triangle text-warning me-1"></i>Resident cannot request <strong>Barangay Clearance, Barangay ID, Good Moral Certificate, Business Permit</strong> for 2 weeks.';
            } else {
              conditionInfo.innerHTML = '';
            }
          });

          // Toggle edit mode
          editBtn.addEventListener('click', () => {
            viewMode.style.display = 'none';
            editMode.style.display = 'block';
            editBtn.style.display = 'none';
            conditionInfo.innerHTML = '';
          });

          // Save changes - make AJAX call
          saveBtn.addEventListener('click', () => {
            const selectedValue = conditionSelect.value;
            const selectedText = conditionSelect.options[conditionSelect.selectedIndex].text;
            
            // Get resident ID from modal
            let rid = residentModalEl.dataset.residentId;
            const numericId = rid ? rid.replace('r', '') : null;
            
            if (!numericId) {
              if (typeof window.showTransientAlert === 'function') {
                window.showTransientAlert('Error: Could not identify resident.', 'danger');
              }
              return;
            }

            // Confirm if changing to suspended or restricted
            if (selectedValue === 'suspended' || selectedValue === 'restricted') {
              const confirmMsg = selectedValue === 'suspended' 
                ? 'This will prevent the resident from requesting ANY documents for 2 weeks. Continue?'
                : 'This will prevent the resident from requesting Barangay Clearance, Barangay ID, Good Moral Certificate, and Business Permit for 2 weeks. Continue?';
              if (!confirm(confirmMsg)) {
                return;
              }
            }

            // Disable button and show loading
            const originalBtnText = saveBtn.innerHTML;
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

            // Make AJAX call
            fetch('update_resident_status.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                resident_id: parseInt(numericId),
                status: selectedValue
              })
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                // Update UI
                conditionText.textContent = selectedText;
                viewMode.style.display = 'block';
                editMode.style.display = 'none';
                editBtn.style.display = 'inline-block';
                conditionInfo.innerHTML = '';

                // Update the status badge and pill
                const activePill = document.getElementById('modal-active-pill');
                const statusBadge = document.getElementById('modal-status');
                
                if (selectedValue === 'active') {
                  activePill.textContent = 'Active';
                  activePill.className = 'badge bg-success text-white rounded-pill px-3 py-2';
                } else if (selectedValue === 'suspended') {
                  activePill.textContent = 'Suspended';
                  activePill.className = 'badge bg-danger text-white rounded-pill px-3 py-2';
                } else if (selectedValue === 'restricted') {
                  activePill.textContent = 'Restricted';
                  activePill.className = 'badge bg-warning text-dark rounded-pill px-3 py-2';
                }

                // Update table row
                const tableBadge = document.querySelector(`.status-badge[data-resident-id="${rid}"]`);
                if (tableBadge) {
                  tableBadge.textContent = selectedValue.toUpperCase();
                  tableBadge.className = 'badge status-badge ';
                  if (selectedValue === 'active') tableBadge.classList.add('bg-success');
                  else if (selectedValue === 'suspended') tableBadge.classList.add('bg-danger');
                  else if (selectedValue === 'restricted') tableBadge.classList.add('bg-warning', 'text-dark');
                }

                // Update view button data attribute
                const viewButton = document.querySelector(`button[data-resident-id="${rid}"]`);
                if (viewButton) {
                  viewButton.setAttribute('data-status', selectedValue.toUpperCase());
                }

                // Show success message
                if (typeof window.showTransientAlert === 'function') {
                  window.showTransientAlert(data.message, 'success');
                }
              } else {
                if (typeof window.showTransientAlert === 'function') {
                  window.showTransientAlert(data.message || 'Failed to update status.', 'danger');
                }
              }
            })
            .catch(error => {
              console.error('Error:', error);
              if (typeof window.showTransientAlert === 'function') {
                window.showTransientAlert('An error occurred. Please try again.', 'danger');
              }
            })
            .finally(() => {
              saveBtn.disabled = false;
              saveBtn.innerHTML = originalBtnText;
            });
          });

          // Cancel edit
          cancelBtn.addEventListener('click', () => {
            viewMode.style.display = 'block';
            editMode.style.display = 'none';
            editBtn.style.display = 'inline-block';
            conditionInfo.innerHTML = '';
          });
        });
      </script>

      <!-- Footer -->
      <div class="modal-footer border-0 d-flex justify-content-end">
        <button class="btn btn-dark px-4" id="modal-deceased-btn">
          <i class="bi bi-person-x me-1"></i> Mark as Deceased
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Request History Modal -->
<div class="modal fade" id="requestHistoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <!-- Header -->
      <div class="modal-header text-white rounded-top-4" 
           style="background: linear-gradient(135deg, #0d6efd, #0099ff);">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-clock-history me-2"></i> Request History
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <!-- Body -->
      <div class="modal-body p-4">
        <div class="resident-info mb-4">
          <h6 class="fw-bold mb-1" id="history-resident-name">—</h6>
          <small class="text-muted" id="history-resident-id">Barangay ID: —</small>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>Document Type</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="request-history-table">
              <!-- Sample history data - will be populated dynamically -->
            </tbody>
          </table>
        </div>
      </div>

      <!-- Footer (header close remains; footer Close removed) -->
      <div class="modal-footer border-0">
      </div>
    </div>
  </div>
</div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <!-- Pass request data to JavaScript -->
  <script>
    // All requests data from PHP
    const residentRequests = <?php echo json_encode($allRequests); ?>;
  </script>
  
  <!-- Request History Script -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const historyModal = new bootstrap.Modal(document.getElementById('requestHistoryModal'));
      
      // Function to get status badge class based on status
      function getStatusBadgeClass(status) {
        const statusLower = (status || '').toLowerCase();
        switch(statusLower) {
          case 'completed':
          case 'released':
          case 'ready':
            return 'bg-success';
          case 'approved':
            return 'bg-primary';
          case 'processing':
          case 'on process':
            return 'bg-info';
          case 'rejected':
            return 'bg-danger';
          case 'pending':
            return 'bg-warning text-dark';
          default:
            return 'bg-secondary';
        }
      }
      
      // Function to format date
      function formatDate(dateString) {
        if (!dateString) return '—';
        try {
          const date = new Date(dateString);
          return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' });
        } catch(e) {
          return dateString;
        }
      }
      
      // Handle Request History button click
      document.getElementById('modal-history-btn').addEventListener('click', function(e) {
        e.preventDefault();
        
        // Get resident info from the main modal
        const residentName = document.getElementById('modal-name').textContent;
        const barangayIdText = document.getElementById('modal-barangay-id').textContent;
        // Extract just the ID number from "Barangay ID: 19" format
        const residentId = barangayIdText.replace('Barangay ID: ', '').trim();
        
        // Update history modal with resident info
        document.getElementById('history-resident-name').textContent = residentName;
        document.getElementById('history-resident-id').textContent = barangayIdText;
        
        // Filter requests for this specific resident
        const residentRequestHistory = residentRequests.filter(req => req.resident_id == residentId);
        
        // Clear and populate history table
        const historyTableBody = document.getElementById('request-history-table');
        historyTableBody.innerHTML = '';
        
        if (residentRequestHistory.length === 0) {
          const row = document.createElement('tr');
          row.innerHTML = `
            <td colspan="3" class="text-center text-muted py-4">
              <i class="bi bi-file-earmark-x fs-1 d-block mb-2"></i>
              <p>No request history found for this resident</p>
            </td>
          `;
          historyTableBody.appendChild(row);
        } else {
          residentRequestHistory.forEach(request => {
            const statusBadgeClass = getStatusBadgeClass(request.status);
            const statusText = (request.status || 'Pending').charAt(0).toUpperCase() + (request.status || 'Pending').slice(1);
            const row = document.createElement('tr');
            row.innerHTML = `
              <td>
                <div class="fw-medium">${formatDate(request.requested_at)}</div>
              </td>
              <td>${request.document_type || '—'}</td>
              <td>
                <span class="badge ${statusBadgeClass} rounded-pill">${statusText}</span>
              </td>
            `;
            historyTableBody.appendChild(row);
          });
        }
        
        // Show the history modal
        historyModal.show();
      });
    });
  </script>

  <!-- ===== JavaScript to Populate Modal ===== -->
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const residentModal = document.getElementById('residentModal');
    residentModal.addEventListener('show.bs.modal', event => {
      const button = event.relatedTarget;
      const modalFields = {
        residentId: button.getAttribute('data-resident-id'),
        name: button.getAttribute('data-name'),
        address: button.getAttribute('data-address'),
        contact: button.getAttribute('data-contact'),
        registered: button.getAttribute('data-registered'),
        barangayId: button.getAttribute('data-barangay-id'),
        civilStatus: button.getAttribute('data-civil-status'),
        occupation: button.getAttribute('data-occupation'),
        residentType: button.getAttribute('data-resident-type'),
        age: button.getAttribute('data-age'),
        gender: button.getAttribute('data-gender'),
        request: button.getAttribute('data-request'),
        date: button.getAttribute('data-date'),
        document: button.getAttribute('data-document'),
        status: button.getAttribute('data-status'),
        notes: button.getAttribute('data-notes'),
        deathDate: button.getAttribute('data-death-date'),
        profilePic: button.getAttribute('data-profile-pic')
      };

      // Store resident id on modal for later actions (e.g., marking deceased)
      if (modalFields.residentId) {
        residentModal.dataset.residentId = modalFields.residentId;
      } else {
        delete residentModal.dataset.residentId;
      }

      // Populate modal fields
      document.getElementById('modal-name').textContent = modalFields.name || '—';
      document.getElementById('modal-address').textContent = 'Address: ' + (modalFields.address || '—');
      document.getElementById('modal-contact').textContent = 'Contact: ' + (modalFields.contact || '—');
      document.getElementById('modal-registered').textContent = 'Registered: ' + (modalFields.registered || '—');
      document.getElementById('modal-barangay-id').textContent = 'Barangay ID: ' + (modalFields.barangayId || '—');
      document.getElementById('modal-civil-status').textContent = 'Civil Status: ' + (modalFields.civilStatus || '—');
      document.getElementById('modal-resident-type').textContent = 'Resident Type: ' + (modalFields.residentType || '—');
      document.getElementById('modal-age').textContent = 'Age: ' + (modalFields.age || '—');
      document.getElementById('modal-gender').textContent = 'Gender: ' + (modalFields.gender || '—');
      document.getElementById('modal-document').textContent = modalFields.document || '—';

      // Handle profile picture display
      const profilePicImg = document.getElementById('modal-profile-pic');
      const profilePicPlaceholder = document.getElementById('modal-profile-pic-placeholder');
      if (modalFields.profilePic && modalFields.profilePic.trim() !== '') {
        profilePicImg.src = modalFields.profilePic + '?t=' + Date.now();
        profilePicImg.style.display = 'block';
        profilePicPlaceholder.style.display = 'none';
      } else {
        profilePicImg.style.display = 'none';
        profilePicImg.src = '';
        profilePicPlaceholder.style.display = 'block';
      }

      // Set condition dropdown to current status
      const conditionSelect = document.getElementById('condition-select');
      const statusLower = (modalFields.status || 'active').toLowerCase();
      if (conditionSelect) {
        // Only set if value exists in options
        const validOptions = ['active', 'restricted', 'suspended'];
        if (validOptions.includes(statusLower)) {
          conditionSelect.value = statusLower;
        } else {
          conditionSelect.value = 'active';
        }
      }

      // Status badge logic (smaller oval)
      const statusBadge = document.getElementById('modal-status');
      statusBadge.textContent = modalFields.status || '—';

      // Reset classes
      statusBadge.className = '';

      // Smaller pill shape
      statusBadge.classList.add('rounded-pill', 'px-2', 'py-1', 'small', 'text-center');

      // Apply color based on resident status
      switch ((modalFields.status || '').toUpperCase()) {
        case 'ACTIVE':
          statusBadge.classList.add('bg-success');
          break;
        case 'INACTIVE':
          statusBadge.classList.add('bg-secondary', 'text-white');
          break;
        case 'DECEASED':
          statusBadge.classList.add('bg-dark', 'text-white');
          break;
        case 'SUSPENDED':
          statusBadge.classList.add('bg-danger', 'text-white');
          break;
        case 'RESTRICTED':
          statusBadge.classList.add('bg-warning', 'text-dark');
          break;
        default:
          statusBadge.classList.add('bg-secondary');
      }

      // Update top pill and action buttons based on status
      const activePill = document.getElementById('modal-active-pill');
      const editBtn = document.getElementById('modal-edit-btn');
      const historyBtn = document.getElementById('modal-history-btn');
      const deceasedBtn = document.getElementById('modal-deceased-btn');
      const conditionText = document.getElementById('condition-text');
      const residentStatus = (modalFields.status || '').toUpperCase();

      if (residentStatus === 'DECEASED') {
        if (activePill) {
          activePill.textContent = 'Deceased';
          activePill.className = '';
          activePill.classList.add('badge', 'bg-dark', 'text-white', 'rounded-pill', 'px-3', 'py-2');
        }
        if (conditionText) {
          conditionText.textContent = 'Deceased — this resident cannot request documents.';
        }
        if (editBtn) editBtn.style.display = 'none';
        if (deceasedBtn) deceasedBtn.style.display = 'none';
      } else if (residentStatus === 'SUSPENDED') {
        if (activePill) {
          activePill.textContent = 'Suspended';
          activePill.className = '';
          activePill.classList.add('badge', 'bg-danger', 'text-white', 'rounded-pill', 'px-3', 'py-2');
        }
        if (conditionText) {
          conditionText.textContent = 'Suspended — cannot request any documents for 2 weeks.';
        }
        if (editBtn) editBtn.style.display = 'inline-block';
        if (deceasedBtn) deceasedBtn.style.display = 'inline-block';
      } else if (residentStatus === 'RESTRICTED') {
        if (activePill) {
          activePill.textContent = 'Restricted';
          activePill.className = '';
          activePill.classList.add('badge', 'bg-warning', 'text-dark', 'rounded-pill', 'px-3', 'py-2');
        }
        if (conditionText) {
          conditionText.textContent = 'Restricted — cannot request Barangay Clearance, Barangay ID, Good Moral, Business Permit for 2 weeks.';
        }
        if (editBtn) editBtn.style.display = 'inline-block';
        if (deceasedBtn) deceasedBtn.style.display = 'inline-block';
      } else {
        // default to Active pill
        if (activePill) {
          activePill.textContent = 'Active';
          activePill.className = '';
          activePill.classList.add('badge', 'bg-success', 'text-white', 'rounded-pill', 'px-3', 'py-2');
        }
        if (conditionText) {
          conditionText.textContent = 'Active resident — can request documents without restrictions.';
        }
        if (editBtn) editBtn.style.display = 'inline-block';
        if (deceasedBtn) deceasedBtn.style.display = 'inline-block';
      }
      if (historyBtn) historyBtn.style.display = 'inline-block';

      // Populate date-of-death and remarks if the triggering button carries them
      const dodEl = document.getElementById('modal-date-of-death');
      const remarksEl = document.getElementById('modal-deceased-remarks');
      if (modalFields.deathDate) {
        dodEl.textContent = 'Date of Death: ' + modalFields.deathDate;
        dodEl.style.display = 'block';
      } else {
        dodEl.style.display = 'none';
      }
      if (modalFields.notes) {
        remarksEl.textContent = 'Remarks: ' + modalFields.notes;
        remarksEl.style.display = 'block';
      } else {
        remarksEl.style.display = 'none';
      }
    });
  });
  </script>

  <!-- Confirm Deceased Modal (improved layout & responsiveness) -->
  <div class="modal fade" id="confirmDeceasedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content border-0 shadow rounded-4">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-person-x me-2"></i>Confirm Mark as Deceased</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <!-- Left: Resident summary -->
            <div class="col-12 col-md-5">
              <div class="mb-3">
                <h6 class="mb-1 fw-semibold" id="confirm-deceased-name">Resident Name</h6>
                <div class="small text-muted" id="confirm-deceased-barangay-id">Barangay ID: —</div>
                <div class="small text-muted" id="confirm-deceased-age">Age: —</div>
                <div class="small text-muted" id="confirm-deceased-gender">Gender: —</div>
                <div class="small text-muted mt-2" id="confirm-deceased-contact">Contact: —</div>
              </div>

              <div class="card bg-light border-0 shadow-sm">
                <div class="card-body small text-muted">
                  <div class="fw-semibold mb-2">Before you continue</div>
                  <ul class="mb-0 ps-3">
                    <li>This will change the resident's status to <strong>DECEASED</strong>.</li>
                    <li>Records will remain in the system but be marked accordingly.</li>
                    <li>Provide a date or remarks to help auditing.</li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Right: Confirmation form -->
            <div class="col-12 col-md-7">
              <form id="confirm-deceased-form" class="row g-2">
                <div class="col-12">
                  <label class="form-label small mb-1">Date of Death (optional)</label>
                  <input type="date" id="deceased-date" class="form-control form-control-sm">
                </div>
                <div class="col-12">
                  <label class="form-label small mb-1">Remarks (optional)</label>
                  <textarea id="deceased-remarks" class="form-control form-control-sm" rows="3" placeholder="e.g., informed by family"></textarea>
                </div>
                <div class="col-12">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="deceased-archive">
                    <label class="form-check-label small" for="deceased-archive">Archive or lock further requests for this resident</label>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 justify-content-end confirm-actions">
          <button type="button" class="btn btn-danger btn-sm" id="confirm-deceased-btn"><i class="bi bi-check-lg me-1"></i>Mark Deceased</button>
        </div>
      </div>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const residentModalEl = document.getElementById('residentModal');
    const residentModalInstance = new bootstrap.Modal(residentModalEl);
    const confirmModalEl = document.getElementById('confirmDeceasedModal');
    const confirmModalInstance = new bootstrap.Modal(confirmModalEl);
    let confirmWasAccepted = false; // track whether user confirmed

    // When clicking the main "Mark as Deceased" button: hide resident modal and show confirmation
    document.getElementById('modal-deceased-btn').addEventListener('click', () => {
      // Check if the resident is already deceased by checking the pill/status
      const activePill = document.getElementById('modal-active-pill');
      const currentStatus = activePill ? activePill.textContent.trim().toUpperCase() : '';
      
      if (currentStatus === 'DECEASED') {
        // Already deceased, show error and don't proceed
        if (typeof window.showTransientAlert === 'function') {
          window.showTransientAlert('This resident is already marked as deceased.', 'warning');
        } else {
          alert('This resident is already marked as deceased.');
        }
        return;
      }
      
      // Populate confirm modal with resident details for context
      const name = document.getElementById('modal-name').textContent;
      const barangayIdText = document.getElementById('modal-barangay-id').textContent || '';
      const ageText = document.getElementById('modal-age').textContent || '';
      const genderText = document.getElementById('modal-gender').textContent || '';
      const contactText = document.getElementById('modal-contact').textContent || '';

      document.getElementById('confirm-deceased-name').textContent = name || 'this resident';
      document.getElementById('confirm-deceased-barangay-id').textContent = barangayIdText.replace('Barangay ID: ', '') || '';
      document.getElementById('confirm-deceased-age').textContent = ageText.replace('Age: ', '') || '';
      document.getElementById('confirm-deceased-gender').textContent = genderText.replace('Gender: ', '') || '';
      document.getElementById('confirm-deceased-contact').textContent = contactText.replace('Contact: ', '') || '';

      // Reset confirm form fields and flag
      document.getElementById('deceased-date').value = '';
      document.getElementById('deceased-remarks').value = '';
      document.getElementById('deceased-archive').checked = false;
      confirmWasAccepted = false;

      // Hide the resident modal first for clarity
      const inst = bootstrap.Modal.getInstance(residentModalEl);
      if (inst) inst.hide();

      // Show confirm modal
      confirmModalInstance.show();
    });

    // When user confirms deceased
    document.getElementById('confirm-deceased-btn').addEventListener('click', () => {
      // Get data for final confirmation
      const residentName = document.getElementById('confirm-deceased-name').textContent;
      
      // Show final confirmation using native confirm dialog
      const finalConfirm = confirm(
        `⚠️ FINAL CONFIRMATION ⚠️\n\n` +
        `Are you ABSOLUTELY SURE you want to mark "${residentName}" as DECEASED?\n\n` +
        `This action:\n` +
        `• CANNOT BE UNDONE\n` +
        `• Will permanently deactivate this resident's account\n` +
        `• The resident will NO LONGER be able to login or access the system\n\n` +
        `Click OK to proceed or Cancel to go back.`
      );
      
      if (!finalConfirm) {
        return; // User cancelled, do nothing
      }
      
      confirmWasAccepted = true;

      // Retrieve resident id stored earlier (remove 'r' prefix if present)
      let rid = residentModalEl.dataset.residentId;
      const numericId = rid ? rid.replace('r', '') : null;

      if (!numericId) {
        if (typeof window.showTransientAlert === 'function') {
          window.showTransientAlert('Error: Could not identify resident.', 'danger');
        } else {
          alert('Error: Could not identify resident.');
        }
        return;
      }

      // Get form values
      const dod = document.getElementById('deceased-date').value || null;
      const remarks = document.getElementById('deceased-remarks').value || null;
      const archiveRequests = document.getElementById('deceased-archive').checked;

      // Disable the button and show loading
      const confirmBtn = document.getElementById('confirm-deceased-btn');
      const originalBtnText = confirmBtn.innerHTML;
      confirmBtn.disabled = true;
      confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';

      // Make AJAX call to mark_deceased.php
      fetch('mark_deceased.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          resident_id: parseInt(numericId),
          date_of_death: dod,
          remarks: remarks,
          archive_requests: archiveRequests
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Update main modal status text
          const statusBadge = document.getElementById('modal-status');
          statusBadge.textContent = 'DECEASED';
          statusBadge.className = '';
          statusBadge.classList.add('rounded-pill', 'px-2', 'py-1', 'small', 'text-center', 'bg-dark', 'text-white');

          // Update top pill to indicate deceased
          const activePill = document.getElementById('modal-active-pill');
          if (activePill) {
            activePill.textContent = 'Deceased';
            activePill.className = '';
            activePill.classList.add('badge', 'bg-dark', 'text-white', 'rounded-pill', 'px-3', 'py-2');
          }

          // Populate date of death and remarks into the resident modal (if provided)
          const dodEl = document.getElementById('modal-date-of-death');
          const remarksEl = document.getElementById('modal-deceased-remarks');
          if (dod) {
            dodEl.textContent = 'Date of Death: ' + dod;
            dodEl.style.display = 'block';
          } else {
            dodEl.style.display = 'none';
          }
          if (remarks) {
            remarksEl.textContent = 'Remarks: ' + remarks;
            remarksEl.style.display = 'block';
          } else {
            remarksEl.style.display = 'none';
          }

          // Update the resident condition area
          const conditionText = document.getElementById('condition-text');
          if (conditionText) {
            conditionText.textContent = 'Deceased — this resident cannot request documents.';
          }

          // Hide or disable action buttons that shouldn't be used after marking deceased
          const editBtn = document.getElementById('modal-edit-btn');
          const historyBtn = document.getElementById('modal-history-btn');
          const deceasedBtn = document.getElementById('modal-deceased-btn');
          if (editBtn) editBtn.style.display = 'none';
          if (deceasedBtn) deceasedBtn.style.display = 'none';

          // Update the table row badge
          if (rid) {
            const tableBadges = document.querySelectorAll('.status-badge[data-resident-id]');
            tableBadges.forEach(b => {
              if (b.getAttribute('data-resident-id') === rid) {
                b.textContent = 'DECEASED';
                b.className = '';
                b.classList.add('badge', 'status-badge', 'bg-dark', 'text-white');
              }
            });

            // Update the corresponding View button's data attributes
            const viewButton = document.querySelector(`button[data-resident-id="${rid}"]`);
            if (viewButton) {
              viewButton.setAttribute('data-status', 'DECEASED');
              if (remarks) viewButton.setAttribute('data-notes', remarks);
              if (dod) viewButton.setAttribute('data-death-date', dod);
            }

            // Also update the table row's data-status attribute for filtering
            const tableRow = document.querySelector(`tr[data-status]`);
            if (tableRow) {
              const rowBadge = tableRow.querySelector(`.status-badge[data-resident-id="${rid}"]`);
              if (rowBadge) {
                tableRow.setAttribute('data-status', 'deceased');
              }
            }
          }

          // Close the confirm modal
          confirmModalInstance.hide();

          // Show success message
          if (typeof window.showTransientAlert === 'function') {
            window.showTransientAlert('Resident marked as deceased successfully. Account has been permanently deactivated.', 'success');
          } else {
            alert('Resident marked as deceased successfully.');
          }

          // Reload the page after a short delay to refresh the data
          setTimeout(() => {
            location.reload();
          }, 2000);
        } else {
          // Show error message
          if (typeof window.showTransientAlert === 'function') {
            window.showTransientAlert(data.message || 'Failed to mark resident as deceased.', 'danger');
          } else {
            alert('Error: ' + (data.message || 'Failed to mark resident as deceased.'));
          }
          // Re-enable button
          confirmBtn.disabled = false;
          confirmBtn.innerHTML = originalBtnText;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        if (typeof window.showTransientAlert === 'function') {
          window.showTransientAlert('An error occurred. Please try again.', 'danger');
        } else {
          alert('An error occurred. Please try again.');
        }
        // Re-enable button
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = originalBtnText;
      });
    });

    // If confirm modal is dismissed (cancel) and NOT accepted, re-open resident modal for continuity
    confirmModalEl.addEventListener('hidden.bs.modal', (e) => {
      if (!confirmWasAccepted && residentModalEl.dataset.residentId) {
        // small delay to ensure smooth transition
        setTimeout(() => residentModalInstance.show(), 150);
      }
      // reset the flag
      confirmWasAccepted = false;
    });

    // Helper: show a centered transient alert (top-center) for professional feedback
    // Expose globally so other scripts can reuse it: window.showTransientAlert(...)
    window.showTransientAlert = function(message, type = 'success') {
      const wrapper = document.createElement('div');
      wrapper.style.position = 'fixed';
      wrapper.style.top = '1.25rem';
      wrapper.style.left = '50%';
      wrapper.style.transform = 'translateX(-50%)';
      wrapper.style.zIndex = 1080;

      const alert = document.createElement('div');
      alert.className = `alert alert-${type} shadow-lg rounded`; // slightly larger visual
      alert.role = 'alert';
      alert.style.minWidth = '320px';
      alert.style.maxWidth = 'min(90vw,560px)';
      alert.style.textAlign = 'center';
      alert.style.opacity = '0';
      alert.style.transition = 'opacity 200ms ease-in-out, transform 200ms ease-in-out';
      alert.style.transform = 'translateY(-6px)';
      alert.innerHTML = `<strong>${message}</strong>`;

      wrapper.appendChild(alert);
      document.body.appendChild(wrapper);

      // fade + slide in
      requestAnimationFrame(() => { alert.style.opacity = '1'; alert.style.transform = 'translateY(0)'; });

      // remove after 2.5s
      setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-6px)';
        setTimeout(() => { document.body.removeChild(wrapper); }, 250);
      }, 2500);
    }
  });

  // Check if staff needs to change default password
  async function checkDefaultPassword() {
    try {
      const response = await fetch('check_default_password.php', {
        method: 'GET',
        cache: 'no-store'
      });
      
      const result = await response.json();
      
      if (result.success && result.isDefaultPassword) {
        const modal = new bootstrap.Modal(document.getElementById('firstLoginPasswordModal'));
        modal.show();
      }
    } catch (error) {
      console.error('Error checking default password:', error);
    }
  }

  function validateFirstLoginPassword(password) {
    const rules = {
      length: password.length >= 8,
      uppercase: /[A-Z]/.test(password),
      lowercase: /[a-z]/.test(password),
      number: /[0-9]/.test(password),
      special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
    };
    
    document.getElementById('firstLogin-rule-length').className = rules.length ? 'text-success' : 'text-muted';
    document.getElementById('firstLogin-rule-uppercase').className = rules.uppercase ? 'text-success' : 'text-muted';
    document.getElementById('firstLogin-rule-lowercase').className = rules.lowercase ? 'text-success' : 'text-muted';
    document.getElementById('firstLogin-rule-number').className = rules.number ? 'text-success' : 'text-muted';
    document.getElementById('firstLogin-rule-special').className = rules.special ? 'text-success' : 'text-muted';
    
    return Object.values(rules).every(Boolean);
  }

  document.getElementById('firstLoginNewPassword')?.addEventListener('input', function() {
    if (this.value) {
      validateFirstLoginPassword(this.value);
    }
  });

    document.getElementById('firstLoginSubmitBtn')?.addEventListener('click', async function() {
      const oldPassword = document.getElementById('firstLoginOldPassword').value;
      const newPassword = document.getElementById('firstLoginNewPassword').value;
      const confirmPassword = document.getElementById('firstLoginConfirmPassword').value;
      const errorDiv = document.getElementById('firstLoginPasswordError');
      const successDiv = document.getElementById('firstLoginPasswordSuccess');
      const submitBtn = this;

    errorDiv.classList.add('d-none');
    successDiv.classList.add('d-none');

      if (newPassword !== confirmPassword) {
        errorDiv.textContent = 'Passwords do not match';
        errorDiv.classList.remove('d-none');
        return;
      }

      if (!oldPassword) {
        errorDiv.textContent = 'Current password is required';
        errorDiv.classList.remove('d-none');
        return;
      }

    if (!validateFirstLoginPassword(newPassword)) {
      errorDiv.textContent = 'Password does not meet all requirements';
      errorDiv.classList.remove('d-none');
      return;
    }

    try {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Changing...';

      const formData = new FormData();
      formData.append('old_password', oldPassword);
      formData.append('new_password', newPassword);

      const response = await fetch('change_first_login_password.php', {
        method: 'POST',
        body: formData,
        cache: 'no-store'
      });

      const result = await response.json();

      if (result.success) {
        successDiv.textContent = 'Password changed successfully! Refreshing...';
        successDiv.classList.remove('d-none');
        
        setTimeout(() => {
          location.reload();
        }, 1500);
      } else {
        errorDiv.textContent = result.message || 'Failed to change password';
        errorDiv.classList.remove('d-none');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Change Password';
      }
    } catch (error) {
      console.error('Password change error:', error);
      errorDiv.textContent = 'An error occurred. Please try again.';
      errorDiv.classList.remove('d-none');
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Change Password';
    }
  });

  document.addEventListener('DOMContentLoaded', function() {
    checkDefaultPassword();
  });

  // Staff logout functionality
  document.getElementById('staffLogoutBtn')?.addEventListener('click', async function() {
    try {
      const staffId = sessionStorage.getItem('staff_id');
      
      if (!staffId) {
        window.location.href = 'staff-login.html';
        return;
      }
      
      const formData = new FormData();
      formData.append('staff_id', staffId);
      
      const response = await fetch('log_logout.php', {
        method: 'POST',
        body: formData,
        cache: 'no-store'
      });
      
      const result = await response.json();
      
      if (result.success) {
        sessionStorage.clear();
        window.location.href = 'staff-login.html';
      } else {
        console.error('Logout logging failed:', result.error);
        sessionStorage.clear();
        window.location.href = 'staff-login.html';
      }
    } catch (error) {
      console.error('Logout error:', error);
      sessionStorage.clear();
      window.location.href = 'staff-login.html';
    }
  });
  </script>
</body>
</html>

