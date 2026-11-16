<?php
chdir(__DIR__);
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
$textbotlang = languagechange();

$setting = config_all('setting');
$stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE type = 'marzban'  ORDER BY RAND() LIMIT 25");
$stmt->execute();
while ($panel = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $response = getusers($panel['name_panel'], "on_hold");
    if (!is_array($response) || !is_array($response['users'] ?? null)) {
        error_log('mirza cron: on_hold: invalid users response for panel ' . $panel['name_panel']);
        continue;
    }

    foreach ($response['users'] as $user) {
        if (!is_array($user) || !isset($user['username'], $user['status'])) {
            continue;
        }

        $invoice = select("invoice", "*", "username", $user['username'], "select");
        if (!$invoice || $invoice['Status'] === "send_on_hold" || $user['status'] !== "on_hold") {
            continue;
        }
        $daysOnHold = (time() - $invoice['time_sell']) / 86400;
        if ($daysOnHold < $setting['on_hold_day']) {
            continue;
        }

        $sql = "SELECT 1 FROM service_other WHERE username = :username  AND type = 'change_location' LIMIT 1";
        $stmtOther = $pdo->prepare($sql);
        $stmtOther->bindParam(':username', $invoice['username'], PDO::PARAM_STR);
        $stmtOther->execute();
        if ($stmtOther->fetchColumn() !== false) {
            continue;
        }

        $text = sprintf($textbotlang['users']['notify']['onHoldReminder'], $invoice['username'], $setting['on_hold_day'], $setting['id_support']);
        sendmessage($invoice['id_user'], $text, null, 'HTML');
        update("invoice", "Status", "send_on_hold", "username", $invoice['username']);
    }
}
