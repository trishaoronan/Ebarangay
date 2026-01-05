<?php
// resident_reset_password.php
// GET: show a minimal reset form when ?token=... is provided
// POST (JSON): { token, new_password }
error_reporting(E_ERROR | E_PARSE);
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = isset($_GET['token']) ? trim($_GET['token']) : '';
    ?>
    <!doctype html>
    <html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset Password</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"></head>
    <body class="bg-light">
    <div class="container py-5">
      <div class="row justify-content-center">
        <div class="col-md-6">
          <div class="card p-4">
            <h5 class="mb-3">Reset Password</h5>
            <p class="small text-muted">Enter a new password for your account.</p>
            <div id="alert"></div>
            <div class="mb-3">
              <label class="form-label">New password</label>
              <input id="pwd" class="form-control" type="password" />
            </div>
            <div class="mb-3">
              <label class="form-label">Confirm password</label>
              <input id="pwd2" class="form-control" type="password" />
            </div>
            <button id="btn" class="btn btn-primary w-100">Set new password</button>
            <div class="mt-3 text-center"><a href="login-register.html">Back to login</a></div>
          </div>
        </div>
      </div>
    </div>
    <script>
      const btn = document.getElementById('btn');
      const token = '<?php echo htmlspecialchars($token, ENT_QUOTES); ?>';
      function show(msg, cls='danger') { document.getElementById('alert').innerHTML = '<div class="alert alert-'+cls+'">'+msg+'</div>'; }
      btn.addEventListener('click', function(){
        const p = document.getElementById('pwd').value;
        const p2 = document.getElementById('pwd2').value;
        if (!p || !p2) { show('Please enter and confirm your new password.'); return; }
        if (p !== p2) { show('Passwords do not match.'); return; }
        fetch('resident_reset_password.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ token: token, new_password: p }) })
        .then(r => r.json()).then(j => {
          if (j && j.success) {
            show(j.message||'Password updated', 'success');
            setTimeout(()=>{ window.location.href = 'login-register.html'; }, 1500);
          } else {
            show((j && j.message) ? j.message : 'Reset failed.');
          }
        }).catch(err => { show('Server error.'); console.error(err); });
      });
    </script>
    </body></html>
    <?php
    exit;
}

// POST processing (JSON)
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];
$token = isset($body['token']) ? trim($body['token']) : '';
$newpw = isset($body['new_password']) ? $body['new_password'] : '';

if (!$token || !$newpw) {
    echo json_encode(['success' => false, 'message' => 'Token and new_password are required.']);
    exit;
}

// Find token
$stmt = $conn->prepare("SELECT id, resident_id, email, expires_at FROM password_resets WHERE token = ? LIMIT 1");
if (!$stmt) { echo json_encode(['success'=>false,'message'=>'Server error']); exit; }
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) { echo json_encode(['success'=>false,'message'=>'Invalid or expired token.']); $stmt->close(); exit; }
$row = $res->fetch_assoc();
$stmt->close();

if (strtotime($row['expires_at']) < time()) {
    echo json_encode(['success' => false, 'message' => 'Token expired. Please request a new reset link.']);
    exit;
}

$resident_id = $row['resident_id'];

// Update resident password
$hash = password_hash($newpw, PASSWORD_DEFAULT);
$upd = $conn->prepare("UPDATE residents SET password_hash = ? WHERE id = ? LIMIT 1");
if (!$upd) { echo json_encode(['success'=>false,'message'=>'Server error (prepare).']); exit; }
$upd->bind_param('si', $hash, $resident_id);
if (!$upd->execute()) { echo json_encode(['success'=>false,'message'=>'Failed to update password.']); $upd->close(); exit; }
$upd->close();

// Delete used token(s)
$del = $conn->prepare("DELETE FROM password_resets WHERE resident_id = ?");
if ($del) { $del->bind_param('i', $resident_id); $del->execute(); $del->close(); }

echo json_encode(['success' => true, 'message' => 'Password updated successfully. You may now login.']);
$conn->close();
exit;
