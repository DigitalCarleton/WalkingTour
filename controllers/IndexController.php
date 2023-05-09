<?php
/**
 * Mall Map
 *
 * @copyright Copyright 2007-2012 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * The Mall Map controller
 *
 * @package Omeka\Plugins\Mall
 */
class MallMap_IndexController extends Omeka_Controller_AbstractActionController
{
    /**
     * Filterable item type IDs
     */
    const ITEM_TYPE_ID_DOCUMENT     = 1;
    const ITEM_TYPE_ID_MOVING_IMAGE = 3;
    const ITEM_TYPE_ID_SOUND        = 5;
    const ITEM_TYPE_ID_STILL_IMAGE  = 6;
    const ITEM_TYPE_ID_EVENT        = 8;

    // Changed because original IDs were hard coded by the Omeka team,
    // and our IDs were different from theirs
    // const ITEM_TYPE_ID_PLACE        = 14;
    const ITEM_TYPE_ID_PLACE        = 18;

    /**
     * Filterable element IDs
     */
    const ELEMENT_ID_EVENT_TYPE   = 29;
    const ELEMENT_ID_MAP_COVERAGE = 38;

    // Same change as above
    // const ELEMENT_ID_PLACE_TYPE   = 87;
    const ELEMENT_ID_PLACE_TYPE   = 51;

    /**
     * @var array Filterable item types in display order
     */
    // public $_itemTypes = array(
    //     self::ITEM_TYPE_ID_PLACE        => 'Place',
    //     self::ITEM_TYPE_ID_EVENT        => 'Event',
    //     self::ITEM_TYPE_ID_DOCUMENT     => 'Document',
    //     self::ITEM_TYPE_ID_STILL_IMAGE  => 'Image', // Still Image
    //     self::ITEM_TYPE_ID_MOVING_IMAGE => 'Video', // Moving Image
    //     self::ITEM_TYPE_ID_SOUND        => 'Audio', // Sound
    // );

    /**
     * @var array Data used when adding the historic map layer.
     */
     //Lets you specify the map tiles: located in the /omeka/plugins/MallMap/maps folder.
     // However, we must tile the map since they have {z}/{x}/{y} - can use https://www.maptiler.com to do this.
    private $_historicMapData = array(
        'Pre-1800s' => array(
            'url' => '/mallhistory/plugins/MallMap/maps/1791/{z}/{x}/{y}.jpg',
            'title' => 'Map by Faehtz, E.F.M. (1791)',
        ),
        '1800-1829' => array(
            'url' => '/mallhistory/plugins/MallMap/maps/1828/{z}/{x}/{y}.jpg',
            'title' => 'Map by Elliot, William (1828)',
        ),
        '1830-1859' => array(
            'url' => '/mallhistory/plugins/MallMap/maps/1858/{z}/{x}/{y}.jpg',
            'title' => 'Map by Boschke, A. (1858)',
        ),
        '1860-1889' => array(
            'url' => '/mallhistory/plugins/MallMap/maps/1887/{z}/{x}/{y}.jpg',
            'title' => 'Map by Silversparre, Axel (1887)',
        ),
        '1890-1919' => array(
            'url' => '/mallhistory/plugins/MallMap/maps/1917/{z}/{x}/{y}.jpg',
            'title' => 'Map by U.S. Public Buildings Commission (1917)',
        ),
        '1920-1949' => array(
            'url' => '/mallhistory/plugins/MallMap/maps/1942/{z}/{x}/{y}.jpg',
            'title' => 'Map by General Drafting Company (1942)',
        ),
        '1950-1979' => array(
            'url' => '/mallhistory/plugins/MallMap/maps/1978/{z}/{x}/{y}.jpg',
            'title' => 'Map by Alexandria Drafting Company (1978)',
        ),
        '1980-1999' => array(
            'url' => '/mallhistory/plugins/MallMap/maps/1996/{z}/{x}/{y}.jpg',
            'title' => 'Map by Joseph Passonneau and Partners (1996)',
        ),
        //'2000-present' => array('url' => null, 'title' => null),
    );

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
        foreach ($results as $tour){
          if($tour['public']==1){
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
        //calls down the data table of the Simple Vocab plugin
        $simpleVocabTerm = $this->_helper->db->getTable('SimpleVocabTerm');
        $mapCoverages = $simpleVocabTerm->findByElementId(self::ELEMENT_ID_MAP_COVERAGE);
        /* REMOVING ADDITIONAL SIMPLE VOCAB FILTERS -AM */
        // $placeTypes = $simpleVocabTerm->findByElementId(self::ELEMENT_ID_PLACE_TYPE);
        // $eventTypes = $simpleVocabTerm->findByElementId(self::ELEMENT_ID_EVENT_TYPE);

        $_tourTypes = $this->publicTours();

        $this->view->tour_types = $_tourTypes;
        // $this->view->item_types = $this->_itemTypes;
        /* REMOVING ADDITIONAL SIMPLE VOCAB FILTERS -AM */
        // if ($mapCoverages && $placeTypes && $eventTypes) {
            // $this->view->place_types = explode("\n", $placeTypes->terms);
            // $this->view->event_types = explode("\n", $eventTypes->terms);
        if ($mapCoverages) {
            $this->view->map_coverages = explode("\n", $mapCoverages->terms);
        }

        // Set the JS and CSS files.
        $this->view->headScript()
            ->appendFile('//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js')
            ->appendFile('//ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/jquery-ui.min.js')
            ->appendFile(src('jquery.cookie', 'javascripts', 'js'))
            ->appendFile('//cdn.leafletjs.com/leaflet-0.7/leaflet.js')
            ->appendFile(src('modernizr.custom.63332', 'javascripts', 'js'))
            ->appendFile(src('Polyline.encoded', 'javascripts', 'js'))
            ->appendFile(src('mall-map', 'javascripts', 'js'));
        $this->view->headLink()
            ->appendStylesheet('//code.jquery.com/ui/1.10.2/themes/smoothness/jquery-ui.css', 'all')
            ->appendStylesheet('//cdn.leafletjs.com/leaflet-0.7/leaflet.css', 'all')
            ->appendStylesheet('//cdn.leafletjs.com/leaflet-0.7/leaflet.ie.css', 'all', 'lte IE 8')
            ->appendStylesheet(src('mall-map', 'css', 'css'));
    }

    public function mapConfigAction() 
    {
        // Process only AJAX requests.
        if (!$this->_request->isXmlHttpRequest()) {
            throw new Omeka_Controller_Exception_403;
        }

        $returnArray = array();
        $returnArray['mall_map_center'] = get_option('mall_map_center');
        $returnArray['mall_map_default_zoom'] = get_option('mall_map_default_zoom');
        $returnArray['mall_map_max_zoom'] = get_option('mall_map_max_zoom');
        $returnArray['mall_map_min_zoom'] = get_option('mall_map_min_zoom');
        $returnArray['mall_map_max_bounds'] = get_option('mall_map_max_bounds');
        $returnArray['mall_map_locate_bounds'] = get_option('mall_map_locate_bounds');
        $returnArray['mall_map_max_locate_meters'] = get_option('mall_map_max_locate_meters');

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

        // Filter public tours' items
        $request_tour_id = $this->publicTours();
        $colorArray = array();

        $tourItemTable = $db->getTable( 'TourItem' );
        $tourItemsIDs = array();
        $returnArray = array();
        foreach($request_tour_id['id'] as $tour_id => $tour_title){
            if($tour_id != 0){
                $tourItemsDat = $tourItemTable->fetchObjects( "SELECT item_id FROM omeka_tour_items 
                                                            WHERE tour_id = $tour_id");
            } else {
                $tourItemsDat = $tourItemTable->fetchObjects( "SELECT item_id FROM omeka_tour_items");
            }
            $tourItemsIDs[$tour_id] = array();
            foreach ($tourItemsDat as $dat){
                array_push($tourItemsIDs[$tour_id], (int) $dat["item_id"]);
            }
        }

        foreach($tourItemsIDs as $tour_id => $item_array){

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
                        array_push( $orderedItems, $dbItems[$j] );
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
                        "marker-color"=> $request_tour_id['color'][$tour_id]
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
     * Get data about the selected historical map.
     */
    public function historicMapDataAction()
    {
        // Process only AJAX requests.
        if (!$this->_request->isXmlHttpRequest()) {
            throw new Omeka_Controller_Exception_403;
        }
        if (!isset($this->_historicMapData[$this->_request->getParam('text')])) {
            throw new Omeka_Controller_Exception_404;
        }
        $data = $this->_historicMapData[$this->_request->getParam('text')];
        $this->_helper->json($data);
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
        $item = get_record_by_id('item', $this->_request->getParam('id'));
        $data = array(
            'id' => $item->id,
            'title' => metadata($item, array('Dublin Core', 'Title')),
            'description' => metadata($item, array('Dublin Core', 'Description'), array('no-escape' => true)),
            'abstract' => metadata($item, array('Dublin Core', 'Abstract'), array('no-escape' => true)),
            'date' => metadata($item, array('Dublin Core', 'Date'), array('all' => true)),
            'thumbnail' => item_image('square_thumbnail', array(), 0, $item),
            'fullsize' => item_image('fullsize', array('style' => 'max-width: 100%; height: auto;'), 0, $item),
            'url' => url(array('module' => 'default',
                               'controller' => 'items',
                               'action' => 'show',
                               'id' => $item['id']),
                         'id'),
        );
        $this->_helper->json($data);
    }

    //https://omeka.readthedocs.io/en/latest/Reference/libraries/globals/get_option.html
}
