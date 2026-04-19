<?php
declare(strict_types=1);

namespace Themis\Auth;

use Themis\System\DatabaseOperator;

final class SessionRepository
{
    public function __construct(private readonly DatabaseOperator $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function findActiveBySessionId(string $sessionId): ?array
    {
        $rows = $this->db->select(
            select: ['*'],
            from: 'sessions',
            where: ['session_id', 'revoked'],
            equals: [$sessionId, 0]
        );

        $session = $rows[0] ?? null;
        if (!is_array($session)) {
            return null;
        }

        $expires = strtotime((string)($session['expires'] ?? ''));
        if ($expires === false || $expires < time()) {
            return null;
        }

        return $session;
    }

    public function extendExpiryById(int $id, string $expires): void
    {
        $this->db->update(
            table: 'sessions',
            columns: ['expires'],
            values: [$expires],
            where: ['id'],
            equals: [$id]
        );
    }

    public function revokeOtherSessions(string $uuid, int $exceptId): void
    {
        $this->db->update(
            table: 'sessions',
            columns: ['revoked'],
            values: [1],
            where: ['uuid'],
            equals: [$uuid],
            notWhere: ['id'],
            notEquals: [$exceptId]
        );
    }
}
