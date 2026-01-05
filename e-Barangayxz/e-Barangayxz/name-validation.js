// Name validation: only letters and spaces allowed
function isLettersAndSpacesOnly(value) {
  return /^[A-Za-z\s]*$/.test(value);
}

// Suffix validation: letters, spaces, and dots allowed (for Jr., Sr., etc.)
function isSuffixValid(value) {
  return /^[A-Za-z\s.]*$/.test(value);
}

// Purpose validation: letters, spaces, and commas only (no numbers or other special characters)
function isPurposeValid(value) {
  return /^[A-Za-z\s,]*$/.test(value);
}

// Income validation: numbers, spaces, and commas only
function isIncomeValid(value) {
  return /^[0-9\s,]*$/.test(value);
}

function validateNameField(inputId, errorId, isSuffix = false) {
  const input = document.getElementById(inputId);
  const error = document.getElementById(errorId);
  
  if (!input) return;

  function validate() {
    const value = input.value;
    
    // If field is empty, remove error (let required validation handle it)
    if (!value) {
      input.classList.remove('is-invalid');
      if (error) error.style.display = 'none';
      return true;
    }

    // Check if value contains only allowed characters
    const isValid = isSuffix ? isSuffixValid(value) : isLettersAndSpacesOnly(value);
    
    if (!isValid) {
      input.classList.add('is-invalid');
      if (error) error.style.display = 'block';
      return false;
    } else {
      input.classList.remove('is-invalid');
      if (error) error.style.display = 'none';
      return true;
    }
  }

  // Validate on input (real-time)
  input.addEventListener('input', validate);
  
  // Validate on blur
  input.addEventListener('blur', validate);
}

// Generic field validation function
function validateField(inputId, errorId, validationFunction) {
  const input = document.getElementById(inputId);
  const error = document.getElementById(errorId);
  
  if (!input) return;

  function validate() {
    const value = input.value;
    
    // If field is empty, remove error (let required validation handle it)
    if (!value) {
      input.classList.remove('is-invalid');
      if (error) error.style.display = 'none';
      return true;
    }

    // Check if value is valid using the provided validation function
    const isValid = validationFunction(value);
    
    if (!isValid) {
      input.classList.add('is-invalid');
      if (error) error.style.display = 'block';
      return false;
    } else {
      input.classList.remove('is-invalid');
      if (error) error.style.display = 'none';
      return true;
    }
  }

  // Validate on input (real-time)
  input.addEventListener('input', validate);
  
  // Validate on blur
  input.addEventListener('blur', validate);
  
  return validate;
}

// Initialize name validation for standard document forms
// prefix parameter allows for custom ID prefixes (e.g., 'res_' for certificate-residency.html)
function initializeNameValidation(formId = null, prefix = '') {
  document.addEventListener('DOMContentLoaded', function() {
    validateNameField(prefix + 'lastName', prefix + 'lastNameError');
    validateNameField(prefix + 'firstName', prefix + 'firstNameError');
    validateNameField(prefix + 'middleName', prefix + 'middleNameError');
    validateNameField(prefix + 'suffix', prefix + 'suffixError', true); // true = allow dots

    // Form submit validation
    const form = formId ? document.getElementById(formId) : document.querySelector('form');
    if (form) {
      form.addEventListener('submit', function(e) {
        const lastName = document.getElementById(prefix + 'lastName');
        const firstName = document.getElementById(prefix + 'firstName');
        const middleName = document.getElementById(prefix + 'middleName');
        const suffix = document.getElementById(prefix + 'suffix');

        let isValid = true;

        // Validate required name fields
        if (lastName && lastName.value && !isLettersAndSpacesOnly(lastName.value)) {
          lastName.classList.add('is-invalid');
          const err = document.getElementById(prefix + 'lastNameError');
          if (err) err.style.display = 'block';
          isValid = false;
        }

        if (firstName && firstName.value && !isLettersAndSpacesOnly(firstName.value)) {
          firstName.classList.add('is-invalid');
          const err = document.getElementById(prefix + 'firstNameError');
          if (err) err.style.display = 'block';
          isValid = false;
        }

        // Validate optional name fields if they have content
        if (middleName && middleName.value && !isLettersAndSpacesOnly(middleName.value)) {
          middleName.classList.add('is-invalid');
          const err = document.getElementById(prefix + 'middleNameError');
          if (err) err.style.display = 'block';
          isValid = false;
        }

        if (suffix && suffix.value && !isSuffixValid(suffix.value)) {
          suffix.classList.add('is-invalid');
          const err = document.getElementById(prefix + 'suffixError');
          if (err) err.style.display = 'block';
          isValid = false;
        }

        if (!isValid) {
          e.preventDefault();
          e.stopPropagation();
        }
      });
    }
  });
}
