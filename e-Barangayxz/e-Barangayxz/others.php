<?php
include 'auth_check.php';
// Server-side prefill: load resident profile for logged-in resident
include_once 'db.php';
$resident = [];
if (!empty($resident_id)) {
  $stmt = $conn->prepare("SELECT first_name, middle_name, last_name, suffix, mobile, street, barangay, municipality, civil_status, birthday FROM residents WHERE id = ? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('i', $resident_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $resident = $res->fetch_assoc() ?: [];
    $stmt->close();
  }
}
$formattedPhone = $resident['mobile'] ?? '';
if ($formattedPhone) {
  $digits = preg_replace('/\D/', '', $formattedPhone);
  if (strlen($digits) == 11) {
    $formattedPhone = substr($digits, 0, 4) . '-' . substr($digits, 4, 3) . '-' . substr($digits, 7);
  }
}
$fullAddress = trim((($resident['street'] ?? '') . (isset($resident['barangay']) && $resident['barangay'] ? ', '.$resident['barangay'] : '') . (isset($resident['municipality']) && $resident['municipality'] ? ', '.$resident['municipality'] : '')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Other Barangay Documents | eBarangay</title>
  <link rel="icon" type="image/jpeg" href="pics/eb-logo.jpg"> <!-- FAVICON -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="style.css">

  <style>
    body {
      background-color: #f9f9fb;
      font-family: "Inter", sans-serif;
      color: #232347;
    }

    /* Navbar */
    .navbar {
      background: linear-gradient(135deg, #c5202f, #222c8e, #6a0dad) !important;
    }
    .navbar .navbar-brand, .navbar .nav-link {
      color: #fff !important;
    }

    /* Page Header */
    .page-header {
      background: #f9f9fb;
      color: #232347;
      padding: 2rem 1rem;
      border-radius: 0 0 15px 15px;
      box-shadow: 0 2px 6px rgba(34,44,142,0.05);
    }

    /* Form Card */
    .form-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(34,44,142,0.1);
      padding: 2rem;
      transition: box-shadow 0.3s ease;
    }
    .form-card:hover {
      box-shadow: 0 8px 30px rgba(106,13,173,0.15);
    }

    /* Buttons */
    .btn-danger {
      background: linear-gradient(135deg, #6a0dad);
      border: none;
      font-weight: 600;
    }
    .btn-danger:hover {
      background: linear-gradient(135deg, #5a0a9d);
    }

    /* Form Label */
    .form-label {
      font-weight: 500;
      color: #232347;
      margin-bottom: 0.5rem;
    }

    .section-title {
      color: #232347;
      margin-bottom: 1rem;
      font-size: 1.1rem;
    }

    /* Input Focus */
    .form-control:focus, .form-select:focus {
      border-color: #6a0dad;
      box-shadow: 0 0 0 0.2rem rgba(106, 13, 173, 0.25);
    }
  </style>
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
      <h2 class="fw-bold mb-2 text-dark">Other Barangay Documents</h2>
      <p class="text-muted">Submit other types of requests or endorsements to your barangay. Please fill out the form completely for faster processing.</p>
    </div>
  </section>

  <!-- Form -->
  <div class="container pb-5">
    <div class="form-card">
      <form class="row g-3" id="otherRequestForm" method="post" action="submit_other.php" enctype="multipart/form-data" data-backend="true">

        <!-- Personal Information -->
        <div class="col-12">
          <h5 class="fw-bold section-title">Applicant Details</h5>
        </div>

        <div class="col-md-4">
          <label class="form-label">First Name<span style="color: red;">*</span></label>
          <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($resident['first_name'] ?? '', ENT_QUOTES); ?>" required />
          <div class="invalid-feedback" id="firstNameError">First name must contain only letters and spaces.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Last Name<span style="color: red;">*</span></label>
          <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo htmlspecialchars($resident['last_name'] ?? '', ENT_QUOTES); ?>" required />
          <div class="invalid-feedback" id="lastNameError">Last name must contain only letters and spaces.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Contact Number<span style="color: red;">*</span></label>
          <input type="tel" class="form-control" name="contactNumber" placeholder="09XX-XXX-XXXX" pattern="^0\d{3}-\d{3}-\d{4}$" inputmode="numeric" title="Please enter a valid 10-11 digit phone number (e.g., 09123456789)" value="<?php echo htmlspecialchars($formattedPhone, ENT_QUOTES); ?>" required />
        </div>

        <div class="col-12">
          <label class="form-label">Complete Address<span style="color: red;">*</span></label>
          <input type="text" class="form-control" name="completeAddress" value="<?php echo htmlspecialchars($fullAddress, ENT_QUOTES); ?>" required />
        </div>

        <!-- Request Details -->
        <div class="col-12 mt-3">
          <h5 class="fw-bold section-title">Request Details</h5>
        </div>

        <!-- Document Type with Dropdown -->
        <div class="col-12">
          <label class="form-label">Please Specify the Document<span style="color: red;">*</span></label>
          <select class="form-select" id="documentType" name="documentType" required>
            <option value="">Select a document type</option>
            <option value="Barangay Endorsement">Barangay Endorsement</option>
            <option value="Certification">Certification</option>
            <option value="Verification">Verification</option>
            <option value="Official Request">Official Request</option>
            <option value="Letter of Recommendation">Letter of Recommendation</option>
            <option value="Others">Others (Please specify)</option>
          </select>
        </div>

        <div class="col-12" id="otherDocumentDiv" style="display: none;">
          <label class="form-label">Please specify the document type<span style="color: red;">*</span></label>
          <input type="text" class="form-control" id="otherDocumentText" name="otherDocumentText" placeholder="Enter document type" required />
        </div>

        <!-- Purpose of Request -->
        <div class="col-12">
          <label class="form-label">Purpose of Request<span style="color: red;">*</span></label>
          <textarea class="form-control" name="purpose" rows="3" placeholder="State the reason or purpose of your request" required></textarea>
        </div>

        <!-- Valid ID Upload (left) and Mode of Release (right) -->
        <div class="col-md-6">
          <label class="form-label">Valid ID Upload<span style="color: red;">*</span></label>
          <input type="file" class="form-control" name="validId" accept="image/*" required />
          <div class="form-text">Upload a clear image (JPG, PNG) of your valid government ID. AI will verify your identity.</div>
        </div>

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

        <!-- Remarks -->
        <div class="col-12">
          <label class="form-label">Additional Details / Remarks</label>
          <textarea class="form-control" name="remarks" rows="4" placeholder="You may provide more information or instructions here..."></textarea>
        </div>

        <!-- Submit -->
        <div class="col-12 text-end">
          <a href="resident-dashboard.php" class="btn btn-secondary px-4 me-2">Back</a>
          <button type="submit" class="btn btn-danger px-4" id="submitBtn">Submit</button>
        </div>

      </form>
    </div>
  </div>

  <!-- Bootstrap Script -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="script.js"></script>
  <script src="contact-format.js"></script>
  <script src="name-validation.js"></script>
  <script src="id-validation.js"></script>
  <script>
    // Initialize ID validation
    if (window.IdValidation) window.IdValidation.initialize('otherRequestForm');
    
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
      initializeNameValidation('otherRequestForm');

      // Handle "Others" option for document type
      const documentSelect = document.getElementById('documentType');
      const otherDocumentDiv = document.getElementById('otherDocumentDiv');
      const otherDocumentText = document.getElementById('otherDocumentText');
      if (documentSelect && otherDocumentDiv) {
        documentSelect.addEventListener('change', function(e) {
          if (e.target.value === 'Others') {
            otherDocumentDiv.style.display = 'block';
            otherDocumentText.required = true;
          } else {
            otherDocumentDiv.style.display = 'none';
            otherDocumentText.value = '';
            otherDocumentText.required = false;
          }
        });
      }

      // Handle form submission
      const form = document.getElementById('otherRequestForm');
      if (form && form.dataset.backend === 'true') {
        form.addEventListener('submit', async function(e) {
          e.preventDefault();

          // If "Others" is selected, copy the custom text to documentType
          if (documentSelect && documentSelect.value === 'Others') {
            const otherText = document.getElementById('otherDocumentText');
            if (otherText && otherText.value) {
              documentSelect.value = otherText.value;
            }
          }

          const formData = new FormData(form);
          try {
            const response = await fetch('submit_other.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
              const modal = new bootstrap.Modal(document.getElementById('successModal'));
              document.getElementById('successTitle').textContent = 'Submitted Successfully!';
              document.getElementById('successMessage').textContent = result.message || 'Your request has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.';
              modal.show();
              document.getElementById('successOkBtn').addEventListener('click', function() {
                window.location.href = 'resident-dashboard.php';
              });
            } else {
              alert('Error: ' + (result.message || 'Submission failed'));
            }
          } catch (error) {
            console.error('Submission error:', error);
            alert('An error occurred during submission');
          }
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
          <p class="text-muted mb-0" id="successMessage">Your request has been submitted successfully.</p>
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
