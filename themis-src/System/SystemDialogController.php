<?php
    namespace Themis\System;

    use Themis\System\SystemDataStorage;
    use Themis\Data\StringProcessor;
    use Themis\Data\ArrayProcessor;
    use Themis\Utilities\Assertion as Assert;
    use Exception;

    /**
     * 
     */
    class SystemDialogController
    {
        private $stringProcessor;
        private $arrayProcessor;
        private object $systemData;
        public function __construct(object $sysDataStorage)
        {
            if ($sysDataStorage instanceof SystemDataStorage) {
                $this->systemData = $sysDataStorage;
            } else {
                // Error, Dialog controller needs system data storage.
                throw new Exception("Dialog controller received bad object. Not an instance of SystemDataStorage.");
            }
            // Let's cut to the chase and just load string and array processors;
            // this class only ever activates when we're doing menu things, which will use both.
            $this->stringProcessor = new StringProcessor();
            $this->arrayProcessor = new ArrayProcessor();
            $this->systemData = $sysDataStorage;
        }
    }