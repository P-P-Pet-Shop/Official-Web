<?php
declare(strict_types=1);

namespace App\Core;

class Logger
{
    private static string $logDir = __DIR__ . '/../../storage/logs';

    private static function write(string $channel, string $level, string $message, array $context = []): void
    {
        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0755, true);
        }

        $date = gmdate('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $logLine = sprintf("[%s UTC] [%s] %s: %s%s\n", $date, strtoupper($channel), strtoupper($level), $message, $contextStr);

        $file = self::$logDir . '/' . $channel . '-' . gmdate('Y-m-d') . '.log';
        @file_put_contents($file, $logLine, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = [], string $channel = 'app'): void
    {
        self::write($channel, 'INFO', $message, $context);
    }

    public static function warning(string $message, array $context = [], string $channel = 'app'): void
    {
        self::write($channel, 'WARNING', $message, $context);
    }

    public static function error(string $message, array $context = [], string $channel = 'app'): void
    {
        self::write($channel, 'ERROR', $message, $context);
    }

    public static function uber(string $level, string $message, array $context = []): void
    {
        self::write('uber', $level, $message, $context);
    }

    public static function audit(string $action, array $details = []): void
    {
        self::write('audit', 'AUDIT', $action, $details);
    }
}

