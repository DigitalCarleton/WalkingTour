# Walking Tour

## Summary

This plugin allows you to create interactive walking tours directly within your Omeka website. Leverage your existing collections of geolocated items to build engaging experiences for visitors to explore your local history, art, architecture, or anything else you can map!

### A Walking tour

![Display Example](https://github.com/DigitalCarleton/WalkingTour/blob/11-feature-embed-exhibit-into-walking-tour-item/images/walkingTourImg.png)

## Installation and Configuration

1. Make sure you have Geolocation installed in your Omeka site before installing this plugin.
    * The plugin ExhibitBuilder allows you to link to exhibits within a walking tour; however, ExhibitBuilder is not required for the installation or usage of this plugin.

2. Install the Walking Tour plugin

3. The center of the map will be automatically calculated to fit your geolocated items. If you wish to change it you can either use the map to select a new center or type the longitude and latitude value of the center you want separated by a comma, i.e. "longitude, latitude”.

# Use Instructions

### 1. Create a Walking Tour:
   
a. Navigate to the "Walking Tour" tab on the left menu. Then select "Add a Tour". 

![Walking Tour](https://github.com/DigitalCarleton/WalkingTour/blob/master/images/WakingTourTab.png)

b. Fill in the relevant information fields.

c.  If you would like to see this walking tour on your site you musth check the "Public" checkbox. Finally, click "Save Changes".

![Add a Tour](https://github.com/DigitalCarleton/WalkingTour/blob/master/images/AddATour.png)

### 2. Adding and Removing Items from a Walking Tour:
Note that you may only add an item to a walking tour if the items has been geolocated using the GeoLocation Plugin.

a. If adding items to an existent walking tour, navigate to the "Walking Tour" tab, find the desired tour and click "edit". If adding items to a walking tour you are currently setting up proceed to the next step.

![Edit a Tour](https://github.com/DigitalCarleton/WalkingTour/blob/master/images/EditTour.png)

b. Scroll down to the "Tour Item" fields. Use the search bar to find the previusly geolocated item(s) you would like to add. Furthermore, note that items will only be visible on your site if the items are public.

c. In order to remove an item click the "remove" button to the right of the item name.

![Remove Item](https://github.com/DigitalCarleton/WalkingTour/blob/master/images/RemoveItem.png)

d. Once you have added and removed all the desired items, select "Save Changes".

### 3. Configure a Walking Tour:
  
a. Reorder the Items in a tour: 
    
    1. Navigate to the Walking Tour tab and click "edit" on the tour you wish to reorder.

    2. Tour items will be connected in the order they were added. In order to change, hold click over the item and drag it to the desired position.

    3. Click "Save Changes".

b. Change the Color of the Path:
    1. Navigate to the Walking Tour tab and click "edit" on the tour you wish to recolor.

    2. In the "Color" field enter the hexadecimal code of the desired color.

![Change Color](https://github.com/DigitalCarleton/WalkingTour/blob/master/images/ChangeColor.png)

    3. Click "Save Changes".

c. Link an Exhibit to an Item:
    1. Navigate to the Walking Tour tab and click "edit" on the tour you wish to link an exhibit to.

    2. Scroll to the Tour Items field and click on the "Link Exhibit" button to the right of the item you wish to link the exhibit to.

![Link Exhibit](https://github.com/DigitalCarleton/WalkingTour/blob/master/images/LinkEhibit.png)

    3. Click "Save Changes".

    4. To see the effects of this, navigate to the walking tour site and select the object. A new button will appear (It will read what was input in the field of "Exhibit Button" during plugin configuration) which will link to the exhibit.

![View Exhibit](https://github.com/DigitalCarleton/WalkingTour/blob/master/images/ViewExhibit.png)   

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



