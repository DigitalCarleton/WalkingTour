<?php
/**
 * Mall Map
 *
 * @copyright Copyright 2007-2012 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * The Mall Map plugin.
 *
 * @package Omeka\Plugins\Mall
 */


 if( !defined( 'MALLMAP_PLUGIN_DIR' ) )
 {
 	define( 'MALLMAP_PLUGIN_DIR', dirname( __FILE__ ) );
 }

class MallMapPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'install',
        'uninstall',
        'define_acl',
        'define_routes',
        'config',
        'config_form',
        'admin_head',
    		'admin_dashboard'
    );

    protected $_filters = array(
        'public_navigation_main',
        'admin_navigation_main',
        'admin_dashboard_stats',
        'search_record_types'
    );

    protected $_options = array(
        'mall_map_filter_tooltip' => '',
        'mall_map_tooltip_button' => 'OK'
    );

    public function hookInstall()
  	{
  		$db = $this->_db;

  		$tourQuery = "
           CREATE TABLE IF NOT EXISTS `$db->Tour` (
              `id` int( 10 ) unsigned NOT NULL auto_increment,
              `title` varchar( 255 ) collate utf8_unicode_ci default NULL,
              `description` text collate utf8_unicode_ci NOT NULL,
              `credits` text collate utf8_unicode_ci,
              `postscript_text` text collate utf8_unicode_ci,
              `featured` tinyint( 1 ) default '0',
              `public` tinyint( 1 ) default '0',
              PRiMARY KEY( `id` )
           ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";

  		$tourItemQuery = "
           CREATE TABLE IF NOT EXISTS `$db->TourItem` (
              `id` INT( 10 ) UNSIGNED NOT NULL AUTO_INCREMENT,
              `tour_id` INT( 10 ) UNSIGNED NOT NULL,
              `ordinal` INT NOT NULL,
              `item_id` INT( 10 ) UNSIGNED NOT NULL,
              PRIMARY KEY( `id` ),
              KEY `tour` ( `tour_id` )
           ) ENGINE=InnoDB ";

  		$db->query( $tourQuery );
  		$db->query( $tourItemQuery );
  	}

  	public function hookUninstall()
  	{
  		$db = $this->_db;
  		$db->query( "DROP TABLE IF EXISTS `$db->TourItem`" );
  		$db->query( "DROP TABLE IF EXISTS `$db->Tour`" );
  	}

  	public function hookDefineAcl( $args )
  	{
  		$acl = $args['acl'];

  		// Create the ACL context
      $acl->addResource( 'TourBuilder_Tours' );

  		// Allow anyone to look but not touch
  		$acl->allow( null, 'TourBuilder_Tours', array('browse', 'show') );

  		// Allow contributor (and better) to do anything with tours
  		$acl->allow( 'contributor','TourBuilder_Tours');

  	}

    /**
     * Display the plugin config form.
     */
    public function hookConfigForm()
    {
        require dirname(__FILE__) . '/config_form.php';
    }

    /**
     * Set the options from the config form input.
     */

    public function hookConfig()
    {
        set_option('mall_map_filter_tooltip', $_POST['mall_map_filter_tooltip']);
        set_option('mall_map_tooltip_button', $_POST['mall_map_tooltip_button']);
    }

    public function hookDefineRoutes($args)
    {
        $args['router']->addConfig( new Zend_Config_Ini(
            MALLMAP_PLUGIN_DIR .
            DIRECTORY_SEPARATOR .
            'routes.ini', 'routes' ) );
        if (is_admin_theme()) {
            return;
        }
        else {
          $args['router']->addRoute('mall_map',
              new Zend_Controller_Router_Route('map',
                  array(
                      'module' => 'mall-map',
                      'controller' => 'index',
                      'action' => 'index',
                  )
              )
          );
        }
    }

    public function filterAdminDashboardStats( $stats )
  	{
  		if( is_allowed( 'TourBuilder_Tours', 'browse' ) )
  		{
  			$stats[] = array( link_to( 'tours', array(),
  					total_records( 'Tours' ) ),
  				__('tours') );
  		}
  		return $stats;
  	}

  	public function hookAdminDashboard()
  	{
  		// Get the database.
  		$db = get_db();

  		// Get the Tour table.
  		$table = $db->getTable('Tour');

  		// Build the select query.
  		$select = $table->getSelect();

  		// Fetch some items with our select.
  		$results = $table->fetchObjects($select);

  		$tourItems = null;
  		$html  = null;

  		for($i=0;$i<=5;$i++){
  			if(array_key_exists($i,$results) && is_object($results[$i])){
  				$tourItems .='<div class="recent-row"><p class="recent"><a href="'.html_escape(url('tours/show/')).$results[$i]->id.'">'
  					.$results[$i]->title.'</a></p><p class="dash-edit"><a href="'.html_escape(url('tours/edit/')).$results[$i]->id.'">Edit</a></p></div>';
  			}
  		}

  		$html .= '<section class="five columns alpha"><div class="panel">';
  		$html .= '<h2>'.__('Recent Tours').'</h2>';
  		$html .= ''.$tourItems.'';
  		$html .= '<p><a class="add-new-item green button" href="'.html_escape(url('tours/add/')).'">'.__('Add a new tour').'</a></p>';
  		$html .= '</div></section>';

  		echo $html;
  	}

  	public function hookAdminHead()
  	{
  	    $request = Zend_Controller_Front::getInstance()->getRequest();
  	    $module = $request->getModuleName();
  	    $controller = $request->getControllerName();
        
  	    if ($module == 'mall-map' && $controller == 'tours')
        {
  	        queue_css_file('tour-1.7');
            queue_js_url('//code.jquery.com/jquery-migrate-3.0.0.min.js');
  	    }
  	}

    public function filterPublicNavigationMain($nav)
    {
        $nav[] = array('label' => 'Map', 'uri' => url('map'));
        return $nav;
    }

    public function filterSearchRecordTypes($recordTypes)
    {
  	    $recordTypes['Tour'] = __('Tour');
  	    return $recordTypes;
  	}


  	public function filterAdminNavigationMain( $nav )
  	{
  		$nav['Tours'] = array( 'label' => __('Walking Tours'),
  			'action' => 'browse',
  			'controller' => 'tours' );
  		return $nav;
  	}

}
include 'helpers/TourFunctions.php';
