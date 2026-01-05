// instant-prefill.js - Resident data prefilling
(function() {
  'use strict';
  
  // Format phone number to 0XXX-XXX-XXXX
  function formatPhone(mobile) {
    if (!mobile) return '';
    const digits = String(mobile).replace(/\D/g, '');
    if (digits.length >= 11) {
      return digits.substring(0, 4) + '-' + digits.substring(4, 7) + '-' + digits.substring(7, 11);
    }
    return mobile;
  }
  
  // Prefill form fields with resident data
  function prefillResidentData() {
    fetch('resident-profile-api.php')
      .then(response => {
        if (!response.ok) {
          console.log('API not ok, probably not logged in');
          return null;
        }
        return response.json();
      })
      .then(data => {
        if (!data || !data.success || !data.data) {
          console.log('No resident data available');
          return;
        }
        
        const d = data.data;
        console.log('Prefilling with resident data:', d);
        console.log('Mobile value is:', d.mobile, 'Type:', typeof d.mobile);
        console.log('d.mobile is truthy?', !!d.mobile);
        
        // Prefill name fields - by ID
        const lastNameEl = document.getElementById('lastName');
        const firstNameEl = document.getElementById('firstName');
        const middleNameEl = document.getElementById('middleName');
        const suffixEl = document.getElementById('suffix');
        
        if (lastNameEl && d.last_name) {
          lastNameEl.value = d.last_name;
          console.log('Filled lastName:', d.last_name);
        }
        if (firstNameEl && d.first_name) {
          firstNameEl.value = d.first_name;
          console.log('Filled firstName:', d.first_name);
        }
        if (middleNameEl && d.middle_name) {
          middleNameEl.value = d.middle_name;
          console.log('Filled middleName:', d.middle_name);
        }
        if (suffixEl && d.suffix) {
          suffixEl.value = d.suffix;
          console.log('Filled suffix:', d.suffix);
        }
        
        // Prefill date of birth - by name attribute
        if (d.birthday) {
          const dobEl = document.querySelector('input[name="dateOfBirth"]');
          if (dobEl) {
            dobEl.value = d.birthday;
            console.log('Filled dateOfBirth:', d.birthday);
          }
        }
        
        // Prefill civil status - by name attribute
        if (d.civil_status) {
          const csEl = document.querySelector('select[name="civilStatus"]');
          if (csEl) {
            // Try exact match
            csEl.value = d.civil_status;
            // Also try to match by option text
            for (let option of csEl.options) {
              if (option.textContent.trim() === d.civil_status || option.value === d.civil_status) {
                csEl.value = option.value;
                console.log('Filled civilStatus:', d.civil_status);
                break;
              }
            }
          }
        }
        
        // Prefill complete address - by name attribute (only if field is not readonly/locked)
        if (d.street || d.barangay || d.municipality) {
          const addrEl = document.querySelector('input[name="completeAddress"]');
          if (addrEl && !addrEl.readOnly && !addrEl.disabled) {
            const addrParts = [];
            if (d.street) addrParts.push(d.street);
            if (d.barangay) addrParts.push(d.barangay);
            if (d.municipality) addrParts.push(d.municipality);
            
            if (addrParts.length > 0) {
              addrEl.value = addrParts.join(', ');
              console.log('Filled completeAddress:', addrEl.value);
            }
          } else if (addrEl && (addrEl.readOnly || addrEl.disabled)) {
            console.log('Skipping completeAddress - field is locked');
          }
        }
        
        // DEBUG: Log before contact check
        console.log('About to check contact number. d.mobile=', d.mobile);
        
        // Prefill contact number with formatting - by name or id fallback
        if (d.mobile) {
          const contactEl = document.querySelector('input[name="contactNumber"]') || document.getElementById('contactNumber');
          if (contactEl) {
            const formatted = formatPhone(d.mobile);
            contactEl.value = formatted;
            console.log('Filled contactNumber:', contactEl.value, 'from mobile:', d.mobile);
          } else {
            console.log('Contact element not found!');
          }
        } else {
          console.log('No mobile data available:', d);
        }
        
        // Auto-format emergency contact number field when user types
        const emergencyContactEl = document.querySelector('input[name="emergencyContactNumber"]');
        if (emergencyContactEl) {
          emergencyContactEl.addEventListener('input', function(e) {
            const digits = this.value.replace(/\D/g, '');
            if (digits.length > 0) {
              if (digits.length <= 4) {
                this.value = digits;
              } else if (digits.length <= 7) {
                this.value = digits.substring(0, 4) + '-' + digits.substring(4);
              } else {
                this.value = digits.substring(0, 4) + '-' + digits.substring(4, 7) + '-' + digits.substring(7, 11);
              }
            }
          });
        }
      })
      .catch(error => console.error('Prefill error:', error));
  }
  
  // Run prefill when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(prefillResidentData, 100);
    });
  } else {
    // DOM already loaded
    setTimeout(prefillResidentData, 100);
  }
})();
