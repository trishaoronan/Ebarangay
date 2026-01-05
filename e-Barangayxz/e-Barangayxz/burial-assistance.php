<?php
session_start();
include 'auth_check.php';
include 'db.php';

$resident = [];
$formattedPhone = '';
$fullAddress = '';
if (!empty($_SESSION['resident_id'])) {
    $rid = $_SESSION['resident_id'];
    $stmt = $conn->prepare("SELECT first_name, middle_name, last_name, suffix, mobile, street, barangay, municipality, civil_status, birthday FROM residents WHERE id = ? LIMIT 1");
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
  <title>Barangay Burial Assistance Application</title>
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
      <h2 class="fw-bold mb-2 text-dark">Burial Assistance Application</h2>
      <p class="text-muted">Financial assistance for funeral and burial expenses for indigent constituents.</p>
    </div>
  </section>

  <!-- Form -->
  <div class="container pb-5">
    <div class="form-card">
      <form class="row g-3" id="burialAssistanceForm" method="post" action="submit_burial_assistance.php" enctype="multipart/form-data" data-backend="true">

        <!-- Claimant Details -->
        <div class="col-12">
          <h5 class="fw-bold section-title">Claimant/Applicant Details</h5>
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
          <label class="form-label">Contact Number<span style="color: red;">*</span></label>
          <input type="tel" class="form-control" id="contactNumber" name="contactNumber" placeholder="09XX-XXX-XXXX" pattern="^0\d{3}-\d{3}-\d{4}$" inputmode="numeric" title="This field is locked and uses your registered profile information" value="<?php echo htmlspecialchars($formattedPhone, ENT_QUOTES); ?>" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Relationship to Deceased<span style="color: red;">*</span></label>
          <select class="form-select" name="relationship" required>
            <option value="">Select</option>
            <option>Spouse</option>
            <option>Child</option>
            <option>Parent</option>
            <option>Sibling</option>
            <option>Other Relative</option>
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label">Complete Address<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="completeAddress" value="<?php echo htmlspecialchars($fullAddress, ENT_QUOTES); ?>" required readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="This field is locked and uses your registered profile information" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Claimant Monthly Income (Estimate)<span style="color: red;">*</span></label>
          <input type="text" class="form-control" id="monthlyIncome" name="monthlyIncome" placeholder="e.g., 10000" inputmode="numeric" pattern="^\d+(\.\d{2})?$" title="Enter numbers only with optional decimals (e.g., 10000 or 10000.50)" required />
          <small class="text-muted">Numbers only, will auto-format with decimals</small>
        </div>

        <!-- Deceased Details -->
        <div class="col-12 mt-3">
          <h5 class="fw-bold section-title">Deceased Person's Details</h5>
        </div>
        <div class="col-md-3">
          <label class="form-label">Last Name<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="deceasedLastName" required />
        </div>
        <div class="col-md-3">
          <label class="form-label">First Name<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="deceasedFirstName" required />
        </div>
        <div class="col-md-3">
          <label class="form-label">Middle Name</label>
          <input type="text" class="form-control" name="deceasedMiddleName" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Suffix (e.g., Jr., Sr.)</label>
          <input type="text" class="form-control" name="deceasedSuffix" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Date of Death<span style="color: red;">*</span></label>
          <input type="date" class="form-control" name="dateOfDeath" max="<?php echo date('Y-m-d'); ?>" required />
        </div>
        <div class="col-md-3">
          <label class="form-label">Place of Death (Barangay)<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="placeOfDeath" required />
        </div>
        <div class="col-12">
          <label class="form-label">Stated Cause of Death<span style="color: red;">*</span></label>
          <select class="form-select" id="causeOfDeath" name="causeOfDeath" required>
            <option value="">Select a cause</option>
            <option value="Natural Causes">Natural Causes</option>
            <option value="Illness/Disease">Illness/Disease</option>
            <option value="Accident">Accident</option>
            <option value="Heart Attack">Heart Attack</option>
            <option value="Stroke">Stroke</option>
            <option value="Cancer">Cancer</option>
            <option value="Respiratory Disease">Respiratory Disease</option>
            <option value="Kidney Disease">Kidney Disease</option>
            <option value="Diabetes">Diabetes</option>
            <option value="Hypertension">Hypertension</option>
            <option value="Pneumonia">Pneumonia</option>
            <option value="Others">Others (Please specify)</option>
          </select>
        </div>
        <div class="col-12" id="otherCauseDiv" style="display: none;">
          <label class="form-label">Please specify the cause of death<span style="color: red;">*</span></label>
          <input type="text" class="form-control" id="otherCauseText" name="otherCauseText" placeholder="Enter cause of death" required />
        </div>

        <!-- Document Uploads -->
        <div class="col-12 mt-3">
          <h5 class="fw-bold section-title">Required Documents</h5>
          <p class="small text-muted">Upload scanned copies of the required documents:</p>
        </div>
        <div class="col-md-6">
          <label class="form-label">Valid Government ID (Claimant)<span style="color: red;">*</span></label>
          <input type="file" class="form-control" name="validId" accept="image/*" required />
          <p class="small text-muted mt-1">Upload a clear image of your valid government ID. AI will verify your identity.</p>
        </div>
        <div class="col-md-6">
          <label class="form-label">Death Certificate<span style="color: red;">*</span></label>
          <input type="file" class="form-control" name="deathCertificate" accept="image/*,.pdf" required />
        </div>
        <div class="col-md-6">
          <label class="form-label">Proof of Kinship (Birth/Marriage Certificate)<span style="color: red;">*</span></label>
          <input type="file" class="form-control" name="proofKinship" accept="image/*,.pdf" required />
        </div>
        <div class="col-md-6">
          <label class="form-label">Barangay Indigency Certificate</label>
          <input type="file" class="form-control" name="barangayIndigency" accept="image/*,.pdf" />
          <p class="small text-muted mt-1">Optional, but speeds up processing.</p>
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
  <script src="contact-format.js"></script>
  <script src="script.js"></script>
  <script src="name-validation.js"></script>
  <script src="id-validation.js"></script>
  <script>
    initializeNameValidation('burialAssistanceForm');
    // Initialize ID validation
    if (window.IdValidation) window.IdValidation.initialize('burialAssistanceForm');
    
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

    // Format monthly income with decimals and restrict to numbers only
    const incomeInput = document.getElementById('monthlyIncome');
    if (incomeInput) {
      incomeInput.addEventListener('input', function(e) {
        // Remove any non-digit characters except decimal point
        let value = e.target.value.replace(/[^\d.]/g, '');
        
        // Prevent multiple decimal points
        const parts = value.split('.');
        if (parts.length > 2) {
          value = parts[0] + '.' + parts[1];
        }
        
        e.target.value = value;
      });

      incomeInput.addEventListener('blur', function(e) {
        // Format to 2 decimal places on blur
        if (e.target.value) {
          const num = parseFloat(e.target.value);
          if (!isNaN(num)) {
            e.target.value = num.toFixed(2);
          }
        }
      });
    }

    // Handle "Others" option for cause of death
    const causeSelect = document.getElementById('causeOfDeath');
    const otherCauseDiv = document.getElementById('otherCauseDiv');
    const otherCauseText = document.getElementById('otherCauseText');
    if (causeSelect && otherCauseDiv) {
      causeSelect.addEventListener('change', function(e) {
        if (e.target.value === 'Others') {
          otherCauseDiv.style.display = 'block';
          otherCauseText.required = true;
        } else {
          otherCauseDiv.style.display = 'none';
          otherCauseText.value = '';
          otherCauseText.required = false;
        }
      });
    }

    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('burialAssistanceForm');
      if (!form || form.dataset.backend !== 'true') return;

      form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Validate required fields
        const requiredFields = [
          { name: 'firstName', label: 'First Name' },
          { name: 'lastName', label: 'Last Name' },
          { name: 'contactNumber', label: 'Contact Number' },
          { name: 'relationship', label: 'Relationship to Deceased' },
          { name: 'completeAddress', label: 'Complete Address' },
          { name: 'monthlyIncome', label: 'Monthly Income' },
          { name: 'deceasedLastName', label: 'Deceased Last Name' },
          { name: 'deceasedFirstName', label: 'Deceased First Name' },
          { name: 'dateOfDeath', label: 'Date of Death' },
          { name: 'placeOfDeath', label: 'Place of Death' },
          { name: 'causeOfDeath', label: 'Cause of Death' },
          { name: 'deathCertificate', label: 'Death Certificate' },
          { name: 'proofKinship', label: 'Proof of Kinship' },
          { name: 'modeOfRelease', label: 'Mode of Release' }
        ];

        const missing = [];
        requiredFields.forEach(field => {
          const input = form.querySelector(`[name="${field.name}"]`);
          if (!input) return;
          
          if (input.type === 'file') {
            if (!input.files || input.files.length === 0) {
              missing.push(field.label);
            }
          } else {
            if (!input.value || input.value.trim() === '') {
              missing.push(field.label);
            }
          }
        });

        // Extra validation: if causeOfDeath is "Others", check otherCauseText
        const causeSelect = form.querySelector('[name="causeOfDeath"]');
        if (causeSelect && causeSelect.value === 'Others') {
          const otherText = form.querySelector('#otherCauseText');
          if (!otherText || !otherText.value || otherText.value.trim() === '') {
            missing.push('Cause of Death (custom)');
          }
        }

        if (missing.length > 0) {
          alert('Please fill in all required fields:\n\n' + missing.join('\n'));
          return;
        }

        // If "Others" is selected, copy the custom text to the causeOfDeath field
        if (causeSelect && causeSelect.value === 'Others') {
          const otherText = form.querySelector('#otherCauseText');
          if (otherText && otherText.value) {
            causeSelect.value = otherText.value;
          }
        }

        const formData = new FormData(form);
        try {
          const response = await fetch(form.action, { method: 'POST', body: formData, credentials: 'include' });
          const result = await response.json();

          if (result.success) {
            const modal = new bootstrap.Modal(document.getElementById('successModal'));
            document.getElementById('successTitle').textContent = 'Submitted Successfully!';
            document.getElementById('successMessage').textContent = result.message || 'Your Burial Assistance request has been submitted successfully. Verification will be conducted, and you will be notified when the assistance is processed.';
            modal.show();
            document.getElementById('successOkBtn').addEventListener('click', function() {
              window.location.href = 'resident-dashboard.php';
            }, { once: true });
            form.reset();
          } else {
            alert(result.message || 'Submission failed. Please try again.');
          }
        } catch (err) {
          alert('An error occurred while submitting the form. Please try again.');
        }
      });
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
          <p class="text-muted mb-0" id="successMessage">Your Burial Assistance request has been submitted successfully. Verification will be conducted, and you will be notified when the assistance is processed.</p>
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
