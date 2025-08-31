<?php
declare(strict_types=1);

namespace Themis\Utils\Exceptions;

use Exception;
class BadRequestException extends Exception {
    protected ?string $details;

    public function __construct(string $message = "Bad request", ?string $details = null) {
        $this->details = $details;
        parent::__construct($message . ($details ? ": " . $details : ""));
    }

    public function getDetails(): string {
        if ($this->details === null) {
            return "No additional details provided.";
        }
        return $this->details;
    }
}
