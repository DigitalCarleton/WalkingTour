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
        foreach ($results as $tour) {
            if ($tour['public'] == 1 || current_user()->role == "super") {
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

        // Set the JS and CSS files.
        $this->view->headScript()
            ->appendFile('//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js')
            ->appendFile('//ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/jquery-ui.min.js')
            ->appendFile(src('jquery.cookie', 'javascripts', 'js'))
            ->appendFile(src('/leaflet/leaflet', 'javascripts', 'js'))
            ->appendFile(src('modernizr.custom.63332', 'javascripts', 'js'))
            ->appendFile(src('Polyline.encoded', 'javascripts', 'js'))
            ->appendFile('//cdn.jsdelivr.net/npm/@allmaps/leaflet/dist/bundled/allmaps-leaflet-1.9.umd.js')
            ->appendFile(src('walking-tour', 'javascripts', 'js'));
        $this->view->headLink()
            ->appendStylesheet('//code.jquery.com/ui/1.10.2/themes/smoothness/jquery-ui.css', 'all')
            // ->appendStylesheet('//cdn.leafletjs.com/leaflet-0.7/leaflet.css', 'all')
            // ->appendStylesheet('//cdn.leafletjs.com/leaflet-0.7/leaflet.ie.css', 'all', 'lte IE 8')
            ->appendStylesheet(src('walking-tour', 'css', 'css'));
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
        $returnArray['walking_tour_locate_bounds'] = get_option('walking_tour_locate_bounds');
        $returnArray['walking_tour_max_locate_meters'] = get_option('walking_tour_max_locate_meters');

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

            $tourItemsID = implode(", ", $item_array);
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
