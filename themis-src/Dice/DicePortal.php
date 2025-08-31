<?php
declare(strict_types=1);
namespace Themis\Dice;

// System Imports
use Themis\System\ThemisContainer;
use Themis\System\DataContainer;
use Themis\System\DatabaseOperator;

// Utils

// Exceptions
use Themis\Utils\Exceptions\BadRequestException;
use Exception;
use Throwable; // Should be caught by init anyway but just in case we wanna do special handling.

class DicePortal
{
    public function rollDice(int $sides): int
    {
        return random_int(1, $sides);
    }
}
