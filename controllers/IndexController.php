<?php
/**
 * Walking Tour
 *
 * @copyright Copyright 2007-2012 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * The Walking Tour controller
 *
 * @package Omeka\Plugins\Mall
 */
class WalkingTour_IndexController extends Omeka_Controller_AbstractActionController
{
    /**
     * Return an associative array of public tours
     * 
     */
    public function publicTours()
    {
        // Get the database.
        $db = get_db();
        // Get the Tour table.
        $tour_table = $db->getTable('Tour');
        // Build the select query.
        $select = $tour_table->getSelect();
        // Fetch some items with our select.
        $results = $tour_table->fetchObjects($select);
        // Build an array with 
        $_tourTypes = array('id' => array(), 'color' => array());
        $user = current_user();
        foreach ($results as $tour) {
            if ($tour['public'] == 1 || ($user && $user->role == "super")) {
                $_tourTypes['id'][$tour['id']] = $tour['title'];
                $_tourTypes['color'][$tour['id']] = $tour['color'];
                $_tourTypes['description'][$tour['id']] = $tour['description'];
                $_tourTypes['credits'][$tour['id']] = $tour['credits'];
            }
        }

        return $_tourTypes;
    }

    /**
     * Display the map.
     */
    public function indexAction()
    {
        $_tourTypes = $this->publicTours();
        $this->view->tour_types = $_tourTypes;

        // Refresh viewer assets after deployment without requiring a database upgrade.
        $assetVersion = get_plugin_ini('WalkingTour', 'version') . '-' .
            max(array_map('filemtime', array(
                WALKINGTOUR_PLUGIN_DIR . '/views/public/javascripts/walking-tour.js',
                WALKINGTOUR_PLUGIN_DIR . '/views/public/javascripts/historical-maps.js',
                WALKINGTOUR_PLUGIN_DIR . '/views/public/javascripts/historical-map-editor.js',
                WALKINGTOUR_PLUGIN_DIR . '/views/public/javascripts/map-place-search.js',
                WALKINGTOUR_PLUGIN_DIR . '/views/public/css/historical-maps.css'
            )));

        // Set the JS and CSS files.
        $this->view->headScript()
            ->appendFile('//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js')
            ->appendFile('//ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/jquery-ui.min.js')
            ->appendFile(src('jquery.cookie', 'javascripts', 'js'))
            ->appendFile(src('leaflet/leaflet', 'javascripts', 'js'))
            ->appendFile(src('modernizr.custom.63332', 'javascripts', 'js'))
            ->appendFile(src('Polyline.encoded', 'javascripts', 'js'))
            ->appendFile(src('map-place-search', 'javascripts', 'js', $assetVersion))
            ->appendFile(src('historical-map-editor', 'javascripts', 'js', $assetVersion))
            ->appendFile(src('historical-maps', 'javascripts', 'js', $assetVersion))
            ->appendFile(src('walking-tour', 'javascripts', 'js', $assetVersion));
        $this->view->headLink()
            ->appendStylesheet('//code.jquery.com/ui/1.10.2/themes/smoothness/jquery-ui.css', 'all')
            // ->appendStylesheet('//cdn.leafletjs.com/leaflet-0.7/leaflet.css', 'all')
            // ->appendStylesheet('//cdn.leafletjs.com/leaflet-0.7/leaflet.ie.css', 'all', 'lte IE 8')
            ->appendStylesheet(src('walking-tour', 'css', 'css'))
            ->appendStylesheet(src('leaflet/leaflet', 'javascripts', 'css'))
            ->appendStylesheet(src('historical-maps', 'css', 'css', $assetVersion));
    }

    public function historicalMapsAction()
    {
        $this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
        if (!$this->getRequest()->isGet()) {
            $this->getResponse()->setHttpResponseCode(405)->setHeader('Allow', 'GET', true);
            $this->_helper->json(array('error' => 'Use GET to browse historical maps.'));
            return;
        }
        try {
            require_once dirname(__FILE__) . '/../models/HistoricalMapRepository.php';
            $repository = new WalkingTour_HistoricalMapRepository(get_db());
            $this->_helper->json(array('maps' => $repository->all(),
                'can_edit' => $this->canEditHistoricalMaps(),
                'csrf_token' => $this->canEditHistoricalMaps() ? (new Omeka_Form_SessionCsrf())->getElement('csrf_token')->getToken() : null));
        } catch (Exception $exception) {
            _log($exception, Zend_Log::ERR);
            $this->getResponse()->setHttpResponseCode(503);
            $this->_helper->json(array('error' => 'Historical maps are unavailable. Please try again later.'));
        }
    }

    private function canEditHistoricalMaps()
    {
        return current_user() && is_allowed('WalkingTourBuilder_Tours', 'edit');
    }

    public function historicalMapEstimateAction()
    {
        $this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
        if (!$this->getRequest()->isGet()) {
            $this->getResponse()->setHttpResponseCode(405)->setHeader('Allow', 'GET', true);
            $this->_helper->json(array('error' => 'Use GET to estimate a position.'));
            return;
        }
        try {
            $x = $this->getRequest()->getQuery('x'); $y = $this->getRequest()->getQuery('y');
            if (!is_numeric($x) || !is_numeric($y)) { throw new InvalidArgumentException('Two numeric coordinates are required.'); }
            $repository = new WalkingTour_HistoricalMapRepository(get_db());
            $map = $repository->find($this->getRequest()->getQuery('map_id'));
            $point = $repository->estimate($map, $this->getRequest()->getQuery('side'), array((float) $x, (float) $y));
            $this->_helper->json(array('point' => $point, 'revision' => $map['revision']));
        } catch (InvalidArgumentException $error) {
            $this->getResponse()->setHttpResponseCode(422);
            $this->_helper->json(array('error' => $error->getMessage()));
        } catch (Exception $error) {
            _log($error, Zend_Log::ERR);
            $this->getResponse()->setHttpResponseCode(503);
            $this->_helper->json(array('error' => 'Position estimates are temporarily unavailable.'));
        }
    }

    public function historicalMapEditAction()
    {
        $this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
        if (!$this->getRequest()->isPost()) {
            $this->getResponse()->setHttpResponseCode(405)->setHeader('Allow', 'POST', true);
            $this->_helper->json(array('error' => 'Use POST to save a map edit.'));
            return;
        }
        if (!$this->canEditHistoricalMaps()) {
            $this->getResponse()->setHttpResponseCode(403);
            $this->_helper->json(array('error' => 'Sign in with an editor account to save map edits. Your draft has been kept.'));
            return;
        }
        $raw = $this->getRequest()->getRawBody();
        $data = strlen($raw) <= 65536 ? json_decode($raw, true) : null;
        $csrf = new Omeka_Form_SessionCsrf();
        if (!is_array($data) || !$csrf->isValid(array('csrf_token' => $data['csrf_token'] ?? null))) {
            $this->getResponse()->setHttpResponseCode(403);
            $this->_helper->json(array('error' => 'Your editing session is invalid. Reload the page and sign in again.'));
            return;
        }
        try {
            $repository = new WalkingTour_HistoricalMapRepository(get_db());
            $map = $repository->mutate($data['map_id'] ?? null, $data['revision'] ?? null, $data['operation'] ?? null, $data);
            $this->_helper->json(array('map' => $map));
        } catch (InvalidArgumentException $error) {
            $this->getResponse()->setHttpResponseCode(422);
            $this->_helper->json(array('error' => $error->getMessage()));
        } catch (Exception $error) {
            $code = $error->getCode() === 409 ? 409 : 503;
            if ($code === 503) { _log($error, Zend_Log::ERR); }
            $this->getResponse()->setHttpResponseCode($code);
            $this->_helper->json(array('error' => $code === 409 ? $error->getMessage() : 'The edit could not be saved. Your draft has been kept.'));
        }
    }

    public function mapConfigAction()
    {
        // Process only AJAX requests.
        if (!$this->_request->isXmlHttpRequest()) {
            throw new Omeka_Controller_Exception_403;
        }

        $returnArray = array();
        $returnArray['walking_tour_center'] = get_option('walking_tour_center');
        $returnArray['walking_tour_default_zoom'] = get_option('walking_tour_default_zoom');
        $returnArray['walking_tour_max_zoom'] = get_option('walking_tour_max_zoom');
        $returnArray['walking_tour_min_zoom'] = get_option('walking_tour_min_zoom');
        $returnArray['walking_tour_max_bounds'] = get_option('walking_tour_max_bounds');
        $returnArray['walking_tour_exhibit_button'] = get_option('walking_tour_exhibit_button');
        $returnArray['walking_tour_detail_button'] = get_option('walking_tour_detail_button');

        $this->_helper->json($returnArray);
    }

    /* 
     *  Beginning to separate tours into separate features
     */
    public function queryAction()
    {
        // Process only AJAX requests.
        if (!$this->_request->isXmlHttpRequest()) {
            throw new Omeka_Controller_Exception_403;
        }

        $db = $this->_helper->db->getDb();
        $joins = array("$db->Item AS items ON items.id = locations.item_id");
        $wheres = array("items.public = 1");
        $prefix = $db->prefix;

        // Filter public tours' items
        $request_tour_id = $this->publicTours();
        $colorArray = array();

        $tourItemTable = $db->getTable('TourItem');
        $tourItemsIDs = array();
        $returnArray = array();
        foreach ($request_tour_id['id'] as $tour_id => $tour_title) {
            if ($tour_id != 0) {
                $tourItemsDat = $tourItemTable->fetchObjects("SELECT item_id FROM " . $prefix . "tour_items 
                                                            WHERE tour_id = $tour_id");
            } else {
                $tourItemsDat = $tourItemTable->fetchObjects("SELECT item_id FROM " . $prefix . "tour_items");
            }
            $tourItemsIDs[$tour_id] = array();
            foreach ($tourItemsDat as $dat) {
                array_push($tourItemsIDs[$tour_id], (int) $dat["item_id"]);
            }
        }

        foreach ($tourItemsIDs as $tour_id => $item_array) {

            $tourItemsID = $item_array ? implode(", ", $item_array) : 'NULL';
            $wheres = array("items.public = 1");
            $wheres[] = $db->quoteInto("items.id IN ($tourItemsID)", Zend_Db::INT_TYPE);

            $sql = "SELECT items.id, locations.latitude, locations.longitude\nFROM $db->Location AS locations";
            foreach ($joins as $join) {
                $sql .= "\nJOIN $join";
            }
            foreach ($wheres as $key => $where) {
                $sql .= (0 == $key) ? "\nWHERE" : "\nAND";
                $sql .= " ($where)";
            }
            $sql .= "\nGROUP BY items.id";

            $dbItems = $db->query($sql)->fetchAll();
            $orderedItems = array();

            // orders items to match the order of the tour
            for ($i = 0; $i < count($item_array); $i++) {
                for ($j = 0; $j < count($dbItems); $j++) {
                    if ($item_array[$i] == $dbItems[$j]['id']) {
                        array_push($orderedItems, $dbItems[$j]);
                    }
                }
            }
            // Build geoJSON: http://www.geojson.org/geojson-spec.html
            $returnArray[$tour_id]["Data"] = array('type' => 'FeatureCollection', 'features' => array());
            foreach ($orderedItems as $row) {
                $returnArray[$tour_id]["Data"]['features'][] = array(
                    'type' => 'Feature',
                    'geometry' => array(
                        'type' => 'Point',
                        'coordinates' => array($row['longitude'], $row['latitude']),
                    ),
                    'properties' => array(
                        'id' => $row['id'],
                        "marker-color" => $request_tour_id['color'][$tour_id]
                    ),
                );
            }
            $returnArray[$tour_id]["Color"] = $request_tour_id['color'][$tour_id];
            $returnArray[$tour_id]["Tour Name"] = $request_tour_id['id'][$tour_id];
            $returnArray[$tour_id]["Description"] = $request_tour_id['description'][$tour_id];
            $returnArray[$tour_id]["Credits"] = $request_tour_id['credits'][$tour_id];
        }
        $this->_helper->json($returnArray);

    }

    /**
     * Get data about the selected item.
     */
    public function getItemAction()
    {
        // Process only AJAX requests.
        if (!$this->_request->isXmlHttpRequest()) {
            throw new Omeka_Controller_Exception_403;
        }
        $item_id = $this->_request->getParam('id');
        $tour_id = $this->_request->getParam('tour');

        $db = $this->_helper->db->getDb();
        $tourItemTable = $db->getTable('TourItem');
        $prefix = $db->prefix;


        $tourItem = $tourItemTable->fetchObjects("SELECT * FROM " . $prefix . "tour_items 
                                                            WHERE tour_id = $tour_id AND item_id = $item_id");

        $exhibit_id = $tourItem[0]["exhibit_id"];

        $item = get_record_by_id('item', $item_id);
        $data = array(
            'id' => $item->id,
            'title' => metadata($item, array('Dublin Core', 'Title')),
            'description' => metadata($item, array('Dublin Core', 'Description'), array('no-escape' => true)),
            // 'abstract' => metadata($item, array('Dublin Core', 'Abstract'), array('no-escape' => true)),
            'date' => metadata($item, array('Dublin Core', 'Date'), array('all' => true)),
            'thumbnail' => item_image('square_thumbnail', array(), 0, $item),
            'fullsize' => item_image('fullsize', array('style' => 'max-width: 100%; height: auto;'), 0, $item),
            'url' => url(
                array(
                    'module' => 'default',
                    'controller' => 'items',
                    'action' => 'show',
                    'id' => $item['id']
                ),
                'id'
            ),
            "exhibitUrl" => ""
        );
        if (plugin_is_active('DublinCoreExtended')) {
            $data['abstract'] = metadata($item, array('Dublin Core', 'Abstract'), array('no-escape' => true));
        }
        if (plugin_is_active('ExhibitBuilder')) {
            $exhibit = get_records('Exhibit', array('id' => $exhibit_id));
            if ($exhibit && count($exhibit) == 1) {
                $data["exhibitUrl"] = exhibit_builder_exhibit_uri($exhibit[0]);
            }
        }
        $this->_helper->json($data);
    }
}
