<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: users.php');
    exit;
}

$user = db_fetch($pdo, "SELECT * FROM user WHERE id = ?", [$id]);
if (!$user) {
    flash('error', $textbotlang['panel']['user_0001']);
    header('Location: users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_balance') {
        $amount = (int) ($_POST['amount'] ?? 0);
        if ($amount >= 1000) {
            db_query($pdo, "UPDATE user SET Balance = Balance + ? WHERE id = ?", [$amount, $id]);
            flash('success', number_format($amount) . $textbotlang['panel']['user_0002']);
        } else {
            flash('error', $textbotlang['panel']['user_0003']);
        }
    } elseif ($action === 'set_role') {
        $newRole = $_POST['new_role'] ?? 'f';
        if (in_array($newRole, ['f', 'n', 'n2', 'all'], true)) {
            db_query($pdo, "UPDATE user SET agent = ? WHERE id = ?", [$newRole, $id]);
            flash('success', $textbotlang['panel']['user_0004'] . user_role_label($newRole) . $textbotlang['panel']['user_0005']);
        }
    }

    header("Location: user.php?id=$id");
    exit;
}

$invoices = [];
$payments = [];
$referrals = [];

try {
    $invoices = db_fetchAll($pdo, "SELECT * FROM invoice WHERE id_user = ? ORDER BY time_sell DESC LIMIT 30", [$id]);
} catch (Exception $e) {
}

try {
    $payments = db_fetchAll($pdo, "SELECT * FROM Payment_report WHERE id_user = ? ORDER BY time DESC LIMIT 20", [$id]);
} catch (Exception $e) {
}

try {
    $referrals = db_fetchAll($pdo, "SELECT id, username, namecustom, Balance, register, agent FROM user WHERE affiliates = ? ORDER BY register DESC LIMIT 20", [$id]);
} catch (Exception $e) {
}

$balance = (int) ($user['Balance'] ?? 0);
$totalSpent = array_sum(array_column($invoices, 'price_product'));
$activeServices = count(array_filter($invoices, fn($inv) => ($inv['Status'] ?? '') === 'active'));
$expiredServices = count(array_filter($invoices, fn($inv) => in_array($inv['Status'] ?? '', ['end_of_time', 'end_of_volume', 'expired'])));
$paidCount = count(array_filter($payments, fn($p) => in_array($p['payment_Status'] ?? '', ['paid', 'success'])));
$convRate = count($payments) > 0 ? round($paidCount / count($payments) * 100) : 0;

$agent = $user['agent'] ?? 'f';
$isBlocked = ($user['User_Status'] ?? '') === 'block';
$fullName = $user['namecustom'] ?? '';
if ($fullName === 'none')
    $fullName = '';
$username = $user['username'] ?? '';
if ($username === 'none')
    $username = '';
$initials = mb_strtoupper(mb_substr($fullName ?: ($username ?: 'U'), 0, 1, 'UTF-8'), 'UTF-8');

$pageTitle = $fullName ?: ($username ? '@' . $username : $textbotlang['panel']['user_0006'] . $id);
$activeNav = 'users';
$showPageHead = false;
include __DIR__ . '/inc/layout_head.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px"
    class="fade-up">
    <a href="users.php" class="btn btn-ghost btn-sm"><?= icon('arrow-left', 14) ?> <?= $textbotlang['panel']['user_html_0001'] ?></a>
    <?php if ($username): ?>
        <a href="https://t.me/<?= htmlspecialchars($username) ?>" target="_blank" rel="noopener"
            class="btn btn-ghost btn-sm">
            <?= icon('eye', 13) ?> <?= $textbotlang['panel']['user_html_0002'] ?>
        </a>
    <?php endif; ?>
</div>

<div class="stats u-stats fade-up" style="margin-bottom:18px">
    <div class="stat fade-up">
        <div class="stat-label"><?= $textbotlang['panel']['user_html_0003'] ?></div>
        <div class="stat-num"><?= number_format($balance) ?><small><?= $textbotlang['panel']['user_html_0004'] ?></small></div>
        <div class="stat-meta"><?= $textbotlang['panel']['user_html_0005'] ?></div>
    </div>
    <div class="stat ok fade-up d1">
        <div class="stat-label"><?= $textbotlang['panel']['user_html_0006'] ?></div>
        <div class="stat-num">
            <?= $totalSpent >= 1_000_000
                ? number_format($totalSpent / 1_000_000, 1) . $textbotlang['panel']['user_0007']
                : number_format($totalSpent) . $textbotlang['panel']['user_0008'] ?>
        </div>
        <div class="stat-meta"><?= count($invoices) ?> <?= $textbotlang['panel']['user_html_0007'] ?></div>
    </div>
    <div class="stat warn fade-up d2">
        <div class="stat-label"><?= $textbotlang['panel']['user_html_0008'] ?></div>
        <div class="stat-num"><?= $activeServices ?></div>
        <div class="stat-meta"><?= $expiredServices ?> <?= $textbotlang['panel']['user_html_0009'] ?></div>
    </div>
    <div class="stat fade-up d3">
        <div class="stat-label"><?= $textbotlang['panel']['user_html_0010'] ?></div>
        <div class="stat-num"><?= $convRate ?>%</div>
        <div class="stat-meta"><?= $paidCount ?> <?= $textbotlang['panel']['user_html_0011'] ?> <?= count($payments) ?></div>
    </div>
</div>

<div class="profile-grid u-profile-grid">

    <div class="u-sidebar" style="display:flex;flex-direction:column;gap:12px">

        <div class="card fade-up">
            <div class="profile-head">
                <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                <div class="profile-name"><?= htmlspecialchars($fullName ?: $textbotlang['panel']['user_0009']) ?></div>
                <?php if ($username): ?>
                    <div class="profile-handle">@<?= htmlspecialchars($username) ?></div>
                <?php endif; ?>
                <div style="margin-top:10px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap">
                    <span class="tag <?= $isBlocked ? 'tag-no' : 'tag-ok' ?>">
                        <?= $isBlocked ? $textbotlang['panel']['user_0010'] : $textbotlang['panel']['user_0011'] ?>
                    </span>
                    <span class="tag <?= user_role_tag($agent) ?>">
                        <?= user_role_label($agent) ?>
                    </span>
                </div>
            </div>

            <div class="kv-list">
                <div class="kv">
                    <span class="kv-key"><?= $textbotlang['panel']['user_html_0012'] ?></span>
                    <span class="kv-val cm"><?= htmlspecialchars($user['id']) ?></span>
                </div>
                <?php if ($fullName): ?>
                    <div class="kv">
                        <span class="kv-key"><?= $textbotlang['panel']['user_html_0013'] ?></span>
                        <span class="kv-val"><?= htmlspecialchars($fullName) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($user['number']) && $user['number'] !== 'none'): ?>
                    <div class="kv">
                        <span class="kv-key"><?= $textbotlang['panel']['user_html_0014'] ?></span>
                        <span class="kv-val cm"><?= htmlspecialchars($user['number']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="kv">
                    <span class="kv-key"><?= $textbotlang['panel']['user_html_0015'] ?></span>
                    <span class="kv-val" style="color:var(--ac)"><?= number_format($balance) ?> <?= $textbotlang['panel']['user_html_0016'] ?></span>
                </div>
                <div class="kv">
                    <span class="kv-key"><?= $textbotlang['panel']['user_html_0017'] ?></span>
                    <span class="kv-val">
                        <span class="tag <?= user_role_tag($agent) ?>"><?= user_role_label($agent) ?></span>
                        <span class="cm cf"
                            style="margin-right:6px;font-size:.72rem"><?= htmlspecialchars($agent) ?></span>
                    </span>
                </div>
                <div class="kv">
                    <span class="kv-key"><?= $textbotlang['panel']['user_html_0018'] ?></span>
                    <span class="kv-val"><?= safe_date($user['register'] ?? null) ?></span>
                </div>
                <?php if (!empty($user['affiliates']) && $user['affiliates'] !== '0'): ?>
                    <div class="kv">
                        <span class="kv-key"><?= $textbotlang['panel']['user_html_0019'] ?></span>
                        <span class="kv-val cm" style="color:var(--ac)"><?= htmlspecialchars($user['affiliates']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((int) ($user['affiliatescount'] ?? 0) > 0): ?>
                    <div class="kv">
                        <span class="kv-key"><?= $textbotlang['panel']['user_html_0020'] ?></span>
                        <span class="kv-val"><?= number_format((int) $user['affiliatescount']) ?> <?= $textbotlang['panel']['user_html_0021'] ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((int) ($user['score'] ?? 0) > 0): ?>
                    <div class="kv">
                        <span class="kv-key"><?= $textbotlang['panel']['user_html_0022'] ?></span>
                        <span class="kv-val" style="color:var(--warn)">⭐ <?= number_format((int) $user['score']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($user['expire'])): ?>
                    <div class="kv">
                        <span class="kv-key"><?= $textbotlang['panel']['user_html_0023'] ?></span>
                        <span class="kv-val"
                            style="<?= is_numeric($user['expire']) && (int) $user['expire'] < time() ? 'color:var(--no)' : '' ?>">
                            <?= safe_date($user['expire']) ?>
                        </span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($user['codeInvitation'])): ?>
                    <div class="kv">
                        <span class="kv-key"><?= $textbotlang['panel']['user_html_0024'] ?></span>
                        <span class="kv-val cm"
                            style="color:var(--ac)"><?= htmlspecialchars($user['codeInvitation']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((int) ($user['message_count'] ?? 0) > 0): ?>
                    <div class="kv">
                        <span class="kv-key"><?= $textbotlang['panel']['user_html_0025'] ?></span>
                        <span class="kv-val cn"><?= number_format((int) $user['message_count']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card fade-up d1">
            <div class="card-head">
                <div class="card-title"><?= $textbotlang['panel']['user_html_0026'] ?></div>
            </div>
            <div style="padding:12px;display:flex;flex-direction:column;gap:6px">
                <button class="btn btn-primary btn-sm" style="justify-content:center" onclick="openModal('addModal')">
                    <?= icon('plus', 13) ?> <?= $textbotlang['panel']['user_html_0027'] ?>
                </button>
                <button class="btn btn-ghost btn-sm" style="justify-content:center" onclick="openModal('roleModal')">
                    <?= icon('users', 13) ?> <?= $textbotlang['panel']['user_html_0028'] ?>
                </button>
                <div style="height:1px;background:var(--bd);margin:2px 0"></div>
                <?php if ($isBlocked): ?>
                    <a href="user_action.php?action=unblock&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                        class="btn btn-ok btn-sm" style="justify-content:center" data-confirm=$textbotlang['panel']['user_0012']>
                        <?= icon('check', 13) ?> <?= $textbotlang['panel']['user_html_0029'] ?>
                    </a>
                <?php else: ?>
                    <a href="user_action.php?action=block&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                        class="btn btn-no btn-sm" style="justify-content:center" data-confirm=$textbotlang['panel']['user_0013']>
                        <?= icon('block', 13) ?> <?= $textbotlang['panel']['user_html_0030'] ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div class="u-main-col" style="display:flex;flex-direction:column;gap:16px">

        <div class="card fade-up">
            <div class="card-head">
                <div class="u-tab-bar" style="display:flex;gap:4px;background:var(--sf2);border-radius:7px;padding:3px">
                    <button class="btn btn-sm" id="tabOrders" onclick="switchTab('orders')"
                        style="background:var(--ac);color:#fff;border-radius:5px;font-size:.75rem">
                        <?= $textbotlang['panel']['user_html_0031'] ?>
                    </button>
                    <button class="btn btn-sm" id="tabPay" onclick="switchTab('pay')"
                        style="background:transparent;color:var(--mute);border-radius:5px;font-size:.75rem;border:none">
                        <?= $textbotlang['panel']['user_html_0032'] ?>
                    </button>
                    <?php if (count($referrals) > 0): ?>
                        <button class="btn btn-sm" id="tabRefs" onclick="switchTab('refs')"
                            style="background:transparent;color:var(--mute);border-radius:5px;font-size:.75rem;border:none">
                            <?= $textbotlang['panel']['user_html_0033'] ?>
                            <span
                                style="background:var(--acs);color:var(--ac);padding:1px 6px;border-radius:99px;font-size:.65rem">
                                <?= count($referrals) ?>
                            </span>
                        </button>
                    <?php endif; ?>
                </div>
                <a href="invoice.php?q=<?= urlencode($id) ?>" class="btn-link" style="font-size:.75rem"><?= $textbotlang['panel']['user_html_0034'] ?></a>
            </div>

            <div id="paneOrders">
                <div class="tbl-wrap">
                    <table class="tbl-lg">
                        <thead>
                            <tr>
                                <th><?= $textbotlang['panel']['user_html_0035'] ?></th>
                                <th><?= $textbotlang['panel']['user_html_0036'] ?></th>
                                <th><?= $textbotlang['panel']['user_html_0037'] ?></th>
                                <th><?= $textbotlang['panel']['user_html_0038'] ?></th>
                                <th><?= $textbotlang['panel']['user_html_0039'] ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty" style="padding:30px">
                                            <p><?= $textbotlang['panel']['user_html_0040'] ?></p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else:
                                $statusMap = [
                                    'active' => ['tag-ok', $textbotlang['panel']['user_0014']],
                                    'end_of_time' => ['tag-warn', $textbotlang['panel']['user_0015']],
                                    'end_of_volume' => ['tag-no', $textbotlang['panel']['user_0016']],
                                    'sendedwarn' => ['tag-warn', $textbotlang['panel']['user_0017']],
                                    'send_on_hold' => ['tag-plain', $textbotlang['panel']['user_0018']],
                                    'unpiad' => ['tag-plain', $textbotlang['panel']['user_0019']],
                                ];
                                foreach ($invoices as $inv):
                                    [$tagClass, $label] = $statusMap[$inv['Status'] ?? ''] ?? ['tag-plain', $inv['Status'] ?? '—'];
                                    ?>
                                    <tr>
                                        <td class="cs"
                                            style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                            <?= htmlspecialchars($inv['name_product'] ?? '—') ?>
                                        </td>
                                        <td class="cn cs" style="white-space:nowrap">
                                            <?= number_format((int) ($inv['price_product'] ?? 0)) ?> <span class="cf"><?= $textbotlang['panel']['user_html_0041'] ?></span>
                                        </td>
                                        <td class="cn cf"><?= htmlspecialchars($inv['Volume'] ?? '—') ?></td>
                                        <td class="cf" style="white-space:nowrap">
                                            <?= safe_date($inv['time_sell'] ?? null, 'Y/m/d') ?>
                                        </td>
                                        <td><span class="tag <?= $tagClass ?>"><?= $label ?></span></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="panePay" style="display:none">
                <div class="tbl-wrap">
                    <table class="tbl-md">
                        <thead>
                            <tr>
                                <th><?= $textbotlang['panel']['user_html_0042'] ?></th>
                                <th><?= $textbotlang['panel']['user_html_0043'] ?></th>
                                <th><?= $textbotlang['panel']['user_html_0044'] ?></th>
                                <th><?= $textbotlang['panel']['user_html_0045'] ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty" style="padding:30px">
                                            <p><?= $textbotlang['panel']['user_html_0046'] ?></p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else:
                                $methodLabels = [
                                    'cart to cart' => $textbotlang['panel']['user_0020'],
                                    'add balance by admin' => $textbotlang['panel']['user_0021'],
                                    'low balance by admin' => $textbotlang['panel']['user_0022'],
                                    'zarinpal' => $textbotlang['panel']['user_0023'],
                                    'aqayepardakht' => $textbotlang['panel']['user_0024'],
                                    'plisio' => 'Plisio',
                                    'nowpayment' => 'NowPayment',
                                    'Star Telegram' => $textbotlang['panel']['user_0025'],
                                    'Currency Rial 1' => $textbotlang['panel']['user_0026'],
                                    'Currency Rial tow' => $textbotlang['panel']['user_0027'],
                                    'Currency Rial 3' => $textbotlang['panel']['user_0028'],
                                    'arze digital offline' => $textbotlang['panel']['user_0029'],
                                ];
                                $payStatusMap = [
                                    'paid' => ['tag-ok', $textbotlang['panel']['user_0030']],
                                    'Unpaid' => ['tag-no', $textbotlang['panel']['user_0031']],
                                    'expire' => ['tag-plain', $textbotlang['panel']['user_0032']],
                                    'reject' => ['tag-no', $textbotlang['panel']['user_0033']],
                                    'waiting' => ['tag-warn', $textbotlang['panel']['user_0034']],
                                    'pending' => ['tag-warn', $textbotlang['panel']['user_0035']],
                                ];
                                foreach ($payments as $p):
                                    $payStatus = $p['payment_Status'] ?? '';
                                    [$tagClass, $label] = $payStatusMap[$payStatus] ?? ['tag-plain', $payStatus ?: '—'];
                                    $method = $methodLabels[$p['Payment_Method'] ?? ''] ?? ($p['Payment_Method'] ?? '—');
                                    ?>
                                    <tr>
                                        <td class="cn cs" style="white-space:nowrap">
                                            <?= number_format((int) ($p['price'] ?? 0)) ?> <span class="cf"><?= $textbotlang['panel']['user_html_0047'] ?></span>
                                        </td>
                                        <td style="font-size:.82rem"><?= htmlspecialchars($method) ?></td>
                                        <td class="cf" style="white-space:nowrap">
                                            <?= safe_date($p['time'] ?? null, 'Y/m/d H:i') ?>
                                        </td>
                                        <td><span class="tag <?= $tagClass ?>"><?= $label ?></span></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (count($referrals) > 0): ?>
                <div id="paneRefs" style="display:none">
                    <div class="tbl-wrap">
                        <table class="tbl-md">
                            <thead>
                                <tr>
                                    <th><?= $textbotlang['panel']['user_html_0048'] ?></th>
                                    <th><?= $textbotlang['panel']['user_html_0049'] ?></th>
                                    <th><?= $textbotlang['panel']['user_html_0050'] ?></th>
                                    <th><?= $textbotlang['panel']['user_html_0051'] ?></th>
                                    <th><?= $textbotlang['panel']['user_html_0052'] ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($referrals as $ref):
                                    $refName = $ref['namecustom'] ?? '';
                                    if ($refName === 'none')
                                        $refName = '';
                                    $refUname = $ref['username'] ?? '';
                                    if ($refUname === 'none')
                                        $refUname = '';
                                    $refAgent = $ref['agent'] ?? 'f';
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="user.php?id=<?= (int) $ref['id'] ?>" class="cm" style="color:var(--ac)">
                                                <?= htmlspecialchars($ref['id']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($refName): ?>
                                                <span class="cs"><?= htmlspecialchars(trunc($refName, 16)) ?></span>
                                            <?php elseif ($refUname): ?>
                                                <span class="cm"
                                                    style="color:var(--ac)">@<?= htmlspecialchars(trunc($refUname, 14)) ?></span>
                                            <?php else: ?>
                                                <span class="cf">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cn" style="white-space:nowrap">
                                            <?= number_format((int) ($ref['Balance'] ?? 0)) ?> <span class="cf"><?= $textbotlang['panel']['user_html_0053'] ?></span>
                                        </td>
                                        <td>
                                            <span class="tag <?= user_role_tag($refAgent) ?>">
                                                <?= user_role_label($refAgent) ?>
                                            </span>
                                        </td>
                                        <td class="cf"><?= safe_date($ref['register'] ?? null, 'm/d') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>
</div>

<div class="modal-veil" id="addModal">
    <div class="modal">
        <div class="modal-head">
            <h3><?= $textbotlang['panel']['user_html_0054'] ?></h3>
            <button class="modal-x" onclick="closeModal('addModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add_balance">
                <div class="field">
                    <label><?= $textbotlang['panel']['user_html_0055'] ?></label>
                    <input type="number" name="amount" class="input" placeholder=$textbotlang['panel']['user_0036'] min="1000" required>
                    <span class="field-hint"><?= $textbotlang['panel']['user_html_0056'] ?> <strong><?= number_format($balance) ?> <?= $textbotlang['panel']['user_html_0057'] ?></strong></span>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('plus', 13) ?> <?= $textbotlang['panel']['user_html_0058'] ?></button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')"><?= $textbotlang['panel']['user_html_0059'] ?></button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="roleModal">
    <div class="modal">
        <div class="modal-head">
            <h3><?= $textbotlang['panel']['user_html_0060'] ?></h3>
            <button class="modal-x" onclick="closeModal('roleModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_role">
                <div class="field">
                    <label><?= $textbotlang['panel']['user_html_0061'] ?></label>
                    <select name="new_role" class="select">
                        <option value="f" <?= $agent === 'f' ? 'selected' : '' ?>><?= $textbotlang['panel']['user_html_role_f'] ?></option>
                        <option value="n" <?= $agent === 'n' ? 'selected' : '' ?>><?= $textbotlang['panel']['user_html_role_n'] ?></option>
                        <option value="n2" <?= $agent === 'n2' ? 'selected' : '' ?>><?= $textbotlang['panel']['user_html_role_n2'] ?></option>
                    </select>
                    <span class="field-hint">
                        <?= $textbotlang['panel']['user_html_0062'] ?> <strong><?= user_role_label($agent) ?></strong>
                        <span class="cm" style="color:var(--mute)">(<?= htmlspecialchars($agent) ?>)</span>
                    </span>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> <?= $textbotlang['panel']['user_html_0063'] ?></button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('roleModal')"><?= $textbotlang['panel']['user_html_0064'] ?></button>
            </div>
        </form>
    </div>
</div>

<script src="js/profile.js"></script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>