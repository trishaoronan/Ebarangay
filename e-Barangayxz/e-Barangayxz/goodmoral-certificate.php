<?php
session_start();
include 'auth_check.php';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Good Moral Character Certificate Request</title>
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
      <h2 class="fw-bold mb-2 text-dark">Good Moral Character Certificate Request</h2>
      <p class="text-muted">Request a certification attesting to your good moral character for purposes such as employment or immigration.</p>
    </div>
  </section>

  <!-- Form -->
  <div class="container pb-5">
    <div class="form-card">
      <form class="row g-3" id="goodCharacterForm" enctype="multipart/form-data">

        <!-- Applicant Details -->
        <div class="col-12">
          <h5 class="section-title">Applicant Details</h5>
        </div>
        <div class="col-md-3">
          <label class="form-label">First Name<span style="color: red;">*</span></label>
          <input type="text" class="form-control" id="firstName" name="firstName" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" value="<?php echo htmlspecialchars($resident['first_name'] ?? '', ENT_QUOTES); ?>" />
          <div class="invalid-feedback" id="firstNameError">First name must contain only letters and spaces.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Middle Name</label>
          <input type="text" class="form-control" id="middleName" name="middleName" readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" />
          <div class="invalid-feedback" id="middleNameError">Middle name must contain only letters and spaces.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Last Name<span style="color: red;">*</span></label>
          <input type="text" class="form-control" id="lastName" name="lastName" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" value="<?php echo htmlspecialchars($resident['last_name'] ?? '', ENT_QUOTES); ?>" />
          <div class="invalid-feedback" id="lastNameError">Last name must contain only letters and spaces.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Suffix (e.g., Jr., Sr.)</label>
          <input type="text" class="form-control" id="suffix" name="suffix" readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" />
          <div class="invalid-feedback" id="suffixError">Suffix must contain only letters, spaces, and dots.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Date of Birth<span style="color: red;">*</span></label>
          <input type="date" class="form-control" name="dateOfBirth" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" value="<?php echo htmlspecialchars($resident['birthday'] ?? '', ENT_QUOTES); ?>" />
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
        <div class="col-md-8">
          <label class="form-label">Complete Address<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="completeAddress" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" value="<?php echo htmlspecialchars(trim((($resident['street'] ?? '') . (isset($resident['barangay']) && $resident['barangay'] ? ', '.$resident['barangay'] : '') . (isset($resident['municipality']) && $resident['municipality'] ? ', '.$resident['municipality'] : ''))), ENT_QUOTES); ?>" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Contact Number<span style="color: red;">*</span></label>
          <input type="tel" class="form-control" name="contactNumber" placeholder="09XX-XXX-XXXX" pattern="^0\d{3}-\d{3}-\d{4}$" inputmode="numeric" title="This field is locked and uses your registered profile information" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" value="<?php 
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
          <h5 class="section-title">Purpose and Declaration</h5>
        </div>
        <div class="col-12">
          <label class="form-label">Specific Purpose for the Certificate<span style="color: red;">*</span></label>
          <select class="form-select" id="specificPurpose" name="specificPurpose" required>
            <option value="">Select a purpose</option>
            <option value="Job Application">Job Application</option>
            <option value="Visa Processing">Visa Processing</option>
            <option value="Scholarship">Scholarship</option>
            <option value="Educational Requirement">Educational Requirement</option>
            <option value="Loan Application">Loan Application</option>
            <option value="Professional License">Professional License</option>
            <option value="Government Position">Government Position</option>
            <option value="Others">Others (Please specify)</option>
          </select>
        </div>
        <div class="col-12" id="otherPurposeDiv" style="display: none;">
          <label class="form-label">Please specify the purpose<span style="color: red;">*</span></label>
          <input type="text" class="form-control" id="otherPurposeText" name="otherPurposeText" placeholder="Enter purpose" required />
        </div>
        <div class="col-12 mt-2">
          <div class="form-check border rounded p-3 bg-light">
            <input type="checkbox" class="form-check-input me-2 mt-1" required />
            <p class="mb-0">I hereby declare that I have maintained good moral character and have no criminal record or community issues.</p>
          </div>
        </div>

        <!-- Document Uploads -->
        <div class="col-12 mt-3">
          <h5 class="section-title">Document Upload</h5>
          <p class="text-muted">Upload one (1) valid government ID. AI will verify your identity.</p>
        </div>
        <div class="col-md-6">
          <label class="form-label">Valid ID Upload<span style="color: red;">*</span></label>
          <input type="file" class="form-control" name="validId" accept="image/*" required />
          <p class="small text-muted mt-1">Upload a clear image (JPG, PNG) of your valid government ID.</p>
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
        <div class="col-12 text-end mt-3">
          <a href="resident-dashboard.php" class="btn btn-secondary px-4 me-2">Back</a>
          <button type="submit" class="btn btn-danger px-4" id="submitBtn">Submit</button>
        </div>

      </form>
    </div>
  </div>

  <!-- Success Modal -->
  <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
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
          <p class="text-muted mb-0" id="successMessage">Your Good Moral Certificate request has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.</p>
        </div>
        <div class="modal-footer border-top-0 justify-content-center py-3">
          <button type="button" class="btn btn-success px-5 py-2 rounded-3" id="successOkBtn" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="script.js"></script>
  <script src="contact-format.js"></script>
  <script src="name-validation.js"></script>
  <script src="id-validation.js"></script>
  <script>
    // initialize name validation for this form (correct form id)
    initializeNameValidation('goodCharacterForm');
    // Initialize ID validation
    if (window.IdValidation) window.IdValidation.initialize('goodCharacterForm');
    
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

    // Handle "Others" option for purpose
    const purposeSelect = document.getElementById('specificPurpose');
    const otherPurposeDiv = document.getElementById('otherPurposeDiv');
    const otherPurposeText = document.getElementById('otherPurposeText');
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
      validateField('specificPurpose', 'specificPurposeError', isPurposeValid);
    });

    // Form submission handler
    const successModal = new bootstrap.Modal(document.getElementById('successModal'), {});
    
    document.getElementById('goodCharacterForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      // Check if civil status is set (required for all requests)
      const civilStatusInput = document.querySelector('input[name="civilStatus"]');
      if (civilStatusInput && !civilStatusInput.value) {
        alert('Please update your profile to set your Civil Status before submitting this request.');
        return;
      }
      
      const formData = new FormData(this);
      
      try {
        const response = await fetch('submit_good_moral.php', {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
          document.getElementById('successMessage').textContent = 'Your Good Moral Certificate request has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.';
          successModal.show();
          document.getElementById('successOkBtn').addEventListener('click', function() {
            window.location.href = 'resident-dashboard.php';
          });
        } else {
          alert('Error: ' + result.message);
        }
      } catch (error) {
        console.error('Submission error:', error);
      }
    });
  </script>

  <style>
    .modal-content { border-radius: 20px; }
    .modal-header { border-radius: 20px 20px 0 0; }
    .btn-success { background-color: #28a745; border: none; font-weight: 600; }
    .btn-success:hover { background-color: #218838; }
  </style>
</body>
</html>

