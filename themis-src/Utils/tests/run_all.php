<?php
declare(strict_types=1);

// Simple test runner for raw PHP test scripts in this directory.
// It finds files matching test_*.php and runs each in a separate PHP process,
// printing their output and exit codes.

$dir = __DIR__;
echo "Test runner started in: $dir" . PHP_EOL;
echo 'mbstring: ' . (function_exists('mb_internal_encoding') ? 'available' : 'missing') . PHP_EOL;
echo 'Normalizer (ext-intl): ' . (class_exists('Normalizer') ? 'available' : 'missing') . PHP_EOL;
echo PHP_EOL;

// Allow optional first argument to select a test name or pattern. Examples:
//  - php run_all.php            -> runs all tests (test_*.php)
//  - php run_all.php stringutils -> runs test_stringutils.php
//  - php run_all.php "*_utils"   -> runs test_*_utils.php
$arg = $argv[1] ?? null;
if ($arg !== null) {
    // If user provided a filename, use it directly; otherwise prefix with test_
    if (str_ends_with($arg, '.php')) {
        $globPattern = $arg;
    } elseif (str_starts_with($arg, 'test_')) {
        $globPattern = $arg . '.php';
    } else {
        $globPattern = 'test_' . $arg . '.php';
    }
} else {
    $globPattern = 'test_*.php';
}

$files = glob($dir . '/' . $globPattern);
if (!$files) {
    echo "No test files found (pattern: test_*.php)\n";
    exit(1);
}

$allOk = true;
foreach ($files as $file) {
    $name = basename($file);
    echo "=== Running $name ===\n";
    $cmd = 'php ' . escapeshellarg($file) . ' 2>&1';
    $output = [];
    exec($cmd, $output, $code);
    foreach ($output as $line) {
        echo $line . PHP_EOL;
    }
    echo "Exit code: $code\n\n";
    if ($code !== 0) {
        $allOk = false;
    }
}

echo ($allOk ? "ALL TESTS PASSED\n" : "SOME TESTS FAILED\n");
exit($allOk ? 0 : 2);
