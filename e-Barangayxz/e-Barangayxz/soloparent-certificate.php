<?php
session_start();
include 'auth_check.php';
include 'db.php';

$resident = [];
$formattedPhone = '';
$fullAddress = '';
if (!empty($_SESSION['resident_id'])) {
    $rid = $_SESSION['resident_id'];
  $stmtR = $conn->prepare("SELECT first_name, middle_name, last_name, suffix, mobile, street, barangay, municipality, civil_status, birthday FROM residents WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $rid);
        $stmt->execute();
        $res = $stmt->get_result();
        $resident = $res->fetch_assoc() ?: [];
        $stmt->close();
    }
    if (!empty($resident['mobile'])) {
        $digits = preg_replace('/\D/', '', $resident['mobile']);
        if (strlen($digits) >= 11) {
            $formattedPhone = substr($digits, 0, 4) . '-' . substr($digits, 4, 3) . '-' . substr($digits, 7, 4);
        } else {
            $formattedPhone = $resident['mobile'];
        }
    }
    $parts = [];
    if (!empty($resident['street'])) $parts[] = $resident['street'];
    if (!empty($resident['barangay'])) $parts[] = $resident['barangay'];
    if (!empty($resident['municipality'])) $parts[] = $resident['municipality'];
    if ($parts) $fullAddress = implode(', ', $parts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Certificate of Solo Parent Request</title>
  <link rel="icon" type="image/jpeg" href="pics/eb-logo.jpg">
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
      <h2 class="fw-bold mb-2 text-dark">Certificate of Solo Parent Request</h2>
      <p class="text-muted">Request a certification for solo parent status for benefits and assistance.</p>
    </div>
  </section>

  <!-- Form -->
  <div class="container pb-5">
    <div class="form-card">
      <form class="row g-3" id="soloParentForm" method="post" action="submit_solo_parent.php" enctype="multipart/form-data" data-backend="true">

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
        <div class="col-md-8">
          <label class="form-label">Complete Address<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="completeAddress" value="<?php echo htmlspecialchars($fullAddress, ENT_QUOTES); ?>" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Contact Number<span style="color: red;">*</span></label>
          <input type="tel" id="contactNumber" class="form-control" name="contactNumber" placeholder="09XX-XXX-XXXX" pattern="^0\d{3}-\d{3}-\d{4}$" inputmode="numeric" title="This field is locked and uses your registered profile information" value="<?php echo htmlspecialchars($formattedPhone, ENT_QUOTES); ?>" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" />
        </div>

        <!-- Solo Parent Details -->
        <div class="col-12 mt-3">
          <h5 class="section-title">Solo Parent Details</h5>
        </div>
        <div class="col-md-6">
          <label class="form-label">Number of Children<span style="color: red;">*</span></label>
          <input type="number" class="form-control" name="childrenCount" min="0" required />
        </div>
        <div class="col-md-6">
          <label class="form-label">Ages of Children (comma-separated)</label>
          <input type="text" class="form-control" name="childrenAges" placeholder="e.g., 5, 8, 10" />
        </div>
        <div class="col-12">
          <label class="form-label">Reason for Solo Parent Status<span style="color: red;">*</span></label>
          <select class="form-select" id="reason" name="reason" required>
            <option value="">Select a reason</option>
            <option value="Widowed">Widowed</option>
            <option value="Separated">Separated</option>
            <option value="Divorced">Divorced</option>
            <option value="Abandoned">Abandoned</option>
            <option value="Unmarried">Unmarried</option>
            <option value="Others">Others (Please specify)</option>
          </select>
        </div>
        <div class="col-12" id="otherReasonDiv" style="display: none;">
          <label class="form-label">Please specify the reason<span style="color: red;">*</span></label>
          <input type="text" class="form-control" id="otherReasonText" name="otherReasonText" placeholder="Enter reason" required />
        </div>

        <!-- Declaration -->
        <div class="col-12 mt-3">
          <h5 class="section-title">Declaration</h5>
          <div class="form-check border rounded p-3 bg-light">
            <input type="checkbox" class="form-check-input me-2 mt-1" name="declaration" value="1" required />
            <p class="mb-0">I hereby attest that I am a solo parent and the information provided is true and accurate to the best of my knowledge.</p>
          </div>
        </div>

        <!-- Document Upload -->
        <div class="col-12 mt-3">
          <h5 class="section-title">Document Upload</h5>
          <p class="text-muted">Upload one (1) valid government ID and supporting documents. AI will verify your identity.</p>
        </div>
        <div class="col-md-6">
          <label class="form-label">Valid ID Upload<span style="color: red;">*</span></label>
          <input type="file" class="form-control" name="validId" accept="image/*" required />
          <p class="small text-muted mt-1">Upload a clear image (JPG, PNG) of your valid government ID.</p>
        </div>
        <div class="col-md-6">
          <label class="form-label">Supporting Documents Upload</label>
          <input type="file" class="form-control" name="supportingDocs[]" accept="image/*,.pdf" multiple />
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
          <button type="submit" class="btn btn-danger px-4">Submit</button>
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
    document.addEventListener('DOMContentLoaded', function() {
      initializeNameValidation('soloParentForm');
      // Initialize ID validation
      if (window.IdValidation) window.IdValidation.initialize('soloParentForm');
      
      // Handle "Others" option for reason
      const reasonSelect = document.getElementById('reason');
      const otherReasonDiv = document.getElementById('otherReasonDiv');
      const otherReasonText = document.getElementById('otherReasonText');
      if (reasonSelect && otherReasonDiv) {
        reasonSelect.addEventListener('change', function(e) {
          if (e.target.value === 'Others') {
            otherReasonDiv.style.display = 'block';
            otherReasonText.required = true;
          } else {
            otherReasonDiv.style.display = 'none';
            otherReasonText.value = '';
            otherReasonText.required = false;
          }
        });
      }
      
      // Payment mode handling
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
      
      if (modeOfRelease && modeOfPayment) {
        modeOfRelease.addEventListener('change', handlePaymentMode);
        modeOfPayment.addEventListener('change', handlePaymentMode);
      }
      
      // Handle form submission with AJAX
      const form = document.getElementById('soloParentForm');
      if (form) {
        form.addEventListener('submit', async function(e) {
          e.preventDefault();
          
          // If "Others" is selected, copy the custom text to reason
          if (reasonSelect && reasonSelect.value === 'Others') {
            const otherText = document.getElementById('otherReasonText');
            if (otherText && otherText.value) {
              reasonSelect.value = otherText.value;
            }
          }
          
          // Disable submit button
          const submitBtn = form.querySelector('button[type="submit"]');
          const originalText = submitBtn.innerHTML;
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
          
          try {
            const formData = new FormData(form);
            const response = await fetch('submit_solo_parent.php', {
              method: 'POST',
              body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
              // Show success modal
              const successModal = new bootstrap.Modal(document.getElementById('successModal'));
              successModal.show();
            } else {
              alert(result.message || 'Failed to submit request. Please try again.');
              submitBtn.disabled = false;
              submitBtn.innerHTML = originalText;
            }
          } catch (error) {
            console.error('Submission error:', error);
            alert('An error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
          }
        });
      }
      
      // Redirect to dashboard after success modal
      const successOkBtn = document.getElementById('successOkBtn');
      if (successOkBtn) {
        successOkBtn.addEventListener('click', function() {
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
          <p class="text-muted mb-0" id="successMessage">Your Solo Parent Certificate request has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.</p>
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
