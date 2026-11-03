<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login();

$user = current_user();
$pdo = db();
$errors = [];
$readFilter = static function (string $key) use (&$errors): string {
    if (!isset($_GET[$key])) {
        return '';
    }
    if (!is_string($_GET[$key])) {
        $errors[] = 'Invalid value for the ' . $key . ' filter.';
        return '';
    }
    return trim($_GET[$key]);
};
$q = $readFilter('q');
$status = $readFilter('status');
$from = $readFilter('from');
$to = $readFilter('to');
$export = $readFilter('export');
$cooperativeId = 0;

if (strlen($q) > 100) {
    $errors[] = 'Search text must be 100 characters or fewer.';
}
if ($status !== '' && !in_array($status, ['active', 'inactive', 'suspended'], true)) {
    $errors[] = 'Select a valid member status.';
}

$validDate = static function (string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};
if ($from !== '' && !$validDate($from)) {
    $errors[] = 'Enter a valid start date.';
}
if ($to !== '' && !$validDate($to)) {
    $errors[] = 'Enter a valid end date.';
}
if ($from !== '' && $to !== '' && $validDate($from) && $validDate($to) && $from > $to) {
    $errors[] = 'The start date must be on or before the end date.';
}

$cooperatives = [];
if ($user['role'] === 'system_admin') {
    $cooperatives = $pdo->query('SELECT id, name FROM cooperatives ORDER BY name')->fetchAll();
    $rawCooperativeId = $_GET['cooperative_id'] ?? '';
    if (!is_string($rawCooperativeId) || ($rawCooperativeId !== '' && !ctype_digit($rawCooperativeId))) {
        $errors[] = 'Select a valid cooperative.';
    } elseif ($rawCooperativeId !== '') {
        $cooperativeId = (int)$rawCooperativeId;
    }
}

$where = [];
$params = [];
if ($user['role'] !== 'system_admin') {
    $where[] = 'm.cooperative_id = ?';
    $params[] = (int)($user['cooperative_id'] ?? 0);
} elseif ($cooperativeId > 0) {
    $where[] = 'm.cooperative_id = ?';
    $params[] = $cooperativeId;
}
if ($q !== '') {
    $where[] = '(m.first_name LIKE ? OR m.last_name LIKE ? OR m.member_number LIKE ? OR m.phone LIKE ? OR m.email LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($status !== '' && in_array($status, ['active', 'inactive', 'suspended'], true)) {
    $where[] = 'm.status = ?';
    $params[] = $status;
}
if ($from !== '' && $validDate($from)) {
    $where[] = 'm.joined_on >= ?';
    $params[] = $from;
}
if ($to !== '' && $validDate($to)) {
    $where[] = 'm.joined_on <= ?';
    $params[] = $to;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$baseSql = ' FROM members m JOIN cooperatives c ON c.id = m.cooperative_id' . $whereSql;

if (!$errors && $export === 'csv') {
    $statement = $pdo->prepare(
        'SELECT m.member_number, m.first_name, m.last_name, c.name AS cooperative_name, '
        . 'm.phone, m.email, m.joined_on, m.status' . $baseSql
        . ' ORDER BY m.joined_on DESC, m.last_name, m.first_name'
    );
    $statement->execute($params);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="member-report-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        throw new RuntimeException('Could not open the CSV output stream.');
    }
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Member number', 'First name', 'Last name', 'Cooperative', 'Phone', 'Email', 'Joined on', 'Status'], ',', '"', '');
    while ($member = $statement->fetch()) {
        $values = [
            $member['member_number'],
            $member['first_name'],
            $member['last_name'],
            $member['cooperative_name'],
            $member['phone'] ?? '',
            $member['email'] ?? '',
            $member['joined_on'],
            $member['status'],
        ];
        foreach ($values as &$value) {
            $value = (string)$value;
            if (preg_match('/^[\s\x00-\x1F]*[=+\-@]/', $value)) {
                $value = "'" . $value;
            }
        }
        unset($value);
        fputcsv($output, $values, ',', '"', '');
    }
    fclose($output);
    exit;
}

$statusCounts = ['active' => 0, 'inactive' => 0, 'suspended' => 0];
$members = [];
if (!$errors) {
    $counts = $pdo->prepare(
        'SELECT m.status, COUNT(*) AS member_count' . $baseSql . ' GROUP BY m.status'
    );
    $counts->execute($params);
    foreach ($counts->fetchAll() as $count) {
        $statusCounts[$count['status']] = (int)$count['member_count'];
    }

    $statement = $pdo->prepare(
        'SELECT m.member_number, m.first_name, m.last_name, c.name AS cooperative_name, '
        . 'm.phone, m.email, m.joined_on, m.status' . $baseSql
        . ' ORDER BY m.joined_on DESC, m.last_name, m.first_name LIMIT 200'
    );
    $statement->execute($params);
    $members = $statement->fetchAll();
}
$total = array_sum($statusCounts);

$pageTitle = 'Member reports';
require __DIR__ . '/includes/header.php';
?>
<div class="welcome-row">
    <div>
        <h2>Member reports</h2>
        <p class="muted">Review membership by status and join date, or export matching records.</p>
    </div>
</div>
<?php if ($errors): ?>
    <div class="alert error" role="alert">
        <strong>Please review your filters:</strong>
        <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<section class="panel">
    <form method="get" class="form-grid compact-form">
        <label>Search members
            <input type="search" name="q" maxlength="100" value="<?= e($q) ?>" placeholder="Name, number, phone or email">
        </label>
        <label>Member status
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach (['active', 'inactive', 'suspended'] as $option): ?>
                    <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if ($user['role'] === 'system_admin'): ?>
            <label>Cooperative
                <select name="cooperative_id">
                    <option value="">All cooperatives</option>
                    <?php foreach ($cooperatives as $cooperative): ?>
                        <option value="<?= (int)$cooperative['id'] ?>" <?= $cooperativeId === (int)$cooperative['id'] ? 'selected' : '' ?>><?= e($cooperative['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label>Joined on or after
            <input type="date" name="from" value="<?= e($from) ?>">
        </label>
        <label>Joined on or before
            <input type="date" name="to" value="<?= e($to) ?>">
        </label>
        <div class="span-2 form-actions">
            <a class="btn ghost" href="reports.php">Clear filters</a>
            <button class="btn secondary" type="submit">Apply filters</button>
            <button class="btn primary" type="submit" name="export" value="csv">Export CSV</button>
        </div>
    </form>
</section>
<div class="stats-grid">
    <article class="stat-card"><div class="stat-top"><span class="stat-icon purple">♙</span><span class="stat-label">MATCHING MEMBERS</span></div><div class="stat-value"><?= number_format($total) ?></div><div class="stat-foot">All records matching these filters</div></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-icon green">✓</span><span class="stat-label">ACTIVE</span></div><div class="stat-value"><?= number_format($statusCounts['active']) ?></div><div class="stat-foot">Active member records</div></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-icon blue">◷</span><span class="stat-label">INACTIVE</span></div><div class="stat-value"><?= number_format($statusCounts['inactive']) ?></div><div class="stat-foot">Inactive member records</div></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-icon amber">!</span><span class="stat-label">SUSPENDED</span></div><div class="stat-value"><?= number_format($statusCounts['suspended']) ?></div><div class="stat-foot">Suspended member records</div></article>
</div>
<section class="panel">
    <div class="panel-heading">
        <div><h3>Matching member records</h3><p class="muted">Contact details are included; national IDs and birth dates are excluded.</p></div>
    </div>
    <?php if (!$members): ?>
        <div class="empty-state"><div class="empty-icon">⌕</div><h3>No matching members</h3><p>Adjust your filters and try again.</p></div>
    <?php else: ?>
        <div class="table-meta"><span><?= number_format($total) ?> matching record(s)</span><span>Showing up to 200; CSV export includes all matches</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Member</th><th>Member number</th><th>Cooperative</th><th>Contact</th><th>Joined</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <td><strong><?= e($member['first_name'] . ' ' . $member['last_name']) ?></strong></td>
                            <td><?= e($member['member_number']) ?></td>
                            <td><?= e($member['cooperative_name']) ?></td>
                            <td><?= e($member['phone'] ?: '—') ?><small class="subline"><?= e($member['email'] ?? '') ?></small></td>
                            <td><?= e($member['joined_on']) ?></td>
                            <td><span class="badge <?= e($member['status']) ?>"><?= e(ucfirst($member['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
