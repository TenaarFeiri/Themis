<?php
declare(strict_types=1);

namespace Themis\Auth;

use Themis\System\DatabaseOperator;

final class LaunchTokenRepository
{
    public function __construct(private readonly DatabaseOperator $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function findUnusedForUpdate(string $token): ?array
    {
        $rows = $this->db->select(
            select: ['*'],
            from: 'launch_tokens',
            where: ['token', 'used'],
            equals: [$token, 0],
            for: 'UPDATE'
        );

        return $rows[0] ?? null;
    }

    public function markUsed(string $token): void
    {
        $this->db->update(
            table: 'launch_tokens',
            columns: ['used'],
            values: [1],
            where: ['token', 'used'],
            equals: [$token, 0]
        );
    }
}
