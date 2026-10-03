<?php
ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../keyboard.php';
require __DIR__ . '/../vendor/autoload.php';

$ManagePanel = new ManagePanel();

$PaySetting = getPaySettingValue('statuscardautoconfirm', 'offautoconfirm');
if ($PaySetting !== 'onautoconfirm') {
    return;
}

// Automagen SMS Relay posts JSON when useFormData is false; older forwarders use form fields.
$payload = $_POST;
if (empty($payload)) {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || $decoded === []) {
        return;
    }
    $payload = $decoded;
}

$name_post = array_keys($payload);
$name_post = array_map('htmlspecialchars', $name_post);
if (!isset($name_post[0]) || $name_post[0] === '') {
    return;
}
$name_post = preg_split('/_+/', $name_post[0], -1);
if (!isset($name_post[0], $name_post[1])) {
    return;
}

$secret_key = select('admin', '*', 'password', base64_decode($name_post[0]), 'count');
if ($secret_key == 0) {
    return;
}

$name_bank = $name_post[1];
$valuepost = $payload["{$name_post[0]}_$name_bank"] ?? null;
if (!is_string($valuepost) || $valuepost === '') {
    return;
}

$amountInteger = null;
if ($name_bank == 'blu') {
    $pattern = "/(\d[\d,]+) ریال به حساب شما نشست\./u";
    preg_match($pattern, $valuepost, $matches);
    if (isset($matches[1])) {
        $amountString = str_replace(',', '', $matches[1]);
        $amount = intval($amountString);
        $amountInteger = intval($amount) * 0.1;
    }
} elseif ($name_bank == 'meli') {
    $pattern = '/انتقال:(.*?)[+\-]/u';
    preg_match($pattern, $valuepost, $matches);
    if (isset($matches[1])) {
        $amount = str_replace([',', '-'], '', $matches[1]);
        $amountInteger = intval($amount) * 0.1;
    }
} elseif ($name_bank == 'grdsh') {
    preg_match('/مبلغ: ([0-9,]+)/u', $valuepost, $matches);
    if (isset($matches[1])) {
        $amountInteger = str_replace(',', '', $matches[1]) * 0.1;
    }
} elseif ($name_bank == 'sadhrat') {
    preg_match('/انتقال: ([\d,]+)/', $valuepost, $matches);
    if (isset($matches[1])) {
        $amountInteger = str_replace(',', '', $matches[1]) * 0.1;
    }
} elseif ($name_bank == 'melet') {
    preg_match('/واریز(\d{1,3}(?:,\d{3})*)/u', $valuepost, $matches);
    if (isset($matches[1])) {
        $amountInteger = str_replace(',', '', $matches[1]) * 0.1;
    }
} elseif ($name_bank == 'terjart') {
    if (preg_match('/واریز\s*:\s*([\d,]+)/u', $valuepost, $matches)) {
        $amountInteger = str_replace(',', '', $matches[1]) * 0.1;
    }
} elseif ($name_bank == 'keshavarsi') {
    if (preg_match('/واريز(\d+(?:,\d+)*)/', $valuepost, $matches)) {
        $amountInteger = str_replace(',', '', $matches[1]) * 0.1;
    }
} elseif ($name_bank == 'resalet') {
    if (preg_match('/\+([\d,]+)/', $valuepost, $matches)) {
        $amountInteger = str_replace(',', '', $matches[1]) * 0.1;
    }
} elseif ($name_bank == 'sheahr') {
    if (preg_match('/مبلغ:(\d+(?:,\d+)*)ريال/u', $valuepost, $matches)) {
        $amountInteger = str_replace(',', '', $matches[1]) * 0.1;
    }
} elseif ($name_bank == 'maskan') {
    if (preg_match('/انتقال اينترنت:\D*([\d,]+)/u', $valuepost, $matches)) {
        $amountInteger = str_replace(',', '', $matches[1]) * 0.1;
    }
} elseif ($name_bank == 'parsian') {
    if (preg_match('/مبلغ:(\d{1,3}(?:,\d{3})*)\+/', $valuepost, $matches)) {
        $amountInteger = str_replace(',', '', $matches[1]) * 0.1;
    }
} elseif ($name_bank == 'sphe') {
    if (preg_match('/مبلغ:\s*([\d,]+)\s*ريال/', $valuepost, $matches)) {
        $amountInteger = str_replace(',', '', $matches[1]) * 0.1;
    }
} elseif ($name_bank == 'paselc') {
    if (preg_match('/\+([0-9,]+)/', $valuepost, $matches)) {
        $amountInteger = str_replace(',', '', $matches[1]) * 0.1;
    }
} elseif ($name_bank == 'gharz') {
    if (preg_match('/(\d{1,3}(?:,\d{3})*\+)/', $valuepost, $matches)) {
        $amountInteger = str_replace(',', '', $matches[1]) * 0.1;
    }
}

if ($amountInteger === null) {
    return;
}
if (is_numeric($amountInteger) && substr((string) $amountInteger, -3) === '000') {
    return;
}

$amountInteger = intval($amountInteger);
$stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE price = :amountInteger AND (payment_Status = 'Unpaid' OR payment_Status = 'waiting') LIMIT 1");
$stmt->bindValue(':amountInteger', $amountInteger, PDO::PARAM_INT);
$stmt->execute();
$Payment_report = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$Payment_report || !isset($Payment_report['price']) || $Payment_report['price'] == null) {
    return;
}

$order_id = $Payment_report['id_order'];
$Balance_id = select('user', '*', 'id', $Payment_report['id_user'], 'select');
if (!$Balance_id) {
    return;
}
$textbotlang = languagechange();

if ($Payment_report['payment_Status'] == 'paid' || $Payment_report['payment_Status'] == 'reject') {
    return;
}

if (!claimPaymentPaid($order_id)) {
    return;
}

DirectPayment($order_id, __DIR__ . '/../images.jpg');

$balanceformatsell = number_format(select('user', 'Balance', 'id', $Payment_report['id_user'], 'select')['Balance'] ?? 0, 0);
$paymentreports = select('topicid', 'idreport', 'report', 'paymentreport', 'select')['idreport'] ?? null;
$setting = select('setting', '*');
$text_report = sprintf(
    $textbotlang['paymentGateway']['reportCard'],
    $Payment_report['price'],
    $Balance_id['id'],
    $Balance_id['username'],
    $balanceformatsell,
    $order_id
);
if (!empty($setting['Channel_Report']) && $paymentreports) {
    telegram('sendmessage', [
        'chat_id' => $setting['Channel_Report'],
        'message_thread_id' => $paymentreports,
        'text' => $text_report,
        'parse_mode' => 'HTML',
    ]);
}
