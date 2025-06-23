<?php
    namespace Themis\Interface;

    use PDO;
    use Themis\System\SystemDataStorage;
    interface DatabaseOperatorInterface
    {
        public function __construct(SystemDataStorage $sysData);
        public function startOrGetPDO(?string $connectTo = null, array $options = [], bool $getPdo = false) : PDO | bool;
    }