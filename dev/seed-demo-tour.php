<?php
require __DIR__ . '/bootstrap.php';
if ($db->fetchOne("SELECT id FROM `{$db->Tour}` WHERE title = ?", array('Development demo route'))) {
    exit("Demo tours already exist.\n");
}
$tour = new Tour;
$tour->title = 'Development demo route';
$tour->description = 'A synthetic route for interface verification, not walking directions.';
$tour->credits = 'Local development fixture';
$tour->postscript_text = 'End of the development route.';
$tour->public = 1;
$tour->color = '#007c91';
$coordinates = array(array(12.476, 41.898), array(12.482, 41.891));
$tour->setPostData(array('tour_item_ids' => '', 'tour_item_exhibit_ids' => ''));
$tour->save();
foreach ($coordinates as $index => $coordinate) {
    $item = insert_item(array('public' => 1), array('Dublin Core' => array(
        'Title' => array(array('text' => 'Demo stop ' . ($index + 1), 'html' => false)),
        'Description' => array(array('text' => 'Synthetic stop for testing existing tour details.', 'html' => false))
    )));
    $location = new Location;
    $location->item_id = $item->id;
    $location->longitude = $coordinate[0];
    $location->latitude = $coordinate[1];
    $location->zoom_level = 15;
    $location->save();
    $stop = new TourItem;
    $stop->tour_id = $tour->id;
    $stop->item_id = $item->id;
    $stop->ordinal = $index;
    $stop->save();
}
$empty = new Tour;
$empty->title = 'Development empty tour';
$empty->description = 'An empty tour verifies first-install behavior.';
$empty->public = 1;
$empty->color = '#865527';
$empty->setPostData(array('tour_item_ids' => '', 'tour_item_exhibit_ids' => ''));
$empty->save();
echo "Added a two-stop demo route and an empty tour.\n";
