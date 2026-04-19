<?php
declare(strict_types=1);

namespace Themis\Auth;

use Themis\System\DatabaseOperator;

final class PlayerRepository
{
    public function __construct(private readonly DatabaseOperator $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByUuid(string $uuid): ?array
    {
        $rows = $this->db->select(
            select: ['*'],
            from: 'players',
            where: ['player_uuid'],
            equals: [$uuid]
        );

        return $rows[0] ?? null;
    }
}
