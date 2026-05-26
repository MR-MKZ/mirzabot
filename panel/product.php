<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
  csrf_check_post();
  $name = trim($_POST['name_product'] ?? '');
  if ($name === '') {
    flash('error', $textbotlang['panel']['product_0001']);
    header('Location: product.php');
    exit;
  }
  if (db_count($pdo, "SELECT COUNT(*) FROM product WHERE name_product = ?", [$name])) {
    flash('error', $textbotlang['panel']['product_0002']);
    header('Location: product.php');
    exit;
  }
  $code = bin2hex(random_bytes(2));
  try {
    db_query(
      $pdo,
      "INSERT INTO product (name_product,code_product,price_product,Volume_constraint,Service_time,Location,agent,data_limit_reset,note,category,hide_panel,one_buy_status) VALUES (?,?,?,?,?,?,?,'no_reset',?,?,'{}','0')",
      [$name, $code, (int) ($_POST['price_product'] ?? 0), (int) ($_POST['volume_product'] ?? 0), (int) ($_POST['time_product'] ?? 0), $_POST['namepanel'] ?? '', $_POST['agent_product'] ?? '', $_POST['note_product'] ?? '', $_POST['cetegory_product'] ?? '']
    );
    flash('success', $textbotlang['panel']['product_0003'] . $name . $textbotlang['panel']['product_0004']);
  } catch (Exception $e) {
    flash('error', $textbotlang['panel']['product_0005'] . $e->getMessage());
  }
  header('Location: product.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
  csrf_check_post();
  $pid = (int) ($_POST['edit_id'] ?? 0);
  $name = trim($_POST['name_product'] ?? '');
  if ($pid && $name !== '') {
    try {
      db_query(
        $pdo,
        "UPDATE product SET name_product=?,price_product=?,Volume_constraint=?,Service_time=?,Location=?,agent=?,note=?,category=? WHERE id=?",
        [$name, (int) ($_POST['price_product'] ?? 0), (int) ($_POST['volume_product'] ?? 0), (int) ($_POST['time_product'] ?? 0), $_POST['namepanel'] ?? '', $_POST['agent_product'] ?? '', $_POST['note_product'] ?? '', $_POST['cetegory_product'] ?? '', $pid]
      );
      flash('success', $textbotlang['panel']['product_0006']);
    } catch (Exception $e) {
      flash('error', $textbotlang['panel']['product_0007'] . $e->getMessage());
    }
  }
  header('Location: product.php');
  exit;
}

if (isset($_GET['delete'])) {
  csrf_check_get();
  db_query($pdo, "DELETE FROM product WHERE id = ?", [(int) $_GET['delete']]);
  flash('success', $textbotlang['panel']['product_0008']);
  header('Location: product.php');
  exit;
}

$panels = [];
try {
  $panels = db_fetchAll($pdo, "SELECT * FROM marzban_panel");
} catch (Exception $e) {
}
$products = db_fetchAll($pdo, "SELECT * FROM product ORDER BY id");

$pageTitle = $textbotlang['panel']['product_0009'];
$pageLede = $textbotlang['panel']['product_0010'];
$activeNav = 'product';
include __DIR__ . '/inc/layout_head.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px" class="fade-up">
  <div style="font-size:.85rem;color:var(--mute)"><?= count($products) ?> <?= $textbotlang['panel']['product_html_0001'] ?></div>
  <button class="btn btn-primary" onclick="openModal('addModal')"><?= icon('plus', 14) ?> <?= $textbotlang['panel']['product_html_0002'] ?></button>
</div>

<div class="card fade-up d1">
  <?php if (empty($products)): ?>
    <div class="empty" style="padding:60px 20px">
      <svg class="ill" viewBox="0 0 200 160" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="40" y="30" width="120" height="100" rx="12" fill="var(--surface-3)" />
        <rect x="56" y="50" width="88" height="12" rx="6" fill="var(--border-strong)" />
        <rect x="56" y="72" width="60" height="8" rx="4" fill="var(--border)" />
        <rect x="56" y="90" width="72" height="8" rx="4" fill="var(--border)" />
        <rect x="56" y="108" width="44" height="8" rx="4" fill="var(--border)" />
        <circle cx="155" cy="125" r="22" fill="var(--accent-s)" stroke="var(--accent)" stroke-width="2" />
        <path d="M147 125h16M155 117v16" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" />
      </svg>
      <p><?= $textbotlang['panel']['product_html_0003'] ?></p>
      <button class="btn btn-primary" style="margin-top:14px" onclick="openModal('addModal')"><?= icon('plus', 14) ?>
        <?= $textbotlang['panel']['product_html_0004'] ?></button>
    </div>
  <?php else: ?>
    <div class="toolbar">
      <div class="toolbar-title"><?= $textbotlang['panel']['product_html_0005'] ?> <small>(<?= count($products) ?>)</small></div>
      <div class="search-box" style="min-width:220px">
        <?= icon('search', 14) ?>
        <input type="text" placeholder=$textbotlang['panel']['product_0011'] data-filter="prodTbl">
        <button type="button" class="search-clear">✕</button>
      </div>
    </div>
    <div class="tbl-wrap">
      <table id="prodTbl" class="tbl-xl">
        <thead>
          <tr>
            <th>#</th>
            <th><?= $textbotlang['panel']['product_html_0006'] ?></th>
            <th><?= $textbotlang['panel']['product_html_0007'] ?></th>
            <th><?= $textbotlang['panel']['product_html_0008'] ?></th>
            <th><?= $textbotlang['panel']['product_html_0009'] ?></th>
            <th><?= $textbotlang['panel']['product_html_0010'] ?></th>
            <th><?= $textbotlang['panel']['product_html_0011'] ?></th>
            <th><?= $textbotlang['panel']['product_html_0012'] ?></th>
            <th><?= $textbotlang['panel']['product_html_0013'] ?></th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1;
          foreach ($products as $p): ?>
            <tr>
              <td class="cf"><?= $i++ ?></td>
              <td class="cs"><?= htmlspecialchars($p['name_product'] ?? '') ?></td>
              <td class="cn cs"><?= number_format((int) ($p['price_product'] ?? 0)) ?> <span class="cf"><?= $textbotlang['panel']['product_html_0014'] ?></span></td>
              <td class="cn"><?= htmlspecialchars($p['Volume_constraint'] ?? '—') ?> <span class="cf">GB</span></td>
              <td class="cn"><?= htmlspecialchars($p['Service_time'] ?? '—') ?> <span class="cf"><?= $textbotlang['panel']['product_html_0015'] ?></span></td>
              <td class="cf"><?= htmlspecialchars(trunc($p['Location'] ?? '—', 16)) ?></td>
              <td><?php if (!empty($p['category'])): ?><span
                    class="tag tag-info"><?= htmlspecialchars($p['category']) ?></span><?php else: ?><span
                    class="cf">—</span><?php endif; ?></td>
              <td class="cm" style="font-size:.72rem"><?= htmlspecialchars($p['code_product'] ?? '') ?></td>
              <td>
                <div style="display:flex;gap:5px">
                  <button class="btn btn-ghost btn-sm btn-icon" title=$textbotlang['panel']['product_0012']
                    onclick="openEditModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">
                    <?= icon('edit', 13) ?>
                  </button>
                  <a href="product.php?delete=<?= (int) $p['id'] ?>&_csrf=<?= csrf_token() ?>"
                    class="btn btn-no btn-sm btn-icon" title=$textbotlang['panel']['product_0013']
                    data-confirm=sprintf($textbotlang['panel']['product_0014'], $p['name_product'])>
                    <?= icon('trash', 13) ?>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="modal-veil" id="addModal">
  <div class="modal">
    <div class="modal-head">
      <h3><?= $textbotlang['panel']['product_html_0016'] ?></h3>
      <button class="modal-x" onclick="closeModal('addModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-grid">
          <div class="field full">
            <label><?= $textbotlang['panel']['product_html_0017'] ?></label>
            <input type="text" name="name_product" class="input" placeholder=$textbotlang['panel']['product_0015'] required>
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['product_html_0018'] ?></label>
            <input type="number" name="price_product" class="input" placeholder=$textbotlang['panel']['product_0016'] min="0">
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['product_html_volume_gb'] ?></label>
            <input type="number" name="volume_product" class="input" placeholder=$textbotlang['panel']['product_0017'] min="0">
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['product_html_0019'] ?></label>
            <input type="number" name="time_product" class="input" placeholder=$textbotlang['panel']['product_0018'] min="0">
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['product_html_0020'] ?></label>
            <input type="text" name="cetegory_product" class="input" placeholder=$textbotlang['panel']['product_0019']>
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['product_html_0021'] ?></label>
            <select name="namepanel" class="select">
              <option value=""><?= $textbotlang['panel']['product_html_0022'] ?></option>
              <?php foreach ($panels as $pl): ?>
                <option value="<?= htmlspecialchars($pl['name_panel'] ?? $pl['id']) ?>">
                  <?= htmlspecialchars($pl['name_panel'] ?? $pl['id']) ?>
                </option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['product_html_0023'] ?></label>
            <select name="agent_product" class="select">
              <option value="f"><?= $textbotlang['panel']['product_html_0024'] ?></option>
              <option value="n"><?= $textbotlang['panel']['product_html_0025'] ?></option>
              <option value="n2"><?= $textbotlang['panel']['product_html_0026'] ?></option>
            </select>
          </div>
          <div class="field full">
            <label><?= $textbotlang['panel']['product_html_0027'] ?></label>
            <input type="text" name="note_product" class="input" placeholder=$textbotlang['panel']['product_0020']>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn btn-primary"><?= icon('plus', 13) ?> <?= $textbotlang['panel']['product_html_0028'] ?></button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')"><?= $textbotlang['panel']['product_html_0029'] ?></button>
      </div>
    </form>
  </div>
</div>

<div class="modal-veil" id="editModal">
  <div class="modal">
    <div class="modal-head">
      <h3><?= $textbotlang['panel']['product_html_0030'] ?></h3>
      <button class="modal-x" onclick="closeModal('editModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="edit_id" id="edit_id">
        <div class="form-grid">
          <div class="field full">
            <label><?= $textbotlang['panel']['product_html_0031'] ?></label>
            <input type="text" name="name_product" id="edit_name" class="input" required>
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['product_html_0032'] ?></label>
            <input type="number" name="price_product" id="edit_price" class="input" min="0">
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['product_html_volume_gb'] ?></label>
            <input type="number" name="volume_product" id="edit_volume" class="input" min="0">
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['product_html_0033'] ?></label>
            <input type="number" name="time_product" id="edit_time" class="input" min="0">
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['product_html_0034'] ?></label>
            <input type="text" name="cetegory_product" id="edit_cat" class="input">
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['product_html_0035'] ?></label>
            <select name="namepanel" id="edit_panel" class="select">
              <option value=""><?= $textbotlang['panel']['product_html_0036'] ?></option>
              <?php foreach ($panels as $pl): ?>
                <option value="<?= htmlspecialchars($pl['name_panel'] ?? $pl['id']) ?>">
                  <?= htmlspecialchars($pl['name_panel'] ?? $pl['id']) ?>
                </option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['product_html_0037'] ?></label>
            <select name="agent_product" id="edit_agent" class="select">
              <option value="f"><?= $textbotlang['panel']['product_html_0038'] ?></option>
              <option value="n"><?= $textbotlang['panel']['product_html_0039'] ?></option>
              <option value="n2"><?= $textbotlang['panel']['product_html_0040'] ?></option>
            </select>
          </div>
          <div class="field full">
            <label><?= $textbotlang['panel']['product_html_0041'] ?></label>
            <input type="text" name="note_product" id="edit_note" class="input">
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> <?= $textbotlang['panel']['product_html_0042'] ?></button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')"><?= $textbotlang['panel']['product_html_0043'] ?></button>
      </div>
    </form>
  </div>
</div>

<script src="js/product.js"></script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>