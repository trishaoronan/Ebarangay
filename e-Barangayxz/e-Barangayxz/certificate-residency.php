<?php
// certificate-residency.php
session_start();

// Check if resident is logged in
if (!isset($_SESSION['resident_id'])) {
    header('Location: login-register.html');
    exit;
}

include 'db.php';

// Load resident profile for server-side prefill
$resident = [];
if (!empty($_SESSION['resident_id'])) {
  $rid = $_SESSION['resident_id'];
  $stmtR = $conn->prepare("SELECT first_name, middle_name, last_name, suffix, mobile, street, barangay, municipality, civil_status, birthday FROM residents WHERE id = ? LIMIT 1");
  if ($stmtR) {
    $stmtR->bind_param('i', $rid);
    $stmtR->execute();
    $resR = $stmtR->get_result();
    $resident = $resR->fetch_assoc() ?: [];
    $stmtR->close();
  }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_residency'])) {
    $resident_id = $_SESSION['resident_id'];
    
    // Get form data
    $last_name = $_POST['last_name'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $middle_name = $_POST['middle_name'] ?? '';
    $suffix = $_POST['suffix'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $civil_status = $_POST['civil_status'] ?? '';
    $complete_address = $_POST['complete_address'] ?? '';
    $date_started_residing = $_POST['date_started_residing'] ?? '';
    $household_head_name = $_POST['household_head_name'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $mode_of_release = $_POST['mode_of_release'] ?? '';
    $mode_of_payment = $_POST['modeOfPayment'] ?? 'GCash';
    
    // Validate required fields
    if (empty($last_name) || empty($first_name) || empty($date_of_birth) || empty($civil_status) || 
        empty($complete_address) || empty($date_started_residing) || empty($contact_number) || 
        empty($purpose) || empty($mode_of_release) || empty($mode_of_payment)) {
        $error = 'All required fields must be filled.';
    } else {
        // Handle valid ID upload
        $valid_id_path = null;
        if (isset($_FILES['valid_id']) && $_FILES['valid_id']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
            $filename = $_FILES['valid_id']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                $error = 'Invalid file type. Only JPG, PNG, and PDF allowed.';
            } else {
                // Create uploads directory if it doesn't exist
                $upload_dir = 'uploads/documents/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_filename = 'residency_id_' . $resident_id . '_' . time() . '.' . $ext;
                $valid_id_path = $upload_dir . $new_filename;
                
                if (!move_uploaded_file($_FILES['valid_id']['tmp_name'], $valid_id_path)) {
                    $error = 'Failed to upload valid ID.';
                }
            }
        }
        
        if (!isset($error)) {
            // Check if mode_of_payment column exists, if not add it
            $columnCheck = $conn->query("SHOW COLUMNS FROM requests LIKE 'mode_of_payment'");
            if ($columnCheck->num_rows === 0) {
                $conn->query("ALTER TABLE requests ADD COLUMN mode_of_payment VARCHAR(20) DEFAULT 'GCash' AFTER document_type");
            }
            
            // Insert into requests table
            $document_type = 'Certificate of Residency';
            $status = 'pending';
            
            $stmt = $conn->prepare("INSERT INTO requests (resident_id, document_type, mode_of_payment, status, requested_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param('isss', $resident_id, $document_type, $mode_of_payment, $status);
            
            if ($stmt->execute()) {
                $request_id = $conn->insert_id;
                $stmt->close();
                
                // Insert into certificate_of_residency table
                $stmt2 = $conn->prepare("INSERT INTO certificate_of_residency 
                    (request_id, resident_id, last_name, first_name, middle_name, suffix, date_of_birth, civil_status, 
                     complete_address, date_started_residing, household_head_name, contact_number, purpose, valid_id_path, mode_of_release) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt2->bind_param('iisssssssssssss', 
                    $request_id, $resident_id, $last_name, $first_name, $middle_name, $suffix, 
                    $date_of_birth, $civil_status, $complete_address, $date_started_residing, 
                    $household_head_name, $contact_number, $purpose, $valid_id_path, $mode_of_release);
                
                if ($stmt2->execute()) {
                    $success = 'Certificate of Residency request submitted successfully!';
                    $_SESSION['success_message'] = $success;
                    header('Location: resident-dashboard.php');
                    exit;
                } else {
                    $error = 'Failed to save certificate details: ' . $stmt2->error;
                }
                $stmt2->close();
            } else {
                $error = 'Failed to create request: ' . $stmt->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Certificate of Residency Request</title>
  <link rel="icon" type="image/jpeg" href="pics/eb-logo.jpg"> <!--FAVICON-->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="style.css" />
</head>
<body class="doc-page">

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg shadow-sm">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="resident-dashboard.php">
        <img src="pics/eb-logo.jpg" alt="eBarangay Logo" class="me-2" style="height:40px; width:auto;" />
        <span class="fw-bold">eBarangay</span>
        <span class="ms-2 text-light small">ONLINE SERVICES</span>
      </a>
    </div>
  </nav>

  <!-- Page Header -->
  <section class="page-header mb-4">
    <div class="container">
      <h2 class="fw-bold mb-2 text-dark">Certificate of Residency Request</h2>
      <p class="text-muted">Request a certification confirming your residency in the barangay for a specified period.</p>
    </div>
  </section>

  <!-- Form -->
  <div class="container pb-5">
    <?php if (isset($error)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    
    <div class="form-card">
      <form class="row g-3" id="residencyForm" method="POST" enctype="multipart/form-data">

        <!-- Applicant Details -->
        <div class="col-12">
          <h5 class="section-title">Applicant Details</h5>
        </div>
        <div class="col-md-3">
          <label class="form-label">First Name<span style="color: red;">*</span></label>
          <input name="first_name" id="res_firstName" type="text" class="form-control" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" value="<?php echo htmlspecialchars($resident['first_name'] ?? '', ENT_QUOTES); ?>" />
          <div class="invalid-feedback" id="res_firstNameError">First name must contain only letters and spaces.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Middle Name</label>
          <input name="middle_name" id="res_middleName" type="text" class="form-control" readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" value="<?php echo htmlspecialchars($resident['middle_name'] ?? '', ENT_QUOTES); ?>" />
          <div class="invalid-feedback" id="res_middleNameError">Middle name must contain only letters and spaces.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Last Name<span style="color: red;">*</span></label>
          <input name="last_name" id="res_lastName" type="text" class="form-control" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" value="<?php echo htmlspecialchars($resident['last_name'] ?? '', ENT_QUOTES); ?>" />
          <div class="invalid-feedback" id="res_lastNameError">Last name must contain only letters and spaces.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Suffix (e.g., Jr., Sr.)</label>
          <input name="suffix" id="res_suffix" type="text" class="form-control" readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" value="<?php echo htmlspecialchars($resident['suffix'] ?? '', ENT_QUOTES); ?>" />
          <div class="invalid-feedback" id="res_suffixError">Suffix must contain only letters, spaces, and dots.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Date of Birth<span style="color: red;">*</span></label>
          <input name="date_of_birth" id="res_dob" type="date" class="form-control" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" value="<?php echo htmlspecialchars($resident['birthday'] ?? '', ENT_QUOTES); ?>" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Civil Status<span style="color: red;">*</span></label>
          <?php 
          $hasCivilStatus = !empty($resident['civil_status']);
          if ($hasCivilStatus): ?>
            <input type="hidden" name="civil_status" value="<?php echo htmlspecialchars($resident['civil_status'], ENT_QUOTES); ?>" />
            <select class="form-select" required disabled style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information">
              <option value="">Select</option>
              <option<?php if ($resident['civil_status'] === 'Single') echo ' selected'; ?>>Single</option>
              <option<?php if ($resident['civil_status'] === 'Married') echo ' selected'; ?>>Married</option>
              <option<?php if ($resident['civil_status'] === 'Widowed') echo ' selected'; ?>>Widowed</option>
              <option<?php if ($resident['civil_status'] === 'Separated') echo ' selected'; ?>>Separated</option>
            </select>
          <?php else: ?>
            <input type="hidden" name="civil_status" value="" />
            <select class="form-select" disabled style="background-color: #f0f0f0; cursor: not-allowed;" title="Please update your profile to set your civil status">
              <option value="">Select</option>
            </select>
            <small class="text-danger"><i class="bi bi-exclamation-circle"></i> Please <a href="resident-profile.php">update your profile</a> to set your civil status first.</small>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Complete Address in Barangay<span style="color: red;">*</span></label>
          <input name="complete_address" id="res_address" type="text" class="form-control" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" value="<?php echo htmlspecialchars(trim((($resident['street'] ?? '') . (isset($resident['barangay']) && $resident['barangay'] ? ', '.$resident['barangay'] : '') . (isset($resident['municipality']) && $resident['municipality'] ? ', '.$resident['municipality'] : ''))), ENT_QUOTES); ?>" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Date Started Residing<span style="color: red;">*</span></label>
          <input name="date_started_residing" id="res_dateStarted" type="date" class="form-control" max="<?php echo date('Y-m-d'); ?>" required />
        </div>
        <div class="col-md-3">
          <label class="form-label">Household Head Name</label>
          <input name="household_head_name" id="res_householdHead" type="text" class="form-control" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Contact Number<span style="color: red;">*</span></label>
          <input name="contact_number" id="res_contactNumber" type="tel" class="form-control" placeholder="09XX-XXX-XXXX" inputmode="numeric" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" value="<?php 
            $mobile = $resident['mobile'] ?? '';
            if ($mobile) {
              $digits = preg_replace('/\D/', '', $mobile);
              if (strlen($digits) == 11) {
                $mobile = substr($digits, 0, 4) . '-' . substr($digits, 4, 3) . '-' . substr($digits, 7);
              }
            }
            echo htmlspecialchars($mobile, ENT_QUOTES); 
          ?>" />
        </div>

        <!-- Purpose -->
        <div class="col-12 mt-3">
          <h5 class="section-title">Purpose</h5>
        </div>
        <div class="col-12">
          <label class="form-label">Specific Purpose for the Certificate<span style="color: red;">*</span></label>
          <select class="form-select" id="res_purpose" name="purpose" required>
            <option value="">Select a purpose</option>
            <option value="School Enrollment">School Enrollment</option>
            <option value="Voter's ID">Voter's ID</option>
            <option value="Bank Account">Bank Account</option>
            <option value="Government ID">Government ID</option>
            <option value="Business Registration">Business Registration</option>
            <option value="Loan Application">Loan Application</option>
            <option value="Utility Connection">Utility Connection</option>
            <option value="Others">Others (Please specify)</option>
          </select>
        </div>
        <div class="col-12" id="res_otherPurposeDiv" style="display: none;">
          <label class="form-label">Please specify the purpose<span style="color: red;">*</span></label>
          <input type="text" class="form-control" id="res_otherPurposeText" name="res_otherPurposeText" placeholder="Enter purpose" required />
        </div>

        <!-- Document Uploads -->
        <div class="col-12 mt-3">
          <h5 class="section-title">Document Upload</h5>
          <p class="text-muted">Upload one (1) valid government ID. AI will verify your identity.</p>
        </div>
        <div class="col-md-6">
          <label class="form-label">Valid ID Upload<span style="color: red;">*</span></label>
          <input name="valid_id" id="res_validId" type="file" class="form-control" accept="image/*" required />
          <p class="small text-muted mt-1">Upload a clear image (JPG, PNG) of your valid government ID.</p>
        </div>
        <!-- Mode of Release -->
        <div class="col-md-6">
          <label class="form-label">Mode of Release<span style="color: red;">*</span></label>
          <select name="mode_of_release" id="res_modeOfRelease" class="form-select" required>
            <option value="">Select</option>
            <option value="Pickup">Pickup</option>
            <option value="Download">Download</option>
          </select>
        </div>
        
        <!-- Mode of Payment -->
        <div class="col-md-6">
          <label class="form-label">Mode of Payment<span style="color: red;">*</span></label>
          <select class="form-select" name="mode_of_payment" id="res_modeOfPayment" required>
            <option value="">Select</option>
            <option value="GCash">GCash</option>
            <option value="Cash">Cash</option>
          </select>
          <small class="text-muted">Document Fee: <strong>₱25.00</strong></small>
        </div>
        
        <!-- Payment Notice -->
        <div class="col-12" id="res_paymentNotice" style="display: none;">
          <div class="alert alert-info mb-0">
            <i class="bi bi-info-circle me-2"></i>
            <span id="res_paymentNoticeText"></span>
          </div>
        </div>

        <!-- Submit -->
        <div class="col-12 text-end mt-3">
          <a href="resident-dashboard.php" class="btn btn-secondary px-4 me-2">Back</a>
          <button type="submit" name="submit_residency" id="res_submitBtn" class="btn btn-danger px-4">Submit</button>
        </div>

      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="contact-format.js"></script>
  <script src="name-validation.js"></script>
  <script src="id-validation.js"></script>
  <script>
    // Custom initialization for certificate-residency with res_ prefix
    initializeNameValidation('', 'res_');
    // Initialize ID validation for residency form
    if (window.IdValidation) window.IdValidation.initialize('residencyForm');
    
    // Payment mode handling
    (function() {
      const modeOfRelease = document.getElementById('res_modeOfRelease');
      const modeOfPayment = document.getElementById('res_modeOfPayment');
      const paymentNotice = document.getElementById('res_paymentNotice');
      const paymentNoticeText = document.getElementById('res_paymentNoticeText');
      const submitBtn = document.getElementById('res_submitBtn');
      
      function handlePaymentMode() {
        const releaseValue = modeOfRelease.value;
        const paymentValue = modeOfPayment.value;
        const cashOption = modeOfPayment.querySelector('option[value="Cash"]');
        
        if (releaseValue === 'Download') {
          modeOfPayment.value = 'GCash';
          cashOption.disabled = true;
          paymentNotice.style.display = 'block';
          paymentNoticeText.innerHTML = '<strong>GCash payment is required for Download.</strong> You will receive payment instructions after submission.';
        } else {
          cashOption.disabled = false;
          if (paymentValue === 'Cash') {
            paymentNotice.style.display = 'block';
            paymentNoticeText.innerHTML = '<strong>Cash payment selected.</strong> Please pay ₱25.00 at the barangay cashier upon pickup.';
          } else if (paymentValue === 'GCash') {
            paymentNotice.style.display = 'block';
            paymentNoticeText.innerHTML = '<strong>GCash payment selected.</strong> You will receive payment instructions after submission.';
          } else {
            paymentNotice.style.display = 'none';
          }
        }
      }
      
      modeOfRelease.addEventListener('change', handlePaymentMode);
      modeOfPayment.addEventListener('change', handlePaymentMode);
    })();
    
    // Handle "Others" option for purpose
    const purposeSelect = document.getElementById('res_purpose');
    const otherPurposeDiv = document.getElementById('res_otherPurposeDiv');
    const otherPurposeText = document.getElementById('res_otherPurposeText');
    if (purposeSelect && otherPurposeDiv) {
      purposeSelect.addEventListener('change', function(e) {
        if (e.target.value === 'Others') {
          otherPurposeDiv.style.display = 'block';
          otherPurposeText.required = true;
        } else {
          otherPurposeDiv.style.display = 'none';
          otherPurposeText.value = '';
          otherPurposeText.required = false;
        }
      });
    }
    
    // Additional validation for purpose field
    document.addEventListener('DOMContentLoaded', function() {
      // Remove validation from dropdown - dropdowns are always valid
      
      // Handle form submission with AJAX
      const form = document.getElementById('residencyCertificateForm');
      if (form) {
        form.addEventListener('submit', function(e) {
          e.preventDefault();
          
          // Check if civil status is set (required for all requests)
          const civilStatusInput = document.querySelector('input[name="civil_status"]');
          if (civilStatusInput && !civilStatusInput.value) {
            alert('Please update your profile to set your Civil Status before submitting this request.');
            return;
          }
          
          // Validate date started residing is not in the future
          const dateStartedInput = document.getElementById('res_dateStarted');
          if (dateStartedInput && dateStartedInput.value) {
            const selectedDate = new Date(dateStartedInput.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            if (selectedDate > today) {
              alert('Date Started Residing cannot be in the future.');
              dateStartedInput.focus();
              return;
            }
          }
          
          // If "Others" is selected, copy the custom text to purpose
          if (purposeSelect && purposeSelect.value === 'Others') {
            const otherText = document.getElementById('res_otherPurposeText');
            if (otherText && otherText.value) {
              purposeSelect.value = otherText.value;
            }
          }
          
          // Remove was-validated class temporarily to check validity without visual errors on dropdown
          form.classList.remove('was-validated');
          
          if (form.checkValidity() === false) {
            e.stopPropagation();
            form.classList.add('was-validated');
            // Ensure the dropdown itself doesn't show invalid styling
            if (purposeSelect) {
              purposeSelect.classList.remove('is-invalid');
            }
            return;
          }
          
          const formData = new FormData(form);
          formData.append('submit_residency', '1');
          
          fetch('certificate-residency.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.text())
          .then(data => {
            const modal = new bootstrap.Modal(document.getElementById('successModal'));
            document.getElementById('successTitle').textContent = 'Submitted Successfully!';
            document.getElementById('successMessage').textContent = 'Your Certificate of Residency request has been submitted successfully. Verification will be conducted, and you will be notified when the certificate is ready.';
            modal.show();
            document.getElementById('successOkBtn').addEventListener('click', function() {
              window.location.href = 'resident-dashboard.php';
            });
          })
          .catch(error => {
            alert('An error occurred while submitting your request. Please try again.');
            console.error('Error:', error);
          });
        });
      }
      
      // Trigger phone number formatting on page load
      setTimeout(() => {
        const contactEl = document.querySelector('input[name="contact_number"]');
        if (contactEl && contactEl.value) {
          contactEl.dispatchEvent(new Event('input', { bubbles: true }));
        }
      }, 100);
    });
  </script>

  <!-- Success Modal -->
  <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg rounded-4">
        <div class="modal-header bg-success text-white border-0 rounded-top-4 py-3">
          <h5 class="modal-title fw-bold" style="color: white;"><i class="bi bi-check-circle me-2"></i> Success!</h5>
        </div>
        <div class="modal-body text-center py-5">
          <div class="mb-4">
            <div style="width: 80px; height: 80px; margin: 0 auto; border: 3px solid #28a745; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
              <i class="bi bi-check2 text-success" style="font-size: 50px;"></i>
            </div>
          </div>
          <h4 class="fw-bold mb-3" id="successTitle">Submitted Successfully!</h4>
          <p class="text-muted mb-0" id="successMessage">Your Certificate of Residency request has been submitted successfully. Verification will be conducted, and you will be notified when the certificate is ready.</p>
        </div>
        <div class="modal-footer border-top-0 justify-content-center py-3">
          <button type="button" class="btn btn-success px-5 py-2 rounded-3" id="successOkBtn" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>

  <style>
    .modal-content { border-radius: 20px; }
    .modal-header { border-radius: 20px 20px 0 0; }
    .btn-success { background-color: #28a745; border: none; font-weight: 600; }
    .btn-success:hover { background-color: #218838; }
  </style>
</body>
</html>
