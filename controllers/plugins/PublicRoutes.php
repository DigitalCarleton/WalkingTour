<?php

/** Reserve the viewer URL after all plugins, including Simple Pages, define routes. */
class WalkingTour_Controller_Plugin_PublicRoutes extends Zend_Controller_Plugin_Abstract
{
    public function routeStartup(Zend_Controller_Request_Abstract $request)
    {
        if (is_admin_theme()) { return; }
        Zend_Controller_Front::getInstance()->getRouter()->addRoute(
            'walking_tour_historical_maps',
            new Zend_Controller_Router_Route('historical-maps', array(
                'module' => 'walking-tour', 'controller' => 'index', 'action' => 'map-viewer'
            ))
        );
    }
}
