# Historical map editing (Stages 2–4)

The plugin version stays at **0.2.3**. After copying or pulling the updated plugin,
run this command from the WalkingTour plugin directory using the server's PHP CLI
with its MySQL extension enabled:

```sh
php scripts/initialize-map-storage.php
```

This command reads the existing Omeka database configuration, adds missing map, mask, and
annotation storage, and preserves existing tours, control points, and saved masks. It can
be run again safely and does not change the installed plugin version. No version-based
upgrade prompt is required. Do not uninstall/reinstall: uninstalling removes plugin-owned data.

Run the initialization command again when deploying Stages 3–4. It adds persistent control-point
numbering and calibration change history without replacing existing points, annotations, or masks.

The two maps remain independently navigable. Existing legacy tour layers remain hidden as in
Stage 1; this release does not restore or edit those layers.

## Map footprint and custom mask

The supplied Allmaps snapshot contains an SVG polygon in **image pixels**, not geographic
coordinates. On storage initialization its boundary is sampled along each edge and transformed
using the ten imported control-point pairs. The resulting estimated geographic footprint is
saved and displayed as a pink outline. Transforming only the four corners would omit its curved edges.

Sign in using an existing Omeka editor account. Select **Draw a map mask** in the modern map's
upper-right controls. Click at least three vertices, then select the first vertex to close the
polygon. Choose **Save** to replace the displayed geographic mask or **Cancel** to retain the
saved mask. **Undo last vertex** reopens a closed draft and removes the most recent vertex.
Open, crossing, degenerate, or out-of-range polygons cannot be saved. One simple polygon with
up to 500 vertices is supported; polygons crossing the international date line are not supported.

Custom mask coordinates become authoritative for the modern-map outline. Repeated initialization does
not overwrite them. Mask vertices are separate from calibration control points: drawing a mask
does not improve or change the coordinate transform. The original image boundary remains visible
on the historical image; a custom geographic mask does not crop or distort that image.

## Estimated point annotations

Select the pencil on either map, then click a position. The source click is preserved and its
counterpart is estimated using the imported thin-plate-spline calibration. Save or cancel the draft.
Blue labels such as **A1** distinguish saved annotations from purple calibration points. Selecting
an annotation highlights its available counterparts and emits a `walkingtour:annotation-selected`
event from the map container, with `mapId` and an `annotation` object for future information panels.

A position outside the control-point hull is marked as extrapolated. If no stable, unique image
counterpart is found, only the original position is saved. Inverse estimates numerically solve the
forward spline from multiple initial positions; they do not establish historical accuracy or
mathematically guarantee global invertibility. Automatic estimates never become calibration
points unless an editor explicitly confirms both positions.

## Adjust and confirm a pair

Select a saved annotation or control point on either map, then choose **Adjust pair**. Drag either
endpoint while keeping the other fixed, or edit **Image X**, **Image Y**, **Longitude**, and
**Latitude**, then choose **Apply coordinates** for numerical adjustment. If a counterpart is
missing, click the other map to supply it or enter its coordinates. Image pixels use a top-left
origin. Coordinates are validated on
the server as well as in the editor.

Choose **Confirm pair** to promote an annotation into a shared control point, or **Save calibration
pair** to save adjustments to an existing control. A new draft may also be confirmed directly;
**Save** preserves an untouched draft as an ordinary estimated annotation. After manual adjustment,
explicit confirmation is required to save the corrected pair. **Cancel** discards the draft.

With the ten original controls, confirming **A1** removes its blue annotation markers and displays
the same purple circular marker style as the imported controls, numbered **11** on both maps.
Numbers are persistent and allocated monotonically; deleting or undoing a control does not renumber
other points or reuse its number. The displayed shared-control count reflects the currently saved controls.

A calibration save keeps all confirmed endpoint pairs fixed and recomputes only the predicted
side of ordinary annotations. Their source side and original source click remain unchanged. The
imported geographic footprint is recomputed; a hand-drawn custom mask stays authoritative.
Duplicate or unsolvable control arrangements are rejected without changing saved data or numbering.
With too few controls, estimates are marked unavailable and source clicks remain available for manual pairing.

## Delete points and undo calibration

Select the **Delete a point pair** trash icon on either map, then select a saved point. This saves
the deletion and removes both markers. Delete mode stays active for further deletions; select the
trash tool again or **Cancel** to exit. A failed deletion preserves the point and offers retry;
a conflict keeps the intended deletion for review against the latest data.

Deleting an ordinary annotation does not alter calibration. Deleting a control pair recomputes
predictions and makes **Undo calibration change** available in the top toolbar. That action restores
the latest calibration addition, adjustment, or deletion and persists the result. Undoing promotion
of a saved annotation restores its original annotation ID. Later ordinary annotations and custom
masks are preserved. This is one-change calibration undo, not a general annotation trash or undo stack.

Calibration writes, deletions, and undo use map revisions, transaction locking, and request identifiers.
A retry after a lost response cannot create another control point or delete another pair. All writes
continue to require existing Omeka editing privileges and a valid session CSRF token.

Writes require existing Omeka editing privileges and a session CSRF token. Each edit carries the
map's revision; outdated edits return a conflict and retain the draft. Choose **Reload latest data**,
review the refreshed estimate or mask, then explicitly save again. Retried point saves use a request
identifier to avoid duplicates if the previous response was lost.

## Place search

Place search starts collapsed as a magnifying-glass button. Select it to open the search field;
use the close button or Escape to collapse it again without losing the query or selected location.
The search field offers suggestions after three characters, or on Search/Enter.
Select a result to move the map; this does not create a saved annotation or change the mask.
Clear removes the search marker. Arrow keys can navigate results, and Escape collapses the search panel.

Search uses [Photon](https://github.com/komoot/photon) with English result preferences, a typing
delay, and a small per-page query cache. Queries are sent to the configured provider. Photon may
return an original place name when no English translation exists. The default public demo service
permits reasonable traffic but does not guarantee availability. For larger deployments use a
self-hosted or managed Photon-compatible service via **Place search endpoint** in the plugin settings.
Its URL must allow browser requests from the site's origin (CORS); use HTTPS on HTTPS sites.
Original image tiles still depend on the BnF IIIF service.

## Omeka administration

Signed-in users with the existing WalkingTour editing permission can open **Historical Maps**
in the Omeka admin navigation. The list shows each map's control-point and annotation counts
and calibration revision. Select a map title to inspect its source metadata, or choose
**Open map editor** to open that specific map in the public dual-map editor.

The catalog routes are registered only in the admin theme (`admin/historical-maps`).
The public dual-map viewer is at `historical-maps`, replacing that address's former
Simple Pages placeholder without deleting its stored page. The `walking-tour` page
again displays the original Digital Commonwealth tour image, tour filters, numbered
stops, detail panels, and the retained Omeka locations snapshot. Each page loads its
own viewer scripts. Older editor bookmarks with `walking-tour?map_id=...` redirect
to Historical Maps and retain valid point links. Existing map API paths remain
compatible. No map, annotation, calibration, upload, or tour data is migrated.
Deploying this change requires only the plugin code update, not database initialization
or a plugin reinstall.

Map detail pages also list confirmed control coordinates, ordinary annotation coordinates and
prediction status, and paginated calibration history (50 changes per page). **Locate on maps**
opens the selected map and highlights that specific saved point pair. Missing counterparts stay
explicitly unavailable. Coordinate edits and calibration undo are performed in the shared map
editor so they continue to use its transactional calibration and conflict handling. History lists
recorded changes and undone state; the existing records do not contain timestamps or editor identities.

Choose **Upload image** to add a JPEG or PNG from your computer, with a title and optional
HTTPS provenance link. No IIIF service is required. Uploaded maps start with no control points,
use the same dual-map editor, and appear in the public map library. The image is decoded and
re-encoded, with JPEG EXIF orientation applied when PHP EXIF is available; metadata is stripped.
The stored pixel dimensions define the fixed coordinate space for all later annotations.
Identical uploaded files are rejected. Uploading a replacement for an existing map is not supported.

Uploads require PHP GD with JPEG/PNG support and working Omeka file storage. Images are stored
through Omeka's configured storage adapter (under `files/original/` for the default filesystem
adapter), independently of Omeka Items. Back up these files along with the Omeka database.
The application limit is 20 MiB, 25 million pixels, and 20,000 pixels per side. PHP
`upload_max_filesize`, `post_max_size`, memory limits, and web-server request limits may be lower.
The first version loads the entire uploaded image as a zoomable original-image layer; it does not
generate image tiles. Use IIIF import for very large scans that exceed the upload limits.

Choose **Import from IIIF** to import an HTTPS IIIF Presentation API 2/3 manifest or
Image API 1/2/3 `info.json`. Image services must support level 1 or 2 region requests. Select the
image's position in the manifest (starting at 1; Presentation 2 uses the first sequence), and
optionally provide a title. The original IIIF metadata is retained as provenance. Duplicate
images are rejected. New maps start with no control points and no predicted geographic footprint;
use the map editor to place both endpoints and confirm initial pairs. Three non-collinear image
points are needed for a solvable transform, but that minimum does not establish historical accuracy.

**Edit map information** changes the title and optional provenance URL. The original image service
or uploaded file and dimensions stay fixed to preserve saved coordinates. Metadata updates use
the same map revision lock as point edits; a conflict retains the entered values and requires
review before another save.

The server requires PHP cURL and outbound HTTPS access for import. Metadata requests have size,
timeout, redirect, and public-address limits; images themselves remain at the source provider and
are loaded by the browser. Private sources and authentication-only image services
are outside the IIIF importer. IIIF compatibility follows the official
[Image API](https://iiif.io/api/image/3.0/) and
[Presentation API](https://iiif.io/api/presentation/3.0/) structures, including older versions.

The admin catalog is not exposed to public visitors. These records use WalkingTour's dedicated
tables in the existing Omeka database; they do not appear as Omeka Items. This admin interface
does not require an additional storage migration after Stages 3–4 have been initialized.

## Data interfaces

All paths are relative to the Omeka site root:

- `GET walking-tour/index/historical-maps`: catalog, control points, masks, annotations, editing
  availability, and a CSRF token for signed-in editors. Responses are private and not cached.
- `GET walking-tour/index/historical-map-estimate`: `map_id`, `side` (`image` or `modern`), `x`, `y`.
  Image coordinates use a top-left origin; modern coordinates use longitude, latitude.
- `POST walking-tour/index/historical-map-edit`: JSON with `map_id`, `revision`, `csrf_token`, and
  `operation`. A `mask` operation includes a closed `ring` in longitude/latitude order. An
  `annotation` operation includes `side`, `coordinates`, and a unique `request_id`.
- `confirm`: `point_type` (`new`, `annotation`, or `control`), `point_id` for a saved point,
  `image` in pixel order and `geographic` in longitude/latitude order, plus a unique `request_id`.
- `delete`: `point_type` (`annotation` or `control`), `point_id`, and a unique `request_id`.
- `undo_calibration`: the catalog's latest `undo_calibration.change_id` and a unique `request_id`.

All edit operations share `map_id`, `revision`, and `csrf_token`. Catalog records now include
`next_control_ordinal` and `undo_calibration` (null when the latest calibration change cannot be undone).
`walkingtour:control-point-selected` and `walkingtour:calibration-updated` events provide extension hooks.

Coordinates and geometry are validated on the server. Annotation estimates are calculated by the
server again when saving an ordinary annotation. Only an explicit, authorized `confirm` operation
can introduce manually reviewed calibration endpoints.
