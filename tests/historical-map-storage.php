<?php
// Integration test using the real Omeka adapter and a private, temporary table prefix.
if (PHP_SAPI !== 'cli' || getenv('WALKINGTOUR_DEV') !== '1') {
    exit("Run this test inside the disposable development container.\n");
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'localhost:8098';
require '/var/www/html/bootstrap.php';
$app = new Omeka_Application('production');
$app->bootstrap('Db');
require_once dirname(__FILE__) . '/../WalkingTourPlugin.php';

class WalkingTour_StorageTestPlugin extends WalkingTourPlugin
{
    public function __construct($db) { $this->_db = $db; }
    protected function _installOptions() {}
    protected function _uninstallOptions() {}
}
function check($condition, $message)
{
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
$prefix = 'wt_test_' . bin2hex(random_bytes(4)) . '_';
$db = new Omeka_Db(get_db()->getAdapter(), $prefix);
$plugin = new WalkingTour_StorageTestPlugin($db);
$repository = new WalkingTour_HistoricalMapRepository($db);
try {
    $plugin->hookInstall();
    check(!$db->fetchOne("SHOW COLUMNS FROM `{$db->Tour}` LIKE 'route'"), 'The add-historic-maps1 Tour schema does not acquire a master-only route column');
    $maps = $repository->all();
    check(count($maps) === 1 && count($maps[0]['control_points']) === 10, 'Fresh installation seeds one map and ten pairs');
    check($maps[0]['image_width'] === 10268 && $maps[0]['image_height'] === 6368, 'Original image dimensions survive database serialization');
    $point = $maps[0]['control_points'][0];
    check($point['image_x'] === 1608.0 && $point['image_y'] === 3043.0 && abs($point['latitude'] - 41.911424357763764) < 1e-10, 'Image coordinates and latitude are not interchanged');
    $snapshot = $db->fetchOne("SELECT source_snapshot FROM `{$prefix}walking_tour_maps`");
    check($snapshot === file_get_contents(dirname(__FILE__) . '/../data/rome-allmaps-annotation.json'), 'Original provenance snapshot is preserved exactly');

    $db->query("INSERT INTO `{$db->Tour}` (title, description) VALUES ('Existing tour', 'Keep this tour')");
    $repository->uninstall();
    $plugin->hookUpgrade(array('old_version' => '0.2.3', 'new_version' => '1.1.1'));
    check(count($repository->all()[0]['control_points']) === 10, 'Upgrade from 0.2.3 adds the catalog');
    check($db->fetchOne("SELECT title FROM `{$db->Tour}`") === 'Existing tour', 'Upgrade preserves existing tours');

    $db->query("UPDATE `{$prefix}walking_tour_control_points` SET image_x = 42 WHERE ordinal = 1");
    $db->query("DELETE FROM `{$prefix}walking_tour_control_points` WHERE ordinal = 2");
    $plugin->hookUpgrade(array('old_version' => '0.2.3', 'new_version' => '1.1.1'));
    $maps = $repository->all();
    check(count($maps) === 1 && count($maps[0]['control_points']) === 9 && $maps[0]['control_points'][0]['image_x'] === 42.0, 'Repeated upgrade neither duplicates nor overwrites edits and deletions');
    $db->query("DELETE FROM `{$prefix}walking_tour_control_points`");
    $repository->install();
    check(count($repository->all()[0]['control_points']) === 0, 'A deliberately empty calibration is not silently reseeded');
} finally {
    $plugin->hookUninstall();
}
