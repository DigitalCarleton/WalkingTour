<?php
/** Initialize map storage without changing the installed plugin version. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line.');
}

$pluginRoot = dirname(__DIR__);
$omekaRoot = dirname($pluginRoot, 2);
if (!is_file($omekaRoot . '/bootstrap.php')) {
    fwrite(STDERR, "Place WalkingTour in the Omeka plugins directory before running this command.\n");
    exit(1);
}

try {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['HTTP_HOST'] = 'localhost';
    require $omekaRoot . '/bootstrap.php';
    $application = new Omeka_Application('production');
    $application->bootstrap('Db');
    $application->bootstrap('Storage');
    require_once $pluginRoot . '/models/HistoricalMapRepository.php';
    $repository = new WalkingTour_HistoricalMapRepository(get_db());
    $repository->install();
    echo "Map storage is ready. Existing map edits, tours, and the installed plugin version were preserved.\n";
} catch (Exception $error) {
    fwrite(STDERR, "Map storage initialization failed: " . $error->getMessage() . "\n");
    exit(1);
}
