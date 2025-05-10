<?php
    namespace Themis\Interface;

    use Themis\System\SystemDataStorage;
    interface DatabaseControllerInterface
    {
        public function __construct(SystemDataStorage $systemDataStorage);
        public function verifyOrImportUser() : bool;
    }