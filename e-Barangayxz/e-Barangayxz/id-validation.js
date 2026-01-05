/**
 * AI-Powered Valid ID Validation Module
 * Validates uploaded ID images using AI before form submission
 */

(function() {
    'use strict';

    // Configuration
    const VALIDATE_ID_ENDPOINT = 'validate_id.php';
    
    // Track validation state
    let validationState = {
        isValidating: false,
        validatedFiles: new Map() // Track which files have been validated
    };

    /**
     * Create validation result display element
     */
    function createValidationDisplay() {
        const display = document.createElement('div');
        display.id = 'id-validation-display';
        display.className = 'mt-2';
        display.style.display = 'none';
        return display;
    }

    /**
     * Show validation status
     */
    function showValidationStatus(container, status, message, details = null) {
        let display = container.querySelector('.id-validation-display');
        if (!display) {
            display = document.createElement('div');
            display.className = 'id-validation-display mt-2';
            container.appendChild(display);
        }

        let html = '';
        let alertClass = '';
        let icon = '';

        switch (status) {
            case 'validating':
                alertClass = 'alert-info';
                icon = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>';
                html = `<div class="alert ${alertClass} d-flex align-items-center mb-0 py-2">
                    ${icon}
                    <span>${message}</span>
                </div>`;
                break;

            case 'valid':
                alertClass = 'alert-success';
                icon = '<i class="bi bi-check-circle-fill me-2"></i>';
                html = `<div class="alert ${alertClass} mb-0 py-2">
                    ${icon}<strong>Valid ID Detected!</strong> ${message}
                    ${details ? `<br><small class="text-muted">ID Type: ${details.id_type}</small>` : ''}
                </div>`;
                break;

            case 'invalid':
                alertClass = 'alert-danger';
                icon = '<i class="bi bi-x-circle-fill me-2"></i>';
                let issuesList = '';
                if (details && details.issues && details.issues.length > 0) {
                    issuesList = '<ul class="mb-0 mt-2 small">' + 
                        details.issues.map(issue => `<li>${issue}</li>`).join('') + 
                        '</ul>';
                }
                html = `<div class="alert ${alertClass} mb-0 py-2">
                    ${icon}<strong>Invalid ID</strong> - ${message}
                    ${issuesList}
                    <div class="mt-2 small">
                        <strong>Requirements:</strong>
                        <ul class="mb-0">
                            <li>Valid government-issued ID</li>
                            <li>Must show ID number</li>
                            <li>Must have your photo</li>
                            <li>Name must match your registered name</li>
                            <li>Birthday must match your registered birthday</li>
                        </ul>
                    </div>
                </div>`;
                break;

            case 'error':
                alertClass = 'alert-warning';
                icon = '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
                html = `<div class="alert ${alertClass} mb-0 py-2">
                    ${icon}${message}
                </div>`;
                break;
        }

        display.innerHTML = html;
        display.style.display = 'block';
    }

    /**
     * Hide validation status
     */
    function hideValidationStatus(container) {
        const display = container.querySelector('.id-validation-display');
        if (display) {
            display.style.display = 'none';
        }
    }

    /**
     * Validate ID file
     */
    async function validateIdFile(fileInput) {
        const file = fileInput.files[0];
        if (!file) return null;

        const container = fileInput.closest('.col-md-6') || fileInput.parentElement;
        
        // Check if file is an image (not PDF)
        if (file.type === 'application/pdf') {
            showValidationStatus(container, 'error', 
                'PDF files cannot be validated automatically. Please upload an image (JPG, PNG) of your valid ID for AI verification.');
            return { skip: true, message: 'PDF uploaded - manual verification needed' };
        }

        // Check if this exact file was already validated
        const fileKey = `${file.name}-${file.size}-${file.lastModified}`;
        if (validationState.validatedFiles.has(fileKey)) {
            const cachedResult = validationState.validatedFiles.get(fileKey);
            if (cachedResult.is_valid) {
                showValidationStatus(container, 'valid', cachedResult.message, cachedResult.details);
            } else {
                showValidationStatus(container, 'invalid', cachedResult.message, cachedResult);
            }
            return cachedResult;
        }

        showValidationStatus(container, 'validating', 'Validating your ID... please wait.');

        const formData = new FormData();
        formData.append('validId', file);

        try {
            const response = await fetch(VALIDATE_ID_ENDPOINT, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });

            const data = await response.json();

            if (!data.success) {
                showValidationStatus(container, 'error', data.error || 'Validation failed. Please try again.');
                return { is_valid: false, error: data.error };
            }

            // Cache the result
            validationState.validatedFiles.set(fileKey, data);

            if (data.is_valid) {
                showValidationStatus(container, 'valid', data.message, data.details);
            } else {
                showValidationStatus(container, 'invalid', data.message, data);
            }

            return data;

        } catch (error) {
            console.error('ID validation error:', error);
            showValidationStatus(container, 'error', 
                'Could not validate ID. Please check your internet connection and try again.');
            return { is_valid: false, error: error.message };
        }
    }

    /**
     * Setup validation for a file input
     */
    function setupFileValidation(fileInput) {
        if (fileInput.dataset.idValidationSetup) return;
        fileInput.dataset.idValidationSetup = 'true';

        fileInput.addEventListener('change', async function() {
            if (this.files && this.files[0]) {
                await validateIdFile(this);
            } else {
                const container = this.closest('.col-md-6') || this.parentElement;
                hideValidationStatus(container);
            }
        });
    }

    /**
     * Find all valid ID file inputs in a form
     */
    function findIdInputs(form) {
        // Common field names for valid ID uploads (government IDs only, NOT photos)
        const selectors = [
            'input[name="validId"]',
            'input[name="valid_id"]'
        ];
        
        const inputs = [];
        selectors.forEach(selector => {
            const input = form.querySelector(selector);
            if (input && input.type === 'file') {
                inputs.push(input);
            }
        });
        
        return inputs;
    }

    /**
     * Initialize ID validation for a form
     */
    function initializeIdValidation(formId) {
        const form = document.getElementById(formId);
        if (!form) {
            console.warn('ID Validation: Form not found:', formId);
            return;
        }

        const idInputs = findIdInputs(form);
        if (idInputs.length === 0) {
            console.log('ID Validation: No ID inputs found in form:', formId);
            return;
        }

        // Setup validation for each ID input
        idInputs.forEach(input => setupFileValidation(input));

        // Intercept form submission
        const originalSubmit = form.onsubmit;
        const submitBtn = form.querySelector('button[type="submit"], #submitBtn');

        async function handleSubmit(e) {
            e.preventDefault();
            e.stopPropagation();

            // Validate all ID inputs before submission
            let allValid = true;
            let hasUnvalidated = false;

            for (const input of idInputs) {
                if (input.files && input.files[0]) {
                    const file = input.files[0];
                    const fileKey = `${file.name}-${file.size}-${file.lastModified}`;
                    
                    // Check if already validated
                    if (validationState.validatedFiles.has(fileKey)) {
                        const cachedResult = validationState.validatedFiles.get(fileKey);
                        if (cachedResult.skip) continue; // PDF - skip AI validation
                        if (!cachedResult.is_valid) {
                            allValid = false;
                        }
                    } else {
                        hasUnvalidated = true;
                        // Validate now
                        const result = await validateIdFile(input);
                        if (result && !result.skip && !result.is_valid) {
                            allValid = false;
                        }
                    }
                }
            }

            if (!allValid) {
                alert('Please upload a valid government ID that matches your registered information before submitting.\n\nYour ID must:\n• Be a valid government-issued ID\n• Show an ID number\n• Have your photo\n• Match your registered name\n• Match your registered birthday');
                return false;
            }

            // All validations passed - proceed with original submission
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
            }

            // Trigger the original form submission
            if (typeof originalSubmit === 'function') {
                originalSubmit.call(form, e);
            } else {
                // Check if form has a custom submit handler via event listener
                const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
                submitEvent.idValidationPassed = true;
                form.dispatchEvent(submitEvent);
            }

            return false;
        }

        // Override form submit
        form.addEventListener('submit', function(e) {
            if (!e.idValidationPassed) {
                handleSubmit(e);
            }
        }, true);

        // Also handle click on submit button
        if (submitBtn) {
            const originalClick = submitBtn.onclick;
            submitBtn.addEventListener('click', async function(e) {
                // Check if this is a button that triggers form submission
                if (submitBtn.type === 'submit' || submitBtn.type === 'button') {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Validate all ID inputs
                    let allValid = true;
                    
                    for (const input of idInputs) {
                        if (input.files && input.files[0]) {
                            const file = input.files[0];
                            const fileKey = `${file.name}-${file.size}-${file.lastModified}`;
                            
                            if (validationState.validatedFiles.has(fileKey)) {
                                const cachedResult = validationState.validatedFiles.get(fileKey);
                                if (cachedResult.skip) continue;
                                if (!cachedResult.is_valid) {
                                    allValid = false;
                                }
                            } else {
                                const result = await validateIdFile(input);
                                if (result && !result.skip && !result.is_valid) {
                                    allValid = false;
                                }
                            }
                        }
                    }

                    if (!allValid) {
                        alert('Please upload a valid government ID that matches your registered information before submitting.\n\nYour ID must:\n• Be a valid government-issued ID\n• Show an ID number\n• Have your photo\n• Match your registered name\n• Match your registered birthday');
                        return false;
                    }

                    // Validation passed - trigger original handler
                    if (typeof originalClick === 'function') {
                        originalClick.call(submitBtn, e);
                    } else {
                        // Mark validation as passed and submit form
                        const form = submitBtn.closest('form');
                        if (form) {
                            const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
                            submitEvent.idValidationPassed = true;
                            form.dispatchEvent(submitEvent);
                        }
                    }
                }
            }, true);
        }

        console.log('ID Validation initialized for form:', formId, 'with', idInputs.length, 'ID input(s)');
    }

    /**
     * Check if all ID inputs in a form have valid IDs
     * Can be called by forms before their own submission logic
     */
    async function checkFormIdValidation(formId) {
        const form = document.getElementById(formId);
        if (!form) return { valid: true, message: 'Form not found' };
        
        const idInputs = findIdInputs(form);
        if (idInputs.length === 0) return { valid: true, message: 'No ID inputs to validate' };
        
        for (const input of idInputs) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileKey = `${file.name}-${file.size}-${file.lastModified}`;
                
                // Check if already validated
                if (validationState.validatedFiles.has(fileKey)) {
                    const cachedResult = validationState.validatedFiles.get(fileKey);
                    if (cachedResult.skip) continue; // PDF - skip AI validation
                    if (!cachedResult.is_valid) {
                        return { valid: false, message: cachedResult.message, details: cachedResult };
                    }
                } else {
                    // Validate now
                    const result = await validateIdFile(input);
                    if (result && !result.skip && !result.is_valid) {
                        return { valid: false, message: result.message, details: result };
                    }
                }
            }
        }
        
        return { valid: true, message: 'All IDs validated successfully' };
    }

    /**
     * Auto-initialize for common form IDs
     */
    function autoInitialize() {
        const formIds = [
            'idApplicationForm',           // barangay-id.php
            'clearanceForm',               // barangay-clearance.php
            'residencyForm',               // certificate-residency.php
            'goodCharacterForm',           // goodmoral-certificate.php
            'nonEmploymentForm',           // non-employment.php
            'lowIncomeForm',               // low-income-certificate.php
            'indigencyForm',               // indigency-certificate.php
            'businessPermitForm',          // business-permit.php
            'noDerogatoryForm',            // no-derogatory.php
            'burialAssistanceForm',        // burial-assistance.php
            'soloParentForm',              // soloparent-certificate.php
            'otherRequestForm'             // others.php
        ];

        formIds.forEach(formId => {
            const form = document.getElementById(formId);
            if (form) {
                initializeIdValidation(formId);
            }
        });
    }

    // Export functions for global use
    window.IdValidation = {
        initialize: initializeIdValidation,
        validateFile: validateIdFile,
        autoInitialize: autoInitialize,
        checkForm: checkFormIdValidation,
        getValidatedFiles: () => validationState.validatedFiles
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInitialize);
    } else {
        autoInitialize();
    }
})();
