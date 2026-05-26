<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$pageTitle = $textbotlang['panel']['service_0001'];
$pageLede = $textbotlang['panel']['service_0002'];
$activeNav = 'service_other';

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($search !== '') {
  $where[] = "(id_user LIKE ? OR COALESCE(username,'') LIKE ? OR COALESCE(type,'') LIKE ?)";
  $params = ["%$search%", "%$search%", "%$search%"];
}
if ($status !== '') {
  $where[] = "status = ?";
  $params[] = $status;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
  $total = db_count($pdo, "SELECT COUNT(*) FROM service_other $whereSQL", $params);
  $services = db_fetchAll($pdo, "SELECT * FROM service_other $whereSQL ORDER BY id DESC LIMIT $perPage OFFSET $offset", $params);
} catch (Exception $e) {
  $total = 0;
  $services = [];
  error_log('service.php error: ' . $e->getMessage());
}
$totalPages = max(1, (int) ceil($total / $perPage));

$typeMap = [
  'change_location' => $textbotlang['panel']['service_0003'],
  'extra_user' => $textbotlang['panel']['service_0004'],
  'extra_time_user' => $textbotlang['panel']['service_0005'],
  'extends_not_user' => $textbotlang['panel']['service_0006'],
  'extend_user' => $textbotlang['panel']['service_0007'],
  'transfertouser' => $textbotlang['panel']['service_0008']
];

$pageTitle = $textbotlang['panel']['service_0009'];
$pageLede = $textbotlang['panel']['service_0010'];
$activeNav = 'service';
include __DIR__ . '/inc/layout_head.php';
?>

<div class="card fade-up">
  <div class="toolbar">
    <div class="toolbar-title"><?= $textbotlang['panel']['service_html_0001'] ?> <small>(<?= number_format($total) ?>)</small></div>
    <form method="GET" id="srvForm" class="toolbar-end">
      <select name="status" class="select" style="width:auto" onchange="document.getElementById('srvForm').submit()">
        <option value=""><?= $textbotlang['panel']['service_html_0002'] ?></option>
        <option value="done" <?= $status === 'done' ? 'selected' : '' ?>><?= $textbotlang['panel']['service_html_0003'] ?></option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>><?= $textbotlang['panel']['service_html_0004'] ?></option>
        <option value="reject" <?= $status === 'reject' ? 'selected' : '' ?>><?= $textbotlang['panel']['service_html_0005'] ?></option>
      </select>
      <div class="search-box" style="min-width:240px">
        <?= icon('search', 14) ?>
        <input type="text" name="q" placeholder=$textbotlang['panel']['service_0011'] value="<?= htmlspecialchars($search) ?>"
          autocomplete="off">
        <button type="button" class="search-clear">✕</button>
        <button type="submit" class="search-btn"><?= $textbotlang['panel']['service_html_0006'] ?></button>
      </div>
      <?php if ($search || $status): ?>
        <a href="service.php" class="btn-link" style="font-size:.78rem"><?= $textbotlang['panel']['service_html_0007'] ?></a>
      <?php endif; ?>
    </form>
  </div>

  <div class="tbl-wrap">
    <table class="tbl-lg">
      <thead>
        <tr>
          <th>#</th>
          <th><?= $textbotlang['panel']['service_html_0008'] ?></th>
          <th><?= $textbotlang['panel']['service_html_0009'] ?></th>
          <th><?= $textbotlang['panel']['service_html_0010'] ?></th>
          <th><?= $textbotlang['panel']['service_html_0011'] ?></th>
          <th><?= $textbotlang['panel']['service_html_0012'] ?></th>
          <th><?= $textbotlang['panel']['service_html_0013'] ?></th>
          <th><?= $textbotlang['panel']['service_html_0014'] ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($services)): ?>
          <tr>
            <td colspan="8">
              <div class="empty">
                <svg class="ill" viewBox="0 0 180 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <rect x="30" y="30" width="120" height="80" rx="10" fill="var(--sf3)" />
                  <rect x="50" y="50" width="40" height="40" rx="6" fill="var(--bds)" />
                  <rect x="100" y="55" width="35" height="8" rx="4" fill="var(--bd)" />
                  <rect x="100" y="70" width="25" height="8" rx="4" fill="var(--bd)" />
                  <rect x="100" y="85" width="30" height="8" rx="4" fill="var(--bd)" />
                  <path d="M60 65 l10 10 l20-20" stroke="var(--ac)" stroke-width="3" stroke-linecap="round" fill="none" />
                </svg>
                <p><?= $search ? $textbotlang['panel']['service_0012'] : $textbotlang['panel']['service_0013'] ?></p>
              </div>
            </td>
          </tr>
        <?php else:
          $i = $offset + 1;
          foreach ($services as $s):
            $stMap = [
              'done' => ['tag-ok', $textbotlang['panel']['service_0014']],
              'pending' => ['tag-warn', $textbotlang['panel']['service_0015']],
              'reject' => ['tag-no', $textbotlang['panel']['service_0016']],
            ];
            [$cls, $lbl] = $stMap[$s['status'] ?? ''] ?? ['tag-plain', $s['status'] ?? '—'];
            $typeLabel = $typeMap[$s['type'] ?? ''] ?? ($s['type'] ?? '—');
            ?>
            <tr>
              <td class="cf"><?= $i++ ?></td>
              <td class="cm"><?= htmlspecialchars($s['id_user'] ?? '—') ?></td>
              <td>
                <?= !empty($s['username']) ? '<span class="cm" style="color:var(--ac)">@' . htmlspecialchars(trunc($s['username'], 18)) . '</span>' : '<span class="cf">—</span>' ?>
              </td>
              <td style="font-size:.82rem;color:var(--text2)"><?= htmlspecialchars($typeLabel) ?></td>
              <td class="cn" style="font-size:.82rem"><?= htmlspecialchars(trunc($s['value'] ?? '—', 20)) ?></td>
              <td class="cn cs"><?= number_format((int) ($s['price'] ?? 0)) ?> <span class="cf"><?= $textbotlang['panel']['service_html_0015'] ?></span></td>
              <td class="cf"><?= safe_date($s['time'] ?? null, 'Y/m/d') ?></td>
              <td><span class="tag <?= $cls ?>"><?= $lbl ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="tbl-foot">
    <span><?= number_format($total) ?> <?= $textbotlang['panel']['service_html_0016'] ?> <?= $page ?> <?= $textbotlang['panel']['service_html_0017'] ?> <?= $totalPages ?></span>
    <div class="pager">
      <?php $qs = fn($p) => '?q=' . urlencode($search) . '&status=' . urlencode($status) . '&page=' . $p; ?>
      <a class="<?= $page <= 1 ? 'dis' : '' ?>" href="<?= $qs(max(1, $page - 1)) ?>">‹</a>
      <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
        <a class="<?= $p === $page ? 'cur' : '' ?>" href="<?= $qs($p) ?>"><?= $p ?></a>
      <?php endfor; ?>
      <a class="<?= $page >= $totalPages ? 'dis' : '' ?>" href="<?= $qs(min($totalPages, $page + 1)) ?>">›</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>