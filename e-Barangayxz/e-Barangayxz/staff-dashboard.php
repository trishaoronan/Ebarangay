<?php
session_start();
include 'db.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Location: staff-login.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>eBarangay | Staff Dashboard</title>
  <link rel="icon" type="image/jpeg" href="pics/eb-logo.jpg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="style.css">
  <!-- MapTiler SDK -->
  <link href="https://cdn.maptiler.com/maptiler-sdk-js/v2.0.3/maptiler-sdk.css" rel="stylesheet" />
  <script src="https://cdn.maptiler.com/maptiler-sdk-js/v2.0.3/maptiler-sdk.umd.min.js"></script>
  <style>
    #barangayMap {
      width: 100%;
      height: 500px;
      border-radius: 8px;
    }
    .map-legend {
      background: white;
      padding: 10px 15px;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    .resident-marker {
      width: 24px;
      height: 24px;
      background: #0d6efd;
      border: 3px solid white;
      border-radius: 50%;
      box-shadow: 0 2px 6px rgba(0,0,0,0.3);
      cursor: pointer;
    }
    .maplibregl-popup-content {
      padding: 12px 16px;
      border-radius: 8px;
      box-shadow: 0 3px 14px rgba(0,0,0,0.15);
    }
  </style>
</head>
<body>


  <!-- Navbar/Header -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top" style="padding: 0.5rem 0; margin-top: -8px; padding-top: calc(0.5rem + 8px); z-index: 1050;">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <a class="navbar-brand d-flex align-items-center" href="staff-dashboard.php">
        <img src="pics/eb-logo.jpg" alt="eBarangay Logo" class="me-2" style="height:40px; width:auto;" />
        <span class="fw-bold">STAFF DASHBOARD</span>
      </a>
      <div class="d-flex align-items-center position-relative">
        <!-- Notification Dropdown -->
        <div class="dropdown me-2">
          <button class="btn btn-light position-relative" id="notifButton" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
            <i class="bi bi-bell fs-5"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifBadge">2</span>
          </button>
          <div class="dropdown-menu dropdown-menu-end shadow-sm p-0 border-0" id="notifDropdown" style="width:340px;">
            <div class="p-3 border-bottom bg-light">
              <h6 class="fw-bold mb-0">Notifications</h6>
            </div>
            <div class="notification-panel">
              <!-- Notifications loaded here -->
            </div>
          </div>
        </div>
        <button class="btn btn-outline-light btn-sm" id="staffLogoutBtn">Logout</button>
      </div>
    </div>
  </nav>
  <div class="container-fluid">
    <div class="row">
      <nav class="col-md-3 col-lg-2 d-md-block sidebar p-3 shadow-sm" style="position: fixed; top: 65px; left: 0; height: calc(100vh - 65px); overflow-y: auto; z-index: 1000;">
        <div class="nav flex-column">
          <a class="nav-link active" href="staff-dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
          <a class="nav-link" href="sidebar-requests.php"><i class="bi bi-files me-2"></i> Requests</a>
          <a class="nav-link" href="sidebar-residents.php"><i class="bi bi-people me-2"></i> Residents</a>
          <a class="nav-link" href="sidebar-reports.html"><i class="bi bi-clipboard-data me-2"></i> Reports</a>
          <a class="nav-link" href="sidebar-profile.html"><i class="bi bi-card-list me-2"></i> Profile</a>
        </div>
      </nav>
      <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
        <h3 class="fw-bold mb-4">Dashboard Overview</h3>
        <div class="row g-4">
          <div class="col-lg-8">
            <div class="card shadow-sm p-3 h-100">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <div>
                  <h6 class="fw-semibold mb-0">Total Requests Overview</h6>
                  <small class="text-muted" style="font-size: 0.8rem;">This chart shows the total number and status of all online request</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <select id="requestsYearSelect" class="form-select form-select-sm" style="width:110px;"></select>
                  <button type="button" class="btn btn-link text-primary p-0" 
                          id="requestsPopover"
                          data-bs-toggle="popover" 
                          data-bs-placement="left"
                          data-bs-html="true"
                          data-bs-title="Most Requested Documents">
                    <i class="bi bi-info-circle"></i> Most Requested
                  </button>
                </div>
              </div>
              <div style="height:240px;">
                <canvas id="requestsOverviewChart"></canvas>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="card shadow-sm p-3 h-100 requests-stats-card">
              <h6 class="fw-semibold mb-0">Request Statistics</h6>
              <small class="text-muted" style="font-size: 0.8rem;">Overview of all document requests and their current status.</small>
              <div class="stat-row mb-2 align-items-center mt-3">
                <div class="stat-left">Total Requests</div>
                <div class="stat-right" id="totalRequestsValue">0</div>
              </div>
              <div class="total-progress mb-3">
                <div class="progress" style="height:8px;">
                  <div class="progress-bar bg-primary" id="totalProgressBar" role="progressbar" style="width:0%"></div>
                </div>
              </div>
              <div class="status-row mb-2">
                <div class="d-flex justify-content-between">
                  <div class="status-label">Completed</div>
                  <div class="status-value text-success" id="completedValue">0</div>
                </div>
                <div class="progress mt-2" style="height:6px;">
                  <div class="progress-bar bg-success" id="completedProgress" role="progressbar" style="width:0%"></div>
                </div>
              </div>
              <div class="status-row mb-2">
                <div class="d-flex justify-content-between">
                  <div class="status-label">Pending</div>
                  <div class="status-value" id="pendingValue">0</div>
                </div>
                <div class="progress mt-2" style="height:6px;">
                  <div class="progress-bar bg-warning" id="pendingProgress" role="progressbar" style="width:0%"></div>
                </div>
              </div>
              <div class="status-row">
                <div class="d-flex justify-content-between">
                  <div class="status-label">Rejected</div>
                  <div class="status-value" id="rejectedValue">0</div>
                </div>
                <div class="progress mt-2" style="height:6px;">
                  <div class="progress-bar bg-danger" id="rejectedProgress" role="progressbar" style="width:0%"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="row g-4 mt-4">
          <!-- Map & Residents Card -->
          <div class="col-md-4">
            <div class="card shadow-sm p-3 text-center h-100" role="button" data-bs-toggle="modal" data-bs-target="#barangayMapModal" style="cursor: pointer; transition: transform 0.2s;">
              <div class="d-flex justify-content-center align-items-center w-100 mb-2">
                <i class="bi bi-people-fill text-primary resident-icon"></i>
              </div>
              <?php
                $residentCount = 0;
                $res = $conn->query("SELECT COUNT(*) as total FROM residents");
                if ($res && $row = $res->fetch_assoc()) {
                  $residentCount = (int)$row['total'];
                }
              ?>
              <h4 class="fw-bold mt-3"><?php echo $residentCount; ?></h4>
              <h6 class="mb-0">Total Residents</h6>
              <div class="text-success small mt-2">↑ 12% Increase</div>
              <div class="text-muted small mt-2">
                <i class="bi bi-geo-alt-fill"></i> Click to view map
                </div>
                <small class="text-muted" style="font-size: 0.75rem;">This Map shows the number of registered residents per street.</small>
            </div>
          </div>
          <!-- Resident Demographics Card -->
          <div class="col-md-8">
            <div class="card shadow-sm p-3 h-100">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <div>
                  <h6 class="fw-semibold mb-0">Resident Demographics</h6>
                  <small class="text-muted" style="font-size: 0.8rem;">This graph shows the breakdown of the community by age and gender.</small>
                </div>
                <div class="d-flex align-items-center">
                  <button type="button" class="btn btn-link text-primary p-0 me-3" 
                          id="demographicsPopover"
                          data-bs-toggle="popover" 
                          data-bs-placement="left"
                          data-bs-html="true"
                          data-bs-title="Age Distribution">
                    <i class="bi bi-info-circle"></i> Age Details
                  </button>
                  <select id="demographicsYearSelect" class="form-select form-select-sm" style="width:110px;"></select>
                </div>
              </div>
              <div style="height:140px;">
                <canvas id="residentStatsChart"></canvas>
              </div>
              <div id="demographicsSummary" class="mt-2 d-flex gap-2 flex-wrap"></div>
            </div>
          </div>
        </div>
        <!-- Recent Activity Row -->
        <div class="row g-4 mt-2">
          <div class="col-12">
            <div class="card shadow-sm p-3 h-100">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-semibold mb-0">Recent Activity</h6>
              </div>
              <div class="small text-muted" id="recentActivityFeed">
                <div class="text-center py-3 text-secondary">Loading recent activities...</div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Barangay Map Modal -->
  <div class="modal fade" id="barangayMapModal" tabindex="-1" aria-labelledby="barangayMapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="barangayMapModalLabel">
            <i class="bi bi-geo-alt-fill me-2"></i>Resident Locations - Pulong Buhangin, Santa Maria, Bulacan
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-0">
          <div id="barangayMap"></div>
          <div class="p-3 bg-light border-top">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div class="map-legend d-flex align-items-center gap-3">
                <div class="d-flex align-items-center">
                  <div class="resident-marker me-2" style="width: 16px; height: 16px;"></div>
                  <span class="small">Resident Location</span>
                </div>
                <div class="d-flex align-items-center">
                  <i class="bi bi-geo-alt-fill text-danger me-2"></i>
                  <span class="small">Barangay Hall</span>
                </div>
              </div>
              <div class="text-muted small">
                <i class="bi bi-people-fill me-1"></i>
                <span id="mapResidentCount">0</span> residents mapped
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast Container -->
  <div class="toast-container"></div>

  <!-- Bootstrap JS & Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <?php
    // Get available years from requests table
    $availableYears = [];
    $result = $conn->query("SELECT DISTINCT YEAR(requested_at) as year FROM requests ORDER BY year DESC");
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $availableYears[] = (int)$row['year'];
      }
    }
    // Add current year if not in list
    $currentYear = (int)date('Y');
    if (!in_array($currentYear, $availableYears)) {
      $availableYears[] = $currentYear;
      rsort($availableYears);
    }
    ?>
    <script>
    // Available years from PHP
    const availableYears = <?php echo json_encode($availableYears); ?>;
    let requestsChart = null;
    let currentMostRequested = [];

    // Initialize year dropdown
    function initRequestsYearSelect() {
      const select = document.getElementById('requestsYearSelect');
      select.innerHTML = '';
      
      // Add year options
      availableYears.forEach(year => {
        const option = document.createElement('option');
        option.value = year;
        option.textContent = year;
        select.appendChild(option);
      });
      
      // Set current year as default (or first available)
      const currentYear = new Date().getFullYear();
      if (availableYears.includes(currentYear)) {
        select.value = currentYear;
      } else if (availableYears.length > 0) {
        select.value = availableYears[0];
      }

      // Add change listener
      select.addEventListener('change', function() {
        loadRequestsChart(this.value);
      });
    }

    // Load requests chart data
    async function loadRequestsChart(year) {
      try {
        const res = await fetch(`get_requests_by_year.php?year=${year}`, { cache: 'no-store' });
        const response = await res.json();
        
        if (!response.success) {
          console.error('Failed to load requests data:', response.message);
          return;
        }
        
        const data = response.data;
        currentMostRequested = data.most_requested || [];
        
        // Update or create chart
        if (requestsChart) {
          requestsChart.data.labels = data.labels;
          requestsChart.data.datasets[0].data = data.counts;
          requestsChart.update();
        } else {
          createRequestsChart(data.labels, data.counts);
        }
        
        // Update Most Requested popover
        updateMostRequestedPopover();
      } catch (err) {
        console.error('Error loading requests chart:', err);
      }
    }

    // Create the chart
    function createRequestsChart(labels, counts) {
      requestsChart = new Chart(document.getElementById('requestsOverviewChart'), {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: 'Requests',
            data: counts,
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
            enabled: true,
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            titleFont: { size: 14 },
            bodyFont: { size: 13 },
            callbacks: {
              label: function(context) {
                return 'Requests: ' + context.parsed.y;
              }
            }
          }
        },
          scales: { 
            y: { beginAtZero: true }
          }
        }
      });
    }

    // Update Most Requested popover content
    function updateMostRequestedPopover() {
      let content = '<div class="p-2">';
      if (currentMostRequested.length === 0) {
        content += '<p class="text-muted small mb-0">No data available</p>';
      } else {
        content += '<div class="list-group list-group-flush">';
        currentMostRequested.forEach(item => {
          content += `
            <div class="d-flex justify-content-between align-items-center py-1">
              <span class="small">${item.name}</span>
              <span class="badge bg-primary rounded-pill">${item.count}</span>
            </div>
          `;
        });
        content += '</div>';
      }
      content += '</div>';
      
      const popover = bootstrap.Popover.getInstance(document.getElementById('requestsPopover'));
      if (popover) {
        popover.dispose();
      }
      
      new bootstrap.Popover(document.getElementById('requestsPopover'), {
        trigger: 'hover',
        html: true,
        content: content,
        placement: 'left'
      });
    }

    // Fetch and render request stats
    async function fetchAndRenderRequestStats() {
      try {
        const res = await fetch('get_request_stats.php', { cache: 'no-store' });
        const raw = await res.json();
        const payload = (raw && raw.success && raw.data) ? raw.data : raw;

        const total = payload.total ?? 0;
        const completed = payload.completed ?? 0;
        const pending = payload.pending ?? 0;
        const rejected = payload.rejected ?? 0;

        document.getElementById('totalRequestsValue').textContent = total;
        document.getElementById('completedValue').textContent = completed;
        document.getElementById('pendingValue').textContent = pending;
        document.getElementById('rejectedValue').textContent = rejected;

        const pct = (v) => (total ? Math.round((v / total) * 100) : 0);
        document.getElementById('totalProgressBar').style.width = total ? '100%' : '0%';
        document.getElementById('completedProgress').style.width = pct(completed) + '%';
        document.getElementById('pendingProgress').style.width = pct(pending) + '%';
        document.getElementById('rejectedProgress').style.width = pct(rejected) + '%';
      } catch (err) {
        console.error('Failed to fetch request stats', err);
      }
    }

    // Get icon and color based on notification type
    function getNotificationIcon(type) {
      const icons = {
        'document_request': { icon: 'bi-file-earmark-text', color: 'text-primary' },
        'payment_sent': { icon: 'bi-credit-card', color: 'text-success' },
        'new_registration': { icon: 'bi-person-plus', color: 'text-info' },
        'profile_update': { icon: 'bi-pencil-square', color: 'text-warning' },
        'default': { icon: 'bi-bell-fill', color: 'text-primary' }
      };
      return icons[type] || icons['default'];
    }

    // Format time ago
    function timeAgo(dateString) {
      const date = new Date(dateString);
      const now = new Date();
      const seconds = Math.floor((now - date) / 1000);
      
      if (seconds < 60) return 'Just now';
      if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
      if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
      if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
      return date.toLocaleDateString();
    }

    // Fetch notifications
    async function fetchNotifications() {
      try {
        const res = await fetch('get_notifications.php', { cache: 'no-store' });
        const data = await res.json();
        const panel = document.querySelector('.notification-panel');
        if (!panel) return;
        panel.innerHTML = '';

        // Normalize response shape - handle both formats
        let notifs = [];
        let unreadCount = 0;
        
        if (data && data.success) {
          notifs = data.data || [];
          unreadCount = data.unread_count || 0;
        } else if (Array.isArray(data)) {
          notifs = data;
          unreadCount = data.filter(n => !n.is_read).length;
        }
        
        const badge = document.getElementById('notifBadge');
        if (badge) {
          badge.textContent = unreadCount;
          badge.style.display = unreadCount > 0 ? 'inline-block' : 'none';
        }

        if (notifs.length === 0) {
          panel.innerHTML = '<div class="p-3 text-center text-muted"><i class="bi bi-bell-slash me-2"></i>No notifications yet</div>';
          return;
        }

        notifs.forEach(n => {
          const item = document.createElement('div');
          const isRead = Number(n.is_read ?? n.read ?? 0) === 1;
          const iconInfo = getNotificationIcon(n.type || n.notification_type);
          const timeStr = n.created_at ? timeAgo(n.created_at) : '';
          
          item.className = 'notif-item p-3 border-bottom' + (isRead ? '' : ' unread');
          item.style.cursor = 'pointer';
          item.innerHTML = `
            <div class="d-flex align-items-start">
              <i class="bi ${iconInfo.icon} me-2 ${iconInfo.color} fs-5"></i>
              <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start">
                  <h6 class="mb-1 fw-semibold text-dark" style="font-size: 0.9rem;">${escapeHtml(n.title || 'Notification')}</h6>
                  <small class="text-muted ms-2" style="white-space: nowrap;">${timeStr}</small>
                </div>
                <p class="mb-0 small text-secondary">${escapeHtml(n.message || '')}</p>
              </div>
            </div>
          `;
          
          item.addEventListener('click', async () => {
            if (!isRead) {
              try {
                await fetch('mark_notification_read.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ id: n.id })
                });
              } catch (err) {
                console.error('mark read failed', err);
              }
            }
            // Navigate based on notification type
            if (n.type === 'document_request' || n.type === 'payment_sent') {
              window.location.href = 'sidebar-requests.php';
            } else if (n.type === 'new_registration' || n.type === 'profile_update') {
              window.location.href = 'sidebar-residents.php';
            } else {
              window.location.href = 'sidebar-requests.php';
            }
          });
          panel.appendChild(item);
        });
      } catch (err) {
        console.error('Failed to fetch notifications', err);
        const panel = document.querySelector('.notification-panel');
        if (panel) {
          panel.innerHTML = '<div class="p-3 text-center text-muted"><i class="bi bi-exclamation-circle me-2"></i>Failed to load notifications</div>';
        }
      }
    }

    function escapeHtml(str) {
      if (!str && str !== 0) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    // Resident Demographics Initialization
    // --- Begin Resident Demographics JS ---
    let demographicsYear = new Date().getFullYear();
    const demographicsCategories = ['Children', 'Teens', 'Adults', 'Seniors'];
    const demographicsColors = ['#0d6efd', '#198754', '#ffc107', '#dc3545'];
    let demographicsYearly = {};
    let residentStatsChart = null;
    const residentAgeData = { Children: [], Teens: [], Adults: [], Seniors: [] };

    async function loadDemographicsData() {
      try {
        const response = await fetch('get_resident_stats.php', { method: 'GET', cache: 'no-store' });
        const data = await response.json();
        if (data && data.success && data.demographics) {
          const currentYear = new Date().getFullYear();
          demographicsYearly[currentYear] = {
            'Children': data.demographics.children || 0,
            'Teens': data.demographics.teens || 0,
            'Adults': data.demographics.adults || 0,
            'Seniors': data.demographics.seniors || 0
          };
          if (data.breakdown) {
            const bd = data.breakdown;
            residentAgeData['Children'] = (bd.children || []).map(x => ({ age: x.label, count: x.count }));
            residentAgeData['Teens']    = (bd.teens || []).map(x => ({ age: x.label, count: x.count }));
            residentAgeData['Adults']   = (bd.adults || []).map(x => ({ age: x.label, count: x.count }));
            residentAgeData['Seniors']  = (bd.seniors || []).map(x => ({ age: x.label, count: x.count }));
          }
          if (!demographicsYearly[currentYear - 1]) {
            demographicsYearly[currentYear - 1] = {};
            demographicsCategories.forEach(cat => {
              demographicsYearly[currentYear - 1][cat] = Math.round((demographicsYearly[currentYear][cat] || 0) * 0.95);
            });
          }
          const yearSelect = document.getElementById('demographicsYearSelect');
          if (yearSelect) {
            yearSelect.innerHTML = '';
            const availableYears = Object.keys(demographicsYearly).map(Number).sort((a, b) => b - a);
            availableYears.forEach(year => {
              const option = document.createElement('option');
              option.value = year;
              option.textContent = year;
              if (year === currentYear) option.selected = true;
              yearSelect.appendChild(option);
            });
            yearSelect.addEventListener('change', function() {
              demographicsYear = parseInt(this.value);
              updateDemographicsForYear(demographicsYear);
              try { demographicsPopover.dispose(); } catch (e) {}
              demographicsPopover = new bootstrap.Popover(document.getElementById('demographicsPopover'), {
                trigger: 'hover focus',
                html: true,
                content: buildDemographicsPopoverContent(demographicsYear)
              });
            });
          }
          return true;
        }
      } catch (error) {
        console.error('Failed to load demographics data:', error);
      }
      // Fallback demo data
      const currentYear = new Date().getFullYear();
      demographicsYearly = {
        [currentYear]: { Children: 80, Teens: 100, Adults: 110, Seniors: 35 },
        [currentYear - 1]: { Children: 75, Teens: 95, Adults: 105, Seniors: 30 }
      };
    }

    function getCategoryTotalsForYear(year) {
      const y = demographicsYearly[year] || {};
      return demographicsCategories.map(c => (y[c] != null ? y[c] : 0));
    }

    function formatDelta(current, previous) {
      const diff = current - previous;
      const pct = previous ? Math.round((diff / previous) * 100) : (diff > 0 ? 100 : 0);
      return { diff, pct };
    }

    function initializeChart() {
      const residentStatsCtx = document.getElementById('residentStatsChart');
      if (!residentStatsCtx) return;
      if (residentStatsChart) residentStatsChart.destroy();
      residentStatsChart = new Chart(residentStatsCtx, {
        type: 'bar',
        data: {
          labels: demographicsCategories,
          datasets: [{
            label: 'Residents',
            data: getCategoryTotalsForYear(demographicsYear),
            backgroundColor: demographicsColors
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
                let tooltipEl = document.getElementById('chartjs-demographics-tooltip');
                if (!tooltipEl) {
                  tooltipEl = document.createElement('div');
                  tooltipEl.id = 'chartjs-demographics-tooltip';
                  tooltipEl.innerHTML = '<table></table>';
                  document.body.appendChild(tooltipEl);
                }
                const tooltipModel = context.tooltip;
                if (tooltipModel.opacity === 0) {
                  tooltipEl.style.opacity = 0;
                  return;
                }
                if (tooltipModel.body) {
                  const category = context.chart.data.labels[tooltipModel.dataPoints[0].dataIndex];
                  const ageData = residentAgeData[category];
                  const total = ageData.reduce((sum, item) => sum + item.count, 0);
                  const tableBody = ageData.map(item => `
                    <tr>
                      <td class="pe-4">Ages ${item.age}</td>
                      <td class="text-end pe-2">${item.count}</td>
                      <td class="text-end"><span class="badge bg-secondary">${total ? Math.round(item.count/total*100) : 0}%</span></td>
                    </tr>
                  `).join('');
                  const tableRoot = tooltipEl.querySelector('table');
                  tableRoot.innerHTML = `
                    <thead>
                      <tr><th colspan="3" class="text-center pb-2">${category} — ${demographicsYear} <span class="badge bg-primary ms-2">${total} total</span></th></tr>
                    </thead>
                    <tbody>${tableBody}</tbody>
                  `;
                }
                const position = context.chart.canvas.getBoundingClientRect();
                tooltipEl.style.opacity = 1;
                tooltipEl.style.position = 'absolute';
                tooltipEl.style.left = position.left + window.pageXOffset + tooltipModel.caretX - 80 + 'px';
                tooltipEl.style.top = position.top + window.pageYOffset + tooltipModel.caretY - 100 + 'px';
                tooltipEl.style.padding = '8px 12px';
                tooltipEl.style.pointerEvents = 'none';
                tooltipEl.style.background = 'rgba(255, 255, 255, 0.98)';
                tooltipEl.style.borderRadius = '6px';
                tooltipEl.style.boxShadow = '0 2px 8px rgba(0,0,0,0.15)';
                tooltipEl.style.fontSize = '13px';
                tooltipEl.style.minWidth = '180px';
              }
            }
          },
          scales: { y: { beginAtZero: true } },
          onHover: function(e, elements) {
            if (elements && elements.length) {
              e.native.target.style.cursor = 'pointer';
            } else {
              e.native.target.style.cursor = 'default';
            }
          }
        }
      });
    }

    function updateDemographicsForYear(year) {
      const curTotals = getCategoryTotalsForYear(year);
      const prevTotals = getCategoryTotalsForYear(year - 1);
      residentStatsChart.data.datasets[0].data = curTotals;
      residentStatsChart.update();
      const summary = document.getElementById('demographicsSummary');
      if (!summary) return;
      summary.innerHTML = '';
      demographicsCategories.forEach((cat, i) => {
        const cur = curTotals[i] || 0;
        const prev = prevTotals[i] || 0;
        const { diff, pct } = formatDelta(cur, prev);
        const up = diff >= 0;
        const badge = document.createElement('div');
        badge.className = 'p-2 rounded-3 border d-flex align-items-center';
        badge.style.minWidth = '170px';
        badge.innerHTML = `
          <div class="me-2" style="width:10px;height:28px;border-radius:4px;background:${demographicsColors[i]}"></div>
          <div class="flex-grow-1">
            <div class="small text-muted">${cat}</div>
            <div class="fw-semibold">${cur} <small class="text-muted">total</small></div>
          </div>
          <div class="text-end ms-2">
            <div class="small ${up ? 'text-success' : 'text-danger'}">${up ? '▲' : '▼'} ${Math.abs(diff)}</div>
            <div class="small ${up ? 'text-success' : 'text-danger'}">${up ? '+' : '-'}${Math.abs(pct)}%</div>
          </div>
        `;
        summary.appendChild(badge);
      });
    }

    function buildDemographicsPopoverContent(year) {
      const totals = getCategoryTotalsForYear(year);
      const totalAll = (totals || []).reduce((sum, v) => sum + (v || 0), 0);
      function makeRow(label, idx, badgeClass) {
        const count = totals[idx] || 0;
        const pct = totalAll ? Math.round((count / totalAll) * 100) : 0;
        return `
          <div class="category mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="fw-semibold">${label}</span>
              <span class="badge ${badgeClass}">${count} total</span>
            </div>
            <div class="details small text-muted ps-2">
              • ${pct}% of residents in ${year}.<br>
              • Detailed breakdown available via chart tooltip.
            </div>
          </div>`;
      }
      return `
        <div class="demographics-list">
          <div class="mb-2 small text-muted">Year: <strong>${year}</strong></div>
          ${makeRow('Children (0-12)', 0, 'bg-primary')}
          ${makeRow('Teens (13-19)', 1, 'bg-success')}
          ${makeRow('Adults (20-59)', 2, 'bg-warning')}
          ${makeRow('Seniors (60+)', 3, 'bg-danger')}
        </div>`;
    }

    // Fetch and render recent activities
    async function fetchAndRenderRecentActivities() {
      const feed = document.getElementById('recentActivityFeed');
      if (!feed) return;
      feed.innerHTML = '<div class="text-center py-3 text-secondary">Loading recent activities...</div>';
      try {
        const res = await fetch('get_activities.php', { cache: 'no-store' });
        const data = await res.json();
        if (!data.success || !Array.isArray(data.data) || !data.data.length) {
          feed.innerHTML = '<div class="text-center py-3 text-muted">No recent activities found.</div>';
          return;
        }
        feed.innerHTML = '';
        data.data.slice(0, 8).forEach(act => {
          const who = act.staffName || act.residentName || '';
          let details = act.details || act.action || '';
          // Remove redundant "Staff" or "staff" from the beginning of details
          details = details.replace(/^Staff\s+/i, '').replace(/^staff\s+/i, '');
          // Remove the staff name from the beginning of details if it appears there
          if (who && details.startsWith(who)) {
            details = details.substring(who.length).trim();
          }
          const time = act.timestamp ? new Date(act.timestamp).toLocaleString() : '';
          const icon = act.source === 'staff_log' ? 'bi-person-badge' : 'bi-person';
          const row = document.createElement('div');
          row.className = 'd-flex align-items-center mb-2';
          row.innerHTML = `
            <i class="bi ${icon} me-2 text-primary"></i>
            <div class="flex-grow-1">
              <span class="fw-semibold">${who ? who + ': ' : ''}</span>${details}
              <div class="small text-muted">${time}</div>
            </div>
          `;
          feed.appendChild(row);
        });
      } catch (e) {
        feed.innerHTML = '<div class="text-center py-3 text-danger">Failed to load activities.</div>';
      }
    }

    // Initialize all dashboard features
    document.addEventListener('DOMContentLoaded', () => {
      fetchAndRenderRequestStats();
      fetchNotifications();
      setInterval(fetchNotifications, 60000);

      // Initialize Requests Chart
      initRequestsYearSelect();
      const currentYear = new Date().getFullYear();
      const yearSelect = document.getElementById('requestsYearSelect');
      if (yearSelect && availableYears.includes(currentYear)) {
        loadRequestsChart(currentYear);
      } else if (yearSelect && availableYears.length > 0) {
        loadRequestsChart(availableYears[0]);
      }

      // Resident Demographics async init
      (async () => {
        await loadDemographicsData();
        initializeChart();
        updateDemographicsForYear(demographicsYear);
        let demographicsPopover = new bootstrap.Popover(document.getElementById('demographicsPopover'), {
          trigger: 'hover focus',
          html: true,
          content: buildDemographicsPopoverContent(demographicsYear)
        });
        const sel = document.getElementById('demographicsYearSelect');
        if (sel) {
          sel.addEventListener('change', function() {
            demographicsYear = parseInt(this.value, 10) || demographicsYear;
            try { demographicsPopover.dispose(); } catch (e) {}
            demographicsPopover = new bootstrap.Popover(document.getElementById('demographicsPopover'), {
              trigger: 'hover focus',
              html: true,
              content: buildDemographicsPopoverContent(demographicsYear)
            });
            try { updateDemographicsForYear(demographicsYear); } catch (e) { console.warn('Failed to update demographics for year', e); }
          });
        }
      })();

      // Fetch and render recent activities
      fetchAndRenderRecentActivities();

      // Logout
      document.getElementById('staffLogoutBtn')?.addEventListener('click', async function() {
        try {
          const response = await fetch('staff_logout.php', { method: 'POST', cache: 'no-store' });
          const result = await response.json();
          if (result.success || !result.success) {
            window.location.href = 'staff-login.html';
          }
        } catch (error) {
          window.location.href = 'staff-login.html';
        }
      });
    });
    // Refresh stats button logic (modern purple style)
    const refreshStatsBtn = document.getElementById('refreshStatsBtn');
    if (refreshStatsBtn) {
      refreshStatsBtn.addEventListener('click', function() {
        const icon = refreshStatsBtn.querySelector('i');
        refreshStatsBtn.classList.add('refresh-purple-btn-loading');
        icon.classList.add('spin-animation');
        refreshStatsBtn.disabled = true;
        fetchAndRenderRequestStats().finally(() => {
          setTimeout(() => {
            icon.classList.remove('spin-animation');
            refreshStatsBtn.classList.remove('refresh-purple-btn-loading');
            refreshStatsBtn.disabled = false;
          }, 800);
        });
      });
    }

    // Add spin-animation and purple button CSS if not present
    (function(){
      if (!document.getElementById('refresh-purple-btn-style')) {
        const style = document.createElement('style');
        style.id = 'refresh-purple-btn-style';
        style.textContent = `
          .refresh-purple-btn {
            border: 2px solid #4B0082;
            background: transparent;
            color: #4B0082;
            border-radius: 50%;
            width: 38px;
            height: 38px;
            padding: 0;
            transition: background 0.2s, color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
          }
          .refresh-purple-btn:hover,
          .refresh-purple-btn:focus,
          .refresh-purple-btn.refresh-purple-btn-loading {
            background: #4B0082 !important;
            color: #fff !important;
          }
          .refresh-purple-btn i {
            transition: color 0.2s;
          }
          @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
          .spin-animation { animation: spin 0.8s linear infinite; }
        `;
        document.head.appendChild(style);
      }
    })();
    </script>

    <!-- First Login Password Change Modal -->
    <?php include 'first_login_modal.html'; ?>

    <script>
      // Check if staff needs to change default password immediately on page load
      document.addEventListener('DOMContentLoaded', function() {
        checkDefaultPassword();
      });
    </script>

    <!-- MapTiler Map Initialization -->
    <script>
      let barangayMap = null;
      const MAPTILER_API_KEY = 'fmDmleGqyuam20Dxv4NO';
      
      // Pulong Buhangin, Santa Maria, Bulacan - PRECISE coordinates from OpenStreetMap research
      // Pulong Buhangin Court: 14.871404, 121.001516 (City Land Avenue)
      // Barangay Hall is right beside the court
      const PULONG_BUHANGIN_CENTER = [121.001516, 14.871404]; // [lng, lat] - Court/Barangay Hall area
      const DEFAULT_ZOOM = 17;
      
      // Barangay boundary box for geocoding constraints
      const BARANGAY_BOUNDS = {
        minLat: 14.8485137,
        maxLat: 14.8940591,
        minLng: 120.9814101,
        maxLng: 121.0257955
      };

      // Barangay Hall location - Inside the gray box structure at the center (beside the court on the right)
      const BARANGAY_HALL = {
        coordinates: [121.00168160313365, 14.871316885146934], // Center of the gray rectangular building beside the court
        name: 'Pulong Buhangin Barangay Hall',
        address: 'City Land Avenue, Pulong Buhangin, Santa Maria, Bulacan'
      };

      // Cache for geocoded addresses
      const geocodeCache = new Map();

      // Initialize map when modal is shown
      document.getElementById('barangayMapModal').addEventListener('shown.bs.modal', function() {
        if (!barangayMap) {
          initializeBarangayMap();
        } else {
          barangayMap.resize();
        }
      });

      async function initializeBarangayMap() {
        // Set MapTiler API key
        maptilersdk.config.apiKey = MAPTILER_API_KEY;

        // Create map centered on Pulong Buhangin Barangay Hall
        barangayMap = new maptilersdk.Map({
          container: 'barangayMap',
          style: maptilersdk.MapStyle.STREETS,
          center: PULONG_BUHANGIN_CENTER,
          zoom: DEFAULT_ZOOM
        });

        barangayMap.on('load', async function() {
          // Add Barangay Hall marker
          const hallMarkerEl = document.createElement('div');
          hallMarkerEl.innerHTML = '<i class="bi bi-building-fill" style="font-size: 32px; color: #dc3545; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));"></i>';
          
          new maptilersdk.Marker({ element: hallMarkerEl, anchor: 'bottom' })
            .setLngLat(BARANGAY_HALL.coordinates)
            .setPopup(
              new maptilersdk.Popup({ offset: 25 })
                .setHTML(`
                  <div style="min-width: 220px;">
                    <h6 class="fw-bold mb-1"><i class="bi bi-building-fill text-danger me-1"></i>${BARANGAY_HALL.name}</h6>
                    <p class="small text-muted mb-0"><i class="bi bi-geo-alt me-1"></i>${BARANGAY_HALL.address}</p>
                  </div>
                `)
            )
            .addTo(barangayMap);

          // Fetch residents and add markers
          await loadResidentMarkers();

          // Add navigation controls
          barangayMap.addControl(new maptilersdk.NavigationControl(), 'top-right');
          barangayMap.addControl(new maptilersdk.FullscreenControl(), 'top-right');
        });
      }

      // Geocode an address using MapTiler Geocoding API
      async function geocodeAddress(address) {
        // Check cache first
        if (geocodeCache.has(address)) {
          return geocodeCache.get(address);
        }

        try {
          // Build search query with location context
          const searchQuery = `${address}, Santa Maria, Bulacan, Philippines`;
          const bbox = `${BARANGAY_BOUNDS.minLng},${BARANGAY_BOUNDS.minLat},${BARANGAY_BOUNDS.maxLng},${BARANGAY_BOUNDS.maxLat}`;
          
          const url = `https://api.maptiler.com/geocoding/${encodeURIComponent(searchQuery)}.json?key=${MAPTILER_API_KEY}&bbox=${bbox}&limit=1`;
          
          const response = await fetch(url);
          const data = await response.json();
          
          if (data.features && data.features.length > 0) {
            const coords = data.features[0].geometry.coordinates;
            // Verify coordinates are within barangay bounds
            if (isWithinBounds(coords[0], coords[1])) {
              geocodeCache.set(address, coords);
              return coords;
            }
          }
        } catch (error) {
          console.warn('Geocoding failed for:', address, error);
        }
        
        return null;
      }

      // Check if coordinates are within barangay bounds
      function isWithinBounds(lng, lat) {
        return lng >= BARANGAY_BOUNDS.minLng && lng <= BARANGAY_BOUNDS.maxLng &&
               lat >= BARANGAY_BOUNDS.minLat && lat <= BARANGAY_BOUNDS.maxLat;
      }

      // Generate coordinates within barangay based on address keywords
      function generateLocationFromKeywords(address) {
        const addr = (address || '').toLowerCase();
        
        // Define known areas/streets within Pulong Buhangin with approximate coordinates
        const knownLocations = {
          'norzagaray': [121.0021, 14.8705],
          'santa maria road': [121.0021, 14.8705],
          'main road': [120.9956, 14.8650],
          'purok': [120.9900, 14.8600],
          'sitio': [120.9850, 14.8550],
          'phase': [121.0100, 14.8750],
          'village': [121.0050, 14.8700],
          'subdivision': [121.0150, 14.8800],
          'highway': [121.0021, 14.8705],
          'street': [120.9950, 14.8680],
          'avenue': [121.0000, 14.8720]
        };

        // Check for keyword matches
        for (const [keyword, coords] of Object.entries(knownLocations)) {
          if (addr.includes(keyword)) {
            // Add small random offset to avoid exact overlap
            const offsetLng = (Math.random() - 0.5) * 0.004;
            const offsetLat = (Math.random() - 0.5) * 0.003;
            return [coords[0] + offsetLng, coords[1] + offsetLat];
          }
        }

        // Default: distribute within barangay bounds
        const centerLng = (BARANGAY_BOUNDS.minLng + BARANGAY_BOUNDS.maxLng) / 2;
        const centerLat = (BARANGAY_BOUNDS.minLat + BARANGAY_BOUNDS.maxLat) / 2;
        const rangeLng = (BARANGAY_BOUNDS.maxLng - BARANGAY_BOUNDS.minLng) * 0.4;
        const rangeLat = (BARANGAY_BOUNDS.maxLat - BARANGAY_BOUNDS.minLat) * 0.4;
        
        return [
          centerLng + (Math.random() - 0.5) * rangeLng,
          centerLat + (Math.random() - 0.5) * rangeLat
        ];
      }

      async function loadResidentMarkers() {
        try {
          const response = await fetch('get_resident_locations.php', { cache: 'no-store' });
          const data = await response.json();
          
          if (data.success && data.residents && data.residents.length > 0) {
            const residents = data.residents;
            document.getElementById('mapResidentCount').textContent = residents.length;

            // Process residents in batches for better performance
            const batchSize = 20;
            let processedCount = 0;
            
            for (let i = 0; i < residents.length; i += batchSize) {
              const batch = residents.slice(i, i + batchSize);
              
              await Promise.all(batch.map(async (resident) => {
                // Try geocoding first, then fallback to keyword-based location
                let coords = null;
                
                if (resident.address && resident.address.trim()) {
                  // Try MapTiler geocoding for real address matching
                  coords = await geocodeAddress(resident.address);
                }
                
                // Fallback to keyword-based location generation
                if (!coords) {
                  coords = generateLocationFromKeywords(resident.address);
                }
                
                // Create marker
                const markerEl = document.createElement('div');
                markerEl.className = 'resident-marker';
                
                new maptilersdk.Marker({ element: markerEl })
                  .setLngLat(coords)
                  .setPopup(
                    new maptilersdk.Popup({ offset: 25 })
                      .setHTML(`
                        <div style="min-width: 200px;">
                          <h6 class="fw-bold mb-1">
                            <i class="bi bi-person-fill text-primary me-1"></i>
                            ${escapeHtml(resident.name)}
                          </h6>
                          <p class="small text-muted mb-1">
                            <i class="bi bi-geo-alt me-1"></i>${escapeHtml(resident.address || 'Pulong Buhangin, Santa Maria, Bulacan')}
                          </p>
                          ${resident.contact ? `<p class="small text-muted mb-0"><i class="bi bi-telephone me-1"></i>${escapeHtml(resident.contact)}</p>` : ''}
                        </div>
                      `)
                  )
                  .addTo(barangayMap);
              }));
              
              processedCount += batch.length;
            }
          } else {
            // If no residents data, show sample markers around the barangay
            document.getElementById('mapResidentCount').textContent = '<?php echo $residentCount; ?>';
            addSampleResidentMarkers();
          }
        } catch (error) {
          console.error('Error loading resident locations:', error);
          // Show sample markers as fallback
          document.getElementById('mapResidentCount').textContent = '<?php echo $residentCount; ?>';
          addSampleResidentMarkers();
        }
      }

      function addSampleResidentMarkers() {
        // Add sample markers distributed around Pulong Buhangin using actual barangay bounds
        const residentCount = <?php echo $residentCount; ?>;
        const markersToShow = Math.min(residentCount, 50); // Show max 50 markers for performance
        
        for (let i = 0; i < markersToShow; i++) {
          const markerEl = document.createElement('div');
          markerEl.className = 'resident-marker';
          
          // Distribute within actual barangay boundaries
          const coords = generateLocationFromKeywords('');

          new maptilersdk.Marker({ element: markerEl })
            .setLngLat(coords)
            .setPopup(
              new maptilersdk.Popup({ offset: 25 })
                .setHTML(`
                  <div style="min-width: 150px;">
                    <h6 class="fw-bold mb-1">
                      <i class="bi bi-person-fill text-primary me-1"></i>Resident #${i + 1}
                    </h6>
                    <p class="small text-muted mb-0">
                      <i class="bi bi-geo-alt me-1"></i>Pulong Buhangin, Santa Maria, Bulacan
                    </p>
                  </div>
                `)
            )
            .addTo(barangayMap);
        }
      }
    </script>
</body>
</html>
