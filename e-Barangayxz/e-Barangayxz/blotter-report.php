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
  <title>Barangay Blotter Report</title>
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
      <h2 class="fw-bold mb-2 text-dark">Barangay Blotter Report</h2>
      <p class="text-muted">Report an incident, complaint, or dispute that occurred within the Barangay jurisdiction.</p>
    </div>
  </section>

  <!-- Form -->
  <div class="container pb-5">
    <div class="form-card">
      <form class="row g-3" id="blotterForm" method="post" action="submit_blotter.php" enctype="multipart/form-data" data-backend="true">

        <!-- Complainant Details -->
        <div class="col-12">
          <h5 class="fw-bold section-title">Complainant Details</h5>
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
        <div class="col-md-6">
          <label class="form-label">Contact Number<span style="color: red;">*</span></label>
          <input type="tel" class="form-control" name="contactNumber" placeholder="09XX-XXX-XXXX" pattern="^0\d{3}-\d{3}-\d{4}$" value="<?php echo htmlspecialchars($formattedPhone, ENT_QUOTES); ?>" inputmode="numeric" title="This field is locked and uses your registered profile information" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Email (Optional)</label>
          <input type="email" class="form-control" name="email" />
        </div>
        <div class="col-md-12">
          <label class="form-label">Complete Address<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="completeAddress" value="<?php echo htmlspecialchars(($resident['street'] ?? '') . ($resident['barangay'] ? ', ' . $resident['barangay'] : '') . ($resident['municipality'] ? ', ' . $resident['municipality'] : ''), ENT_QUOTES); ?>" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" />
        </div>

        <!-- Incident Details -->
        <div class="col-12 mt-3">
          <h5 class="fw-bold section-title">Incident Details</h5>
        </div>
        <div class="col-md-4">
          <label class="form-label">Type of Incident<span style="color: red;">*</span></label>
          <select class="form-select" name="incidentType" required>
            <option value="">Select Type</option>
            <option>Physical Injury/Assault</option>
            <option>Theft/Robbery</option>
            <option>Property Damage/Vandalism</option>
            <option>Land/Boundary Dispute</option>
            <option>Domestic/Family Dispute</option>
            <option>Debt/Financial Dispute</option>
            <option>Noise Complaint</option>
            <option>Other</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Date of Incident<span style="color: red;">*</span></label>
          <input type="date" class="form-control" name="incidentDate" max="<?php echo date('Y-m-d'); ?>" required />
        </div>
        <div class="col-md-4">
          <label class="form-label">Time of Incident<span style="color: red;">*</span></label>
          <input type="time" class="form-control" name="incidentTime" required />
        </div>
        <div class="col-md-12">
          <label class="form-label">Exact Location of Incident<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="incidentLocation" placeholder="e.g., Corner of Rizal St. and Mango Ave." required />
        </div>
        <div class="col-md-12">
          <label class="form-label">Detailed Narrative<span style="color: red;">*</span></label>
          <textarea class="form-control" name="narrative" rows="5" placeholder="Describe what happened, include witnesses if any." required></textarea>
        </div>

        <!-- Respondent -->
        <div class="col-12 mt-3">
          <h5 class="fw-bold section-title">Respondent Details (If Known)</h5>
        </div>
        <div class="col-md-6">
          <label class="form-label">Name(s) of Respondent(s)</label>
          <input type="text" class="form-control" name="respondentName" placeholder="Juan Dela Cruz or UNKNOWN" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Address(es) of Respondent(s)</label>
          <input type="text" class="form-control" name="respondentAddress" placeholder="123 Sampaguita St. or UNKNOWN" />
        </div>

        <!-- Evidence -->
        <div class="col-12 mt-3">
          <h5 class="fw-bold section-title">Supporting Evidence</h5>
          <p class="small text-muted">Upload 1-3 photos, videos, or documents (at least 1 required), up to 10MB each.</p>
        </div>
        <div class="col-md-4">
          <label class="form-label">Evidence File 1<span style="color: red;">*</span></label>
          <input type="file" class="form-control" name="evidence[]" accept="image/*,.pdf,.mp4" required />
        </div>
        <div class="col-md-4">
          <label class="form-label">Evidence File 2 (Optional)</label>
          <input type="file" class="form-control" name="evidence[]" accept="image/*,.pdf,.mp4" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Evidence File 3 (Optional)</label>
          <input type="file" class="form-control" name="evidence[]" accept="image/*,.pdf,.mp4" />
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
          <p class="text-muted mb-0" id="successMessage">Your report has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.</p>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="script.js"></script>
  <script src="contact-format.js"></script>
  <script src="name-validation.js"></script>
  <script>
    initializeNameValidation('blotterForm');
    
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
      const form = document.getElementById('blotterForm');
      const successModalEl = document.getElementById('successModal');
      const successMessageEl = document.getElementById('successMessage');
      const okBtn = document.getElementById('successOkBtn');
      if (!form) return;

      const successModal = successModalEl ? new bootstrap.Modal(successModalEl) : null;

      form.addEventListener('submit', async function (e) {
        e.preventDefault();
        
        if (form.checkValidity() === false) {
          form.classList.add('was-validated');
          return;
        }

        const formData = new FormData(form);
        try {
          const response = await fetch('submit_blotter.php', { method: 'POST', body: formData });
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
</body>
</html>
