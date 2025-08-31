Test runner (raw PHP)
======================

This directory contains raw, framework-free PHP tests and a tiny test runner.

Runner: `run_all.php`
- Usage:
  - `php run_all.php` — runs all tests matching `test_*.php`.
  - `php run_all.php stringutils` — runs `test_stringutils.php`.
  - `php run_all.php test_stringutils.php` — runs that exact file.

Notes:
- Tests are meant to be simple, zero-dependency scripts. They rely on `php-mbstring` and optionally `ext-intl` for Unicode normalization.
- The runner executes each test in a separate PHP process and reports their output and exit codes.

Add new tests by creating `test_<name>.php` scripts that return exit code 0 on success.
