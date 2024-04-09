<?php
/**
 * Walking Tour
 *
 * @copyright Copyright 2007-2012 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * The Waling Tour plugin.
 *
 * @package Omeka\Plugins\Mall
 */


 if( !defined( 'WALKINGTOUR_PLUGIN_DIR' ) )
 {
 	define( 'WALKINGTOUR_PLUGIN_DIR', dirname( __FILE__ ) );
 }

class WalkingTourPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'install',
        'uninstall',
        'define_acl',
        'upgrade',
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
        'walking_tour_filter_tooltip' => '',
        'walking_tour_tooltip_button' => 'OK'
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
              `color` text collate utf8_unicode_ci,
              PRiMARY KEY( `id` )
           ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";

  		$tourItemQuery = "
           CREATE TABLE IF NOT EXISTS `$db->TourItem` (
              `id` INT( 10 ) UNSIGNED NOT NULL AUTO_INCREMENT,
              `tour_id` INT( 10 ) UNSIGNED NOT NULL,
              `ordinal` INT NOT NULL,
              `item_id` INT( 10 ) UNSIGNED NOT NULL,
              `exhibit_id` INT NOT NULL,
              PRIMARY KEY( `id` ),
              KEY `tour` ( `tour_id` )
           ) ENGINE=InnoDB ";

  		$db->query( $tourQuery );
  		$db->query( $tourItemQuery );
        $this->_installOptions();
  	}

  	public function hookUninstall()
  	{
  		$db = $this->_db;
  		$db->query( "DROP TABLE IF EXISTS `$db->TourItem`" );
  		$db->query( "DROP TABLE IF EXISTS `$db->Tour`" );
        $this->_uninstallOptions();
  	}

    public function hookUpgrade( $args )
    {
        if (version_compare($args['old_version'], '0.1-dev', "<")){
            $sql = "ALTER TABLE `{$db->prefix}tour_items` MODIFY COLUMN `exhibit_id` INT NOT NULL;";
            $this->_db->query($sql);
        }
    }

  	public function hookDefineAcl( $args )
  	{
  		$acl = $args['acl'];

  		// Create the ACL context
      $acl->addResource( 'WalkingTourBuilder_Tours' );

  		// Allow anyone to look but not touch
  		$acl->allow( null, 'WalkingTourBuilder_Tours', array('browse', 'show') );

  		// Allow contributor (and better) to do anything with tours
  		$acl->allow( 'contributor','WalkingTourBuilder_Tours');

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
        set_option('walking_tour_filter_tooltip', $_POST['walking_tour_filter_tooltip']);
        set_option('walking_tour_tooltip_button', $_POST['walking_tour_tooltip_button']);
        set_option('walking_tour_center', $_POST['walking_tour_center']);
        set_option('walking_tour_default_zoom', $_POST['walking_tour_default_zoom']);
        set_option('walking_tour_max_zoom', $_POST['walking_tour_max_zoom']);
        set_option('walking_tour_min_zoom', $_POST['walking_tour_min_zoom']);
        set_option('walking_tour_max_bounds', $_POST['walking_tour_max_bounds']);
    }

    public function hookDefineRoutes($args)
    {
        $args['router']->addConfig( new Zend_Config_Ini(
            WALKINGTOUR_PLUGIN_DIR .
            DIRECTORY_SEPARATOR .
            'routes.ini', 'routes' ) );
        if (is_admin_theme()) {
            return;
        }
        else {
          $args['router']->addRoute('walking_tour',
              new Zend_Controller_Router_Route('walking-tour',
                  array(
                      'module' => 'walking-tour',
                      'controller' => 'index',
                      'action' => 'index',
                  )
              )
          );
        }
    }

    public function filterAdminDashboardStats( $stats )
    {
        if( is_allowed( 'WalkingTourBuilder_Tours', 'browse' ) )
        {
            if(version_compare(OMEKA_VERSION,'3.1') >= 0){
                $stats['tours'] = array(total_records( 'Tours' ), __('tours') );
            }else{
                $stats[] = array( link_to( 'tours', array(), total_records( 'Tours' ) ), __('tours') );
            }
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

  		$html .= '<section class="five columns alpha panel">';
  		$html .= '<h2>'.__('Recent Tours').'</h2>';
  		$html .= ''.$tourItems.'';
  		$html .= '<p><a class="add-new-item green button" href="'.html_escape(url('tours/add/')).'">'.__('Add a new tour').'</a></p>';
  		$html .= '</section>';

  		echo $html;
  	}

  	public function hookAdminHead()
  	{
  	    $request = Zend_Controller_Front::getInstance()->getRequest();
  	    $module = $request->getModuleName();
  	    $controller = $request->getControllerName();
        
  	    if ($module == 'walking-tour' && $controller == 'tours')
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
