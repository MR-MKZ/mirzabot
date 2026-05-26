<?php ?>
  </main>
</div>

<nav class="bottom-nav">
  <div class="bottom-nav-row">
    <a href="index.php"   class="bnav-item <?= ($activeNav??'')==='dashboard'?'active':''?>"><?= icon('dashboard',22) ?><span><?= $textbotlang['panel']['layout_foot_html_0001'] ?></span></a>
    <a href="users.php"   class="bnav-item <?= ($activeNav??'')==='users'?'active':''?>"><?= icon('users',22) ?><span><?= $textbotlang['panel']['layout_foot_html_0002'] ?></span></a>
    <a href="invoice.php" class="bnav-item <?= ($activeNav??'')==='invoice'?'active':''?>"><?= icon('invoice',22) ?><span><?= $textbotlang['panel']['layout_foot_html_0003'] ?></span></a>
    <a href="payment.php" class="bnav-item <?= ($activeNav??'')==='payment'?'active':''?>"><?= icon('card',22) ?><span><?= $textbotlang['panel']['layout_foot_html_0004'] ?></span></a>
    <a href="settings.php"class="bnav-item <?= ($activeNav??'')==='settings'?'active':''?>"><?= icon('settings',22) ?><span><?= $textbotlang['panel']['layout_foot_html_0005'] ?></span></a>
  </div>
</nav>
</div>

<script src="js/app.js"></script>
</body>
</html>
