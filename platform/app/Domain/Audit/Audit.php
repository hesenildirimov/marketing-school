<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
use App\Support\DbContext;
use App\Support\Ip;
use Illuminate\Support\Facades\DB;

/**
 * Dəyişməz audit log. Hər qeyd əvvəlkinin hash-ini saxlayır (hash zənciri): silinmə və ya
 * dəyişiklik `platform:audit:verify` ilə aşkarlanır. DB səviyyəsində UPDATE/DELETE bloklanıb.
 */
final class Audit
{
    public static function record(string $action, ?object $entity = null, ?array $before = null, ?array $after = null,
        ?string $reason = null, ?int $actorId = null, ?string $entityType = null, ?int $entityId = null): AuditLog
    {
        return DB::transaction(function () use ($action, $entity, $before, $after, $reason, $actorId, $entityType, $entityId) {
            DB::select('SELECT pg_advisory_xact_lock(?)', [77001]);
            $prev = AuditLog::orderByDesc('id')->value('hash');
            $row = [
                'actor_user_id' => $actorId ?? DbContext::current() ?? auth()->id(),
                'action' => $action,
                'entity_type' => $entityType ?? ($entity ? class_basename($entity) : null),
                'entity_id' => $entityId ?? ($entity->id ?? null),
                'before' => $before ? self::scrub($before) : null,
                'after' => $after ? self::scrub($after) : null,
                'reason' => $reason,
                'ip_hash' => app()->runningInConsole() ? null : Ip::hash(request()->ip()),
                'prev_hash' => $prev,
                'created_at' => now()->utc()->format('Y-m-d H:i:s.uP'),
            ];
            $row['hash'] = self::hashOf($row);

            return AuditLog::create($row);
        });
    }

    public static function hashOf(array $row): string
    {
        // jsonb açarların sırasını dəyişir, ona görə hash-dən əvvəl kanonik forma (rekursiv ksort).
        $canon = json_encode([$row['prev_hash'], self::int($row['actor_user_id']), $row['action'], $row['entity_type'],
            self::int($row['entity_id']), self::canon($row['before']), self::canon($row['after']), $row['reason']],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', (string) $canon);
    }

    private static function int(mixed $v): ?int
    {
        return $v === null ? null : (int) $v;
    }

    private static function canon(mixed $v): mixed
    {
        if (! is_array($v)) {
            return $v;
        }
        if (! array_is_list($v)) {
            ksort($v, SORT_STRING);
        }

        return array_map([self::class, 'canon'], $v);
    }

    /** @return int|null zəncirin qırıldığı ilk qeyd */
    public static function verifyChain(): ?int
    {
        $prev = null;
        foreach (AuditLog::orderBy('id')->cursor() as $log) {
            $row = $log->only(['prev_hash', 'actor_user_id', 'action', 'entity_type', 'entity_id', 'before', 'after', 'reason']);
            if ($log->prev_hash !== $prev || self::hashOf($row) !== $log->hash) {
                return $log->id;
            }
            $prev = $log->hash;
        }

        return null;
    }

    /** Sirlər və tokenlər audit log-a düşmür. */
    private static function scrub(array $data): array
    {
        foreach ($data as $k => $v) {
            if (preg_match('/password|secret|token|signature|card/i', (string) $k)) {
                $data[$k] = '[redacted]';
            } elseif (is_array($v)) {
                $data[$k] = self::scrub($v);
            }
        }

        return $data;
    }
}
