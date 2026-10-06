<?php

/** Admin catalog; coordinate edits remain in the shared map editor. */
class WalkingTour_HistoricalMapsController extends Omeka_Controller_AbstractActionController
{
    public $aclResource = 'WalkingTourBuilder_Tours';
    private $maps;

    public function init()
    {
        $this->_helper->acl->setAutoloadResourceObject(false);
        $this->maps = new WalkingTour_HistoricalMapRepository(get_db());
    }

    public function preDispatch()
    {
        if (!is_admin_theme() || !current_user() || !is_allowed('WalkingTourBuilder_Tours', 'edit')) {
            throw new Omeka_Controller_Exception_403;
        }
        $this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
    }

    public function browseAction()
    {
        $this->view->maps = $this->maps->all();
    }

    public function showAction()
    {
        $this->view->map = $this->findMap();
    }

    private function findMap()
    {
        try { return $this->maps->find($this->getParam('id')); }
        catch (InvalidArgumentException $error) { throw new Omeka_Controller_Exception_404; }
    }
}
