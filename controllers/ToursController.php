<?php
require_once 'Tour.php';
require_once 'TourItem.php';

class WalkingTour_ToursController extends Omeka_Controller_AbstractActionController
{
	public function init()
	{
		$this->_helper->db->setDefaultModelName( 'Tour' );
	}

}
