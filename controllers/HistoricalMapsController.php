<?php

/** Admin Allmaps import and catalog; legacy calibration writes are retired. */
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
        if (!in_array($this->getRequest()->getActionName(), array('browse', 'show', 'import', 'add', 'upload', 'edit', 'delete', 'refresh'), true)) { throw new Omeka_Controller_Exception_404; }
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
            $this->view->error = 'Use GET to view this form or POST to submit it.';
            return false;
        }
        if (!$this->getRequest()->isPost()) { return false; }
        if (empty($this->getRequest()->getPost()) && (int) $this->getRequest()->getServer('CONTENT_LENGTH') > 0) {
            $this->getResponse()->setHttpResponseCode(413);
            $this->view->error = 'The request exceeds the server size limit. Reload the form and submit a smaller request.';
            return false;
        }
        if (!$this->view->csrf->isValid($this->getRequest()->getPost())) {
            $this->getResponse()->setHttpResponseCode(403);
            $this->view->error = 'Your editing session is invalid. Reload this form and sign in again.';
            return false;
        }
        return true;
    }

    private function formError($error, $unavailable = 'The map could not be saved. Your entered values have been kept. Please try again.')
    {
        $status = $error instanceof InvalidArgumentException ? 422 : ($error->getCode() === 409 ? 409 : 503);
        $this->getResponse()->setHttpResponseCode($status);
        if ($status === 503) { _log($error, Zend_Log::ERR); }
        $this->view->error = $status === 503 ? $unavailable : $error->getMessage();
    }

    public function addAction()
    {
        if ($this->getRequest()->isGet()) {
            $this->_redirect(url('historical-maps/import'), array('prependBase' => false)); return;
        }
        $this->retiredImport('IIIF-only import is retired. Import an Allmaps annotation instead.');
    }

    public function importAction()
    {
        require_once dirname(__FILE__) . '/../models/HistoricalMapAllmaps.php';
        $values = array('annotation_url' => $this->text('annotation_url'), 'preview_token' => $this->text('preview_token'));
        $this->view->images = array();
        if (!$this->form($values)) { return; }
        $session = new Zend_Session_Namespace('WalkingTourAllmapsImport');
        $previews = is_array($session->previews) ? $session->previews : array();
        foreach ($previews as $token => $preview) { if ($preview['expires'] < time()) { unset($previews[$token]); } }
        $session->previews = $previews;
        try {
            $reader = new WalkingTour_HistoricalMapAllmaps;
            if ($this->text('step', 'preview') === 'preview') {
                $images = $reader->review($values['annotation_url']);
                // Keep the reviewed payload server-side so a modified form cannot replace it.
                if (count($previews) >= 3) { array_shift($previews); }
                $token = bin2hex(random_bytes(16));
                $previews[$token] = array('images' => $images, 'expires' => time() + 900);
                $session->previews = $previews;
                $this->view->values['preview_token'] = $token;
                $this->view->images = $images;
                return;
            }
            if ($this->text('step') !== 'import' || !isset($previews[$values['preview_token']])) {
                throw new InvalidArgumentException('This import preview is missing or expired. Preview the annotation URL again.');
            }
            $images = $previews[$values['preview_token']]['images'];
            $this->view->images = $images;
            $this->view->values['annotation_url'] = $images[0]['source_url'];
            $indexes = $this->getRequest()->getPost('map_indexes');
            if (!is_array($indexes) || !$indexes || count($indexes) > count($images)) { throw new InvalidArgumentException('Select the maps you want to import.'); }
            $titles = $this->getRequest()->getPost('titles', array());
            if (!is_array($titles)) { throw new InvalidArgumentException('Enter valid map titles.'); }
            // Preserve edited titles and selections when image validation fails.
            foreach ($titles as $index => $title) {
                if (isset($images[$index]) && is_string($title)) { $this->view->images[$index]['title'] = $title; }
            }
            $this->view->selectedIndexes = $indexes;
            $selected = array(); $seen = array();
            foreach ($indexes as $index) {
                if (!is_string($index) || !ctype_digit($index) || !isset($images[(int) $index]) || isset($seen[(int) $index])) {
                    throw new InvalidArgumentException('Select each reviewed map at most once.');
                }
                $seen[(int) $index] = true;
                $image = $images[(int) $index];
                $title = $titles[(int) $index] ?? $image['title'];
                if (!is_string($title) || trim($title) === '' || mb_strlen(trim($title)) > 255) { throw new InvalidArgumentException('Enter map titles of 1 to 255 characters.'); }
                $image['title'] = trim($title);
                $selected[] = $image;
            }
            // Check all selected images before the transaction publishes any map.
            $selected = array_map(array($reader, 'checkImage'), $selected);
            $saved = $this->maps->importAllmaps($selected);
            unset($previews[$values['preview_token']]); $session->previews = $previews;
            $this->_helper->flashMessenger(count($saved) . ' map(s) available from Allmaps. The imported calibration is read-only.', 'success');
            $this->_redirect(url(count($saved) === 1 ? 'historical-maps/show/' . $saved[0]['id'] : 'historical-maps'), array('prependBase' => false));
        } catch (Exception $error) {
            $this->formError($error, 'The Allmaps import could not finish. No selected maps were published. Please retry or contact the server administrator.');
        }
    }

    public function refreshAction()
    {
        require_once dirname(__FILE__) . '/../models/HistoricalMapRefresh.php';
        $map = $this->findMap(); $this->view->map = $map; $this->view->preview = null;
        $values = array('annotation_url'=>$this->getRequest()->isPost() ? $this->text('annotation_url') : $map['source_url'],
            'revision'=>$this->getRequest()->isPost() ? $this->text('revision') : (string) $map['revision'], 'preview_token'=>$this->text('preview_token'));
        if (!$this->form($values)) { return; }
        $session = new Zend_Session_Namespace('WalkingTourAllmapsRefresh');
        $previews = is_array($session->previews) ? $session->previews : array();
        foreach ($previews as $token=>$preview) { if ($preview['expires'] < time()) { unset($previews[$token]); } }
        $session->previews = $previews;
        try {
            $refresh = new WalkingTour_HistoricalMapRefresh;
            if ($this->text('step', 'preview') === 'preview') {
                if (!ctype_digit($values['revision']) || (int) $values['revision'] !== $map['revision']) { throw new RuntimeException('This map changed. Open its latest record and preview the refresh again.', 409); }
                $image = $refresh->prepare($map, $values['annotation_url']);
                if (count($previews) >= 3) { array_shift($previews); }
                $token = bin2hex(random_bytes(16));
                $preview = array('map_id'=>$map['id'], 'revision'=>$map['revision'], 'image'=>$image,
                    'image_changed'=>WalkingTour_HistoricalMapRefresh::imageChanged($map, $image), 'expires'=>time()+900);
                $previews[$token] = $preview; $session->previews = $previews;
                $this->view->preview = $preview; $this->view->values['preview_token'] = $token;
                return;
            }
            $token = $values['preview_token'];
            if ($this->text('step') !== 'publish' || !isset($previews[$token]) || $previews[$token]['map_id'] !== $map['id']) { throw new InvalidArgumentException('This refresh preview is missing or expired. Preview the selected map again.'); }
            $preview = $previews[$token]; $this->view->preview = $preview; $this->view->values['annotation_url'] = $preview['image']['source_url'];
            // Only the server-side reviewed payload can be published; recheck image availability.
            $image = $refresh->checkImage($preview['image']);
            $result = $this->maps->refreshAllmaps($map['id'], $preview['revision'], $image, $this->text('review_image_change') === '1');
            unset($previews[$token]); $session->previews = $previews;
            $this->_helper->flashMessenger($result['changed'] ? ($result['image_changed'] ? 'Allmaps snapshot refreshed. Saved historical position overrides need review in the viewer.' : 'Allmaps snapshot refreshed. Local position and display decisions were preserved.') : 'The Allmaps snapshot is unchanged. No saved data was modified.', 'success');
            $this->_redirect(url('historical-maps/show/' . $map['id']), array('prependBase'=>false));
        } catch (Exception $error) { $this->formError($error, 'The Allmaps refresh could not finish. The active snapshot and local decisions were kept. Retry the preview or contact the server administrator.'); }
    }

    public function editAction()
    {
        $map = $this->findMap();
        $this->view->map = $map;
        $values = $this->getRequest()->isPost() ? array('title' => $this->text('title'), 'source_url' => $this->text('source_url'), 'revision' => $this->text('revision')) :
            array('title' => $map['title'], 'source_url' => $map['source_url'], 'revision' => (string) $map['revision']);
        foreach (array('period_label','period_start','period_end') as $key) { $values[$key] = $this->getRequest()->isPost() ? $this->text($key) : (string) ($map[$key] ?? ''); }
        if (!$this->form($values)) { return; }
        try {
            if (!ctype_digit($values['revision'])) { throw new InvalidArgumentException('The saved map revision is required.'); }
            $this->maps->updateMetadata($map['id'], (int) $values['revision'], $values['title'], $values['source_url'], array('label'=>$values['period_label'],'start'=>$values['period_start'],'end'=>$values['period_end']));
            $this->_helper->flashMessenger('Map information saved.', 'success');
            $this->_redirect(url('historical-maps/show/' . $map['id']), array('prependBase' => false));
        } catch (Exception $error) { $this->formError($error); }
    }

    public function uploadAction()
    {
        $this->retiredImport('Direct image uploads are retired. Find a IIIF source, georeference it in Allmaps, and import its annotation URL. Existing uploads remain available for inspection and deletion.');
    }

    private function retiredImport($message)
    {
        $this->getResponse()->setHttpResponseCode(410);
        $this->view->message = $message;
        $this->render('retired');
    }

    public function deleteAction()
    {
        $map = $this->findMap();
        $this->view->map = $map;
        $values = array('revision' => $this->getRequest()->isPost() ? $this->text('revision') : (string) $map['revision']);
        if (!$this->form($values)) { return; }
        try {
            if (!ctype_digit($values['revision'])) { throw new InvalidArgumentException('The saved map revision is required.'); }
            if ($this->text('confirm_delete') !== '1') {
                throw new InvalidArgumentException('Confirm that you want to permanently delete this map and its saved data.');
            }
            $result = $this->maps->deleteMap($map['id'], (int) $values['revision'], Zend_Registry::get('storage'));
            $this->_helper->flashMessenger('Historical map deleted.', 'success');
            if ($result['image_cleanup_failed']) {
                $this->_helper->flashMessenger('The map was deleted, but its staged image file could not be removed. Ask the server administrator to check the application log and finish file cleanup.', 'error');
            }
            $this->_redirect(url('historical-maps'), array('prependBase' => false));
        } catch (Exception $error) {
            $this->formError($error, 'Deletion could not finish. Reload the latest record and try again, or contact the server administrator.');
        }
    }
}
