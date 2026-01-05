<?php
// get_request_stats.php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
include 'db.php';

// Defaults
$months = isset($_GET['months']) ? (int)$_GET['months'] : 6;
if ($months <= 0) $months = 6;

try {
    $data = [
        'total' => 0,
        'completed' => 0,
        'pending' => 0,
        'rejected' => 0,
        'monthly' => [],
        'most_requested' => []
    ];

    // Check that `requests` table exists
    $check = $conn->query("SHOW TABLES LIKE 'requests'");
    if (!$check || $check->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => "Table 'requests' not found. Please confirm your request table name."]);
        exit;
    }

    // Build optional filters from GET params: category (document_type), month (full name), year
    $filters = [];
    $filterSql = '';
    if (!empty($_GET['category']) && strtolower($_GET['category']) !== 'all') {
        $cat = $conn->real_escape_string($_GET['category']);
        $filters[] = "document_type = '" . $cat . "'";
    }
    if (!empty($_GET['month']) && strtolower($_GET['month']) !== 'all') {
        // Accept full month name (e.g., 'September') or numeric values
        $mRaw = $_GET['month'];
        if (is_numeric($mRaw)) {
            $mNum = intval($mRaw);
            if ($mNum >= 1 && $mNum <= 12) $filters[] = "MONTH(requested_at) = " . $mNum;
        } else {
            $mName = $conn->real_escape_string($mRaw);
            // Use MONTHNAME comparison
            $filters[] = "MONTHNAME(requested_at) = '" . $mName . "'";
        }
    }
    if (!empty($_GET['year']) && strtolower($_GET['year']) !== 'all') {
        $yRaw = $_GET['year'];
        if (is_numeric($yRaw)) {
            $yNum = intval($yRaw);
            $filters[] = "YEAR(requested_at) = " . $yNum;
        } else {
            // Allow 'Archived' or other non-numeric - no-op (skip filter)
        }
    }
    if (!empty($filters)) $filterSql = 'WHERE ' . implode(' AND ', $filters);

    // Totals by status (respecting filters)
    $sql = "SELECT status, COUNT(*) AS cnt FROM requests " . $filterSql . " GROUP BY status";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $s = strtolower(trim($r['status']));
            $c = (int)$r['cnt'];
            $data['total'] += $c;
            if ($s === 'completed' || $s === 'done' || $s === 'approved') $data['completed'] += $c;
            else if ($s === 'pending') $data['pending'] += $c;
            else if ($s === 'rejected' || $s === 'cancelled') $data['rejected'] += $c;
        }
    }

    // Compute additional metrics for popovers
    // Success rate
    $data['success_rate'] = ($data['total'] > 0) ? round(($data['completed'] / $data['total']) * 100, 1) : 0;

    // Completed processing time breakdown (uses given_at when available)
    $data['completed_time'] = [ 'within_24h' => 0, 'within_48h' => 0, 'within_72h' => 0, 'avg_hours' => null ];
    $timeSql = "SELECT 
        SUM(CASE WHEN TIMESTAMPDIFF(HOUR, requested_at, given_at) <= 24 THEN 1 ELSE 0 END) AS w24,
        SUM(CASE WHEN TIMESTAMPDIFF(HOUR, requested_at, given_at) > 24 AND TIMESTAMPDIFF(HOUR, requested_at, given_at) <= 48 THEN 1 ELSE 0 END) AS w48,
        SUM(CASE WHEN TIMESTAMPDIFF(HOUR, requested_at, given_at) > 48 AND TIMESTAMPDIFF(HOUR, requested_at, given_at) <= 72 THEN 1 ELSE 0 END) AS w72,
        AVG(NULLIF(TIMESTAMPDIFF(HOUR, requested_at, given_at), 0)) AS avg_hrs
        FROM requests WHERE (LOWER(status) IN ('completed','done','approved'))";
    // append filters if present (do not apply category/month/year filters here: these metrics are global)
    $resTime = $conn->query($timeSql);
    if ($resTime) {
        $r = $resTime->fetch_assoc();
        if ($r) {
            $data['completed_time']['within_24h'] = (int)$r['w24'];
            // include cumulative counts so UI shows processed within 48 including <=24? We'll present as separate buckets
            $data['completed_time']['within_48h'] = (int)$r['w48'];
            $data['completed_time']['within_72h'] = (int)$r['w72'];
            $data['completed_time']['avg_hours'] = $r['avg_hrs'] !== null ? round((float)$r['avg_hrs'], 1) : null;
        }
    }

    // Pending breakdown by status (other statuses grouped)
    $pendingBreak = [];
    $resPending = $conn->query("SELECT status, COUNT(*) AS cnt FROM requests WHERE LOWER(status) = 'pending' OR (LOWER(status) NOT IN ('completed','done','approved','rejected','cancelled') ) GROUP BY status ORDER BY cnt DESC LIMIT 10");
    if ($resPending) {
        while ($r = $resPending->fetch_assoc()) {
            $pendingBreak[$r['status']] = (int)$r['cnt'];
        }
    }
    $data['pending_breakdown'] = $pendingBreak;

    // Cancellation reasons if such a column exists (cancellation_reason or cancel_reason)
    $cancelReasons = [];
    $colCheck = $conn->query("SHOW COLUMNS FROM requests LIKE 'cancellation_reason'");
    $colName = '';
    if ($colCheck && $colCheck->num_rows > 0) $colName = 'cancellation_reason';
    else {
        $colCheck2 = $conn->query("SHOW COLUMNS FROM requests LIKE 'cancel_reason'");
        if ($colCheck2 && $colCheck2->num_rows > 0) $colName = 'cancel_reason';
    }
    if ($colName) {
        $sqlCR = "SELECT " . $colName . " AS reason, COUNT(*) AS cnt FROM requests WHERE LOWER(status) IN ('rejected','cancelled') GROUP BY " . $colName . " ORDER BY cnt DESC";
        $resCR = $conn->query($sqlCR);
        if ($resCR) {
            while ($r = $resCR->fetch_assoc()) {
                $cancelReasons[$r['reason']] = (int)$r['cnt'];
            }
        }
    }
    $data['cancellation_reasons'] = $cancelReasons;
    // Cancellation rate
    $data['cancellation_rate'] = ($data['total'] > 0) ? round(($data['rejected'] / $data['total']) * 100, 1) : 0;

    // Average pending wait time in hours
    $data['avg_pending_wait_hours'] = null;
    $resWait = $conn->query("SELECT AVG(TIMESTAMPDIFF(HOUR, requested_at, NOW())) AS avg_wait FROM requests WHERE LOWER(status) = 'pending'");
    if ($resWait) {
        $rw = $resWait->fetch_assoc();
        if ($rw && $rw['avg_wait'] !== null) $data['avg_pending_wait_hours'] = round((float)$rw['avg_wait'], 1);
    }

    // Monthly counts for last $months months
    $monthly = [];
    // Monthly counts for last $months months (apply filters in subquery)
    $monthly = [];
    // Build a base WHERE for date range + filters
    $dateWhere = "requested_at >= DATE_SUB(CURDATE(), INTERVAL " . intval($months) . " MONTH)";
    $fullWhere = $dateWhere;
    if (!empty($filterSql)) {
        // strip leading WHERE from $filterSql and append
        $fullWhere .= ' AND ' . substr($filterSql, 6);
    }

    $stmt = $conn->prepare("SELECT YEAR(requested_at) AS yr, MONTH(requested_at) AS m, COUNT(*) AS cnt
        FROM requests
        WHERE " . $fullWhere . "
        GROUP BY YEAR(requested_at), MONTH(requested_at)
        ORDER BY YEAR(requested_at), MONTH(requested_at)");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $monthName = date('M', mktime(0,0,0,(int)$r['m'],1,(int)$r['yr']));
            $monthly[$monthName] = (int)$r['cnt'];
        }
        $stmt->close();
    }

    // Ensure we have entries for each of the last $months months (chronological)
    $labels = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $ts = strtotime("-{$i} months");
        $label = date('M', $ts);
        $labels[] = $label;
        if (!isset($monthly[$label])) $monthly[$label] = 0;
    }
    // Preserve order
    $ordered = [];
    foreach ($labels as $lab) $ordered[$lab] = $monthly[$lab];
    $data['monthly'] = $ordered;

    // Compute per-month top document types (monthly_details) for tooltip/popover
    $monthly_details = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $ts = strtotime("-{$i} months");
        $yr = (int)date('Y', $ts);
        $mo = (int)date('n', $ts);
        $lab = date('M', $ts);

        $stmt2 = $conn->prepare("SELECT document_type AS name, COUNT(*) AS cnt FROM requests WHERE YEAR(requested_at)=? AND MONTH(requested_at)=? GROUP BY document_type ORDER BY cnt DESC LIMIT 6");
        if ($stmt2) {
            $stmt2->bind_param('ii', $yr, $mo);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            $arr = [];
            while ($r2 = $res2->fetch_assoc()) {
                $arr[] = ['name' => $r2['name'], 'count' => (int)$r2['cnt']];
            }
            $stmt2->close();
            $monthly_details[$lab] = $arr;
        } else {
            $monthly_details[$lab] = [];
        }
    }
    $data['monthly_details'] = $monthly_details;
    // Include all known document types (if available) so client can show names even when months have no requests
    $all_docs = [];
    $check_docs = $conn->query("SHOW TABLES LIKE 'document_types'");
    if ($check_docs && $check_docs->num_rows > 0) {
        $resDocs = $conn->query("SELECT name FROM document_types ORDER BY name ASC");
        if ($resDocs) {
            while ($r = $resDocs->fetch_assoc()) {
                $all_docs[] = $r['name'];
            }
        }
    }
    // Fallback list of common document types for installations without a populated document_types table
    $fallback_docs = [
        'Certificate of Residency',
        'Barangay ID',
        'Business Permit',
        'Indigency Certificate',
        'Good Moral Certificate',
        'Burial Assistance',
        'Low Income Certificate',
        'No Derogatory',
        'Certificate of Residency (Multiple Copies)',
        'Non-Employment Certificate',
        'Soloparent Certificate'
    ];
    // Merge and keep unique, preserving existing DB names first
    $all_docs = array_values(array_unique(array_merge($all_docs, $fallback_docs)));
    $data['all_document_types'] = $all_docs;

    // Ensure each month's details contains a consistent list of document types
    $limit = 6;
    foreach ($data['monthly_details'] as $mkey => $items) {
        $current = $items;
        $needed = min($limit, max(1, count($all_docs)));
        $existingNames = array_column($current, 'name');

        // If there are fewer items than needed, append missing document types (count 0)
        if (count($current) < $needed) {
            foreach ($all_docs as $docName) {
                if (in_array($docName, $existingNames)) continue;
                $current[] = ['name' => $docName, 'count' => 0];
                if (count($current) >= $needed) break;
            }
        }

        // If unexpectedly empty (no docs at all), still fill using the first N all_docs
        if (empty($current)) {
            $filled = [];
            for ($i = 0; $i < min($limit, count($all_docs)); $i++) {
                $filled[] = ['name' => $all_docs[$i], 'count' => 0];
            }
            $current = $filled;
        }

        $data['monthly_details'][$mkey] = $current;
    }

    // If debug flag is set, include raw per-month rows for inspection
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        $debugRows = [];
        $sqlDebug = "SELECT YEAR(requested_at) AS yr, MONTH(requested_at) AS mo, DATE_FORMAT(requested_at, '%b') AS mon, document_type, COUNT(*) AS cnt
                     FROM requests
                     WHERE requested_at >= DATE_SUB(CURDATE(), INTERVAL " . intval($months) . " MONTH)
                     GROUP BY YEAR(requested_at), MONTH(requested_at), document_type
                     ORDER BY YEAR(requested_at), MONTH(requested_at), cnt DESC";
        $resDebug = $conn->query($sqlDebug);
        if ($resDebug) {
            while ($r = $resDebug->fetch_assoc()) {
                $debugRows[] = $r;
            }
        }
        $data['debug_rows'] = $debugRows;
    }

    // Most requested document types
    $sql2 = "SELECT document_type AS name, COUNT(*) AS cnt FROM requests " . $filterSql . " GROUP BY document_type ORDER BY cnt DESC LIMIT 6";
    $res2 = $conn->query($sql2);
    if ($res2) {
        while ($r = $res2->fetch_assoc()) {
            $data['most_requested'][] = ['name' => $r['name'], 'count' => (int)$r['cnt']];
        }
    }

    // Build a breakdown for the reports table (document_type x month x year)
    $tableRows = [];
    $sqlTable = "SELECT document_type AS document_type, COUNT(*) AS total,
        SUM(CASE WHEN LOWER(status) IN ('completed','done','approved') THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN LOWER(status) = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN LOWER(status) IN ('rejected','cancelled') THEN 1 ELSE 0 END) AS cancelled,
        MONTHNAME(requested_at) AS month, YEAR(requested_at) AS year
        FROM requests " . $filterSql . "
        GROUP BY document_type, YEAR(requested_at), MONTH(requested_at)
        ORDER BY YEAR(requested_at) DESC, MONTH(requested_at) DESC, total DESC
        LIMIT 1000";
    $resTable = $conn->query($sqlTable);
    if ($resTable) {
        while ($r = $resTable->fetch_assoc()) {
            $tableRows[] = [
                'document_type' => $r['document_type'],
                'total' => (int)$r['total'],
                'approved' => (int)$r['approved'],
                'pending' => (int)$r['pending'],
                'cancelled' => (int)$r['cancelled'],
                'month' => $r['month'],
                'year' => (int)$r['year']
            ];
        }
    }
    $data['table_rows'] = $tableRows;

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>
