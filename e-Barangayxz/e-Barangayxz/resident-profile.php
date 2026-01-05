<?php include 'auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Profile | eBarangay</title>
    <link rel="icon" type="image/jpeg" href="pics/eb-logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
        <link href="style.css" rel="stylesheet">
        <style>
        /* Ensure no gap above the navbar on this page without shifting content below.
             Keep profile header's inline offsets intact so the profile picture doesn't move. */
        html, body { margin: 0; }
        .navbar { top: 0 !important; margin-top: 0 !important; border-top: none !important; }

        /* Strong visual overrides for the navbar only (no layout changes):
             - remove any white borders that create the thin line at the very top
             - ensure the gradient fills to the top using background-clip
             - remove any unexpected box outlines */
        .navbar {
            border-top: none !important;
            border-bottom: none !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important;
            background-clip: padding-box !important;
            outline: none !important;
        }

        /* If a pseudo-element or earlier rule inserted a top border, hide it */
        .navbar::before, .navbar::after { display: none !important; }

        /* Make logout button smaller but interactive (match dashboard sizing) */
        .resident-topbar .logout-box {
            background: transparent !important;
            padding: 0 !important;
            box-shadow: none !important;
            border-radius: 0 !important;
        }
        .resident-topbar .topbar-action,
        .resident-topbar .logout-box .topbar-action {
            padding: 0.45rem 0.85rem !important; /* smaller */
            font-weight: 600 !important;
            border-radius: 8px !important;
            font-size: 0.9rem !important;
            min-height: 34px !important;
            line-height: 1 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.2s ease, color 0.2s ease;
        }
        .resident-topbar .topbar-action:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(255,255,255,0.18); }
        .resident-topbar .topbar-action:active { transform: translateY(0); box-shadow: 0 2px 6px rgba(0,0,0,0.25) inset; }
        .resident-topbar .topbar-action:focus-visible { outline: 2px solid #ffffff; outline-offset: 2px; }
        /* Place the eye inside the input while preventing overlap with the native validation icon.
           Technique: make wrapper relative, position the toggle absolute inside the input area,
           and give the input enough right padding so the browser's validation icon (when shown)
           appears to the right of the eye. */
        .password-wrapper { position: relative; }
        .password-wrapper .input-group { align-items: center; }
        /* Reserve space on the right for the eye + potential validation icon */
        .password-wrapper .input-group .form-control { width: 100%; padding-right: 64px; border-radius: .5rem; }
        /* Position the toggle inside the input box (to the right). Place it a bit left
           of the absolute right edge so native validation icons don't overlap. */
        .password-wrapper .toggle-password {
            position: absolute;
            top: 50%;
            right: 18px;
            transform: translateY(-50%);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem;
            width: 34px;
            height: 34px;
            z-index: 6;
            background: transparent;
            border: none;
        }
        .password-wrapper .toggle-password:focus { box-shadow: none; outline: none; }
        .password-wrapper .input-group { gap: 0.5rem; }
        .password-wrapper .toggle-password i { transform: translateY(1px); font-size: 1.05rem; }
    /* Password requirements list */
    .password-requirements { list-style: none; padding-left: 0; margin-top: .5rem; }
    .password-requirements li { display: flex; align-items: center; gap: .5rem; font-size: .9rem; }
    .password-requirements li .requirement-icon { width: 1rem; display: inline-flex; justify-content: center; }
    .password-requirements li.valid { color: #198754; }
    .password-requirements li.invalid { color: #dc3545; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg px-4 py-2 sticky-top resident-topbar">
        <a class="navbar-brand" href="resident-dashboard.php">
            <img src="pics/eb-logo.jpg" alt="eBarangay Logo" class="me-2" style="height: 55px; width: auto;"> <strong>eBarangay</strong> | Resident Portal
        </a>
        <div class="ms-auto d-flex align-items-center">
            <div class="logout-box"><a href="#" id="logoutLink" class="btn btn-outline-light topbar-action">Logout</a></div>
        </div>
    </nav>

    <main class="container py-5">
        <h3 class="mb-3">My Profile</h3>
        
        <!-- Profile Picture Card -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Profile Picture</h5>
                <div class="d-flex align-items-center gap-4">
                    <div class="position-relative">
                        <div id="profilePicPreview" class="rounded-circle d-flex align-items-center justify-content-center" 
                             style="width: 120px; height: 120px; background: linear-gradient(90deg, #b31217 0%, #6a0572 50%, #2d0b8c 100%); color: white; font-size: 2.5rem; font-weight: bold; overflow: hidden;">
                            <span id="profileInitials">--</span>
                        </div>
                        <label for="profilePicInput" class="position-absolute bottom-0 end-0 bg-white rounded-circle p-2 shadow-sm" style="cursor: pointer; border: 2px solid #6a0572;">
                            <i class="bi bi-camera-fill text-primary"></i>
                        </label>
                    </div>
                    <div>
                        <input type="file" id="profilePicInput" accept="image/*" class="d-none">
                        <p class="mb-1 text-muted small">Click the camera icon to upload a new photo</p>
                        <p class="mb-2 text-muted small">Accepted: JPG, PNG, GIF, WEBP (Max 5MB)</p>
                        <button type="button" id="uploadPicBtn" class="btn btn-sm btn-danger" disabled>
                            <i class="bi bi-upload me-1"></i> Save Photo
                        </button>
                        <button type="button" id="removePicBtn" class="btn btn-sm btn-outline-secondary ms-2" style="display: none;">
                            <i class="bi bi-trash me-1"></i> Remove
                        </button>
                    </div>
                </div>
                <div id="profilePicAlert" class="mt-2"></div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div id="profileAlert" class="mb-2"></div>
                <form id="profileForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input id="firstName" class="form-control" required readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input id="lastName" class="form-control" required readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Middle Name</label>
                            <input id="middleName" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Suffix</label>
                            <input id="suffix" class="form-control" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <input id="address" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact</label>
                            <input id="contact" class="form-control" type="tel" inputmode="numeric" placeholder="09XXXXXXXXX" maxlength="11" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input id="email" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select id="gender" class="form-select">
                                <option value="">Select gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                                <option value="Prefer not to say">Prefer not to say</option>
                            </select>
                        </div>
                            <div class="col-md-6">
                                <label class="form-label">Civil Status</label>
                                <select id="civilStatus" class="form-select">
                                    <option value="">Select civil status</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Widowed">Widowed</option>
                                </select>
                            </div>
                        <div class="col-md-6">
                            <label class="form-label">Birthday</label>
                            <input id="birthday" type="date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Age</label>
                            <input id="age" type="number" class="form-control" min="0" max="130">
                        </div>
                        <div class="col-12 text-end mt-3">
                            <button id="editProfileBtn" type="button" class="btn btn-outline-secondary">Edit</button>
                            <button id="saveProfileBtn" type="submit" class="btn btn-danger" disabled>Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    
        <!-- Password Change Card -->
        <div class="row justify-content-center mt-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">Change Password</h5>
                        <div id="passwordAlert" class="mb-2"></div>
                        <form id="passwordForm">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Current Password</label>
                                    <div class="password-wrapper input-group">
                                        <input id="currentPassword" class="form-control" type="password" required>
                                        <button type="button" class="btn btn-outline-secondary toggle-password" aria-label="Toggle password visibility" onclick="togglePassword('currentPassword')" title="Show / hide" aria-pressed="false">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">New Password</label>
                                    <div class="password-wrapper input-group">
                                        <input id="newPassword" class="form-control" type="password" minlength="8" required aria-describedby="newPasswordHelp">
                                        <button type="button" class="btn btn-outline-secondary toggle-password" aria-label="Toggle new password visibility" onclick="togglePassword('newPassword')" title="Show / hide" aria-pressed="false">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div id="newPasswordHelp" class="form-text text-danger d-none"></div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Confirm New Password</label>
                                    <div class="password-wrapper input-group">
                                        <input id="confirmPassword" class="form-control" type="password" required aria-describedby="confirmPasswordHelp">
                                        <button type="button" class="btn btn-outline-secondary toggle-password" aria-label="Toggle confirm password visibility" onclick="togglePassword('confirmPassword')" title="Show / hide" aria-pressed="false">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div id="confirmPasswordHelp" class="form-text text-danger d-none"></div>
                                </div>

                                <div class="col-12 text-end mt-3">
                                    <button type="submit" class="btn btn-outline-secondary">Update Password</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const api = 'resident-profile-api.php';
        const logoutLink = document.getElementById('logoutLink');
        if (logoutLink) logoutLink.addEventListener('click', e=>{ e.preventDefault(); fetch('resident_logout.php',{method:'POST'}).then(r=>r.json()).then(j=>{ window.location.href = (j && j.data && j.data.redirect) ? j.data.redirect : 'login-register.html'; }).catch(()=>window.location.href='login-register.html'); });

        // Profile Picture Upload Handling
        const profilePicInput = document.getElementById('profilePicInput');
        const profilePicPreview = document.getElementById('profilePicPreview');
        const profileInitials = document.getElementById('profileInitials');
        const uploadPicBtn = document.getElementById('uploadPicBtn');
        const removePicBtn = document.getElementById('removePicBtn');
        let selectedPicFile = null;
        let hasExistingPic = false;

        function showPicAlert(msg, success) {
            const c = document.getElementById('profilePicAlert');
            if(!c) return;
            c.innerHTML = '<div class="alert ' + (success ? 'alert-success' : 'alert-danger') + ' py-2" role="alert">' + msg + '</div>';
            setTimeout(()=>{ if(c) c.innerHTML = ''; }, 4500);
        }

        function updateProfilePicPreview(data) {
            const firstName = data?.first_name || '';
            const lastName = data?.last_name || '';
            const initials = (firstName.charAt(0) + lastName.charAt(0)).toUpperCase() || '--';
            
            if (data?.profile_pic) {
                hasExistingPic = true;
                profilePicPreview.innerHTML = '<img src="' + data.profile_pic + '?t=' + Date.now() + '" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">';
                removePicBtn.style.display = 'inline-block';
            } else {
                hasExistingPic = false;
                profilePicPreview.innerHTML = '<span id="profileInitials">' + initials + '</span>';
                removePicBtn.style.display = 'none';
            }
        }

        profilePicInput.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;
            
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                showPicAlert('Invalid file type. Please upload JPG, PNG, GIF, or WEBP.', false);
                this.value = '';
                return;
            }
            
            // Validate file size (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                showPicAlert('File size exceeds 5MB limit.', false);
                this.value = '';
                return;
            }
            
            selectedPicFile = file;
            uploadPicBtn.disabled = false;
            
            // Preview the image
            const reader = new FileReader();
            reader.onload = function(e) {
                profilePicPreview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">';
            };
            reader.readAsDataURL(file);
        });

        uploadPicBtn.addEventListener('click', async function() {
            if (!selectedPicFile) return;
            
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Uploading...';
            
            // Use cached profile data to preserve all fields when uploading picture
            const cachedData = lastProfileData || {};
            
            const formData = new FormData();
            formData.append('profile_pic', selectedPicFile);
            
            // Only send fields that have actual values to prevent overwriting with empty strings
            // Required fields that must always be sent
            formData.append('first_name', cachedData.first_name || document.getElementById('firstName').value || '');
            formData.append('last_name', cachedData.last_name || document.getElementById('lastName').value || '');
            
            // Send ALL fields to preserve them when uploading profile photo
            // Use cached data as the source of truth since we're just uploading a photo
            formData.append('middle_name', cachedData.middle_name || '');
            formData.append('suffix', cachedData.suffix || '');
            formData.append('mobile', cachedData.mobile || '');
            formData.append('street', cachedData.street || '');
            formData.append('municipality', cachedData.municipality || '');
            formData.append('barangay', cachedData.barangay || '');
            formData.append('gender', cachedData.gender || '');
            formData.append('civil_status', cachedData.civil_status || '');
            formData.append('birthday', cachedData.birthday || '');
            formData.append('age', cachedData.age !== null && cachedData.age !== undefined ? cachedData.age : '');
            
            try {
                const response = await fetch(api, {
                    method: 'POST',
                    body: formData
                });
                
                console.log('Upload response status:', response.status);
                
                if (!response.ok) {
                    console.error('Upload HTTP error:', response.status);
                }
                
                const result = await response.json();
                console.log('Upload result:', result);
                
                if (result.success) {
                    showPicAlert('Profile picture updated successfully!', true);
                    selectedPicFile = null;
                    profilePicInput.value = '';
                    hasExistingPic = true;
                    removePicBtn.style.display = 'inline-block';
                    
                    // Immediately update preview with the returned profile_pic path
                    if (result.profile_pic) {
                        const picPath = result.profile_pic + '?t=' + Date.now();
                        profilePicPreview.innerHTML = '<img src="' + picPath + '" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">';
                    }
                    
                    // Reload to get the new pic path and refresh all profile data
                    fetch(api).then(r=>r.json()).then(j=>{ 
                        if(j && j.success) {
                            lastProfileData = j.data; // Update cached data
                            populate(j.data); // Refresh form fields
                            updateProfilePicPreview(j.data);
                        }
                    }).catch(err => {
                        console.error('Error reloading profile after photo upload:', err);
                        // Even if reload fails, the photo was already updated above
                    });
                } else {
                    showPicAlert(result.message || 'Failed to upload picture', false);
                }
            } catch (error) {
                console.error('Upload error details:', error);
                showPicAlert('Error uploading picture: ' + (error.message || 'Network error'), false);
            }
            
            this.disabled = true; // Disable button after upload since selectedPicFile was cleared
            this.innerHTML = '<i class="bi bi-upload me-1"></i> Save Photo';
        });

        removePicBtn.addEventListener('click', async function() {
            if (!confirm('Are you sure you want to remove your profile picture?')) return;
            
            this.disabled = true;
            
            try {
                const response = await fetch('remove_profile_pic.php', {
                    method: 'POST'
                });
                const result = await response.json();
                
                if (result.success) {
                    showPicAlert('Profile picture removed', true);
                    hasExistingPic = false;
                    this.style.display = 'none';
                    // Reset to initials
                    const firstName = document.getElementById('firstName').value || '';
                    const lastName = document.getElementById('lastName').value || '';
                    const initials = (firstName.charAt(0) + lastName.charAt(0)).toUpperCase() || '--';
                    profilePicPreview.innerHTML = '<span id="profileInitials">' + initials + '</span>';
                } else {
                    showPicAlert(result.message || 'Failed to remove picture', false);
                }
            } catch (error) {
                showPicAlert('Network error', false);
            }
            
            this.disabled = false;
        });

        const editBtn = document.getElementById('editProfileBtn');
        const saveBtn = document.getElementById('saveProfileBtn');
        const form = document.getElementById('profileForm');
        const fields = ['firstName','lastName','middleName','suffix','address','contact','email','gender','civilStatus','birthday','age'];
        let lastProfileData = null;
        let isEditing = false;
        
        // Add input validation for contact number
        const contactInput = document.getElementById('contact');
        if (contactInput) {
            contactInput.addEventListener('input', function(e) {
                // Remove any non-digit characters
                let value = this.value.replace(/\\D/g, '');
                
                // Limit to 11 digits
                if (value.length > 11) {
                    value = value.slice(0, 11);
                }
                
                this.value = value;
                
                // Real-time validation feedback
                if (value.length > 0) {
                    if (value.length === 11 && value.startsWith('09')) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.remove('is-valid');
                        this.classList.add('is-invalid');
                    }
                } else {
                    this.classList.remove('is-valid', 'is-invalid');
                }
            });
        }

        function showProfileAlert(msg, success){
            const c = document.getElementById('profileAlert');
            if(!c) return;
            c.innerHTML = '<div class="alert ' + (success ? 'alert-success' : 'alert-danger') + '" role="alert">' + msg + '</div>';
            setTimeout(()=>{ if(c) c.innerHTML = ''; }, 4500);
        }

        function showPasswordAlert(msg, success){
            const c = document.getElementById('passwordAlert');
            if(!c) return;
            c.innerHTML = '<div class="alert ' + (success ? 'alert-success' : 'alert-danger') + '" role="alert">' + msg + '</div>';
            setTimeout(()=>{ if(c) c.innerHTML = ''; }, 4500);
        }

        // Fields that are permanently locked based on civil status rules
        let lockedFieldsByStatus = [];

        // Determine which fields should be permanently locked based on gender and civil status
        function updateLockedFields(data) {
            // First name, suffix, gender, birthday, age, email are ALWAYS permanently locked
            lockedFieldsByStatus = ['firstName', 'suffix', 'gender', 'birthday', 'age', 'email'];
            if (!data) return;
            
            const gender = (data.gender || '').toLowerCase();
            const civilStatus = (data.civil_status || '').toLowerCase();
            const nameEditUsed = data.name_edit_used === 1 || data.name_edit_used === '1' || data.name_edit_used === true;
            const nameEditUsedMarriage = data.name_edit_used_marriage === 1 || data.name_edit_used_marriage === '1' || data.name_edit_used_marriage === true;
            const civilStatusChanged = data.civil_status_changed === 1 || data.civil_status_changed === '1' || data.civil_status_changed === true;
            
            // Check if address is within 1-month cooldown
            if (data.address_last_changed) {
                const lastChanged = new Date(data.address_last_changed);
                const now = new Date();
                const oneMonthLater = new Date(lastChanged);
                oneMonthLater.setMonth(oneMonthLater.getMonth() + 1);
                if (now < oneMonthLater) {
                    lockedFieldsByStatus.push('address');
                }
            }
            
            // Rule: MALE residents can NEVER edit their middle name and last name
            if (gender === 'male') {
                lockedFieldsByStatus.push('middleName', 'lastName');
            }
            
            // Rule: Civil status can only be changed ONCE for all residents (male or female)
            if (civilStatusChanged) {
                lockedFieldsByStatus.push('civilStatus');
            }
            
            // Rule: If civil status is ALREADY Married, lock middle name and last name permanently
            // Also lock civil status itself - once married, cannot change civil status
            if (civilStatus === 'married') {
                if (!lockedFieldsByStatus.includes('middleName')) lockedFieldsByStatus.push('middleName');
                if (!lockedFieldsByStatus.includes('lastName')) lockedFieldsByStatus.push('lastName');
                if (!lockedFieldsByStatus.includes('civilStatus')) lockedFieldsByStatus.push('civilStatus');
            }
            // Rule: If Female and Single, middle name and last name can only be edited once
            else if (gender === 'female' && civilStatus === 'single' && nameEditUsed) {
                lockedFieldsByStatus.push('middleName', 'lastName');
            }
            // Rule: If transitioning TO married from another status AND already used marriage name edit
            else if (gender === 'female' && civilStatus !== 'married' && nameEditUsedMarriage) {
                lockedFieldsByStatus.push('middleName', 'lastName');
            }
        }

        function setReadOnly(readonly){
            fields.forEach(id => {
                const el = document.getElementById(id);
                if(!el) return;
                const tag = (el.tagName || '').toLowerCase();
                
                // Check if this field should be permanently locked
                const isPermanentlyLocked = lockedFieldsByStatus.includes(id);
                
                if (tag === 'select' || el.type === 'checkbox' || el.type === 'radio') {
                    el.disabled = isPermanentlyLocked || !!readonly;
                } else {
                    el.readOnly = isPermanentlyLocked || !!readonly;
                }
                
                // Add visual indicator for permanently locked fields
                if (isPermanentlyLocked && !readonly) {
                    el.style.backgroundColor = '#f0f0f0';
                    el.style.cursor = 'not-allowed';
                    if (id === 'firstName' || id === 'suffix' || id === 'gender' || id === 'birthday' || id === 'age' || id === 'email') {
                        el.title = 'This field cannot be changed';
                    } else if (id === 'address' && lastProfileData && lastProfileData.address_last_changed) {
                        const lastChanged = new Date(lastProfileData.address_last_changed);
                        const oneMonthLater = new Date(lastChanged);
                        oneMonthLater.setMonth(oneMonthLater.getMonth() + 1);
                        const daysLeft = Math.ceil((oneMonthLater - new Date()) / (1000 * 60 * 60 * 24));
                        el.title = 'Address can be changed again in ' + daysLeft + ' days';
                    } else if (id === 'middleName' || id === 'lastName') {
                        const gender = (lastProfileData?.gender || '').toLowerCase();
                        const civilStatus = (lastProfileData?.civil_status || '').toLowerCase();
                        const nameEditUsed = lastProfileData?.name_edit_used || false;
                        
                        if (gender === 'male') {
                            el.title = 'Male residents cannot change middle name or last name';
                        } else if (gender === 'female' && civilStatus === 'single' && nameEditUsed) {
                            el.title = 'You have already used your one-time name change as a single female';
                        } else if (civilStatus === 'married') {
                            el.title = 'Name cannot be changed after marriage';
                        } else {
                            el.title = 'This field cannot be changed';
                        }
                    } else if (id === 'civilStatus') {
                        const civilStatus = (lastProfileData?.civil_status || '').toLowerCase();
                        if (civilStatus === 'married') {
                            el.title = 'Married status is permanent and cannot be changed';
                        } else {
                            el.title = 'Civil status can only be changed once';
                        }
                    } else {
                        el.title = 'This field cannot be changed';
                    }
                } else if (!readonly && !isPermanentlyLocked) {
                    el.style.backgroundColor = '';
                    el.style.cursor = '';
                    el.title = '';
                }
            });
            saveBtn.disabled = !!readonly;
        }
        // start locked
        setReadOnly(true);

        editBtn.addEventListener('click', ()=> {
            if (!isEditing) {
                // enable editing
                setReadOnly(false);
                isEditing = true;
                editBtn.textContent = 'Cancel';
            } else {
                // Cancel -> restore cached values
                if (lastProfileData) populate(lastProfileData);
                fields.forEach(id => { const el = document.getElementById(id); if(!el) return; el.classList && el.classList.remove('is-invalid','is-valid'); });
                setReadOnly(true);
                isEditing = false;
                editBtn.textContent = 'Edit';
                const pa = document.getElementById('profileAlert'); if(pa) pa.innerHTML = '';
            }
        });

        function populate(data){
            if(!data) return;
            document.getElementById('firstName').value = data.first_name || '';
            document.getElementById('lastName').value = data.last_name || '';
            document.getElementById('middleName').value = data.middle_name || '';
            document.getElementById('suffix').value = data.suffix || '';
            document.getElementById('contact').value = data.mobile || '';
            document.getElementById('address').value = ((data.street||'') + (data.barangay?(', '+data.barangay):'') + (data.municipality?(', '+data.municipality):'')).replace(/^,\s*/,'');
            document.getElementById('email').value = data.email || '';
            document.getElementById('gender').value = data.gender || '';
            document.getElementById('civilStatus').value = data.civil_status || '';
            document.getElementById('birthday').value = data.birthday || '';
            document.getElementById('age').value = (typeof data.age !== 'undefined' && data.age !== null) ? data.age : '';
            
            // Update locked fields based on gender and civil status
            updateLockedFields(data);
        }

        // load profile and cache it so Cancel can restore original values
        fetch(api).then(r=>r.json()).then(j=>{ 
            console.log('API Response:', j);
            if(j && j.success){ 
                lastProfileData = j.data; 
                populate(j.data); 
                updateLockedFields(j.data);
                updateProfilePicPreview(j.data);
            } else {
                showProfileAlert((j && j.message) ? j.message : 'Failed to load profile data', false);
            }
        }).catch(err=>{
            console.error('Profile fetch error:', err);
            showProfileAlert('Network error loading profile. Please refresh the page.', false);
        });

        form.addEventListener('submit', function(e){
            e.preventDefault();
            
            // Validate contact number
            const contactValue = document.getElementById('contact').value.trim();
            const contactDigits = contactValue.replace(/\D/g, ''); // Remove non-digit characters
            
            if (contactDigits.length !== 11) {
                showProfileAlert('Invalid contact number: it must start with 09', false);
                return;
            }
            
            if (!contactDigits.startsWith('09')) {
                showProfileAlert('Invalid contact number: it must start with 09', false);
                return;
            }
            
            // Build payload - always include required fields (first_name, last_name) even if locked
            // The API validates that locked fields haven't changed
            const payload = {
                first_name: document.getElementById('firstName').value.trim(),
                last_name: document.getElementById('lastName').value.trim(),
                mobile: contactDigits,
                street: document.getElementById('address').value.trim(),
                municipality: (lastProfileData && lastProfileData.municipality) ? lastProfileData.municipality : '',
                barangay: (lastProfileData && lastProfileData.barangay) ? lastProfileData.barangay : ''
            };
            
            // Only send other fields if they are NOT locked
            if (!lockedFieldsByStatus.includes('middleName')) {
                payload.middle_name = document.getElementById('middleName').value.trim();
            }
            if (!lockedFieldsByStatus.includes('suffix')) {
                payload.suffix = document.getElementById('suffix').value.trim();
            }
            if (!lockedFieldsByStatus.includes('gender')) {
                payload.gender = (document.getElementById('gender') && document.getElementById('gender').value) ? document.getElementById('gender').value : null;
            }
            if (!lockedFieldsByStatus.includes('birthday')) {
                payload.birthday = (document.getElementById('birthday') && document.getElementById('birthday').value) ? document.getElementById('birthday').value : null;
            }
            if (!lockedFieldsByStatus.includes('age')) {
                payload.age = (document.getElementById('age') && document.getElementById('age').value) ? parseInt(document.getElementById('age').value) : null;
            }
            if (!lockedFieldsByStatus.includes('civilStatus')) {
                payload.civil_status = (document.getElementById('civilStatus') && document.getElementById('civilStatus').value) ? document.getElementById('civilStatus').value : null;
            }
            
            console.log('Locked fields:', lockedFieldsByStatus);
            console.log('Sending payload:', payload);
            
            // Note: first_name and last_name are always sent because they're required by the API
            // The API validates that locked fields haven't changed
            
            fetch(api,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
            .then(r => {
                console.log('Save response status:', r.status);
                if (!r.ok) {
                    console.error('HTTP error:', r.status, r.statusText);
                }
                return r.json();
            })
            .then(j=>{
                console.log('Save response:', j);
                if(j && j.success){
                    // Reload profile data from server to get updated locked fields status
                    fetch(api).then(r=>r.json()).then(refreshData=>{
                        if(refreshData && refreshData.success){
                            lastProfileData = refreshData.data;
                            populate(refreshData.data);
                            updateLockedFields(refreshData.data);
                        }
                    }).catch(()=>{});
                    showProfileAlert(j.message || 'Profile updated', true);
                    setReadOnly(true);
                    isEditing = false;
                    editBtn.textContent='Edit';
                } else {
                    showProfileAlert((j && j.message) ? j.message : 'Could not save profile', false);
                }
            }).catch(err => {
                console.error('Save error details:', err);
                showProfileAlert('Error saving profile: ' + (err.message || 'Network error'), false);
            });
        });

        // Password form handling: realtime validation and posts to resident-change-password.php
        const passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            const newPassEl = document.getElementById('newPassword');
            const confirmPassEl = document.getElementById('confirmPassword');
            const newHelp = document.getElementById('newPasswordHelp');
            const confirmHelp = document.getElementById('confirmPasswordHelp');

            function checkPasswordRules(pw) {
                const rules = [];
                if (pw.length < 8) rules.push('At least 8 characters');
                if (!/[A-Z]/.test(pw)) rules.push('One uppercase letter');
                if (!/[a-z]/.test(pw)) rules.push('One lowercase letter');
                if (!/[0-9]/.test(pw)) rules.push('One number');
                if (!/[!@#$%^&*(),.?":{}|<>]/.test(pw)) rules.push('One special character');
                return rules;
            }

            function updateNewHelp() {
                const pw = (newPassEl.value || '').trim();
                const unmet = checkPasswordRules(pw);
                if (unmet.length === 0) {
                    newHelp.classList.add('d-none');
                    newPassEl.classList.remove('is-invalid');
                    newPassEl.classList.add('is-valid');
                    return true;
                } else {
                    newHelp.textContent = 'Password must contain: ' + unmet.join(', ');
                    newHelp.classList.remove('d-none');
                    newPassEl.classList.remove('is-valid');
                    newPassEl.classList.add('is-invalid');
                    return false;
                }
            }

            function updateConfirmHelp() {
                const a = (newPassEl.value || '').trim();
                const b = (confirmPassEl.value || '').trim();
                if (!b) {
                    confirmHelp.classList.add('d-none');
                    confirmPassEl.classList.remove('is-invalid');
                    confirmPassEl.classList.remove('is-valid');
                    return false;
                }
                if (a === b) {
                    confirmHelp.classList.add('d-none');
                    confirmPassEl.classList.remove('is-invalid');
                    confirmPassEl.classList.add('is-valid');
                    return true;
                } else {
                    confirmHelp.textContent = 'Passwords do not match';
                    confirmHelp.classList.remove('d-none');
                    confirmPassEl.classList.remove('is-valid');
                    confirmPassEl.classList.add('is-invalid');
                    return false;
                }
            }

            newPassEl.addEventListener('input', function() {
                updateNewHelp();
                if (confirmPassEl.value) updateConfirmHelp();
            });

            confirmPassEl.addEventListener('input', function() {
                updateConfirmHelp();
            });

            passwordForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const currentPass = (document.getElementById('currentPassword').value || '').trim();
                const newPass = (newPassEl.value || '').trim();
                const confirmPass = (confirmPassEl.value || '').trim();

                if (!currentPass) { showPasswordAlert('Current password is required', false); return; }
                if (!updateNewHelp()) { showPasswordAlert('New password does not meet requirements', false); return; }
                if (!updateConfirmHelp()) { showPasswordAlert('Confirm password does not match', false); return; }

                fetch('resident-change-password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ old_password: currentPass, new_password: newPass })
                }).then(r=>r.json()).then(j=>{
                    if (j && j.success) {
                        showPasswordAlert('Password updated successfully', true);
                        passwordForm.reset();
                        newPassEl.classList.remove('is-valid');
                        confirmPassEl.classList.remove('is-valid');
                    } else {
                        showPasswordAlert((j && j.message) ? j.message : 'Could not update password', false);
                    }
                }).catch(()=>showPasswordAlert('Network error', false));
            });
        }

        // Small helper to toggle visibility of password inputs and update icon
        function togglePassword(id) {
            const el = document.getElementById(id);
            if (!el) return;
            // find the closest input-group/button icon
            const wrapper = el.closest('.input-group') || el.closest('.password-wrapper');
            const btnIcon = wrapper ? wrapper.querySelector('.toggle-password i') : null;
            if (el.type === 'password') {
                el.type = 'text';
                if (btnIcon) { btnIcon.classList.remove('bi-eye'); btnIcon.classList.add('bi-eye-slash'); }
                const btn = wrapper ? wrapper.querySelector('.toggle-password') : null;
                if (btn) btn.setAttribute('aria-pressed', 'true');
            } else {
                el.type = 'password';
                if (btnIcon) { btnIcon.classList.remove('bi-eye-slash'); btnIcon.classList.add('bi-eye'); }
                const btn = wrapper ? wrapper.querySelector('.toggle-password') : null;
                if (btn) btn.setAttribute('aria-pressed', 'false');
            }
        }
    </script>
</body>
</html>
