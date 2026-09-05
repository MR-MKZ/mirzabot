<?php

declare(strict_types=1);

$cronbotDir = __DIR__;
chdir($cronbotDir);

$lockFh = fopen($cronbotDir . '/.run.lock', 'c+');
if ($lockFh === false || !flock($lockFh, LOCK_EX | LOCK_NB)) {
    if (is_resource($lockFh)) {
        fclose($lockFh);
    }
    exit(0);
}

try {
    $configFile = dirname($cronbotDir) . '/config.php';
    if (!is_file($configFile)) {
        exit(1);
    }

    $seed = $cronbotDir;
    $snippet = @file_get_contents($configFile);
    if (is_string($snippet) && preg_match('/\$domainhosts\s*=\s*[\'"]([^\'"]+)[\'"]/', $snippet, $m)) {
        $seed = $m[1];
    }
    $delay = (int) (sprintf('%u', crc32($seed)) % 45);
    if ($delay > 0) {
        sleep($delay);
    }

    $slotDir = '/tmp/mirza-cron-slots';
    if (!is_dir($slotDir)) {
        @mkdir($slotDir, 0777, true);
    }
    $slotFh = null;
    $deadline = time() + 50;
    while ($slotFh === null && time() <= $deadline) {
        for ($i = 0; $i < 3; $i++) {
            $fh = @fopen($slotDir . '/slot-' . $i . '.lock', 'c+');
            if ($fh !== false && flock($fh, LOCK_EX | LOCK_NB)) {
                $slotFh = $fh;
                break;
            }
            if ($fh !== false) {
                fclose($fh);
            }
        }
        if ($slotFh === null) {
            usleep(200000);
        }
    }
    if ($slotFh === null) {
        exit(0);
    }

    try {
        require_once $cronbotDir . '/jobs.php';
        require_once $cronbotDir . '/bootstrap.php';

        $now = new DateTimeImmutable('now');
        foreach (mirza_cron_jobs() as $job) {
            $script = $cronbotDir . '/' . $job['job'] . '.php';
            if (!is_file($script)) {
                continue;
            }
            try {
                if (!Cron\CronExpression::factory($job['schedule'])->isDue($now)) {
                    continue;
                }
                include $script;
            } catch (Throwable $e) {
                error_log('cronbot ' . $job['job'] . ': ' . $e->getMessage());
            }
        }
    } finally {
        flock($slotFh, LOCK_UN);
        fclose($slotFh);
    }
} finally {
    flock($lockFh, LOCK_UN);
    fclose($lockFh);
}
