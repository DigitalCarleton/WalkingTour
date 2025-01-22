# Walking Tour

## Summary

This plugin allows you to create interactive walking tours directly within an Omeka Classic project. Leverage your existing collections of geolocated items to build engaging experiences for visitors to explore your local history, art, architecture, or anything else you can map!

With the [Geolocation plugin](https://omeka.org/classic/plugins/Geolocation/) installed, any item that has been geolocated can be added to a walking tour, and the plugin will build line features plotting foot walking routes between tour stops using the [Openrouteservice Directions API](https://openrouteservice.org/dev/#/api-docs/directions%20service). Users on site can enable location on the map and view their position as they follow the routes between locations. Multiple tours can be created and color coded so that they are uniquely identifiable on the overview map. 

### Walking Tour map view and sample tour stop 

![Display Example](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/walkingTourImg.png)

![Sample Tour Stop](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/SampleTourStop.png)

## Installation and Configuration

1. Make sure you have [Geolocation](https://omeka.org/classic/plugins/Geolocation/) installed in your Omeka site before installing this plugin.
    * Optionally, with [ExhibitBuilder](https://omeka.org/classic/docs/Plugins/ExhibitBuilder/) enabled, you can link to exhibits from walking tour stops, e.g. to highlight features inside a location.

2. Install the Walking Tour plugin with the standard [Omeka plugin installation procedure](https://omeka.org/classic/docs/Admin/Adding_and_Managing_Plugins/#installing-a-plugin).

3. Use the plugin Configuration Settings to set the help text and map default options. If you want the center of the map to be automatically calculated based off all geolocated items that have been added to walking tours, check the Auto-fit to Locations checkbox.

# Use Instructions

### 1. Create a Walking Tour:
   
1. Navigate to the "Walking Tour" tab in the left menu. Then select "Add a Tour". 
![Walking Tour](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/WakingTourTab.png)

2. Fill in the relevant information fields that will populate the tour start and end screens.
![Add a Tour](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/AddATour.png)

### Adding and Removing Items from a Walking Tour:

1. Scroll down to the "Tour Items" field on the Add a Tour page (or navigate to the "edit" page for existing walking tours).
![Edit a Tour](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/EditTour.png)

2. Use the search bar to find the previously geolocated item(s) you would like to add by their titles. 
3. In order to remove an item click the "remove" button to the right of the item name.
![Remove Item](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/RemoveItem.png)

4. Once you have added and removed all the desired items, select "Add Tour" (for new tours) or "Save Changes" (if editing an existing).

### Configure a Walking Tour:
  
1. Reorder the Items in a tour: 
    1. Navigate to the Walking Tour tab and click "edit" on the tour you wish to reorder.
    2. Tour items will be connected into a route in the order they are listed. To change the order, hold click over the item and drag it to the desired position.
    3. Click "Save Changes".

2. Change the Tour Color: 
    1. Navigate to the Walking Tour tab and click "edit" on the tour you wish to recolor.
    2. In the "Color" field enter the [hexadecimal code](https://www.w3schools.com/colors/colors_picker.asp) of the desired color. This will apply to the color of the map markers and path, the tour filter and the tour stop heading background color.
    ![Change Color](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/ChangeColor.png)

    3. Click "Save Changes".

4. Link an Exhibit to an Item:
    1. Navigate to the Walking Tour tab and click "edit" on the tour you wish to link with an exhibit.
    2. Scroll to the Tour Items field and click on the "Link Exhibit" button to the right of the item.
    ![Link Exhibit](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/LinkExhibit.png)

    3. Search for exhibits by title in the box that appears and click on the results to select.
    4. Click "Save Changes".
    5. When exhibits are linked, a new button will appear on the tour stop to open the exhibit. (The text can be changed in the "Exhibit Button" field on the plugin configuration form)
    ![View Exhibit](https://github.com/DigitalCarleton/WalkingTour/raw/master/images/ViewExhibit.png) 

## License

This plugin is published under [GNU/GPL](https://www.gnu.org/licenses/gpl-3.0.html).

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

## Contact

Maintained by [Digital Humanities @ Carleton College](https://www.carleton.edu/digital-humanities/)

 
## Acknowledgements
This plugin builds on a number of other projects. Thus, we would like to thank the following:

1. Jim Safley, Kim Nguyen, and John Flatness for the [Mall Map](https://github.com/omeka/MallMap.git) plugin.
2. Kim Nguyen and Sheila Brennan for the [Mall Theme](https://github.com/chnm/mall-theme.git) theme.
3. Erin Bell, Greyson, Eli Pousson, and jbretmaney for the [Tour Builder](https://github.com/CPHDH/Curatescape/tree/master/plugins/TourBuilder) plugin.
4. The team that maintains the [openrouteservice APIs](https://openrouteservice.org).
