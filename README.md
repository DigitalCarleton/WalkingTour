# Walking Tour

## Summary

This plugin allows you to create interactive walking tours directly within an Omeka Classic project. Leverage your existing collections of geolocated items to build engaging experiences for visitors to explore your local history, art, architecture, or anything else you can map!

With the [Geolocation plugin](https://omeka.org/classic/plugins/Geolocation/) installed, any item that has been geolocated can be added to a walking tour, and the plugin will build line features plotting foot walking routes between tour stops using the [Openrouteservice Directions API](https://openrouteservice.org/dev/#/api-docs/directions%20service). Users on site can enable location on the map and view their position as they follow the routes between locations.

### A Sample Walking Tour

![Display Example](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/walkingTourImg.png)

## Installation and Configuration

1. Make sure you have [Geolocation](https://omeka.org/classic/plugins/Geolocation/) installed in your Omeka site before installing this plugin.
    * Optionally, with [ExhibitBuilder](https://omeka.org/classic/docs/Plugins/ExhibitBuilder/) enabled, you can link to exhibits from walking tour stops, e.g. to highlight features inside a location.

2. Install the Walking Tour plugin with the standard [Omeka plugin installation procedure](https://omeka.org/classic/docs/Admin/Adding_and_Managing_Plugins/#installing-a-plugin).

3. Use the plugin Configuration Settings to set the help text and map default options. If you want the center of the map to be automatically calculated based off all geolocated items that have been added to walking tours, check the Auto-fit to Locations checkbox.

# Use Instructions

### 1. Create a Walking Tour:
   
1. Navigate to the "Walking Tour" tab in the left menu. Then select "Add a Tour". 

![Walking Tour](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/WakingTourTab.png)

2. Fill in the relevant information fields.

3.  If you would like to see this walking tour on your site you must check the "Public" checkbox. Finally, click "Save Changes".

![Add a Tour](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/AddATour.png)

### Adding and Removing Items from a Walking Tour:
Note that you may only add an item to a walking tour if the item has been geolocated using the GeoLocation Plugin.

a. Navigate to the "edit" page for the desired walking tour.
![Edit a Tour](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/EditTour.png)

b. Scroll down to the "Tour Items" field. Use the search bar to find the previously geolocated item(s) you would like to add. Note that items will only be visible on your site if the items are public.

c. In order to remove an item click the "remove" button to the right of the item name.

![Remove Item](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/RemoveItem.png)

d. Once you have added and removed all the desired items, select "Save Changes".

### Configure a Walking Tour:
  
1. Reorder the Items in a tour: 
    
    1. Navigate to the Walking Tour tab and click "edit" on the tour you wish to reorder.

    2. Tour items will be connected in the order they were added. In order to change, hold click over the item and drag it to the desired position.

    3. Click "Save Changes".

2. Change the Color of the Path:
    1. Navigate to the Walking Tour tab and click "edit" on the tour you wish to recolor.

    2. In the "Color" field enter the hexadecimal code of the desired color.

    ![Change Color](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/ChangeColor.png)

    3. Click "Save Changes".

3. Link an Exhibit to an Item:
    1. Navigate to the Walking Tour tab and click "edit" on the tour you wish to link an exhibit to.

    2. Scroll to the Tour Items field and click on the "Link Exhibit" button to the right of the item you wish to link the exhibit to.

    ![Link Exhibit](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/LinkExhibit.png)

    3. Click "Save Changes".

    4. To see the effects of this, navigate to the walking tour site and select the object. A new button will appear (It will read what was input in the field of "Exhibit Button" during plugin configuration) which will link to the exhibit.

    ![View Exhibit](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/ViewExhibit.png) 

## License

This plugin is published under [GNU/GPL](https://www.gnu.org/licenses/gpl-3.0.html).

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

## Contact

Maintained by [Digital Humanities @ Carleton College](https://www.carleton.edu/digital-humanities/)

 
## Acknowledgements
This plugin was built on the work of previously created plugins. Thus, we would like to thank the following people:

1. Jim Safley, Kim Nguyen, and John Flatness for their work in [Mall Map](https://github.com/omeka/MallMap.git)
2. Kim Nguyen and Sheila Brennan for their work in [Mall Theme](https://github.com/chnm/mall-theme.git)
3. Erin Bell, Greyson, Eli Pousson, and jbretmaney for their work in [Tour Builder](https://github.com/CPHDH/Curatescape/tree/master/plugins/TourBuilder)



