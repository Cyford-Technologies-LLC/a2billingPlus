#!/usr/bin/env php
<?php

declare(strict_types=1);

// Renamed to install-phone-text.php; this shim forwards for backwards compatibility.
passthru('php ' . escapeshellarg(__DIR__ . '/install-phone-text.php') . ' ' . escapeshellarg($argv[1] ?? 'install'), $code);
exit($code);
