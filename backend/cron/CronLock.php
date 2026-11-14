<?php
declare(strict_types=1);

namespace App\Cron;

use App\Core\Database;
use App\Core\Logger;
use PDO;

class CronLock
{
    public static function acquire(string $lockKey, int $ttlSeconds = 900): bool
    {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Clean expired locks
            $del = $db->prepare("DELETE FROM cron_locks WHERE lock_key = :k AND expires_at < UTC_TIMESTAMP()");
            $del->execute(['k' => $lockKey]);

            // Attempt lock insertion
            $stmt = $db->prepare("
                INSERT INTO cron_locks (lock_key, locked_at, expires_at, pid)
                VALUES (:k, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL :ttl SECOND), :pid)
            ");
            
            $pid = getmypid() ?: 0;
            return $stmt->execute(['k' => $lockKey, 'ttl' => $ttlSeconds, 'pid' => $pid]);
        } catch (\Throwable $e) {
            Logger::error("Failed acquiring cron lock for {$lockKey}: " . $e->getMessage());
            return false;
        }
    }

    public static function release(string $lockKey): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM cron_locks WHERE lock_key = :k");
            $stmt->execute(['k' => $lockKey]);
        } catch (\Throwable) {
            // Ignore release errors
        }
    }
}

