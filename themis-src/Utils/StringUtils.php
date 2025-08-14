<?php 
declare(strict_types=1);
namespace Themis\Utils;

use Themis\System\DataContainer;
use Exception;

/**
 * Class StringUtils
 * @package Themis\Utils
 * 
 * Utility methods for string parsing.
 */
class StringUtils
{
    private DataContainer $dataContainer;
    public function __construct(DataContainer $dataContainer) {
        $this->dataContainer = $dataContainer;
    }

    
}
