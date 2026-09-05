<?php

if (!function_exists('mirza_cron_jobs')) {
    function mirza_cron_jobs(): array
    {
        return [
            ['job' => 'croncard', 'schedule' => '*/1 * * * *', 'optional' => false, 'title' => 'تأیید خودکار رسید کارت به کارت'],
            ['job' => 'NoticationsService', 'schedule' => '*/1 * * * *', 'optional' => false, 'title' => 'ارسال اعلان‌های ربات'],
            ['job' => 'sendmessage', 'schedule' => '*/1 * * * *', 'optional' => false, 'title' => 'صف ارسال پیام همگانی'],
            ['job' => 'activeconfig', 'schedule' => '*/1 * * * *', 'optional' => false, 'title' => 'فعال‌سازی سرویس‌های خریداری‌شده'],
            ['job' => 'disableconfig', 'schedule' => '*/1 * * * *', 'optional' => false, 'title' => 'غیرفعال‌سازی سرویس‌های منقضی'],
            ['job' => 'iranpay1', 'schedule' => '*/1 * * * *', 'optional' => false, 'title' => 'پیگیری پرداخت‌های ایران‌پی'],
            ['job' => 'gift', 'schedule' => '*/2 * * * *', 'optional' => false, 'title' => 'پردازش کدهای هدیه'],
            ['job' => 'configtest', 'schedule' => '*/2 * * * *', 'optional' => false, 'title' => 'مدیریت سرویس‌های تست'],
            ['job' => 'plisio', 'schedule' => '*/3 * * * *', 'optional' => false, 'title' => 'پیگیری پرداخت‌های ارز دیجیتال'],
            ['job' => 'payment_expire', 'schedule' => '*/5 * * * *', 'optional' => false, 'title' => 'انقضای فاکتورهای پرداخت‌نشده'],
            ['job' => 'statusday', 'schedule' => '*/15 * * * *', 'optional' => false, 'title' => 'گزارش وضعیت روزانه'],
            ['job' => 'on_hold', 'schedule' => '*/15 * * * *', 'optional' => false, 'title' => 'سرویس‌های در حالت انتظار'],
            ['job' => 'uptime_node', 'schedule' => '*/15 * * * *', 'optional' => false, 'title' => 'پایش وضعیت نودها'],
            ['job' => 'uptime_panel', 'schedule' => '*/15 * * * *', 'optional' => false, 'title' => 'پایش وضعیت پنل‌ها'],
            ['job' => 'expireagent', 'schedule' => '*/30 * * * *', 'optional' => false, 'title' => 'انقضای اشتراک نمایندگان'],
            ['job' => 'backupbot', 'schedule' => '0 */5 * * *', 'optional' => false, 'title' => 'پشتیبان‌گیری ربات‌ساز'],
            ['job' => 'lottery', 'schedule' => '*/1 * * * *', 'optional' => true, 'title' => 'قرعه‌کشی و امتیازات (فقط اگر امتیازدهی را فعال کنید)'],
        ];
    }
}

if (!function_exists('mirza_cron_dispatcher_path')) {
    function mirza_cron_dispatcher_path(): string
    {
        return __DIR__ . '/run.php';
    }
}

if (!function_exists('mirza_cron_dispatcher_command')) {
    function mirza_cron_dispatcher_command(): string
    {
        $php = is_executable('/usr/bin/php') ? '/usr/bin/php' : (PHP_BINARY !== '' ? PHP_BINARY : 'php');

        return '* * * * * ' . $php . ' ' . mirza_cron_dispatcher_path() . ' >/dev/null 2>&1';
    }
}

if (!function_exists('mirza_cron_dispatcher_curl_command')) {
    function mirza_cron_dispatcher_curl_command(string $baseUrl): string
    {
        return '* * * * * curl -s ' . rtrim($baseUrl, '/') . '/cronbot/run.php > /dev/null 2>&1';
    }
}
