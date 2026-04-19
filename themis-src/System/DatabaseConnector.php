<?php
	namespace Themis\System;

	// Database connection class for Themis.
	use PDO;
	use PDOException;
    class DatabaseConnector
    {
		private string $usr;
		private string $pass;
		private const DEFAULT_OPTIONS = [
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES   => false,
		];
		private const DEFAULT_DB_NAME = "themis";
		private const DEFAULT_HOST = "localhost";
        private const DEFAULT_PORT = 3306;

        public function __construct()
        {
			$this->usr = (string)(getenv('THEMIS_DB_USER') ?: 'testadmin');
			$this->pass = (string)(getenv('THEMIS_DB_PASS') ?: 'testadmin');
        }

        public function connect(?string $databaseName = null, array $options = []): PDO
        {
			// Check if the options array is empty and set it to the default options if so
			if (empty($options)) 
			{
				$options = self::DEFAULT_OPTIONS;
			}

			$host = (string)(getenv('THEMIS_DB_HOST') ?: self::DEFAULT_HOST);
			$port = (int)(getenv('THEMIS_DB_PORT') ?: self::DEFAULT_PORT);
			$dbName = $databaseName ?: (string)(getenv('THEMIS_DB_NAME') ?: self::DEFAULT_DB_NAME);
			$charset = (string)(getenv('THEMIS_DB_CHARSET') ?: 'utf8mb4');
			$dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

			if(!$databaseName) 
			{
	            	return new PDO($dsn, $this->usr, $this->pass, $options);
        	} 
			else 
			{
				return new PDO($dsn, $this->usr, $this->pass, $options);
			}
		}
	}
