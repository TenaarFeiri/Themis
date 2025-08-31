<?php
declare(strict_types=1);
namespace Themis\System;
use Exception;
use Themis\Init;
use JsonException;


/**
 * Class DataContainer
 *
 * Provides secure, in-memory storage for runtime data and file contents.
 * Supports strict file I/O from the DataFiles directory, enforces file type and size restrictions,
 * and prevents directory traversal and symlink attacks. Allows retrieval and wiping of loaded file data.
 */
final class DataContainer {
    private array $data = [];
    private array $fileData = [];
    public const DATAFILES_PATH = __DIR__ . '/../DataFiles/';
    public const ALLOWED_FILE_TYPES = [
        'json', 
        'txt'
    ];
    private const FILE_SIZE_LIMIT_MB = 2;

    /**
     * Sets a value in the runtime data container by key.
     *
     * @param string $key   The key to set.
     * @param mixed $value  The value to store.
     * @return void
     */
    public function set(string $key, mixed $value): void {
        $this->data[$key] = $value;
    }

    /**
     * Retrieves a value from the runtime data container by key.
     *
     * @param string $key The key to retrieve.
     * @return mixed      The value, or null if not set.
     */
    public function get(string $key): mixed {
        return $this->data[$key] ?? null;
    }

    /**
     * Checks if a key exists in the runtime data container.
     *
     * @param string $key The key to check.
     * @return bool       True if set, false otherwise.
     */
    public function has(string $key): bool {
        return isset($this->data[$key]);
    }

    /**
     * Securely loads a file from the DataFiles directory (or subdirectory) and stores its contents in memory.
     *
     * Only allows files within DataFiles, refuses symlinks, enforces file type/size restrictions,
     * and supports JSON (decoded to array) and text files (as string). Stores loaded data under the full file name,
     * optionally with a suffix for differentiation. Only allows overwriting if the same file is loaded again.
     *
     * @param string $fileName      File name to load (with extension).
     * @param string|null $suffix   Optional suffix to differentiate multiple loads of the same file.
     * @param string $directory     Optional subdirectory within DataFiles.
     * @return mixed                Decoded array for JSON, string for text files.
     * @throws Exception            If file not found, unreadable, outside DataFiles, is a symlink, contains malformed/invalid JSON, exceeds size limit, or has a disallowed extension.
     */
        public function loadFile(string $fileName, ?string $suffix = null, string $directory = self::DATAFILES_PATH): mixed {
            // Use realpath only for the root directory
            $dataFilesRoot = realpath(path: self::DATAFILES_PATH);
            if ($dataFilesRoot === false) {
                throw new Exception("DataFiles directory not found.");
            }
            // Build the full path manually
            $filePath = $directory . $fileName;
            // Normalize path separators
            $filePath = str_replace(search: ['\\', '//'], replace: '/', subject: $filePath);
            // Ensure file is inside DataFiles
            $realDir = realpath(path: dirname($filePath));
            if ($realDir === false || strpos(haystack: $realDir, needle: $dataFilesRoot) !== 0) {
                throw new Exception("Access denied: Cannot load files outside DataFiles directory.");
            }
            if (!file_exists(filename: $filePath)) {
                throw new Exception("File not found: {$filePath}");
            }
            if (is_link(filename: $filePath)) {
                throw new Exception("Symlink detected: Refusing to load file {$filePath}");
            }
            if (!is_readable(filename: $filePath)) {
                throw new Exception("File is not readable: {$filePath}");
            }

            // File size limit: 2MB
            $maxSize = self::FILE_SIZE_LIMIT_MB * 1024 * 1024;
            $fileSize = filesize(filename: $filePath);
            if ($fileSize > $maxSize) {
                throw new Exception("File size exceeds 2MB limit: {$filePath}");
            }

            // Whitelist allowed file types: .json, .txt, and extensionless files
            $extRaw = pathinfo(path: $filePath, flags: PATHINFO_EXTENSION);
            $ext = strtolower(string: $extRaw);
            if ($extRaw !== $ext) {
                throw new Exception("File extension must be lowercase: .{$extRaw} ({$filePath})");
            }
            if (!in_array(needle: $ext, haystack: self::ALLOWED_FILE_TYPES, strict: true)) {
                throw new Exception("File type not allowed: .{$ext} ({$filePath})");
            }

            $contents = file_get_contents(filename: $filePath);
            $fileNameKey = basename(path: $filePath); // full file name with extension
            $key = $fileNameKey;
            if ($suffix !== null) {
                $key = $fileNameKey . '_' . $suffix; // Use suffix to differentiate
            }

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
         * Retrieves data loaded from a file by key.
         *
         * Accepts either the full file name (with extension) or full path.
         *
         * @param string $filePathOrKey File name or path.
         * @return mixed                Loaded file data, or null if not found.
         */
        public function getFileData(string $filePathOrKey) {
            $key = basename($filePathOrKey);
            return $this->fileData[$key] ?? null;
        }

        /**
         * Removes data loaded from a specific file key.
         *
         * @param string $filePathOrKey File name or path to wipe.
         * @return void
         */
        public function wipeFileData(string $filePathOrKey): void {
            $key = basename($filePathOrKey);
            unset($this->fileData[$key], $this->fileData[$key . '_path']);
        }

        /**
         * Removes all loaded file data from memory.
         *
         * @return void
         */
        public function wipeAllFileData(): void {
            $this->fileData = [];
        }
}
