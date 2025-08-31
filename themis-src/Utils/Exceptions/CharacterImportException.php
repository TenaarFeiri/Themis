<?php
declare(strict_types=1);

namespace Themis\Utils\Exceptions;

use Exception;
class CharacterImportException extends Exception {
    protected $message = "Character import error occurred: %s";

    public function __construct(string $message) {
        $this->message = sprintf($this->message, $message);
        parent::__construct($this->message);
    }
}
