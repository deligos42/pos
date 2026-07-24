<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Require permission to view audit log
require_permission('audit_log.view', 'dashboard.php');

$pageTitle = 'Audit Log';

// Filters
$user_id_filter = (int)($_GET['user_id'] ?? 0);
$action_filter = $_GET['action'] ?? '';
$entity_filter = $_GET['entity_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;

// Build query
$where_clauses = [];
$params = [];

if ($user_id_filter > 0) {
    $where_clauses[] = "a.user_id = ?";
    $params[] = $user_id_filter;
}

if (!empty($action_filter)) {
    $where_clauses[] = "a.action = ?";
    $params[] = $action_filter;
}

if (!empty($entity_filter)) {
    $where_clauses[] = "a.entity_type = ?";
    $params[] = $entity_filter;
}

if (!empty($date_from)) {
    $where_clauses[] = "DATE(a.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_clauses[] = "DATE(a.created_at) <= ?";
    $params[] = $date_to;
}

$where = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get total count
$count_query = "SELECT COUNT(*) FROM audit_log a $where";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Get data
$offset = ($page - 1) * $per_page;
$query = "SELECT a.*, u.full_name 
          FROM audit_log a
          LEFT JOIN users u ON a.user_id = u.id
          $where
          ORDER BY a.created_at DESC
          LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get available values for filters
$users = $pdo->query("SELECT DISTINCT id, full_name FROM users ORDER BY full_name")->fetchAll();
$actions = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll();
$entities = $pdo->query("SELECT DISTINCT entity_type FROM audit_log ORDER BY entity_type")->fetchAll();

include 'includes/header.php';
?>

<h2>Audit Log</h2>

<div class="card mb-3">
    <div class="card-header">
        <h5>Filters</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="user_id_filter" class="form-label">User</label>
                <select id="user_id_filter" name="user_id" class="form-select form-select-sm">
                    <option value="0">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= $user_id_filter === $user['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="action_filter" class="form-label">Action</label>
                <select id="action_filter" name="action" class="form-select form-select-sm">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?= htmlspecialchars($action['action']) ?>" 
                                <?= $action_filter === $action['action'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($action['action'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="entity_filter" class="form-label">Entity</label>
                <select id="entity_filter" name="entity_type" class="form-select form-select-sm">
                    <option value="">All Entities</option>
                    <?php foreach ($entities as $entity): ?>
                        <option value="<?= htmlspecialchars($entity['entity_type']) ?>" 
                                <?= $entity_filter === $entity['entity_type'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($entity['entity_type'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" id="date_from" name="date_from" class="form-control form-control-sm" 
                       value="<?= htmlspecialchars($date_from) ?>">
            </div>

            <div class="col-md-3">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" id="date_to" name="date_to" class="form-control form-control-sm" 
                       value="<?= htmlspecialchars($date_to) ?>">
            </div>

            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="admin/audit_log.php" class="btn btn-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="alert alert-info">
    Showing <strong><?= count($logs) ?></strong> of <strong><?= $total_records ?></strong> records
</div>

<div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>User</th>
                <th>Action</th>
                <th>Entity</th>
                <th>Entity ID</th>
                <th>Change Reason</th>
                <th>IP Address</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td>
                        <small><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></small>
                    </td>
                    <td>
                        <?= htmlspecialchars($log['full_name'] ?? 'System') ?>
                    </td>
                    <td>
                        <span class="badge bg-info"><?= htmlspecialchars($log['action']) ?></span>
                    </td>
                    <td>
                        <small><?= htmlspecialchars($log['entity_type']) ?></small>
                    </td>
                    <td>
                        <code><?= htmlspecialchars($log['entity_id'] ?? '-') ?></code>
                    </td>
                    <td>
                        <small><?= htmlspecialchars($log['change_reason'] ?? '-') ?></small>
                    </td>
                    <td>
                        <code><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></code>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-secondary" 
                                data-bs-toggle="modal" 
                                data-bs-target="#detailsModal"
                                onclick="showDetails(<?= htmlspecialchars(json_encode($log)) ?>)">
                            Details
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=1&<?= http_build_query(compact('user_id_filter', 'action_filter', 'entity_filter', 'date_from', 'date_to')) ?>">First</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query(compact('user_id_filter', 'action_filter', 'entity_filter', 'date_from', 'date_to')) ?>">Previous</a>
                </li>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(compact('user_id_filter', 'action_filter', 'entity_filter', 'date_from', 'date_to')) ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&<?= http_build_query(compact('user_id_filter', 'action_filter', 'entity_filter', 'date_from', 'date_to')) ?>">Next</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $total_pages ?>&<?= http_build_query(compact('user_id_filter', 'action_filter', 'entity_filter', 'date_from', 'date_to')) ?>">Last</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Audit Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="detailsContent"></div>
            </div>
        </div>
    </div>
</div>

<script>
function showDetails(log) {
    let html = '<dl class="row">';
    html += '<dt class="col-sm-3">Timestamp:</dt><dd class="col-sm-9">' + new Date(log.created_at).toLocaleString() + '</dd>';
    html += '<dt class="col-sm-3">User:</dt><dd class="col-sm-9">' + (log.full_name || 'System') + '</dd>';
    html += '<dt class="col-sm-3">Action:</dt><dd class="col-sm-9">' + log.action + '</dd>';
    html += '<dt class="col-sm-3">Entity Type:</dt><dd class="col-sm-9">' + log.entity_type + '</dd>';
    html += '<dt class="col-sm-3">Entity ID:</dt><dd class="col-sm-9">' + (log.entity_id || '-') + '</dd>';
    html += '<dt class="col-sm-3">Change Reason:</dt><dd class="col-sm-9">' + (log.change_reason || '-') + '</dd>';
    html += '<dt class="col-sm-3">IP Address:</dt><dd class="col-sm-9"><code>' + (log.ip_address || 'N/A') + '</code></dd>';
    html += '<dt class="col-sm-3">User Agent:</dt><dd class="col-sm-9"><small><code>' + (log.user_agent || 'N/A') + '</code></small></dd>';
    
    if (log.old_value) {
        html += '<dt class="col-sm-3">Old Value:</dt><dd class="col-sm-9"><pre>' + JSON.stringify(JSON.parse(log.old_value), null, 2) + '</pre></dd>';
    }
    if (log.new_value) {
        html += '<dt class="col-sm-3">New Value:</dt><dd class="col-sm-9"><pre>' + JSON.stringify(JSON.parse(log.new_value), null, 2) + '</pre></dd>';
    }
    
    html += '</dl>';
    document.getElementById('detailsContent').innerHTML = html;
}
</script>

<?php include 'includes/footer.php'; ?>
