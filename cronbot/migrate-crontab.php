#!/usr/bin/env php
<?php

declare(strict_types=1);

$opts = getopt('', ['user::', 'scan::', 'roots::', 'dry-run', 'apply', 'help']);
if (isset($opts['help'])) {
    fwrite(STDOUT, <<<TXT
Usage:
  php migrate-crontab.php [--dry-run] [--apply] [--user=www-data] [--scan=/var/www,/home] [--roots=/a,/b]

  --dry-run   Show discovered bots and new crontab (default if --apply is omitted)
  --apply     Write crontab after backup
  --user      Crontab owner (default: www-data)
  --scan      Comma-separated dirs to search for cronbot/run.php
  --roots     Explicit bot install roots (skips discovery)

TXT);
    exit(0);
}

$user = $opts['user'] ?? 'www-data';
$apply = isset($opts['apply']);
$dryRun = !$apply || isset($opts['dry-run']);
if (isset($opts['apply']) && isset($opts['dry-run'])) {
    $dryRun = true;
    $apply = false;
}

$crontabBin = trim((string) shell_exec('command -v crontab 2>/dev/null'));
if ($crontabBin === '') {
    fwrite(STDERR, "crontab not found\n");
    exit(1);
}

$current = (string) shell_exec(sprintf('%s -u %s -l 2>/dev/null', escapeshellarg($crontabBin), escapeshellarg($user)));
if (stripos($current, 'no crontab') !== false) {
    $current = '';
}

$php = is_executable('/usr/bin/php') ? '/usr/bin/php' : 'php';

function mirza_is_bot_root(string $root): bool
{
    $root = rtrim($root, '/');
    return is_file($root . '/cronbot/run.php')
        && is_file($root . '/config.php')
        && is_file($root . '/function.php');
}

/**
 * @param array<string, true> $roots
 */
function mirza_add_root(array &$roots, string $root): void
{
    $root = realpath(rtrim($root, '/')) ?: rtrim($root, '/');
    if ($root !== '' && mirza_is_bot_root($root)) {
        $roots[$root] = true;
    }
}

/**
 * @return list<string>
 */
function mirza_scan_dir(string $base, int $maxDepth = 4): array
{
    $found = [];
    $base = rtrim($base, '/');
    if ($base === '' || !is_dir($base) || !is_readable($base)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $iterator->setMaxDepth($maxDepth);

    foreach ($iterator as $item) {
        if (!$item->isDir() || $item->getFilename() !== 'cronbot') {
            continue;
        }
        $run = $item->getPathname() . '/run.php';
        if (is_file($run)) {
            mirza_add_root($found, dirname($item->getPathname()));
        }
    }

    return array_keys($found);
}

/**
 * Map hostname -> document root from apache/nginx configs.
 *
 * @return array<string, string>
 */
function mirza_vhost_map(): array
{
    $map = [];
    $files = [];
    foreach ([
        '/etc/apache2/sites-enabled',
        '/etc/httpd/conf.d',
        '/etc/nginx/sites-enabled',
        '/etc/nginx/conf.d',
    ] as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file) || is_link($file)) {
                $files[] = $file;
            }
        }
    }

    foreach ($files as $file) {
        $text = @file_get_contents($file);
        if (!is_string($text) || $text === '') {
            continue;
        }

        if (preg_match_all('/^\s*(?:ServerName|server_name)\s+([^;]+);?\s*$/mi', $text, $names)) {
            $hosts = [];
            foreach ($names[1] as $chunk) {
                foreach (preg_split('/\s+/', trim($chunk)) ?: [] as $host) {
                    $host = strtolower(rtrim($host, ';'));
                    if ($host !== '' && $host !== '_' && $host !== 'default_server') {
                        $hosts[] = $host;
                    }
                }
            }
            $docroot = null;
            if (preg_match('/^\s*DocumentRoot\s+[\"\']?([^\"\'\s]+)[\"\']?/mi', $text, $m)) {
                $docroot = $m[1];
            } elseif (preg_match('/^\s*root\s+([^;]+);/mi', $text, $m)) {
                $docroot = trim($m[1]);
            }
            if ($docroot !== null) {
                foreach ($hosts as $host) {
                    $map[$host] = rtrim($docroot, '/');
                }
            }
        }
    }

    return $map;
}

$roots = [];

if (!empty($opts['roots'])) {
    foreach (explode(',', $opts['roots']) as $root) {
        mirza_add_root($roots, trim($root));
    }
} else {
    $scan = [];
    if (!empty($opts['scan'])) {
        foreach (explode(',', $opts['scan']) as $dir) {
            $dir = trim($dir);
            if ($dir !== '') {
                $scan[] = $dir;
            }
        }
    } else {
        $scan = array_values(array_filter([
            '/var/www',
            '/var/www/html',
            '/home',
            getcwd() ?: null,
            dirname(__DIR__),
            dirname(dirname(__DIR__)),
        ]));
        foreach (mirza_vhost_map() as $docroot) {
            $scan[] = dirname($docroot);
            $scan[] = $docroot;
        }
    }
    $scan = array_values(array_unique(array_filter($scan, 'is_dir')));

    foreach ($scan as $dir) {
        foreach (mirza_scan_dir($dir, str_starts_with($dir, '/home') ? 5 : 4) as $root) {
            mirza_add_root($roots, $root);
        }
    }

    // Resolve domains from existing curl crontab lines.
    $vhosts = mirza_vhost_map();
    foreach (preg_split('/\r?\n/', $current) ?: [] as $line) {
        if (!preg_match('#https?://([^/\s]+)/cronbot/#', $line, $m)) {
            continue;
        }
        $host = strtolower($m[1]);
        $candidates = [];
        if (isset($vhosts[$host])) {
            $candidates[] = $vhosts[$host];
        }
        foreach ([
            '/var/www/' . $host,
            '/var/www/html/' . $host,
            '/home/' . $host . '/public_html',
        ] as $guess) {
            $candidates[] = $guess;
        }
        foreach (glob('/home/*/public_html/' . $host) ?: [] as $guess) {
            $candidates[] = $guess;
        }
        foreach ($candidates as $candidate) {
            mirza_add_root($roots, $candidate);
        }
    }

    // Paths already present in crontab.
    foreach (preg_split('/\r?\n/', $current) ?: [] as $line) {
        if (preg_match('#(/[^\s]+)/cronbot/(?:run\.php|[A-Za-z0-9_]+\.php)#', $line, $m)) {
            mirza_add_root($roots, $m[1]);
        }
    }
}

$rootList = array_keys($roots);
sort($rootList);

fwrite(STDOUT, 'Found ' . count($rootList) . " bot install(s):\n");
foreach ($rootList as $root) {
    fwrite(STDOUT, "  - {$root}\n");
}

if ($rootList === []) {
    fwrite(STDERR, "No bots found. Use --scan=/path or --roots=/bot1,/bot2\n");
    exit(2);
}

$kept = [];
foreach (preg_split('/\r?\n/', $current) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }
    if (strpos($line, '/cronbot/') !== false) {
        continue;
    }
    $kept[] = $line;
}

foreach ($rootList as $root) {
    $kept[] = '* * * * * ' . $php . ' ' . $root . '/cronbot/run.php >/dev/null 2>&1';
}
$kept = array_values(array_unique($kept));
$content = implode(PHP_EOL, $kept) . PHP_EOL;

$oldCronbot = 0;
foreach (preg_split('/\r?\n/', $current) ?: [] as $line) {
    if (strpos($line, '/cronbot/') !== false) {
        $oldCronbot++;
    }
}

fwrite(STDOUT, "\nOld cronbot lines: {$oldCronbot}\n");
fwrite(STDOUT, 'New cronbot lines: ' . count($rootList) . "\n\n");
fwrite(STDOUT, "---- new crontab ----\n{$content}---- end ----\n");

if ($dryRun || !$apply) {
    fwrite(STDOUT, "Dry run only (pass --apply to write).\n");
    exit(0);
}

$backup = '/tmp/mirza-cron-' . $user . '-' . date('Ymd-His') . '.bak';
file_put_contents($backup, $current);
fwrite(STDOUT, "Backup: {$backup}\n");

$tmp = tempnam(sys_get_temp_dir(), 'mirza-cron');
file_put_contents($tmp, $content);
$code = 0;
passthru(sprintf('%s -u %s %s', escapeshellarg($crontabBin), escapeshellarg($user), escapeshellarg($tmp)), $code);
unlink($tmp);
exit($code === 0 ? 0 : 1);
