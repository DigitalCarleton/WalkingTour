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
        // Preserve bookmarks created while the historical editor lived here.
        $mapId = $this->getRequest()->getQuery('map_id');
        if (is_string($mapId) && ctype_digit($mapId) && (int) $mapId > 0) {
            $parameters = array('map_id' => $mapId);
            $this->_redirect(public_url('historical-maps') . '?' . http_build_query($parameters), array('prependBase' => false));
            return;
        }
        $_tourTypes = $this->publicTours();
        $this->view->tour_types = $_tourTypes;

        $this->viewerAssets(array('live-map-places', 'walking-tour'), array('walking-tour'));
    }

    /** Public Historical Maps viewer; the similarly named action below is the JSON catalog. */
    public function mapViewerAction()
    {
        $this->viewerAssets(
            array('allmaps-transform', 'historical-map-cluster', 'map-place-search', 'historical-map-places', 'historical-maps', 'historical-map-page'),
            array('walking-tour', 'historical-map-cluster', 'historical-maps')
        );
    }

    private function viewerAssets($scripts, $styles)
    {
        $files = array();
        foreach ($scripts as $script) { $files[] = WALKINGTOUR_PLUGIN_DIR . '/views/public/javascripts/' . $script . '.js'; }
        foreach ($styles as $style) { $files[] = WALKINGTOUR_PLUGIN_DIR . '/views/public/css/' . $style . '.css'; }
        $assetVersion = get_plugin_ini('WalkingTour', 'version') . '-' . max(array_map('filemtime', $files));
        $this->view->headScript()
            ->appendFile('//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js')
            ->appendFile(src('leaflet/leaflet', 'javascripts', 'js'));
        if (in_array('walking-tour', $scripts, true)) {
            $this->view->headScript()
                ->appendFile('//ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/jquery-ui.min.js')
                ->appendFile(src('jquery.cookie', 'javascripts', 'js'))
                ->appendFile(src('modernizr.custom.63332', 'javascripts', 'js'))
                ->appendFile(src('Polyline.encoded', 'javascripts', 'js'));
            $this->view->headLink()->appendStylesheet('//code.jquery.com/ui/1.10.2/themes/smoothness/jquery-ui.css', 'all');
        }
        foreach ($scripts as $script) { $this->view->headScript()->appendFile(src($script, 'javascripts', 'js', $assetVersion)); }
        $this->view->headLink()->appendStylesheet(src('leaflet/leaflet', 'javascripts', 'css'));
        foreach ($styles as $style) { $this->view->headLink()->appendStylesheet(src($style, 'css', 'css', $assetVersion)); }
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
            $this->_helper->json(array('maps' => $repository->catalog(), 'can_edit' => false, 'workflow' => 'allmaps'));
        } catch (Exception $exception) {
            _log($exception, Zend_Log::ERR);
            $this->getResponse()->setHttpResponseCode(503);
            $this->_helper->json(array('error' => 'Historical maps are unavailable. Please try again later.'));
        }
    }

    public function historicalMapPlacesAction()
    {
        $this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
        if (!$this->getRequest()->isGet()) { $this->getResponse()->setHttpResponseCode(405)->setHeader('Allow', 'GET', true); $this->_helper->json(array('error'=>'Use GET to browse places.')); return; }
        try {
            require_once dirname(__FILE__) . '/../models/HistoricalMapPlaces.php';
            $cursor = $this->getRequest()->getQuery('after', '0');
            if (!is_string($cursor) || !ctype_digit($cursor)) { throw new InvalidArgumentException('Use a valid Item cursor.'); }
            $this->_helper->json((new WalkingTour_HistoricalMapPlaces(get_db()))->page((int) $cursor));
        } catch (Exception $error) {
            $this->getResponse()->setHttpResponseCode($error instanceof InvalidArgumentException ? 422 : 503);
            $this->_helper->json(array('error'=>$error instanceof InvalidArgumentException ? $error->getMessage() : 'Places are unavailable. Ask the administrator to enable Geolocation and check its location storage.'));
        }
    }

    private function placeEditorAllowed()
    {
        return current_user() && is_allowed('WalkingTourBuilder_Tours', 'edit');
    }

    public function historicalMapDecisionsAction()
    {
        $this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
        if (!$this->getRequest()->isGet()) { $this->getResponse()->setHttpResponseCode(405)->setHeader('Allow', 'GET', true); $this->_helper->json(array('error'=>'Use GET to read decisions.')); return; }
        try {
            require_once dirname(__FILE__) . '/../models/HistoricalMapRepository.php';
            $id = $this->getRequest()->getQuery('map_id');
            if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) { throw new InvalidArgumentException('Choose a historical map.'); }
            $map = (new WalkingTour_HistoricalMapRepository(get_db()))->find((int) $id);
            $canEdit = $this->placeEditorAllowed() && $map['source_kind'] === 'allmaps';
            $result = array('revision'=>$map['revision'], 'decisions'=>(new WalkingTour_HistoricalMapDecisions(get_db()))->publicRows($map['id']), 'can_edit'=>(bool) $canEdit);
            if ($canEdit) { $csrf = new Omeka_Form_SessionCsrf; $result['csrf_token'] = $csrf->getElement('csrf_token')->getToken(); }
            $this->_helper->json($result);
        } catch (Exception $error) { $this->placeDecisionError($error); }
    }

    public function historicalMapDecisionAction()
    {
        $this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
        if (!$this->getRequest()->isPost()) { $this->getResponse()->setHttpResponseCode(405)->setHeader('Allow', 'POST', true); $this->_helper->json(array('error'=>'Use POST to save a place decision.')); return; }
        if (!$this->placeEditorAllowed()) { $this->getResponse()->setHttpResponseCode(403); $this->_helper->json(array('error'=>'Sign in with map editing permission to save decisions.')); return; }
        try {
            $body = $this->getRequest()->getRawBody();
            if (strlen($body) > 16384) { throw new InvalidArgumentException('The place decision request is too large.'); }
            $data = json_decode($body, true);
            if (!is_array($data) || !array_key_exists('value', $data)) { throw new InvalidArgumentException('Submit a JSON place decision with an explicit value.'); }
            $csrf = new Omeka_Form_SessionCsrf;
            if (!$csrf->isValid($data)) { $this->getResponse()->setHttpResponseCode(403); $this->_helper->json(array('error'=>'Your editing session expired. Sign in again and reload the page. Your draft has not been saved.')); return; }
            require_once dirname(__FILE__) . '/../models/HistoricalMapDecisions.php';
            $this->_helper->json((new WalkingTour_HistoricalMapDecisions(get_db()))->save($data['map_id'] ?? null, $data['revision'] ?? null,
                $data['item_id'] ?? null, $data['operation'] ?? null, $data['value'] ?? null));
        } catch (Exception $error) { $this->placeDecisionError($error); }
    }

    private function placeDecisionError($error)
    {
        $status = $error instanceof InvalidArgumentException ? 422 : ($error->getCode() === 409 ? 409 : 503);
        $this->getResponse()->setHttpResponseCode($status);
        if ($status === 503) { _log($error, Zend_Log::ERR); }
        $this->_helper->json(array('error'=>$status === 503 ? 'Place decisions are unavailable. Ask the administrator to run initialize-map-storage.php and check Geolocation storage.' : $error->getMessage()));
    }

    public function historicalMapEstimateAction()
    {
        $this->retiredHistoricalMapAction();
    }

    public function historicalMapEditAction()
    {
        $this->retiredHistoricalMapAction();
    }

    private function retiredHistoricalMapAction()
    {
        $this->getResponse()->setHeader('Cache-Control', 'private, no-store', true)->setHttpResponseCode(410);
        $this->_helper->json(array('error' => 'The website calibration editor is retired. Georeference the map in Allmaps and import its annotation URL.'));
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
