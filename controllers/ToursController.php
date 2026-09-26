<?php
require_once 'WalkingTour.php';
require_once 'WalkingTourItem.php';

class WalkingTour_ToursController extends Omeka_Controller_AbstractActionController
{
	public function init()
	{
		$this->_helper->db->setDefaultModelName( 'WalkingTour' );
	}

}
