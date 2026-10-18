<?php
ini_set('error_log', 'error_log');

function cardSmsNormalize($text)
{
    return strtr((string) $text, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        'ي' => 'ی', 'ك' => 'ک',
        '٬' => ',', '،' => ',',
        "\u{200c}" => '', "\u{200e}" => '', "\u{200f}" => '',
    ]);
}

function cardSmsBody($value)
{
    if (is_string($value)) {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
    if (!is_array($value)) {
        return null;
    }
    foreach (['text', 'body', 'message', 'sms', 'content', 'msg'] as $key) {
        if (isset($value[$key]) && is_string($value[$key]) && trim($value[$key]) !== '') {
            return trim($value[$key]);
        }
    }
    return null;
}

function cardSmsToman($bank, $text)
{
    $text = cardSmsNormalize($text);
    $patterns = [
        'blu' => '/(\d[\d,]*)\s*ریال به حساب شما نشست/u',
        'meli' => '/انتقال:(.*?)[+\-]/u',
        'grdsh' => '/مبلغ:\s*([\d,]+)/u',
        'sadhrat' => '/انتقال:\s*([\d,]+)/u',
        'melet' => '/واریز\s*([\d,]+)/u',
        'terjart' => '/واریز\s*:\s*([\d,]+)/u',
        'keshavarsi' => '/واریز\s*([\d,]+)/u',
        'resalet' => '/\+([\d,]+)/u',
        'sheahr' => '/مبلغ:\s*([\d,]+)\s*ریال/u',
        'maskan' => '/انتقال اینترنت:\D*([\d,]+)/u',
        'parsian' => '/مبلغ:\s*([\d,]+)\+/u',
        'sphe' => '/مبلغ:\s*([\d,]+)\s*ریال/u',
        'paselc' => '/\+([\d,]+)/u',
        'gharz' => '/([\d,]+)\+/u',
    ];
    if (!isset($patterns[$bank]) || !preg_match($patterns[$bank], $text, $matches)) {
        return null;
    }
    $digits = preg_replace('/\D+/', '', $matches[1] ?? '');
    if ($digits === '' || strlen($digits) > 15) {
        return null;
    }
    $rial = (int) $digits;
    if ($rial <= 0) {
        return null;
    }
    return intdiv($rial, 10);
}

function cardSmsFieldLabel($field)
{
    if (!is_string($field)) {
        return gettype($field);
    }
    $parts = explode('_', $field, 2);
    if (count($parts) < 2 || $parts[1] === '') {
        return $field;
    }
    return '***_' . $parts[1];
}

function cardSmsSnippet($text)
{
    $text = preg_replace('/\s+/u', ' ', cardSmsNormalize($text));
    if (!is_string($text)) {
        return '';
    }
    if (function_exists('mb_strlen') && mb_strlen($text) > 240) {
        return mb_substr($text, 0, 240) . '...';
    }
    if (strlen($text) > 240) {
        return substr($text, 0, 240) . '...';
    }
    return $text;
}

function cardSmsLog($event, array $context = [])
{
    $line = date('Y-m-d H:i:s') . ' card-sms ' . $event;
    if ($context !== []) {
        $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($encoded)) {
            $line .= ' ' . $encoded;
        }
    }
    $line .= "\n";
    error_log(rtrim($line));

    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return;
    }
    $file = $dir . '/card-sms.log';
    if (@filesize($file) > 5 * 1024 * 1024) {
        @rename($file, $file . '.1');
    }
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

if (defined('CARD_SMS_LIBRARY')) {
    return;
}

// Read the body before botapi.php, which also reads php://input and can only do so once.
$rawSmsBody = file_get_contents('php://input');
if ($rawSmsBody === false) {
    $rawSmsBody = '';
}
$smsPost = $_POST;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../keyboard.php';
require __DIR__ . '/../vendor/autoload.php';

$ManagePanel = new ManagePanel();

$step = 'boot';
try {
    $step = 'settings';
    $PaySetting = getPaySettingValue('statuscardautoconfirm', 'offautoconfirm');
    $payload = is_array($smsPost) ? $smsPost : [];
    $source = $payload !== [] ? 'post' : 'empty';
    if ($payload === [] && $rawSmsBody !== '') {
        $decoded = json_decode($rawSmsBody, true);
        if (is_array($decoded)) {
            $payload = $decoded;
            $source = 'json';
        } else {
            $parsed = [];
            parse_str($rawSmsBody, $parsed);
            if (is_array($parsed) && $parsed !== []) {
                $payload = $parsed;
                $source = 'raw-form';
            }
        }
    }
    $fields = [];
    foreach ($payload as $field => $unused) {
        $fields[] = cardSmsFieldLabel($field);
    }
    cardSmsLog('request', [
        'source' => $source,
        'bytes' => strlen($rawSmsBody),
        'fields' => $fields,
        'auto_confirm' => $PaySetting,
    ]);
    if ($PaySetting !== 'onautoconfirm') {
        cardSmsLog('skipped', ['reason' => 'auto confirm is off', 'setting' => $PaySetting]);
        return;
    }
    if ($payload === []) {
        cardSmsLog('skipped', ['reason' => 'empty payload']);
        return;
    }

    $step = 'match';
    $attempts = [];
    $approved = false;
    foreach ($payload as $field => $fieldValue) {
        $label = cardSmsFieldLabel($field);
        if (!is_string($field) || !str_contains($field, '_')) {
            $attempts[] = ['field' => $label, 'result' => 'ignored field'];
            continue;
        }
        [$secretKey, $bank] = array_pad(explode('_', $field, 2), 2, '');
        if ($secretKey === '' || $bank === '') {
            $attempts[] = ['field' => $label, 'result' => 'missing secret or bank'];
            continue;
        }
        $password = base64_decode(str_replace(' ', '+', $secretKey), true);
        if ($password === false || $password === '') {
            $password = base64_decode($secretKey);
        }
        if (!is_string($password) || $password === '') {
            $attempts[] = ['field' => $label, 'bank' => $bank, 'result' => 'secret is not valid base64'];
            continue;
        }
        if ((int) select('admin', '*', 'password', $password, 'count') === 0) {
            $attempts[] = ['field' => $label, 'bank' => $bank, 'result' => 'secret does not match an admin'];
            continue;
        }
        $sms = cardSmsBody($fieldValue);
        if ($sms === null) {
            $attempts[] = ['field' => $label, 'bank' => $bank, 'result' => 'sms text missing', 'value_type' => gettype($fieldValue)];
            continue;
        }
        $toman = cardSmsToman($bank, $sms);
        if ($toman === null || $toman <= 0) {
            $attempts[] = ['field' => $label, 'bank' => $bank, 'result' => 'amount not recognized', 'sms' => cardSmsSnippet($sms)];
            continue;
        }
        if (substr((string) $toman, -3) === '000') {
            $attempts[] = ['field' => $label, 'bank' => $bank, 'result' => 'round amount ignored', 'toman' => $toman, 'sms' => cardSmsSnippet($sms)];
            continue;
        }

        $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE Payment_Method = 'cart to cart' AND payment_Status IN ('Unpaid', 'waiting') AND CAST(price AS DECIMAL(20,0)) = :amount ORDER BY time ASC LIMIT 1");
        $stmt->bindValue(':amount', $toman, PDO::PARAM_INT);
        $stmt->execute();
        $Payment_report = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($Payment_report) || empty($Payment_report['id_order'])) {
            $pendingStmt = $pdo->query("SELECT id_order, id_user, price, payment_Status, Payment_Method, time FROM Payment_report WHERE payment_Status IN ('Unpaid', 'waiting') ORDER BY time DESC LIMIT 8");
            $pending = $pendingStmt ? $pendingStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $attempts[] = ['field' => $label, 'bank' => $bank, 'result' => 'no waiting card order for this amount', 'toman' => $toman, 'sms' => cardSmsSnippet($sms), 'waiting' => $pending];
            continue;
        }

        $order_id = $Payment_report['id_order'];
        $Balance_id = select('user', '*', 'id', $Payment_report['id_user'], 'select');
        if (!is_array($Balance_id)) {
            $attempts[] = ['field' => $label, 'bank' => $bank, 'result' => 'user missing', 'order' => $order_id, 'user' => $Payment_report['id_user'], 'toman' => $toman];
            continue;
        }
        if ($Payment_report['payment_Status'] == 'paid' || $Payment_report['payment_Status'] == 'reject') {
            $attempts[] = ['field' => $label, 'bank' => $bank, 'result' => 'order already closed', 'order' => $order_id, 'status' => $Payment_report['payment_Status']];
            continue;
        }
        if (!claimPaymentPaid($order_id)) {
            $attempts[] = ['field' => $label, 'bank' => $bank, 'result' => 'order was claimed by another request', 'order' => $order_id, 'user' => $Balance_id['id'], 'toman' => $toman];
            continue;
        }

        $step = 'credit ' . $order_id;
        $from_id = $Balance_id['id'];
        $textbotlang = languagechange(null, $Balance_id['lang'] ?? 'fa');
        DirectPayment($order_id, __DIR__ . '/../images.jpg');

        $balanceRow = select('user', 'Balance', 'id', $Payment_report['id_user'], 'select');
        $balance = is_array($balanceRow) ? (float) ($balanceRow['Balance'] ?? 0) : 0;
        $statusRow = select('Payment_report', 'payment_Status', 'id_order', $order_id, 'select');
        $statusNow = is_array($statusRow) ? ($statusRow['payment_Status'] ?? null) : null;
        $topic = select('topicid', 'idreport', 'report', 'paymentreport', 'select');
        $paymentreports = is_array($topic) ? ($topic['idreport'] ?? null) : null;
        $setting = select('setting', '*');
        $reportFormat = $textbotlang['paymentGateway']['reportCard'] ?? '';
        $reportSent = false;
        if (is_string($reportFormat) && $reportFormat !== '' && is_array($setting) && !empty($setting['Channel_Report']) && $paymentreports) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $paymentreports,
                'text' => sprintf(
                    $reportFormat,
                    $Payment_report['price'],
                    $Balance_id['id'],
                    $Balance_id['username'] ?? '',
                    number_format($balance, 0),
                    $order_id
                ),
                'parse_mode' => 'HTML',
            ]);
            $reportSent = true;
        }
        cardSmsLog('approved', [
            'order' => $order_id,
            'user' => $Balance_id['id'],
            'username' => $Balance_id['username'] ?? '',
            'bank' => $bank,
            'toman' => $toman,
            'previous_status' => $Payment_report['payment_Status'],
            'status' => $statusNow,
            'balance' => $balance,
            'invoice' => $Payment_report['id_invoice'] ?? '',
            'report_sent' => $reportSent,
        ]);
        $approved = true;
        break;
    }
    if (!$approved) {
        cardSmsLog('unmatched', ['attempts' => $attempts]);
    }
} catch (Throwable $cardError) {
    cardSmsLog('exception', [
        'step' => $step,
        'error' => $cardError->getMessage(),
        'file' => $cardError->getFile(),
        'line' => $cardError->getLine(),
    ]);
}
