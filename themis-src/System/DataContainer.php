<?php
declare(strict_types=1);
namespace Themis\System;
use Exception;
use Themis\Init;
use JsonException;

final class DataContainer {
    private array $data = [];
    private array $fileData = [];
    public const DATAFILES_PATH = __DIR__ . '/../DataFiles/';

    public function set(string $key, mixed $value): void {
        $this->data[$key] = $value;
    }

    public function get(string $key): mixed {
        return $this->data[$key] ?? null;
    }

    public function has(string $key): bool {
        return isset($this->data[$key]);
    }

    /**
     * Securely loads a file from the DataFiles directory (or subdirectory) and stores its contents in memory.
     *
     * - Only allows access to files within the DataFiles directory (prevents directory traversal).
     * - Refuses to load symlinks.
     * - Supports JSON (decoded to array) and text files (stored as string).
     * - Stores loaded data under the file name (sans extension).
     * - Only allows overwriting if the same file is loaded again.
     *
     * @param string $fileName      The file name to load (with extension).
     * @param string $directory     Optional subdirectory within DataFiles.
     * @return mixed                Decoded array for JSON, string for text files.
     * @throws Exception            If file not found, unreadable, outside DataFiles, is a symlink, or contains malformed/invalid JSON.
     */
        public function loadFile(string $fileName, string $directory = self::DATAFILES_PATH): mixed {
            // Use realpath only for the root directory
            $dataFilesRoot = realpath(self::DATAFILES_PATH);
            if ($dataFilesRoot === false) {
                throw new Exception("DataFiles directory not found.");
            }
            // Build the full path manually
            $filePath = $directory . $fileName;
            // Normalize path separators
            $filePath = str_replace(['\\', '//'], '/', $filePath);
            // Ensure file is inside DataFiles
            $realDir = realpath(dirname($filePath));
            if ($realDir === false || strpos($realDir, $dataFilesRoot) !== 0) {
                throw new Exception("Access denied: Cannot load files outside DataFiles directory.");
            }
            if (!file_exists($filePath)) {
                throw new Exception("File not found: {$filePath}");
            }
            if (is_link($filePath)) {
                throw new Exception("Symlink detected: Refusing to load file {$filePath}");
            }
            if (!is_readable($filePath)) {
                throw new Exception("File is not readable: {$filePath}");
            }
            $contents = file_get_contents($filePath);
            $fileNameKey = pathinfo($filePath, PATHINFO_FILENAME);
            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
            $key = $fileNameKey;

            // Only allow overwrite if the same file is loaded again
            if (isset($this->fileData[$key]) && $this->fileData[$key . '_path'] !== $filePath) {
                throw new Exception("Overwriting file data is only allowed for the same file: {$filePath}");
            }

            if (strtolower($ext) === 'json') {
                if ($contents === '' || $contents === null) {
                    throw new JsonException("Malformed JSON: File is empty or null: {$filePath}");
                }
                try {
                    $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new JsonException("Invalid JSON in file: {$filePath}. " . $e->getMessage(), $e->getCode(), $e);
                }
                $this->fileData[$key] = $data;
                $this->fileData[$key . '_path'] = $filePath;
                return $data;
            } else {
                $this->fileData[$key] = $contents;
                $this->fileData[$key . '_path'] = $filePath;
                return $contents;
            }
        }

        /**
         * Get data loaded from a file by key.
         */
        public function getFileData(string $filePathOrKey) {
            // Accept either file name (sans extension) or full path
            $key = pathinfo($filePathOrKey, PATHINFO_FILENAME);
            return $this->fileData[$key] ?? null;
        }

        /**
         * Wipe data loaded from a specific file key.
         */
        public function wipeFileData(string $filePathOrKey): void {
            $key = pathinfo($filePathOrKey, PATHINFO_FILENAME);
            unset($this->fileData[$key], $this->fileData[$key . '_path']);
        }

        /**
         * Wipe all loaded file data.
         */
        public function wipeAllFileData(): void {
            $this->fileData = [];
        }
}
