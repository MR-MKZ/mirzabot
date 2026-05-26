<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    csrf_check_post();
    $cur = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $admin = db_fetch($pdo, "SELECT * FROM admin WHERE username = ?", [$_SESSION['admin_user']]);
    $valid = password_verify($cur, $admin['password']) || $cur === $admin['password'];

    if (!$valid) {
        flash('error', $textbotlang['panel']['settings_0001']);
    } elseif ($new !== $confirm) {
        flash('error', $textbotlang['panel']['settings_0002']);
    } elseif (strlen($new) < 6) {
        flash('error', $textbotlang['panel']['settings_0003']);
    } else {
        db_query(
            $pdo,
            "UPDATE admin SET password = ? WHERE username = ?",
            [password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), $_SESSION['admin_user']]
        );
        flash('success', $textbotlang['panel']['settings_0004']);
    }
    header('Location: settings.php?tab=security');
    exit;
}

$tab = $_GET['tab'] ?? 'appearance';

$themes = [
    'navy' => ['name' => $textbotlang['panel']['settings_0005'], 'desc' => $textbotlang['panel']['settings_0006'], 'c' => ['#0F172A', '#1E293B', '#06B6D4', '#22C55E'], 'dark' => true],
    'purple' => ['name' => $textbotlang['panel']['settings_0007'], 'desc' => $textbotlang['panel']['settings_0008'], 'c' => ['#180D2E', '#231545', '#A855F7', '#F43F5E'], 'dark' => true],
    'emerald' => ['name' => $textbotlang['panel']['settings_0009'], 'desc' => $textbotlang['panel']['settings_0010'], 'c' => ['#0A1F1C', '#132E2A', '#10B981', '#84CC16'], 'dark' => true],
    'sunset' => ['name' => $textbotlang['panel']['settings_0011'], 'desc' => $textbotlang['panel']['settings_0012'], 'c' => ['#1A0D0D', '#2A1615', '#F97316', '#FBBF24'], 'dark' => true],
    'slate' => ['name' => $textbotlang['panel']['settings_0013'], 'desc' => $textbotlang['panel']['settings_0014'], 'c' => ['#080808', '#141414', '#E2E8F0', '#22C55E'], 'dark' => true],
    'light' => ['name' => $textbotlang['panel']['settings_0015'], 'desc' => $textbotlang['panel']['settings_0016'], 'c' => ['#F1F5F9', '#FFFFFF', '#0891B2', '#16A34A'], 'dark' => false],
    'linen' => ['name' => $textbotlang['panel']['settings_0017'], 'desc' => $textbotlang['panel']['settings_0018'], 'c' => ['#FAF7F2', '#FFFFFF', '#B87333', '#5D7C4A'], 'dark' => false],
    'mint' => ['name' => $textbotlang['panel']['settings_0019'], 'desc' => $textbotlang['panel']['settings_0020'], 'c' => ['#F0FDF4', '#FFFFFF', '#166534', '#1D4ED8'], 'dark' => false],
    'lavender' => ['name' => $textbotlang['panel']['settings_0021'], 'desc' => $textbotlang['panel']['settings_0022'], 'c' => ['#FAF5FF', '#FFFFFF', '#6D28D9', '#15803D'], 'dark' => false],
];

$tabs = [
    'appearance' => ['icon' => 'settings', 'label' => $textbotlang['panel']['settings_0023']],
    'security' => ['icon' => 'block', 'label' => $textbotlang['panel']['settings_0024']],
    'system' => ['icon' => 'dashboard', 'label' => $textbotlang['panel']['settings_0025']],
];

$pageTitle = $textbotlang['panel']['settings_0026'];
$activeNav = 'settings';
$showPageHead = false;
include __DIR__ . '/inc/layout_head.php';
?>

<div style="display:flex;gap:4px;margin-bottom:18px;background:var(--sf);border:1px solid var(--bd);border-radius:10px;padding:5px;overflow-x:auto"
    class="fade-up">
    <?php foreach ($tabs as $key => $tab_data): ?>
        <a href="?tab=<?= $key ?>"
            style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:7px;font-size:.82rem;font-weight:600;white-space:nowrap;flex-shrink:0;transition:all .15s;text-decoration:none;
                  <?= $tab === $key ? 'background:var(--ac);color:#fff;box-shadow:0 0 14px var(--acg)' : 'color:var(--mute)' ?>">
            <?= icon($tab_data['icon'], 15) ?>     <?= $tab_data['label'] ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'appearance'): ?>

    <div class="card fade-up">
        <div class="card-head">
            <div>
                <div class="card-title"><?= $textbotlang['panel']['settings_html_0001'] ?></div>
                <div class="card-subtitle"><?= $textbotlang['panel']['settings_html_0002'] ?></div>
            </div>
        </div>
        <div class="card-body">
            <div
                style="font-size:.75rem;font-weight:700;color:var(--mute);letter-spacing:.08em;text-transform:uppercase;margin-bottom:10px">
                <?= $textbotlang['panel']['settings_html_0003'] ?></div>
            <div class="theme-grid" style="margin-bottom:20px">
                <?php foreach ($themes as $key => $theme):
                    if (!$theme['dark'])
                        continue; ?>
                    <div class="theme-card" data-tk="<?= $key ?>" onclick="pickTheme('<?= $key ?>')">
                        <div class="theme-preview">
                            <?php foreach ($theme['c'] as $color): ?>
                                <div style="background:<?= $color ?>"></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="theme-name"><?= htmlspecialchars($theme['name']) ?></div>
                        <div class="theme-desc"><?= htmlspecialchars($theme['desc']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div
                style="font-size:.75rem;font-weight:700;color:var(--mute);letter-spacing:.08em;text-transform:uppercase;margin-bottom:10px">
                <?= $textbotlang['panel']['settings_html_0004'] ?></div>
            <div class="theme-grid">
                <?php foreach ($themes as $key => $theme):
                    if ($theme['dark'])
                        continue; ?>
                    <div class="theme-card" data-tk="<?= $key ?>" onclick="pickTheme('<?= $key ?>')">
                        <div class="theme-preview" style="border:1px solid var(--bd)">
                            <?php foreach ($theme['c'] as $color): ?>
                                <div style="background:<?= $color ?>"></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="theme-name"><?= htmlspecialchars($theme['name']) ?></div>
                        <div class="theme-desc"><?= htmlspecialchars($theme['desc']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card fade-up d1" style="margin-top:14px">
        <div class="card-head">
            <div>
                <div class="card-title"><?= $textbotlang['panel']['settings_html_0005'] ?></div>
            </div>
        </div>
        <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap">
            <button onclick="setSidebarMode(false)" class="btn btn-ghost" id="modeExpanded"
                style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:14px 20px;flex:1;min-width:120px">
                <svg width="44" height="32" viewBox="0 0 44 32" fill="none">
                    <rect x="0" y="0" width="13" height="32" rx="3" fill="var(--sf3)" />
                    <rect x="2" y="5" width="9" height="2" rx="1" fill="var(--ac)" />
                    <rect x="2" y="10" width="9" height="2" rx="1" fill="var(--bd)" />
                    <rect x="15" y="0" width="29" height="32" rx="3" fill="var(--sf3)" />
                </svg>
                <span style="font-size:.78rem;font-weight:600"><?= $textbotlang['panel']['settings_html_0006'] ?></span>
            </button>
            <button onclick="setSidebarMode(true)" class="btn btn-ghost" id="modeCollapsed"
                style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:14px 20px;flex:1;min-width:120px">
                <svg width="44" height="32" viewBox="0 0 44 32" fill="none">
                    <rect x="0" y="0" width="7" height="32" rx="3" fill="var(--sf3)" />
                    <rect x="2" y="5" width="3" height="2" rx="1" fill="var(--ac)" />
                    <rect x="2" y="10" width="3" height="2" rx="1" fill="var(--bd)" />
                    <rect x="9" y="0" width="35" height="32" rx="3" fill="var(--sf3)" />
                </svg>
                <span style="font-size:.78rem;font-weight:600"><?= $textbotlang['panel']['settings_html_0007'] ?></span>
            </button>
        </div>
    </div>

<?php elseif ($tab === 'security'): ?>

    <div class="two-col">
        <div class="card fade-up">
            <div class="card-head">
                <div>
                    <div class="card-title"><?= $textbotlang['panel']['settings_html_0008'] ?></div>
                    <div class="card-subtitle"><?= $textbotlang['panel']['settings_html_0009'] ?></div>
                </div>
            </div>
            <form method="POST" class="card-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="change_password">
                <div style="display:flex;flex-direction:column;gap:14px">
                    <div class="field">
                        <label><?= $textbotlang['panel']['settings_html_0010'] ?></label>
                        <div style="position:relative">
                            <input type="password" name="current_password" id="pw1" class="input" required
                                autocomplete="current-password" style="padding-left:40px">
                            <button type="button" onclick="togglePw('pw1', this)"
                                style="position:absolute;left:10px;top:50%;transform:translateY(-50%);border:none;background:none;color:var(--dim);cursor:pointer">
                                <?= icon('eye', 16) ?>
                            </button>
                        </div>
                    </div>
                    <div class="field">
                        <label><?= $textbotlang['panel']['settings_html_0011'] ?></label>
                        <div style="position:relative">
                            <input type="password" name="new_password" id="pw2" class="input" minlength="6" required
                                autocomplete="new-password" style="padding-left:40px" oninput="checkPwStr(this.value)">
                            <button type="button" onclick="togglePw('pw2', this)"
                                style="position:absolute;left:10px;top:50%;transform:translateY(-50%);border:none;background:none;color:var(--dim);cursor:pointer">
                                <?= icon('eye', 16) ?>
                            </button>
                        </div>
                        <div style="height:4px;background:var(--sf3);border-radius:99px;margin-top:5px">
                            <div id="pwBar"
                                style="height:100%;width:0;border-radius:99px;transition:all .3s;background:var(--no)">
                            </div>
                        </div>
                        <span id="pwHint" class="field-hint"><?= $textbotlang['panel']['settings_html_0012'] ?></span>
                    </div>
                    <div class="field">
                        <label><?= $textbotlang['panel']['settings_html_0013'] ?></label>
                        <input type="password" name="confirm_password" class="input" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> <?= $textbotlang['panel']['settings_html_0014'] ?></button>
                </div>
            </form>
        </div>

        <div class="card fade-up d1" style="height:fit-content">
            <div class="card-head">
                <div>
                    <div class="card-title"><?= $textbotlang['panel']['settings_html_0015'] ?></div>
                </div>
                <a href="logout.php" class="btn btn-no btn-sm"><?= icon('logout', 13) ?> <?= $textbotlang['panel']['settings_html_0016'] ?></a>
            </div>
            <div class="kv-list">
                <div class="kv">
                    <span class="kv-key"><?= $textbotlang['panel']['settings_html_0017'] ?></span>
                    <span class="kv-val"><?= htmlspecialchars($_SESSION['admin_user']) ?></span>
                </div>
                <div class="kv">
                    <span class="kv-key"><?= $textbotlang['panel']['settings_html_0018'] ?></span>
                    <span class="kv-val">
                        <?= isset($_SESSION['login_time']) ? date('Y/m/d H:i:s', $_SESSION['login_time']) : '—' ?>
                    </span>
                </div>
                <div class="kv">
                    <span class="kv-key"><?= $textbotlang['panel']['settings_html_0019'] ?></span>
                    <span class="kv-val cm"><?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '—') ?></span>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'system'): ?>

    <div class="card fade-up">
        <div class="card-head">
            <div>
                <div class="card-title"><?= $textbotlang['panel']['settings_html_0020'] ?></div>
            </div>
        </div>
        <div class="kv-list">
            <?php
            $dbVer = '—';
            try {
                $dbVer = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            } catch (Exception $e) {
            }
            $sysInfo = [
                [$textbotlang['panel']['settings_0027'], 1.0],
                ['PHP', phpversion()],
                ['MySQL', $dbVer],
                [$textbotlang['panel']['settings_0028'], $_SERVER['SERVER_SOFTWARE'] ?? '—'],
                [$textbotlang['panel']['settings_0029'], $_SESSION['admin_user']],
                [$textbotlang['panel']['settings_0030'], date('Y/m/d H:i:s')],
                [$textbotlang['panel']['settings_0031'], ini_get('memory_limit')],
            ];
            foreach ($sysInfo as [$key, $value]):
                ?>
                <div class="kv">
                    <span class="kv-key"><?= $key ?></span>
                    <span class="kv-val cm" style="font-size:.78rem"><?= htmlspecialchars($value) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php endif; ?>

<script src="js/settings.js"></script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>