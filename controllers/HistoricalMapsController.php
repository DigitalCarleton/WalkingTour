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
        if (!in_array($this->getRequest()->getActionName(), array('browse', 'show', 'add', 'upload', 'edit'), true)) { throw new Omeka_Controller_Exception_404; }
        $this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
    }

    public function browseAction()
    {
        $this->view->maps = $this->maps->all();
    }

    public function showAction()
    {
        $map = $this->findMap();
        $this->view->map = $map;
        $before = $this->getRequest()->getQuery('before');
        if ($before !== null && (!is_string($before) || !ctype_digit($before) || (int) $before < 1)) {
            throw new Omeka_Controller_Exception_404;
        }
        $this->view->history = $this->maps->calibrationHistory($map['id'], $before === null ? null : (int) $before);
        $this->view->olderPage = $before !== null;
    }

    private function findMap()
    {
        try { return $this->maps->find($this->getParam('id')); }
        catch (InvalidArgumentException $error) { throw new Omeka_Controller_Exception_404; }
    }

    private function text($name, $default = '')
    {
        $value = $this->getRequest()->getPost($name, $default);
        return is_string($value) ? trim($value) : '';
    }

    private function form($values)
    {
        $this->view->values = $values;
        $this->view->error = null;
        $this->view->csrf = new Omeka_Form_SessionCsrf;
        if (!$this->getRequest()->isGet() && !$this->getRequest()->isPost()) {
            $this->getResponse()->setHttpResponseCode(405)->setHeader('Allow', 'GET, POST', true);
            $this->view->error = 'Use GET to view this form or POST to save it.';
            return false;
        }
        if (!$this->getRequest()->isPost()) { return false; }
        if (empty($this->getRequest()->getPost()) && (int) $this->getRequest()->getServer('CONTENT_LENGTH') > 0) {
            $this->getResponse()->setHttpResponseCode(413);
            $this->view->error = 'The request exceeds the server upload limit. Choose a smaller image and reload the form.';
            return false;
        }
        if (!$this->view->csrf->isValid($this->getRequest()->getPost())) {
            $this->getResponse()->setHttpResponseCode(403);
            $this->view->error = 'Your editing session is invalid. Reload this form and sign in again.';
            return false;
        }
        return true;
    }

    private function formError($error)
    {
        $status = $error instanceof InvalidArgumentException ? 422 : ($error->getCode() === 409 ? 409 : 503);
        $this->getResponse()->setHttpResponseCode($status);
        if ($status === 503) { _log($error, Zend_Log::ERR); }
        $this->view->error = $status === 503 ? 'The map could not be saved. Your entered values have been kept. Please try again.' : $error->getMessage();
    }

    public function addAction()
    {
        $values = array('iiif_url' => $this->text('iiif_url'), 'image_number' => $this->text('image_number', '1'), 'title' => $this->text('title'));
        if (!$this->form($values)) { return; }
        try {
            if (!preg_match('/^[1-9][0-9]{0,3}$/D', $values['image_number'])) { throw new InvalidArgumentException('Choose an image number between 1 and 1000.'); }
            $image = (new WalkingTour_HistoricalMapIiif)->import($values['iiif_url'], (int) $values['image_number']);
            if ($values['title'] !== '') { $image['title'] = $values['title']; }
            $map = $this->maps->createMap($image);
            $this->_helper->flashMessenger('Historical map added. Open the map editor to establish its control-point pairs.', 'success');
            $this->_redirect(url('historical-maps/show/' . $map['id']), array('prependBase' => false));
        } catch (Exception $error) { $this->formError($error); }
    }

    public function editAction()
    {
        $map = $this->findMap();
        $this->view->map = $map;
        $values = $this->getRequest()->isPost() ? array('title' => $this->text('title'), 'source_url' => $this->text('source_url'), 'revision' => $this->text('revision')) :
            array('title' => $map['title'], 'source_url' => $map['source_url'], 'revision' => (string) $map['revision']);
        if (!$this->form($values)) { return; }
        try {
            if (!ctype_digit($values['revision'])) { throw new InvalidArgumentException('The saved map revision is required.'); }
            $this->maps->updateMetadata($map['id'], (int) $values['revision'], $values['title'], $values['source_url']);
            $this->_helper->flashMessenger('Map information saved.', 'success');
            $this->_redirect(url('historical-maps/show/' . $map['id']), array('prependBase' => false));
        } catch (Exception $error) { $this->formError($error); }
    }

    public function uploadAction()
    {
        $values = array('title' => $this->text('title'), 'source_url' => $this->text('source_url'));
        if (!$this->form($values)) { return; }
        try {
            $map = (new WalkingTour_HistoricalMapUpload)->save($_FILES['image'] ?? null, $values['title'], $values['source_url'], $this->maps, Zend_Registry::get('storage'));
            $this->_helper->flashMessenger('Image uploaded. Open the map editor to establish its control-point pairs.', 'success');
            $this->_redirect(url('historical-maps/show/' . $map['id']), array('prependBase' => false));
        } catch (Exception $error) { $this->formError($error); }
    }
}
