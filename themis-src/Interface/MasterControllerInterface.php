<?php
    namespace Themis\Interface;

    interface MasterControllerInterface
    {
        public function __construct(array $headerData, array $requestData, bool $inDebugMode);
    }
    