<?php
	namespace Themis\Database;
	// Database connection class for Themis.
	use PDO;
	use PDOException;
    class Database
    {
		private $usr = "testadmin";
		private $pass = "testadmin";
		private const DEFAULT_OPTIONS = [
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES   => false,
		];
		private const DEFAULT_DB_NAME = "theseus";
		private const DEFAULT_HOST = "localhost";
        public function connect(?string $databaseName = null, array $options = []): \PDO
        {
			// Check if the options array is empty and set it to the default options if so
			if (empty($options)) 
			{
				$options = self::DEFAULT_OPTIONS;
			}
			if(!$databaseName) 
			{
            	return new PDO("mysql:host=" . 
					self::DEFAULT_HOST . 
					";dbname=" . 
					self::DEFAULT_DB_NAME, 
					$this->usr, $this->pass, $options
				);
        	} 
			else 
			{
				$str = "mysql:host=". self::DEFAULT_HOST .";dbname={$databaseName}";
				return new PDO($str, $this->usr, $this->pass, $options);
			}
		}
	}
