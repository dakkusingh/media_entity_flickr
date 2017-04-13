# Media Entity Flickr
This module provides Flickr integration (i.e. media type
provider plugin) for [Media Entity Module](https://www.drupal.org/project/media_entity).

![media-entity-flickr](_documentation/images/4-flickr-media.jpg)

## Installation
1. Enable the media_entity and media_entity_flickr module.
2. Go to `/admin/structure/media` and click 'Add media bundle' to create a new bundle.
3. Under **Type provider** select Flickr.
4. Save the bundle.
5. Add a field to the bundle to store the flickr source. (this should be a plain text field).
6. Edit the bundle again, and select the field created above as the **Flickr source field**.

## Usage
[Check Usage guide](_documentation/USAGE.md)

## Integration with Flickr API
### Without Flickr API
If you need just to embedded pics, you can use this module without using Flickr's API. That will give you access to the only field available from the embed code.

### With Flickr API
TODO - Flickr API for Drupal 8 does not exist yet.
