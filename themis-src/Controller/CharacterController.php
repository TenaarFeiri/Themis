<?php
	namespace Themis\Controller;

	use Themis\Interface\CharacterControllerInterface;
	use Themis\System\SystemDataStorage;

	class CharacterController implements CharacterControllerInterface
	{
		private SystemDataStorage $systemData; // Sysdata inherited from MC.
		public function __construct(SystemDataStorage $sysData)
		{
			$this->systemData = $sysData;
		}
	}

