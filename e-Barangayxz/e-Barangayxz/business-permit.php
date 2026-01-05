<?php
session_start();
include 'auth_check.php';
include 'db.php';

// Load resident profile for server-side prefill
$resident = [];
if (!empty($_SESSION['resident_id'])) {
  $rid = $_SESSION['resident_id'];
  $stmtR = $conn->prepare("SELECT first_name, last_name, middle_name, suffix, mobile, street, barangay, municipality, civil_status, birthday FROM residents WHERE id = ? LIMIT 1");
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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Business Permit Request</title>
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
      <h2 class="fw-bold mb-2 text-dark">Business Permit Request</h2>
      <p class="text-muted">Request a permit for operating a business within the barangay.</p>
    </div>
  </section>

  <!-- Form -->
  <div class="container pb-5">
    <div class="form-card">
      <form class="row g-3" id="businessPermitForm" method="post" enctype="multipart/form-data">

        <!-- Applicant Details -->
        <div class="col-12">
          <h5 class="section-title">Applicant Details</h5>
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
          <input type="date" class="form-control" name="dateOfBirth" id="dateOfBirth" value="<?php echo htmlspecialchars($resident['birthday'] ?? '', ENT_QUOTES); ?>" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" />
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
          <input type="text" class="form-control" name="completeAddress" value="<?php echo htmlspecialchars(($resident['street'] ?? '') . ($resident['barangay'] ? ', ' . $resident['barangay'] : '') . ($resident['municipality'] ? ', ' . $resident['municipality'] : ''), ENT_QUOTES); ?>" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Contact Number<span style="color: red;">*</span></label>
          <input type="tel" class="form-control" name="contactNumber" placeholder="09XX-XXX-XXXX" pattern="^0\d{3}-\d{3}-\d{4}$" value="<?php echo htmlspecialchars($formattedPhone, ENT_QUOTES); ?>" inputmode="numeric" title="This field is locked and uses your registered profile information" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" />
        </div>

        <!-- Business Details -->
        <div class="col-12 mt-3">
          <h5 class="section-title">Business Details</h5>
        </div>
        <div class="col-md-6">
          <label class="form-label">Business Name<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="businessName" required />
        </div>
        <div class="col-md-6">
          <label class="form-label">Business Type<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="businessType" required />
        </div>
        <div class="col-md-6">
          <label class="form-label">Business Location<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="businessLocation" required />
        </div>

        <!-- Document Upload -->
        <div class="col-12 mt-3">
          <h5 class="section-title">Document Upload</h5>
          <p class="text-muted">Upload one (1) valid government ID and proof of ownership. AI will verify your identity.</p>
        </div>
        <div class="col-md-6">
          <label class="form-label">Valid ID Upload<span style="color: red;">*</span></label>
          <input type="file" class="form-control" name="validId" accept="image/*" required />
          <p class="small text-muted mt-1">Upload a clear image (JPG, PNG) of your valid government ID.</p>
        </div>
        <div class="col-md-6">
          <label class="form-label">Lease Agreement or Proof of Ownership Upload<span style="color: red;">*</span></label>
          <input type="file" class="form-control" name="proofOfOwnership" accept="image/*,.pdf" required />
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="script.js"></script>
  <script src="contact-format.js"></script>
  <script src="name-validation.js"></script>
  <script src="id-validation.js"></script>
  <script>
    initializeNameValidation('businessPermitForm');
    // Initialize ID validation
    if (window.IdValidation) window.IdValidation.initialize('businessPermitForm');
    
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
    
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('businessPermitForm');
      if (form) {
        form.addEventListener('submit', async function(e) {
          e.preventDefault();
          if (form.checkValidity() === false) {
            e.stopPropagation();
            form.classList.add('was-validated');
            return;
          }

          // Check if civil status is set (required for all requests)
          const civilStatusInput = document.querySelector('input[name="civilStatus"]');
          if (civilStatusInput && !civilStatusInput.value) {
            alert('Please update your profile to set your Civil Status before submitting this request.');
            return;
          }

          // Prepare form data
          const formData = new FormData(form);
          formData.append('document_type', 'Business Permit');

          try {
            const response = await fetch('submit_business_permit.php', {
              method: 'POST',
              body: formData
            });

            const text = await response.text();
            console.log('Response status:', response.status);
            console.log('Response text:', text);
            
            let result;
            try {
              result = JSON.parse(text);
            } catch (e) {
              console.error('Failed to parse JSON:', text);
              alert('Error: Server returned invalid response. Check console.');
              return;
            }

            console.log('Parsed result:', result);

            if (result.success) {
              // Use shared success modal (will redirect on close)
              showSuccessAndRedirect('Your Business Permit request has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.');
            } else {
              console.error('Submission failed:', result);
              alert('Error: ' + (result.message || 'Failed to submit request'));
            }
          } catch (error) {
            console.error('Submission error:', error);
            alert('An error occurred while submitting your request. Please try again.');
          }
        });
      }
    });
  </script>
</body>
</html>
