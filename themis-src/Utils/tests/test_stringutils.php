<?php
declare(strict_types=1);

// Simple test harness for StringUtils::changeCase
// Run: php tests/test_stringutils.php (from Themis/Utils directory) or absolute path

require_once __DIR__ . '/../StringUtils.php';

use Themis\Utils\StringUtils;

// Ensure mbstring is available in this environment. If not, abort with a clear message.
if (!function_exists('mb_internal_encoding')) {
    fwrite(STDERR, "mbstring extension is required to run these tests. Please enable php-mbstring.\n");
    exit(1);
}
mb_internal_encoding('UTF-8');

// Echo environment capabilities so test output shows whether normalization will be used.
echo 'mbstring: ' . (function_exists('mb_internal_encoding') ? 'available' : 'missing') . PHP_EOL;
echo 'Normalizer (ext-intl): ' . (class_exists('Normalizer') ? 'available' : 'missing') . PHP_EOL;

$tests = [];

// 1) Whole range uppercase
$tests[] = [
    'name' => 'whole-range-upper',
    'input' => 'hello, world!',
    'args' => [true, 'hello, world!', 0, 0, -1],
    'expected' => 'HELLO, WORLD!'
];

// 2) First-letter-of-words with punctuation
$tests[] = [
    'name' => 'first-letter-punct',
    'input' => '"hello world!'
        ,
    'args' => [true, '"hello world!', 1, 0, -1],
    'expected' => '"Hello World!'
];

// 3) Combining mark (e + combining acute) + a
$tests[] = [
    'name' => 'combining-mark',
    'input' => "e\u{0301}a", // e + combining acute, then 'a'
    'args' => [true, "e\u{0301}a", 1, 0, -1],
    'expected' => "\u{00C9}a" // Éa (single precomposed uppercase E acute and 'a')
];

// 4) Emoji sequence with letters
$tests[] = [
    'name' => 'emoji-with-letters',
    'input' => "a👩‍👩‍👦b",
    'args' => [true, "a👩‍👩‍👦b", 1, 0, -1],
    'expected' => "A👩‍👩‍👦b"
];

// 5) Every-2nd-letter (changeRate=2) with punctuation and digits
$tests[] = [
    'name' => 'every-2nd-letter',
    'input' => 'a1b2c3d',
    'args' => [true, 'a1b2c3d', 2, 0, -1],
    'expected' => 'A1b2C3d' // letters at indices 0 and 2 (a and c) uppercased
];

// 6) Range selection (middle of string)
$tests[] = [
    'name' => 'range-mid',
    'input' => 'keep THIS part!',
    'args' => [false, 'keep THIS part!', 0, 5, 8], // lower-case only the range "THIS"
    'expected' => 'keep this part!'
];

$passed = 0;
$failed = 0;
$results = [];

foreach ($tests as $t) {
    [$toUpper, $input, $changeRate, $start, $end] = $t['args'];
    try {
        $actual = StringUtils::changeCase($toUpper, $input, $changeRate, $start, $end);
    } catch (Throwable $e) {
        $actual = 'EXCEPTION: ' . get_class($e) . ' - ' . $e->getMessage();
    }

    if ($actual === $t['expected']) {
        $results[] = [ 'name' => $t['name'], 'ok' => true, 'actual' => $actual ];
        $passed++;
    } else {
        $results[] = [ 'name' => $t['name'], 'ok' => false, 'expected' => $t['expected'], 'actual' => $actual ];
        $failed++;
    }
}

// Output summary
foreach ($results as $r) {
    if ($r['ok']) {
        echo "[PASS] {$r['name']} -> {$r['actual']}\n";
    } else {
        echo "[FAIL] {$r['name']}\n";
        echo "  Expected: " . (isset($r['expected']) ? $r['expected'] : '(none)') . "\n";
        echo "  Actual:   " . (isset($r['actual']) ? $r['actual'] : '(none)') . "\n";
    }
}

echo "\nSummary: Passed={$passed}, Failed={$failed}\n";
exit($failed === 0 ? 0 : 2);
