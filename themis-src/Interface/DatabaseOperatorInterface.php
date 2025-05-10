<?php
    namespace Themis\Interface;

    use PDO;
    interface DatabaseOperatorInterface
    {
        public function __construct(bool $inDebugMode);
        public function startOrGetPDO(?string $connectTo = null, array $options = [], bool $getPdo = false) : PDO | bool;
    }