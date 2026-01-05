// Redirect to dashboard on register (demo only)
document.addEventListener('DOMContentLoaded', function() {
});
// Login/Register tab switcher for login-register.html
document.addEventListener('DOMContentLoaded', function() {
	const loginTab = document.getElementById('loginTab');
	const registerTab = document.getElementById('registerTab');
	const loginForm = document.getElementById('loginForm');
	const registerForm = document.getElementById('registerForm');
	if (loginTab && registerTab && loginForm && registerForm) {
		loginTab.addEventListener('click', function() {
			loginTab.classList.add('active');
			registerTab.classList.remove('active');
			loginForm.style.display = '';
			registerForm.style.display = 'none';
		});
		registerTab.addEventListener('click', function() {
			registerTab.classList.add('active');
			loginTab.classList.remove('active');
			registerForm.style.display = '';
			loginForm.style.display = 'none';
		});
	}
});
// Enable Bootstrap tooltips everywhere
document.addEventListener('DOMContentLoaded', function () {
	var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
	tooltipTriggerList.forEach(function (tooltipTriggerEl) {
		new bootstrap.Tooltip(tooltipTriggerEl);
	});
});

// Example: Smooth scroll for anchor links (if you use # anchors) 
// di ko mapagana to :( HAHAHAHA
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
	anchor.addEventListener('click', function (e) {
		const target = document.querySelector(this.getAttribute('href'));
		if (target) {
			e.preventDefault();
			target.scrollIntoView({ behavior: 'smooth' });
		}
	});
});

// Test Bootstrap JS: Show a modal on page load
// REMOVED - Bootstrap test modal commented out
/*
document.addEventListener('DOMContentLoaded', function () {
		// Create modal HTML
		const modalHtml = `
		<div class="modal fade" id="testModal" tabindex="-1" aria-labelledby="testModalLabel" aria-hidden="true">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="testModalLabel">Bootstrap JS Test</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						Bootstrap JS is working!
					</div>
				</div>
			</div>
		</div>`;
		document.body.insertAdjacentHTML('beforeend', modalHtml);
		// Show modal
		var modal = new bootstrap.Modal(document.getElementById('testModal'));
		modal.show();
});
*/

// staff-login.js logic moved from staff-login.html

document.addEventListener("DOMContentLoaded", function() {
  var staffLoginForm = document.getElementById("staffLoginForm");
  if (staffLoginForm) {
    staffLoginForm.addEventListener("submit", function(event) {
      event.preventDefault(); 
      window.location.href = "staff-dashboard.html";
    });
  }

  var forgotPasswordForm = document.getElementById("forgotPasswordForm");
  if (forgotPasswordForm) {
    forgotPasswordForm.addEventListener("submit", function(e) {
      e.preventDefault();
      let email = document.getElementById("resetEmail").value.trim();
      let newPass = document.getElementById("newPassword").value.trim();
      let confirmPass = document.getElementById("confirmPassword").value.trim();
      let messageBox = document.getElementById("resetMessage");

      if (newPass !== confirmPass) {
        messageBox.className = "alert alert-danger small mt-2 text-center";
        messageBox.textContent = "Passwords do not match.";
        messageBox.classList.remove("d-none");
        return;
      }

      if (newPass.length < 6) {
        messageBox.className = "alert alert-warning small mt-2 text-center";
        messageBox.textContent = "Password must be at least 6 characters.";
        messageBox.classList.remove("d-none");
        return;
      }

      messageBox.className = "alert alert-success small mt-2 text-center";
      messageBox.textContent = "Password successfully reset for " + email + ". You may now log in.";
      messageBox.classList.remove("d-none");

      setTimeout(() => {
        var modal = bootstrap.Modal.getInstance(document.getElementById('forgotPasswordModal'));
        modal.hide();
        document.getElementById("forgotPasswordForm").reset();
        messageBox.classList.add("d-none");
      }, 2000);
    });
  }
});

// staff-dashboard.js logic moved from staff-dashboard.html


document.addEventListener("DOMContentLoaded", function() {
  // Chart.js charts
  if (window.Chart) {
    // --- Dashboard Overview Chart with Breakdown Tooltip ---
    var overviewChartEl = document.getElementById('requestsOverviewChart');
    if (overviewChartEl) {
      // Example breakdown data for each month
      const monthlyRequestDetails = {
        'Jan': [
          { name: 'Barangay Clearance', count: 2 },
          { name: 'Indigency', count: 1 }
        ],
        'Feb': [
          { name: 'Barangay Clearance', count: 0 },
          { name: 'Indigency', count: 0 }
        ],
        'Mar': [
          { name: 'Barangay Clearance', count: 0 },
          { name: 'Indigency', count: 0 }
        ],
        'Apr': [
          { name: 'Barangay Clearance', count: 0 },
          { name: 'Indigency', count: 0 }
        ],
        'May': [
          { name: 'Barangay Clearance', count: 0 },
          { name: 'Indigency', count: 0 }
        ],
        'Jun': [
          { name: 'Barangay Clearance', count: 0 },
          { name: 'Indigency', count: 0 }
        ],
        'Jul': [
          { name: 'Barangay Clearance', count: 0 },
          { name: 'Indigency', count: 0 }
        ],
        'Aug': [
          { name: 'Barangay Clearance', count: 0 },
          { name: 'Indigency', count: 0 }
        ],
        'Sep': [
          { name: 'Barangay Clearance', count: 0 },
          { name: 'Indigency', count: 0 }
        ],
        'Oct': [
          { name: 'Barangay Clearance', count: 0 },
          { name: 'Indigency', count: 0 }
        ],
        'Nov': [
          { name: 'Barangay Clearance', count: 5 },
          { name: 'Indigency', count: 1 }
        ],
        'Dec': [
          { name: 'Barangay Clearance', count: 70 },
          { name: 'Indigency', count: 20 },
          { name: 'Good Moral', count: 0 }
        ]
      };

      function escapeHtml(text) {
        return String(text).replace(/[&<>"]/g, function (c) {
          return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
        });
      }

      const chart = new Chart(overviewChartEl, {
        type: 'line',
        data: {
          labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
          datasets: [{
            label: 'Requests',
            data: [0,0,0,0,0,0,0,0,0,0,5,90], // Example data, update as needed
            borderColor: '#2b6cb0',
            backgroundColor: 'rgba(43,108,176,0.12)',
            tension: 0.3,
            fill: true,
            pointRadius: 6,
            pointHoverRadius: 8
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              enabled: false,
              external: function(context) {
                // Remove previous tooltip if it exists
                let tooltipEl = document.getElementById('chartjs-requests-breakdown');
                if (!tooltipEl) {
                  tooltipEl = document.createElement('div');
                  tooltipEl.id = 'chartjs-requests-breakdown';
                  tooltipEl.innerHTML = '<table></table>';
                  document.body.appendChild(tooltipEl);
                }
                // Hide if no tooltip
                const tooltipModel = context.tooltip;
                if (tooltipModel.opacity === 0) {
                  tooltipEl.style.opacity = 0;
                  return;
                }
                // Set Text
                if (tooltipModel.body) {
                  const month = context.chart.data.labels[tooltipModel.dataPoints[0].dataIndex];
                  const monthData = (monthlyRequestDetails && monthlyRequestDetails[month]) ? monthlyRequestDetails[month] : null;
                  let tableRoot = tooltipEl.querySelector('table');
                  if (!tableRoot) {
                    tooltipEl.innerHTML = '<table></table>';
                    tableRoot = tooltipEl.querySelector('table');
                  }
                  // Build table body
                  if (monthData && monthData.length) {
                    // List of all possible document types (in desired order)
                    const docTypes = [
                      'Barangay Clearance',
                      'Certificate of Residency',
                      'Certificate of Indigency',
                      'Certificate of Good Moral Character',
                      'Business Clearance / Permit',
                      'Certificate of Solo Parent',
                      'Certificate of No Derogatory Record',
                      'Blotter / Incident Report',
                      'Barangay ID',
                      'Certificate of Low Income',
                      'Certificate of Non-Employment',
                      'Certificate for Burial Assistance',
                      'Other (Please Specify)'
                    ];
                    // Map monthData to a lookup
                    const lookup = {};
                    monthData.forEach(item => {
                      lookup[item.name] = item.count;
                    });
                    // Build bullet list
                    const listItems = docTypes.map(type => {
                      const val = lookup[type];
                      const display = (val === undefined || val === null || val === '' || Number(val) === 0) ? '-' : String(val);
                      return `<li>${escapeHtml(type)}: ${escapeHtml(display)}</li>`;
                    }).join('');
                    tableRoot.innerHTML = `
                      <thead><tr><th class='text-start pb-2' colspan='2'>Total Document Requests</th></tr></thead>
                      <tbody>
                        <tr><td colspan='2'><b>Request Breakdown:</b><ul style='padding-left:18px;margin-bottom:0;'>${listItems}</ul></td></tr>
                        <tr><td colspan='2'><hr style='margin:8px 0 4px 0;'/><span class='small text-muted'>Last 30 days activity</span></td></tr>
                      </tbody>
                    `;
                  } else {
                    tableRoot.innerHTML = `<tbody><tr><td class='small text-muted'>No additional details available</td></tr></tbody>`;
                  }
                }
                // Position tooltip and set styles
                const position = context.chart.canvas.getBoundingClientRect();
                tooltipEl.style.opacity = 1;
                tooltipEl.style.position = 'absolute';
                tooltipEl.style.left = position.left + window.pageXOffset + tooltipModel.caretX - 100 + 'px';
                tooltipEl.style.top = position.top + window.pageYOffset + tooltipModel.caretY - 120 + 'px';
                tooltipEl.style.padding = '8px 12px';
                tooltipEl.style.pointerEvents = 'none';
                tooltipEl.style.background = 'rgba(255, 255, 255, 0.98)';
                tooltipEl.style.borderRadius = '6px';
                tooltipEl.style.boxShadow = '0 2px 8px rgba(0,0,0,0.15)';
                tooltipEl.style.fontSize = '13px';
                tooltipEl.style.minWidth = '200px';
              }
            }
          }
        }
      });

      // Force tooltip to show on hover
      overviewChartEl.addEventListener('mousemove', function(evt) {
        const points = chart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
        if (points.length) {
          chart.setActiveElements([points[0]]);
          chart.tooltip.setActiveElements([points[0]], {x: points[0].element.x, y: points[0].element.y});
          chart.update();
        } else {
          chart.setActiveElements([]);
          chart.tooltip.setActiveElements([], {});
          chart.update();
        }
      });
      overviewChartEl.addEventListener('mouseleave', function() {
        chart.setActiveElements([]);
        chart.tooltip.setActiveElements([], {});
        chart.update();
      });
    }

    // ...existing code for paymentsChart...
    var paymentsChart = document.getElementById('paymentsChart');
    if (paymentsChart) {
      new Chart(paymentsChart, {
        type: 'bar',
        data: {
          labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
          datasets: [{ label: 'Payments (₱)', data: [2000, 2500, 1800, 3200, 3000, 2750], backgroundColor: '#198754' }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
      });
    }
  }

  // Notification logic (robust): use delegation and avoid manual dropdown toggles
  (function () {
    const notifButton = document.getElementById('notifButton');
    const notifDropdown = document.getElementById('notifDropdown');
    const notifBadge = document.getElementById('notifBadge');
    const notifModalEl = document.getElementById('notifModal');
    const notifModal = notifModalEl ? new bootstrap.Modal(notifModalEl) : null;
    const notifModalLabel = document.getElementById('notifModalLabel');
    const notifModalBody = document.getElementById('notifModalBody');

    function updateBadge() {
      if (!notifBadge) return;
      const unreadCount = document.querySelectorAll('.notif-item.unread').length;
      notifBadge.textContent = unreadCount;
      notifBadge.style.display = unreadCount > 0 ? 'inline-block' : 'none';
    }

    // Delegated click handler on the dropdown menu so dynamically-added items also work
    if (notifDropdown) {
      notifDropdown.addEventListener('click', function (e) {
        const item = e.target.closest('.notif-item');
        if (!item) return;

        // Mark as read
        if (item.classList.contains('unread')) {
          item.classList.remove('unread');
          const icon = item.querySelector('i');
          if (icon && icon.classList.contains('bi-bell-fill')) {
            icon.classList.replace('bi-bell-fill', 'bi-bell');
          }
          updateBadge();
          // Persist read state to shared storage if possible
          try {
            const itemId = item.getAttribute('data-id');
            if (itemId && typeof window.markNotificationRead === 'function') {
              window.markNotificationRead(itemId);
            } else if (typeof window.getStoredNotifications === 'function') {
              const titleText = item.getAttribute('data-title') || (item.querySelector('h6') ? item.querySelector('h6').textContent : '');
              const messageText = item.getAttribute('data-message') || (item.querySelector('p') ? item.querySelector('p').textContent : '');
              const stored = window.getStoredNotifications() || [];
              const found = stored.find(s => s.title === titleText && s.message === messageText);
              if (found && found.id && typeof window.markNotificationRead === 'function') {
                window.markNotificationRead(found.id);
              }
            }
          } catch (e) { /* ignore persistence errors */ }
        }

        // Show modal with info
        if (notifModal && notifModalLabel && notifModalBody) {
          const title = item.getAttribute('data-title') || (item.querySelector('h6') ? item.querySelector('h6').textContent : 'Notification');
          const message = item.getAttribute('data-message') || (item.querySelector('p') ? item.querySelector('p').textContent : '');
          const url = item.getAttribute('data-url') || '#';
          notifModalLabel.textContent = title;
          notifModalBody.textContent = message;
          const viewBtn = document.getElementById('notifViewBtn');
          if (viewBtn) viewBtn.href = url;
          notifModal.show();
        }
      });
    }

    // Play sound when new notification items are added to the notification panel
    const notifPanel = document.querySelector('.notification-panel');
    if (notifPanel) {
      let initialCount = notifPanel.querySelectorAll('.notif-item').length;
      const mo = new MutationObserver(muts => {
        muts.forEach(m => {
          if (m.addedNodes && m.addedNodes.length) {
            const newItems = Array.from(m.addedNodes).filter(n => n.classList && n.classList.contains('notif-item'));
            if (newItems.length) { try { playNotificationSound(); } catch (e) { } }
            initialCount = notifPanel.querySelectorAll('.notif-item').length;
          }
        });
      });
      mo.observe(notifPanel, { childList: true, subtree: false });
    }

    // When the dropdown is shown via Bootstrap, optionally play sound if there are unread items
    if (notifButton) {
      // Bootstrap triggers 'shown.bs.dropdown' on the toggle element
      notifButton.addEventListener('shown.bs.dropdown', function () {
        const unread = document.querySelectorAll('.notification-panel .notif-item.unread').length;
        if (unread > 0) { try { playNotificationSound(); } catch (e) { } }
      });
    }

    // Initialize badge on load
    updateBadge();

    // Sync from central localStorage so every page shows the same notifications
    try {
      const STORAGE_KEY = 'eb_notifications_v1';
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        const stored = JSON.parse(raw);
        if (Array.isArray(stored) && notifPanel) {
          // Replace panel contents with stored notifications (keeps order)
          notifPanel.innerHTML = '';
          stored.forEach(obj => {
            const div = document.createElement('div');
            div.className = 'notif-item p-3 border-bottom' + (obj.unread ? ' unread' : '');
            if (obj.id) div.setAttribute('data-id', obj.id);
            if (obj.title) div.setAttribute('data-title', obj.title);
            if (obj.message) div.setAttribute('data-message', obj.message);
            if (obj.url) div.setAttribute('data-url', obj.url || '#');
            const iconClass = obj.iconClass || 'bi bi-bell-fill';
            const title = obj.title || 'Notification';
            const message = obj.message || '';
            div.innerHTML = `<div class="d-flex align-items-start"><i class="${iconClass} me-2 text-primary fs-5"></i><div><h6 class="mb-1 fw-semibold text-dark">${title}</h6><p class="mb-0 small text-secondary">${message}</p></div></div>`;
            notifPanel.appendChild(div);
          });
          // Update badge to match stored unread count
          updateBadge();
        }
      }
    } catch (e) { /* ignore parse errors */ }
  })();
});

// --- Cross-tab notification helpers & realtime sync ---
// Provides: getStoredNotifications, saveStoredNotifications, addNotification, markNotificationRead, removeNotification
(function () {
  const STORAGE_KEY = 'eb_notifications_v1';

  function getStoredNotifications() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (e) { return []; }
  }

  function saveStoredNotifications(arr) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(arr || []));
      return true;
    } catch (e) { return false; }
  }

  // Render stored notifications into the panel (if present on page)
  function renderStoredNotifications() {
    try {
      const panel = document.querySelector('.notification-panel');
      const badge = document.getElementById('notifBadge');
      const stored = getStoredNotifications();
      if (!panel) return;

      // If stored notifications exist, render them: show first 3 as main, rest in .extra-notifs
      if (Array.isArray(stored) && stored.length > 0) {
        const main = stored.slice(0, 3);
        const extras = stored.slice(3);
        panel.innerHTML = '';

        main.forEach(obj => {
          const div = document.createElement('div');
          div.className = 'notif-item p-3 border-bottom' + (obj.unread ? ' unread' : '');
          if (obj.id) div.setAttribute('data-id', obj.id);
          if (obj.title) div.setAttribute('data-title', obj.title);
          if (obj.message) div.setAttribute('data-message', obj.message);
          if (obj.url) div.setAttribute('data-url', obj.url || '#');
          const iconClass = obj.iconClass || 'bi bi-bell-fill';
          const title = obj.title || 'Notification';
          const message = obj.message || '';
          div.innerHTML = `<div class="d-flex align-items-start"><i class="${iconClass} me-2 text-primary fs-5"></i><div><h6 class="mb-1 fw-semibold text-dark">${title}</h6><p class="mb-0 small text-secondary">${message}</p></div></div>`;
          panel.appendChild(div);
        });

        // Create or reuse extra-notifs container
        let extraEl = panel.querySelector('.extra-notifs');
        if (!extraEl) {
          extraEl = document.createElement('div');
          extraEl.className = 'extra-notifs mt-0';
        } else {
          extraEl.innerHTML = '';
        }

        extras.forEach(obj => {
          const div = document.createElement('div');
          div.className = 'notif-item p-3 border-bottom' + (obj.unread ? ' unread' : '');
          if (obj.id) div.setAttribute('data-id', obj.id);
          if (obj.title) div.setAttribute('data-title', obj.title);
          if (obj.message) div.setAttribute('data-message', obj.message);
          if (obj.url) div.setAttribute('data-url', obj.url || '#');
          const iconClass = obj.iconClass || 'bi bi-bell-fill';
          const title = obj.title || 'Notification';
          const message = obj.message || '';
          div.innerHTML = `<div class="d-flex align-items-start"><i class="${iconClass} me-2 text-primary fs-5"></i><div><h6 class="mb-1 fw-semibold text-dark">${title}</h6><p class="mb-0 small text-secondary">${message}</p></div></div>`;
          extraEl.appendChild(div);
        });

        if (extraEl.children.length) panel.appendChild(extraEl);

        // update badge from stored data
        if (badge) {
          const unread = stored.filter(s => s.unread).length;
          badge.textContent = unread;
          badge.style.display = unread > 0 ? 'inline-block' : 'none';
        }
        return;
      }

      // If no stored notifications, keep existing DOM (do not overwrite)
      return;
    } catch (e) { /* ignore render errors */ }
  }

  function addNotification(obj) {
    const stored = getStoredNotifications();
    const now = Date.now();
    const n = Object.assign({ id: obj.id || `n-${now}-${stored.length}`, title: obj.title || 'Notification', message: obj.message || '', url: obj.url || '#', unread: typeof obj.unread === 'boolean' ? obj.unread : true, iconClass: obj.iconClass || 'bi bi-bell-fill' }, obj);
    stored.unshift(n); // add to top
    saveStoredNotifications(stored);
    renderStoredNotifications();
    try { playNotificationSound(); } catch (e) {}
    return n;
  }

  function markNotificationRead(id) {
    const stored = getStoredNotifications();
    let changed = false;
    const updated = stored.map(s => {
      if (s.id && s.id === id) { changed = true; return Object.assign({}, s, { unread: false }); }
      return s;
    });
    if (changed) { saveStoredNotifications(updated); renderStoredNotifications(); }
    return changed;
  }

  function removeNotification(id) {
    const stored = getStoredNotifications();
    const filtered = stored.filter(s => !(s.id && s.id === id));
    if (filtered.length !== stored.length) { saveStoredNotifications(filtered); renderStoredNotifications(); return true; }
    return false;
  }

  // Expose helpers for console / other scripts
  window.getStoredNotifications = getStoredNotifications;
  window.saveStoredNotifications = saveStoredNotifications;
  window.addNotification = addNotification;
  window.markNotificationRead = markNotificationRead;
  window.removeNotification = removeNotification;

  // Listen for storage events from other tabs and re-render
  window.addEventListener('storage', function (e) {
    if (!e) return;
    if (e.key === STORAGE_KEY) {
      renderStoredNotifications();
    }
  });

  // Render on initial load (in case the DOMContentLoaded handler earlier didn't)
  document.addEventListener('DOMContentLoaded', function () { renderStoredNotifications(); });
})();

// Common form submission handlers for service request pages

// Helper function to show success modal and redirect
function showSuccessAndRedirect(message) {
  // Create success modal
  const modalHtml = `
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title" id="successModalLabel">
              <i class="bi bi-check-circle-fill me-2"></i>Success!
            </h5>
          </div>
          <div class="modal-body text-center py-4">
            <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
            <h5 class="mt-3">Submitted Successfully!</h5>
            <p class="text-muted">${message}</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-bs-dismiss="modal" id="successOkBtn">OK</button>
          </div>
        </div>
      </div>
    </div>`;
  
  // Remove existing modal if any
  const existingModal = document.getElementById('successModal');
  if (existingModal) {
    existingModal.remove();
  }
  
  // Add modal to page
  document.body.insertAdjacentHTML('beforeend', modalHtml);
  
  // Show modal
  const successModal = new bootstrap.Modal(document.getElementById('successModal'));
  successModal.show();
  
  // Redirect to resident dashboard when OK button is clicked or modal is hidden
  const modalElement = document.getElementById('successModal');
  modalElement.addEventListener('hidden.bs.modal', function () {
    window.location.href = 'resident-dashboard.php';
  });
}

// barangay-clearance.html
document.addEventListener('DOMContentLoaded', function() {
  const clearanceForm = document.getElementById('clearanceForm');
  if (clearanceForm) {
    clearanceForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showSuccessAndRedirect("Your Barangay Clearance request has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.");
      this.reset();
    });
  }
});

// barangay-id (only intercept the legacy static HTML; let PHP/backend submit normally)
document.addEventListener('DOMContentLoaded', function() {
  const idForm = document.getElementById('idApplicationForm');
  const isPhpPage = window.location.pathname.toLowerCase().endsWith('.php');
  const hasBackendHandler = idForm && idForm.dataset.backend === 'true';

  if (idForm && !isPhpPage && !hasBackendHandler) {
    idForm.addEventListener('submit', function(e) {
      e.preventDefault();
      showSuccessAndRedirect("Your Barangay ID Application has been submitted successfully. You will be notified via email or text for the next steps.");
      this.reset();
    });
  }
});

// burial-assistance.html
document.addEventListener('DOMContentLoaded', function() {
  const burialForm = document.getElementById('burialAssistanceForm');
  const isPhpPage = window.location.pathname.toLowerCase().endsWith('.php');
  const hasBackendHandler = burialForm && burialForm.dataset.backend === 'true';

  // Only intercept the legacy static HTML; allow PHP/backend submission to proceed
  if (burialForm && !isPhpPage && !hasBackendHandler) {
    burialForm.addEventListener('submit', function(e) {
      e.preventDefault();
      showSuccessAndRedirect("Your Burial Assistance application has been submitted successfully. We will process your documents and notify you of the claim status shortly.");
      this.reset();
    });
  }
});

// business-permit.html
document.addEventListener('DOMContentLoaded', function() {
  const businessPermitForm = document.getElementById('businessPermitForm');
  // Only attach the dummy handler on the static HTML version; skip for PHP so real submission runs
  const isPhpPage = window.location.pathname.toLowerCase().endsWith('.php');
  if (businessPermitForm && !isPhpPage) {
    businessPermitForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showSuccessAndRedirect("Your Business Permit request has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.");
      this.reset();
    });
  }
});

// certificate-residency.html
document.addEventListener('DOMContentLoaded', function() {
  const residencyForm = document.getElementById('residencyForm');
  if (residencyForm) {
    residencyForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showSuccessAndRedirect("Your Certificate of Residency request has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.");
      this.reset();
    });
  }
});

// goodmoral-certificate.html
document.addEventListener('DOMContentLoaded', function() {
  const goodCharacterForm = document.getElementById('goodCharacterForm');
  if (goodCharacterForm) {
    goodCharacterForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showSuccessAndRedirect("Your Good Moral Character request has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.");
      this.reset();
    });
  }
});

// indigency-certificate.html
document.addEventListener('DOMContentLoaded', function() {
  const indigencyForm = document.getElementById('indigencyForm');
  if (indigencyForm) {
    indigencyForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showSuccessAndRedirect("Your Certificate of Indigency request has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.");
      this.reset();
    });
  }
});

// low-income-certificate (only intercept static HTML; let PHP/backend submit normally)
document.addEventListener('DOMContentLoaded', function() {
  const lowIncomeForm = document.getElementById('lowIncomeForm');
  const isPhpPage = window.location.pathname.toLowerCase().endsWith('.php');
  const hasBackendHandler = lowIncomeForm && lowIncomeForm.dataset.backend === 'true';

  if (lowIncomeForm && !isPhpPage && !hasBackendHandler) {
    lowIncomeForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showSuccessAndRedirect("Your Low-Income Certificate Request has been submitted successfully. We will verify your details and notify you once it is ready.");
      this.reset();
    });
  }
});

// no-derogatory.html
document.addEventListener('DOMContentLoaded', function() {
  const noDerogatoryForm = document.getElementById('noDerogatoryForm');
  const isPhpPage = window.location.pathname.toLowerCase().endsWith('.php');
  const hasBackendHandler = noDerogatoryForm && noDerogatoryForm.dataset.backend === 'true';

  if (noDerogatoryForm && !isPhpPage && !hasBackendHandler) {
    noDerogatoryForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showSuccessAndRedirect("Your Certificate of No Derogatory Record request has been submitted successfully.");
      this.reset();
    });
  }
});

// non-employment.html
document.addEventListener('DOMContentLoaded', function() {
  const nonEmploymentForm = document.getElementById('nonEmploymentForm');
  if (nonEmploymentForm) {
    nonEmploymentForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showSuccessAndRedirect("Your Certificate of Non-Employment request has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.");
      this.reset();
    });
  }
});

// blotter-report.html
document.addEventListener('DOMContentLoaded', function() {
  const blotterForm = document.getElementById('blotterForm');
  const isPhpPage = window.location.pathname.toLowerCase().endsWith('.php');
  const hasBackendHandler = blotterForm && blotterForm.dataset.backend === 'true';

  if (blotterForm && !isPhpPage && !hasBackendHandler) {
    blotterForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showSuccessAndRedirect("Your Blotter Report has been submitted successfully. The barangay will review your report and contact you for further action.");
      this.reset();
    });
  }
});

// soloparent-certificate.html
document.addEventListener('DOMContentLoaded', function() {
  const soloParentForm = document.getElementById('soloParentForm');
  const isPhpPage = window.location.pathname.toLowerCase().endsWith('.php');
  const hasBackendHandler = soloParentForm && soloParentForm.dataset.backend === 'true';

  if (soloParentForm && !isPhpPage && !hasBackendHandler) {
    soloParentForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showSuccessAndRedirect("Your Certificate of Solo Parent request has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.");
      this.reset();
    });
  }
});

// others.html
document.addEventListener('DOMContentLoaded', function() {
  const otherRequestForm = document.getElementById('otherRequestForm');
  if (otherRequestForm) {
    otherRequestForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showSuccessAndRedirect("Your request has been successfully submitted. The barangay will contact you soon for updates.");
      this.reset();
    });
  }
});

// === System settings (email & upload management) ===
document.addEventListener('DOMContentLoaded', function () {
  const saveBtn = document.getElementById('saveSystemSettings');
  const resetBtn = document.getElementById('resetSystemSettings');
  const statusEl = document.getElementById('systemSettingsStatus');
  const key = 'eb_system_settings_v1';

  // Inputs
  const enableSystemEmails = document.getElementById('enableSystemEmails');
  const adminAlertEmail = document.getElementById('adminAlertEmail');
  const alertHighPriority = document.getElementById('alertHighPriority');
  const alertSLAOverrun = document.getElementById('alertSLAOverrun');
  const sendUploadNotifications = document.getElementById('sendUploadNotifications');
  const adminUploadEmail = document.getElementById('adminUploadEmail');

  if (!saveBtn || !enableSystemEmails) return; // not on this page

  function isValidEmail(v) {
    return /^\S+@\S+\.\S+$/.test(v);
  }

  function showStatus(msg, kind) {
    statusEl.textContent = msg;
    statusEl.className = 'ms-auto align-self-center small text-' + (kind === 'error' ? 'danger' : kind === 'success' ? 'success' : 'muted');
    if (!msg) statusEl.className = 'ms-auto align-self-center small text-muted';
    setTimeout(() => { statusEl.textContent = ''; statusEl.className = 'ms-auto align-self-center small text-muted'; }, 4000);
  }

  function loadSettings() {
    try {
      const raw = localStorage.getItem(key);
      if (!raw) return;
      const s = JSON.parse(raw);
      enableSystemEmails.checked = !!s.enableSystemEmails;
      adminAlertEmail.value = s.adminAlertEmail || '';
      alertHighPriority.checked = !!s.alertHighPriority;
      alertSLAOverrun.checked = !!s.alertSLAOverrun;
      sendUploadNotifications.checked = !!s.sendUploadNotifications;
      adminUploadEmail.value = s.adminUploadEmail || '';
      applyDependentVisuals();
    } catch (e) {
      console.error('Failed to load settings', e);
    }
  }

  function saveSettings() {
    const aAlert = adminAlertEmail.value.trim();
    const aUpload = adminUploadEmail.value.trim();

    // If admin alert toggles are on, require admin email
    if ((alertHighPriority.checked || alertSLAOverrun.checked) && aAlert === '') {
      showStatus('Provide Admin Email for alerts when alert toggles are enabled.', 'error');
      adminAlertEmail.focus();
      return;
    }
    if (aAlert && !isValidEmail(aAlert)) {
      showStatus('Admin alert email is not valid.', 'error');
      adminAlertEmail.focus();
      return;
    }

    // If upload notifications are on, require upload admin email
    if (sendUploadNotifications.checked && aUpload === '') {
      showStatus('Provide Central Admin Email for uploads when upload notifications are enabled.', 'error');
      adminUploadEmail.focus();
      return;
    }
    if (aUpload && !isValidEmail(aUpload)) {
      showStatus('Upload admin email is not valid.', 'error');
      adminUploadEmail.focus();
      return;
    }

    const s = {
      enableSystemEmails: !!enableSystemEmails.checked,
      adminAlertEmail: aAlert,
      alertHighPriority: !!alertHighPriority.checked,
      alertSLAOverrun: !!alertSLAOverrun.checked,
      sendUploadNotifications: !!sendUploadNotifications.checked,
      adminUploadEmail: aUpload
    };
    localStorage.setItem(key, JSON.stringify(s));
    showStatus('Settings saved locally', 'success');

    // Simulate side-effects for demo
    if (s.enableSystemEmails) {
      createEphemeralToast('System emails enabled — test notification sent (demo)');
    }
  }

  function resetSettings() {
    localStorage.removeItem(key);
    enableSystemEmails.checked = false;
    adminAlertEmail.value = '';
    alertHighPriority.checked = false;
    alertSLAOverrun.checked = false;
    sendUploadNotifications.checked = false;
    adminUploadEmail.value = '';
    applyDependentVisuals();
    showStatus('Settings reset', 'muted');
  }

  function applyDependentVisuals() {
    // highlight inputs when their related toggles are enabled
    if (alertHighPriority.checked || alertSLAOverrun.checked) {
      adminAlertEmail.classList.add('border', 'border-warning');
    } else {
      adminAlertEmail.classList.remove('border', 'border-warning');
    }
    if (sendUploadNotifications.checked) {
      adminUploadEmail.classList.add('border', 'border-warning');
    } else {
      adminUploadEmail.classList.remove('border', 'border-warning');
    }
  }

  function createEphemeralToast(text) {
    const id = 'sysToast' + Date.now();
    const html = `<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080"><div id="${id}" class="toast align-items-center text-bg-primary border-0 show" role="status" aria-live="polite" aria-atomic="true"><div class="d-flex"><div class="toast-body small">${text}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div></div></div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    setTimeout(() => { const el = document.getElementById(id); if (el) el.remove(); }, 5000);
  }

  // Bind events
  saveBtn.addEventListener('click', saveSettings);
  resetBtn.addEventListener('click', resetSettings);
  alertHighPriority.addEventListener('change', applyDependentVisuals);
  alertSLAOverrun.addEventListener('change', applyDependentVisuals);
  sendUploadNotifications.addEventListener('change', applyDependentVisuals);

  loadSettings();
});

// Email-on-blur validation: require a dot (.) before leaving the field
document.addEventListener('DOMContentLoaded', function () {
  const emailInputs = Array.from(document.querySelectorAll('input[type="email"]'));
  if (!emailInputs.length) return;

  function ensureInvalidFeedback(el) {
    // add a bootstrap invalid-feedback element if not present
    const next = el.nextElementSibling;
    if (next && next.classList && next.classList.contains('invalid-feedback')) return next;
  const fb = document.createElement('div');
  fb.className = 'invalid-feedback';
  fb.textContent = 'Please enter a valid email that contains a dot (.) in the domain part.';
    el.insertAdjacentElement('afterend', fb);
    return fb;
  }

  emailInputs.forEach(function (inp) {
  const feedback = ensureInvalidFeedback(inp);

    function validateAndMaybeRefocus(e) {
      const v = (inp.value||'').trim();
      // If empty and not required, it's valid
      if (v === '' && !inp.hasAttribute('required')) {
        inp.classList.remove('is-invalid');
        return true;
      }
      // require at least one dot in the email (e.g. user@domain.tld)
      if (!v.includes('.')) {
        inp.classList.add('is-invalid');
        // move focus back to the field after blur so user can't leave
        // setTimeout is needed because calling focus() synchronously in blur may be ignored
        setTimeout(function() { try { inp.focus(); } catch(e){} }, 0);
        return false;
      }
      // valid
      inp.classList.remove('is-invalid');
      return true;
    }

    // On blur (leaving field) validate and, if invalid, refocus
    inp.addEventListener('blur', validateAndMaybeRefocus);

    // Also validate on input so feedback clears as user types
    inp.addEventListener('input', function () {
      const v = (inp.value||'').trim();
      if (v.includes('.') || (v === '' && !inp.hasAttribute('required'))) {
        inp.classList.remove('is-invalid');
      }
    });
  });
});


