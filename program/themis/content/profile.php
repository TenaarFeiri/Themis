<?php
declare(strict_types=1);

namespace Themis\Content;

require_once __DIR__ . '/../StrictErrorHandler.php';
require_once __DIR__ . '/../Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (!session_start()) {
        throw new ProfileException('Failed to start session for profile rendering');
    }
}

use Exception;
use Themis\System\DatabaseOperator;

class ProfileException extends Exception {}
class ProfileManager {
    private ?DatabaseOperator $db = null;

    public function __construct() {
        // Here we will write to $_SESSION anyway so we don't need our containers.
        $this->db = new DatabaseOperator();
        if (!$this->db) {
            throw new ProfileException("Database operator not found.");
        }
    }

    public function render(): string {
        // Fill this out later.
        $out = '';
        $out .= '<link rel="stylesheet" href="/themis/content/css/content.css">';
        return $out;
    }
}


session_write_close();
