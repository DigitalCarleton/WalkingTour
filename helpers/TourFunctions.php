<?php

/*
 * Helper functions
 */

function availableLocationItemsJSON() {
		$db = get_db();
		$prefix=$db->prefix;
		$itemTable = $db->getTable( 'Item' );
		$locationTable = $db->getTable( 'Location' );
		$locationItemsDat = $locationTable->fetchObjects( "SELECT item_id FROM ".$prefix."locations");
		if ($locationItemsDat) {
			$locationItemsIDs = array();
			foreach ($locationItemsDat as $dat){
				$locationItemsIDs[] = (int) $dat["item_id"];
			}
			$locationItemsIDs = implode(", ", $locationItemsIDs);
			$items = $itemTable->fetchObjects( "SELECT * FROM ".$prefix."items WHERE id IN ($locationItemsIDs) ORDER BY modified DESC" );
			foreach($items as $key => $arr) {
				$items[$key]['label'] = metadata( $arr, array( 'Dublin Core', 'Title' ) );
			}
			return json_encode($items);
		}
}

function has_tours()
{
	return( total_tours() > 0 );
}

function has_tours_for_loop()
{
	$view = get_view();
	return $view->tours && count( $view->tours );
}


function tour( $fieldName, $options=array(), $tour=null )
{
	if( ! $tour ) {
		$tour = get_current_tour();
	}

	switch( strtolower( $fieldName ) ) {
	case 'id':
		$text = $tour->id;
		break;
	case 'title':
		$text = $tour->title;
		break;
	case 'description':
		$text = $tour->description;
		break;
	case 'credits':
		$text = $tour->credits;
		break;
	case 'postscript_text':
		$text = $tour->postscript_text;
		break;
	default:
		throw new Exception( "\"$fieldName\" does not exist for tours!" );
		break;
	}

	if( isset( $options['snippet'] ) ) {
		$text = snippet( $text, 0, (int)$options['snippet'] );
	}

	if( !is_array( $text ) ) {
		$text = html_escape( $text );
	} else {
		$text = array_map( 'html_escape', $text );

		if( isset( $options['delimiter'] ) ) {
			$text = join( (string) $options['delimiter'], (array) $text );
		}
	}

	return $text;
}

function get_current_tour()
{
	return get_view()->tour;
}

function link_to_tour(
	$text=null, $props=array(), $action='show', $tourObj = null )
{
	# Use the current tour object if none given
	if( ! $tourObj ) {
		$tourObj = get_current_tour();
	}

	# Create default text, if it was not passed in.
	if( empty( $text ) ) {
		$tourName = tour('title', array(), $tourObj);
		$text = (! empty( $tourName )) ? $tourName : '[Untitled]';
	}

	return link_to($tourObj, $action, $text, $props);
}


function total_tours()
{
	$view = get_view();
	return count( $view->tours );
}

function nls2p($str) {
	$str = str_replace('<p></p>', '', '<p>'
		. preg_replace('#([
]\s*?[
]){2,}#', '</p><p>', $str)
		. '</p>');
	return $str;
}

/*
** Get an ID of an item in a tour
** $tour sets the tour object
** $i is used to choose the position in the item array
** USAGE: tour_item_id($this->tour,0)
*/
function tour_item_id($tour,$i){
	$tourItems =array();
	foreach( $tour->Items as $items ){
		array_push($tourItems,$items->id);
	}
	return isset($tourItems[$i]) ? $tourItems[$i] : null;
}
