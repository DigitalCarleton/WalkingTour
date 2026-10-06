<?php
require_once 'WalkingTour.php';
require_once 'WalkingTourItem.php';

class WalkingTour_ToursController extends Omeka_Controller_AbstractActionController
{
    public function init()
    {
        /** @var Omeka_Controller_Action_Helper_Db $db */
        $db = $this->_helper->getHelper('db');
        $db->setDefaultModelName('WalkingTour');
    }

}