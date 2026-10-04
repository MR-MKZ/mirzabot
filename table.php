<?php

require_once __DIR__ . '/db/bootstrap.php';

global $domainhosts;

$setting = select("setting", "*");
if (intval($setting['Channel_Report'] ?? 0) != 0) {
    syncReportTopics($setting['Channel_Report'], languagechange());
}

$webhookSecret = ensureWebhookSecret();

telegram('setWebhook', [
    'url' => "https://$domainhosts/index.php?secret={$webhookSecret['secret']}",
]);
