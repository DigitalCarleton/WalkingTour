<?php
/** Preview and apply an operator-reviewed legacy migration. Private artifacts are not web assets. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Run this script from the command line.'); }
$pluginRoot = dirname(__DIR__);
$omekaRoot = dirname($pluginRoot, 2);
$options = getopt('', array('export:', 'preview:', 'apply:', 'plan:', 'review:', 'files-root:', 'cleanup-preview:', 'cleanup:', 'recovery:'));
if (!$options) {
    echo "Usage:\n  php scripts/migrate-historical-maps.php --export=/private/new-backup [--files-root=/configured/files]\n  php scripts/migrate-historical-maps.php --preview=/private/backup --plan=/private/plan.json\n  php scripts/migrate-historical-maps.php --apply=/private/backup --review=PREVIEW_SHA256\n";
    echo "  php scripts/migrate-historical-maps.php --cleanup-preview=/private/backup\n  php scripts/migrate-historical-maps.php --cleanup=/private/backup --review=CLEANUP_SHA256\n";
    exit(0);
}
try {
    if (!is_file($omekaRoot . '/bootstrap.php')) { throw new RuntimeException('Run from the installed Omeka plugin directory.'); }
    $commands = array_intersect(array_keys($options), array('export','preview','apply','cleanup-preview','cleanup'));
    if (count($commands) !== 1) { throw new InvalidArgumentException('Choose exactly one export, migration preview/apply, or cleanup preview/apply operation.'); }
    $command = reset($commands); $directory = $options[$command];
    if (!is_string($directory) || substr($directory,0,1) !== '/') { throw new InvalidArgumentException('Use an absolute private backup directory.'); }
    // Resolve symlinks in an existing parent; a web-served export could expose private points/history.
    $parent = realpath($command === 'export' ? dirname($directory) : $directory);
    $webRoot = realpath($omekaRoot);
    if (!$parent || $parent === $webRoot || strpos($parent, $webRoot . '/') === 0) { throw new InvalidArgumentException('The backup directory must exist outside the Omeka web root (its parent must exist for export).'); }
    $_SERVER['SCRIPT_NAME']='/index.php'; $_SERVER['HTTP_HOST']='localhost';
    require $omekaRoot . '/bootstrap.php';
    $application = new Omeka_Application('production'); $application->bootstrap('Db'); $application->bootstrap('Storage');
    require_once $pluginRoot . '/models/HistoricalMapMigration.php';
    $migration = new WalkingTour_HistoricalMapMigration(get_db());
    if ($command === 'export') {
        $filesRoot = $options['files-root'] ?? null;
        $adapter = Zend_Registry::get('storage')->getAdapter();
        if (!$filesRoot && $adapter instanceof Omeka_Storage_Adapter_Filesystem) { $filesRoot = $adapter->getOptions()['localDir']; }
        $backup = $migration->export($directory, $filesRoot);
        echo 'Recovery export created: ' . $directory . "\nBackup SHA-256: " . WalkingTour_HistoricalMapMigration::hash($backup) . "\nRead inventory.txt before preparing a migration plan.\n";
    } else {
        $backup = $migration->loadBackup($directory);
        $recoveries = array();
        foreach ((array) ($options['recovery'] ?? array()) as $recovery) { $recoveries[] = $migration->loadBackup($recovery); }
        if ($command === 'cleanup-preview') {
            $review = $migration->contractionPreview($backup,$recoveries);
            WalkingTour_HistoricalMapMigration::write($directory . '/cleanup-preview.json',$review);
            echo json_encode($review,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) . "\nCleanup SHA-256: " . WalkingTour_HistoricalMapMigration::hash($review) . "\nReview the export, unresolved/unmatched points and archived uploads. No storage changed.\n";
        } elseif ($command === 'cleanup') {
            $review = json_decode(file_get_contents($directory . '/cleanup-preview.json'),true,512,JSON_THROW_ON_ERROR);
            echo json_encode($migration->contract($backup,$review,$options['review'] ?? '',$recoveries)) . "\nRetired storage removed. Source Items, Geolocation, Tours and uploaded files preserved.\n";
        } elseif ($command === 'preview') {
            if (!isset($options['plan'])) { throw new InvalidArgumentException('Supply --plan=/private/plan.json.'); }
            $review = $migration->preview($backup, json_decode(file_get_contents($options['plan']),true,512,JSON_THROW_ON_ERROR));
            WalkingTour_HistoricalMapMigration::write($directory . '/preview.json', $review);
            echo json_encode($review,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) . "\nPreview SHA-256: " . WalkingTour_HistoricalMapMigration::hash($review) . "\nReview control differences, unmatched annotations and archive reasons before applying. No records changed.\n";
        } else {
            $review = json_decode(file_get_contents($directory . '/preview.json'),true,512,JSON_THROW_ON_ERROR);
            $result = $migration->apply($backup,$review,$options['review'] ?? '');
            echo json_encode($result) . "\nExisting map IDs and files preserved. Legacy tables retained.\n";
        }
    }
} catch (Throwable $error) { fwrite(STDERR,'Migration failed: ' . $error->getMessage() . "\n"); exit(1); }
