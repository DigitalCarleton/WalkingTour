# CGMRStreetMap

A fork of the Omeka team's MallMap plugin to be used for the Carleton Guide to Medieval Rome project.

## Configuration

This section describes the process of getting the **original Omeka** version to work on your own Omeka site. It was a roadblock when we developed this version of the plugin, so we're documenting the process here. In a future stable release of our version, we'd like to make it a bit easier to use the plugin.

(We're not entirely sure how necessary it is to follow these instructions to the letter--we haven't tested precisely which of these configurations are absolutely necessary. Follow these instructions if you want to configure the plugin the same way the original MallMap creators configured theirs.)

If you clone the plugin directly into the `/plugins` folder in your Omeka project, you won't see any items on your map until you've properly configured a few things:

#### Simple Vocab

A number of Simple Vocab terms need to be configured in order for the map to render any items

- Coverage (Dublin Core)
  - The coverage field needs to be populated with a simple vocab definition. The original mallhistory.org site uses 20-year time slices as its definition for this field. You can see this under the filter dropdown as the "Map eras" option.
- Type (Dublin Core)
  - The type field needs to be populated as well. For the original Mall Map site, these options are visible under the "Item type" dropdown.

In addition to vocabularies for the above terms, you also need to add new item types (this config form is available in the left sidebar of your Omeka admin page.) These types are Event and Place.

- Event
  - In the configuration of the new "Event" type, add an element called "Event Type"
- Place
  - In the configuration of the new "Place" type, add an element called "Type"

Finally, we need Simple Vocab definitions for the new Event::Event Type and Place::Type terms. For both of these, add a Simple Vocab definition, and a list of event and place types. You can see these on the mallhistory.org site if you select the item type dropdown, and within the dropdown, select either event or place--this will render a new dropdown where the event and place types can be seen.

#### Item type IDs and Element IDs

In `MallMap/controllers/IndexController.php`, there are a number of `const` definitions at the top of the file, which are in charge of linking to your database. These numbers may be different from server to server, depending on which plugins you've installed (among other things).

In the original MallMap version, these are customized for the MallMap server, so you need to go into your database and find the IDs for all of the item types and event types.

Note that some of the Dublin Core types are likely to be standard across many people's sites--for us, we only needed to edit the `ITEM_TYPE_ID_PLACE` , `ELEMENT_ID_MAP_COVERAGE`, and `ELEMENT_ID_PLACE_TYPE` (because these are the new parameters added for this plugin).

#### Geolocation

If you want an item to render to the map, you need to GeoLocate it. Go to an Item Edit page, and select the Map tab to add a Geolocation to an item.

**Note:** Items must be public in order to render to the map.

#### Filter theming issues

One annoying issue we found is that there is some coupling between the MallMap theme and the MallMap plugin. One such issue causes the button which opens the dropdown to be the exact shade of white as the background theme, making it seem like something's gone wrong. In reality, it rendered correctly for us, but it was invisible! It's slightly above the map window, on the right side of the div. Hover your mouse around until it becomes a pointer--we promise, it's there!

## How to Debug for PHP

- For view, use `d($var)` to overlay the debug information on the screen
- For controllers, use `_log($var)` to write the debug information on the `errors.log` in site application folder

## TODO

- [x] items and tour must be public --> check user role
- [x] Default color value
- [x] Make the tour `getItemAction` not require abstract --> abstract needs `Dublin core extended`
- [x] Font resources
