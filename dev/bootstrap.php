<?php
// Run only inside the disposable Compose service, never against an existing site.
if (PHP_SAPI !== 'cli' || getenv('WALKINGTOUR_DEV') !== '1') {
    exit("This command requires the WalkingTour development container.\n");
}
set_exception_handler(function ($error) {
    fwrite(STDERR, (string) $error . "\n");
    exit(1);
});
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'localhost:8098';
require '/var/www/html/bootstrap.php';
$application = new Omeka_Application('development');
$application->bootstrap('Db');
$db = get_db();
$front = Zend_Controller_Front::getInstance();
$front->setControllerDirectory(CONTROLLER_DIR);
$front->setRequest(new Zend_Controller_Request_Http());
$front->getRouter()->addDefaultRoutes();

class WalkingTour_DevInstaller extends Installer_Default
{
    protected function _getValue($name)
    {
        $values = array(
            'username' => 'developer', 'password' => 'local-development-only',
            'super_email' => 'developer@example.com', 'administrator_email' => 'developer@example.com',
            'site_title' => 'WalkingTour Development', 'author' => 'WalkingTour',
            'description' => 'Disposable local validation site.', 'copyright' => '',
            'thumbnail_constraint' => 200, 'square_thumbnail_constraint' => 200,
            'fullsize_constraint' => 800, 'per_page_admin' => 10, 'per_page_public' => 10,
            'show_empty_elements' => 0, 'path_to_convert' => '/usr/bin'
        );
        return $values[$name];
    }
}
$installer = new WalkingTour_DevInstaller($db);
if (!$installer->isInstalled()) {
    $installer->install();
}
if (!$db->fetchOne("SELECT value FROM `{$db->Option}` WHERE name = 'omeka_version'")) {
    throw new RuntimeException('The disposable database contains an incomplete Omeka installation. Recreate the development volume before retrying.');
}
$application->bootstrap();
$broker = get_plugin_broker();
$loader = Zend_Registry::get('pluginloader');
$pluginInstaller = new Omeka_Plugin_Installer($broker, $loader);
foreach (array('Geolocation', 'WalkingTour') as $directory) {
    $plugin = $loader->getPlugin($directory);
    if (!$plugin) {
        $plugin = new Plugin;
        $plugin->name = $directory;
        $loader->registerPlugin($plugin);
    }
    Zend_Registry::get('plugin_ini_reader')->load($plugin);
    if (!$plugin->isInstalled()) {
        $pluginInstaller->install($plugin);
    } elseif ($plugin->hasNewVersion()) {
        $pluginInstaller->upgrade($plugin);
    }
}
set_option('walking_tour_center', '41.895, 12.48');
set_option('walking_tour_default_zoom', '13');
echo "Development site ready at http://localhost:8098/walking-tour\n";
