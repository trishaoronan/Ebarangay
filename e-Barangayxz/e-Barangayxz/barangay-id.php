<?php
session_start();
include 'auth_check.php';
include 'db.php';

// Load resident profile for server-side prefill
$resident = [];
if (!empty($_SESSION['resident_id'])) {
  $rid = $_SESSION['resident_id'];
  $stmtR = $conn->prepare("SELECT first_name, last_name, middle_name, suffix, mobile, street, barangay, municipality, civil_status, birthday, gender, email FROM residents WHERE id = ? LIMIT 1");
  if ($stmtR) {
    $stmtR->bind_param('i', $rid);
    $stmtR->execute();
    $resR = $stmtR->get_result();
    $resident = $resR->fetch_assoc() ?: [];
    $stmtR->close();
  }
}

// Format phone number
$formattedPhone = '';
if (!empty($resident['mobile'])) {
  $digits = preg_replace('/\D/', '', $resident['mobile']);
  if (strlen($digits) >= 11) {
    $formattedPhone = substr($digits, 0, 4) . '-' . substr($digits, 4, 3) . '-' . substr($digits, 7);
  }
}
?>
<!-- DEBUG: mobile=<?php echo htmlspecialchars($resident['mobile'] ?? 'EMPTY', ENT_QUOTES); ?> | formattedPhone=<?php echo htmlspecialchars($formattedPhone ?: 'EMPTY', ENT_QUOTES); ?> -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Barangay ID Application</title>
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
      <h2 class="fw-bold mb-2 text-dark">Barangay ID Application</h2>
      <p class="text-muted">Please fill out the form accurately to process your official Barangay Identification Card.</p>
    </div>
  </section>

  <!-- Form -->
  <div class="container pb-5">
    <div class="form-card">
      <form class="row g-3" id="idApplicationForm" method="post" action="submit_barangay_id.php" enctype="multipart/form-data" data-backend="true">

        <!-- Personal Details -->
        <div class="col-12">
          <h5 class="fw-bold section-title">Personal Details</h5>
        </div>
        <div class="col-md-3">
          <label class="form-label">First Name<span style="color: red;">*</span></label>
          <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($resident['first_name'] ?? '', ENT_QUOTES); ?>" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" />
          <div class="invalid-feedback" id="firstNameError">First name must contain only letters and spaces.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Middle Name</label>
          <input type="text" class="form-control" id="middleName" name="middleName" value="<?php echo htmlspecialchars($resident['middle_name'] ?? '', ENT_QUOTES); ?>" readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" />
          <div class="invalid-feedback" id="middleNameError">Middle name must contain only letters and spaces.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Last Name<span style="color: red;">*</span></label>
          <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo htmlspecialchars($resident['last_name'] ?? '', ENT_QUOTES); ?>" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" />
          <div class="invalid-feedback" id="lastNameError">Last name must contain only letters and spaces.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Suffix (e.g., Jr., Sr.)</label>
          <input type="text" class="form-control" id="suffix" name="suffix" value="<?php echo htmlspecialchars($resident['suffix'] ?? '', ENT_QUOTES); ?>" readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" />
          <div class="invalid-feedback" id="suffixError">Suffix must contain only letters, spaces, and dots.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Date of Birth<span style="color: red;">*</span></label>
          <input type="date" class="form-control" name="dateOfBirth" id="dateOfBirth" value="<?php echo htmlspecialchars($resident['birthday'] ?? '', ENT_QUOTES); ?>" max="<?php echo date('Y-m-d'); ?>" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Gender<span style="color: red;">*</span></label>
          <?php 
          $hasGender = !empty($resident['gender']);
          if ($hasGender): ?>
            <input type="hidden" name="gender" value="<?php echo htmlspecialchars($resident['gender'], ENT_QUOTES); ?>" />
            <select class="form-select" required disabled style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information">
              <option value="">Select</option>
              <option<?php if ($resident['gender'] === 'Male') echo ' selected'; ?>>Male</option>
              <option<?php if ($resident['gender'] === 'Female') echo ' selected'; ?>>Female</option>
              <option<?php if ($resident['gender'] === 'Other') echo ' selected'; ?>>Other</option>
            </select>
          <?php else: ?>
            <select class="form-select" name="gender" required>
              <option value="">Select</option>
              <option>Male</option>
              <option>Female</option>
              <option>Other</option>
            </select>
            <small class="text-muted">Please select your gender (not set in profile)</small>
          <?php endif; ?>
        </div>
        <div class="col-md-3">
          <label class="form-label">Civil Status<span style="color: red;">*</span></label>
          <?php 
          $hasCivilStatus = !empty($resident['civil_status']);
          if ($hasCivilStatus): ?>
            <input type="hidden" name="civilStatus" value="<?php echo htmlspecialchars($resident['civil_status'], ENT_QUOTES); ?>" />
            <select class="form-select" required disabled style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information">
              <option value="">Select</option>
              <option<?php if ($resident['civil_status'] === 'Single') echo ' selected'; ?>>Single</option>
              <option<?php if ($resident['civil_status'] === 'Married') echo ' selected'; ?>>Married</option>
              <option<?php if ($resident['civil_status'] === 'Widowed') echo ' selected'; ?>>Widowed</option>
              <option<?php if ($resident['civil_status'] === 'Separated') echo ' selected'; ?>>Separated</option>
            </select>
          <?php else: ?>
            <input type="hidden" name="civilStatus" value="" />
            <select class="form-select" disabled style="background-color: #f0f0f0; cursor: not-allowed;" title="Please update your profile to set your civil status">
              <option value="">Select</option>
            </select>
            <small class="text-danger"><i class="bi bi-exclamation-circle"></i> Please <a href="resident-profile.php">update your profile</a> to set your civil status first.</small>
          <?php endif; ?>
        </div>
        <div class="col-md-4">
          <label class="form-label">Place of Birth (City/Province)<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="placeOfBirth" pattern="^[A-Za-z\s,.-]+$" title="Only letters, spaces, commas, periods, and hyphens allowed" required />
        </div>
        <div class="col-md-4">
          <label class="form-label">Citizenship<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="citizenship" value="Filipino" pattern="^[A-Za-z\s]+$" title="Only letters and spaces allowed" required />
        </div>
         <div class="col-md-4">
          <label class="form-label">How long have you lived in this Barangay?<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="yearsInBarangay" placeholder="e.g., 5 years" pattern="^[0-9]+\s*(year|years|month|months)$" title="Enter format like '5 years' or '6 months'" required />
        </div>
        <div class="col-12">
          <label class="form-label">Complete Address (House/Lot No., Street, Purok)<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="completeAddress" value="<?php echo htmlspecialchars(($resident['street'] ?? '') . ($resident['barangay'] ? ', ' . $resident['barangay'] : '') . ($resident['municipality'] ? ', ' . $resident['municipality'] : ''), ENT_QUOTES); ?>" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Contact Number<span style="color: red;">*</span></label>
          <input type="tel" id="contactNumber" class="form-control" name="contactNumber" placeholder="09XX-XXX-XXXX" pattern="^0\d{3}-\d{3}-\d{4}$" value="<?php echo htmlspecialchars($formattedPhone, ENT_QUOTES); ?>" inputmode="numeric" title="This field is locked and uses your registered profile information" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Email Address<span style="color: red;">*</span></label>
          <input type="email" class="form-control" name="email" placeholder="juan.delacruz@email.com" value="<?php echo htmlspecialchars($resident['email'] ?? '', ENT_QUOTES); ?>" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" />
        </div>

        <!-- Emergency Contact -->
        <div class="col-12 mt-3">
          <h5 class="fw-bold section-title">Emergency Contact Information</h5>
        </div>
        <div class="col-md-5">
          <label class="form-label">Emergency Contact Person<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="emergencyContactPerson" pattern="^[A-Za-z\s]+$" title="Only letters and spaces allowed" required />
        </div>
        <div class="col-md-4">
          <label class="form-label">Contact Person's Number<span style="color: red;">*</span></label>
          <input type="tel" class="form-control" name="emergencyContactNumber" placeholder="09XX-XXX-XXXX" pattern="^\d{4}-\d{3}-\d{4}$" inputmode="numeric" title="Please enter a valid 11-digit phone number (e.g., 0912-345-6789)" required />
        </div>
        <div class="col-md-3">
          <label class="form-label">Relationship<span style="color: red;">*</span></label>
          <select class="form-select" name="emergencyRelationship" required>
            <option value="">Select</option>
            <option>Parent</option>
            <option>Spouse</option>
            <option>Child</option>
            <option>Sibling</option>
            <option>Relative</option>
            <option>Friend</option>
            <option>Guardian</option>
            <option>Other</option>
          </select>
        </div>

        <!-- Documents -->
        <div class="col-12 mt-3">
          <h5 class="fw-bold section-title">Document Uploads</h5>
        </div>
        <div class="col-md-6">
          <label class="form-label">Upload Valid Government ID<span style="color: red;">*</span></label>
          <input type="file" class="form-control" name="validId" accept="image/*" required />
          <p class="small text-muted mt-1">Upload a clear image of your valid government ID (National ID, Driver's License, Passport, etc.). AI will verify your identity.</p>
        </div>
        <div class="col-md-6">
          <label class="form-label">Upload 2x2 ID Picture<span style="color: red;">*</span></label>
          <input type="file" class="form-control" name="idPicture" accept="image/*" required />
          <p class="small text-muted mt-1">Max file size: 2MB. Clear, recent photo with white background.</p>
        </div>
        <div class="col-md-6">
          <label class="form-label">Upload Proof of Residency (e.g., Utility Bill)<span style="color: red;">*</span></label>
          <input type="file" class="form-control" name="proofOfResidency" accept="image/*,.pdf" required />
          <p class="small text-muted mt-1">Max file size: 5MB. Must show your name and address.</p>
        </div>
      <!-- Mode of Release -->
        <div class="col-md-6">
          <label class="form-label">Mode of Release<span style="color: red;">*</span></label>
          <select class="form-select" name="modeOfRelease" id="modeOfRelease" required>
            <option value="">Select</option>
            <option value="Pickup">Pickup</option>
            <option value="Download">Download</option>
          </select>
        </div>
        
        <!-- Mode of Payment -->
        <div class="col-md-6">
          <label class="form-label">Mode of Payment<span style="color: red;">*</span></label>
          <select class="form-select" name="modeOfPayment" id="modeOfPayment" required>
            <option value="">Select</option>
            <option value="GCash">GCash</option>
            <option value="Cash">Cash</option>
          </select>
          <small class="text-muted">Document Fee: <strong>₱25.00</strong></small>
        </div>
        
        <!-- Payment Notice -->
        <div class="col-12" id="paymentNotice" style="display: none;">
          <div class="alert alert-info mb-0">
            <i class="bi bi-info-circle me-2"></i>
            <span id="paymentNoticeText"></span>
          </div>
        </div>

        <!-- Submit -->
        <div class="col-12 text-end">          <a href="resident-dashboard.php" class="btn btn-secondary px-4 me-2">Back</a>          <button type="submit" class="btn btn-danger px-4" id="submitBtn">Submit</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="script.js"></script>
  <script src="name-validation.js"></script>
  <script src="contact-format.js"></script>
  <script src="instant-prefill.js"></script>
  <script src="id-validation.js"></script>
  <script>
    initializeNameValidation('idApplicationForm');
    // Initialize ID validation for this form
    if (window.IdValidation) {
      window.IdValidation.initialize('idApplicationForm');
    }
    
    // Payment mode handling
    (function() {
      const modeOfRelease = document.getElementById('modeOfRelease');
      const modeOfPayment = document.getElementById('modeOfPayment');
      const paymentNotice = document.getElementById('paymentNotice');
      const paymentNoticeText = document.getElementById('paymentNoticeText');
      
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

    document.addEventListener('DOMContentLoaded', function () {
      const form = document.getElementById('idApplicationForm');
      const successModalEl = document.getElementById('successModal');
      const successMessageEl = document.getElementById('successMessage');
      const okBtn = document.getElementById('successOkBtn');
      if (!form) return;

      const successModal = successModalEl ? new bootstrap.Modal(successModalEl) : null;

      form.addEventListener('submit', async function (e) {
        e.preventDefault();
        
        // Check if civil status is set (required for all requests)
        const civilStatusInput = document.querySelector('input[name="civilStatus"]');
        if (civilStatusInput && !civilStatusInput.value) {
          alert('Please update your profile to set your Civil Status before submitting this request.');
          return;
        }
        
        if (form.checkValidity() === false) {
          form.classList.add('was-validated');
          return;
        }

        const formData = new FormData(form);
        try {
          const response = await fetch('submit_barangay_id.php', { method: 'POST', body: formData });
          const data = await response.json();

          if (data.success) {
            if (successMessageEl && data.message) {
              successMessageEl.textContent = data.message;
            }
            if (successModal) successModal.show();
          } else {
            alert(data.message || 'Submission failed.');
          }
        } catch (err) {
          console.error('Submission error:', err);
          alert('Submission failed. Please try again.');
        }
      });

      if (okBtn) {
        okBtn.addEventListener('click', function () {
          window.location.href = 'resident-dashboard.php';
        });
      }
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
          <p class="text-muted mb-0" id="successMessage">Your Barangay ID application has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.</p>
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

