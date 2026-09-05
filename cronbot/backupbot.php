<?php
require_once __DIR__ . '/bootstrap.php';
$textbotlang = languagechange();

if (!isShellExecAvailable() || !isExecAvailable()) {
    return;
}

$reportbackup = select("topicid", "idreport", "report", "backupfile", "select")['idreport'];
$destination = __DIR__;
$setting = select("setting", "*");
$canSendReport = !isTelegramChatIdEmpty($setting['Channel_Report'] ?? '');
$sourcefir = dirname($destination);
$botlist = select("botsaz", "*", null, null, "fetchAll");
if ($botlist && $canSendReport) {
    foreach ($botlist as $bot) {
        $folderName = $bot['id_user'] . $bot['username'];
        shell_exec("zip -r $destination/file.zip $sourcefir/vpnbot/$folderName/data $sourcefir/vpnbot/$folderName/product.json $sourcefir/vpnbot/$folderName/product_name.json");
        telegram('sendDocument', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $reportbackup,
            'document' => new CURLFile(__DIR__ . '/file.zip'),
            'caption' => "@{$bot['username']} | {$bot['id_user']}",
        ]);
        unlink(__DIR__ . '/file.zip');
    }
}

$backup_file_name = __DIR__ . '/backup_' . date("Y-m-d") . '.sql';
$zip_file_name = __DIR__ . '/backup_' . date("Y-m-d") . '.zip';
$dbhost = empty($dbhost) ? "localhost" : $dbhost;
$command = "mysqldump -h $dbhost -u $usernamedb -p'$passworddb' --no-tablespaces --ssl-mode=DISABLED $dbname > $backup_file_name";

$output = [];
$return_var = 0;
exec($command, $output, $return_var);
if ($return_var !== 0) {
    if ($canSendReport) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $reportbackup,
            'text' => $textbotlang['keyboard']['backupError'],
        ]);
    }
} else {
    if ($canSendReport) {
        telegram('sendDocument', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $reportbackup,
            'document' => new CURLFile($backup_file_name),
            'caption' => $textbotlang['Admin']['report']['backupCaption'],
        ]);
    }
    unlink($backup_file_name);
}