# Historical map editing (Stage 2)

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
mathematically guarantee global invertibility. Automatic annotations never become calibration
points. Manual pairing, adjustment, confirmation, and calibration undo belong to the next stage.

Writes require existing Omeka editing privileges and a session CSRF token. Each edit carries the
map's revision; outdated edits return a conflict and retain the draft. Choose **Reload latest data**,
review the refreshed estimate or mask, then explicitly save again. Retried point saves use a request
identifier to avoid duplicates if the previous response was lost.

## Place search

The modern map's search field offers suggestions after three characters, or on Search/Enter.
Select a result to move the map; this does not create a saved annotation or change the mask.
Clear removes the search marker. Arrow keys can navigate results, and Escape closes them.

Search uses [Photon](https://github.com/komoot/photon) with English result preferences, a typing
delay, and a small per-page query cache. Queries are sent to the configured provider. Photon may
return an original place name when no English translation exists. The default public demo service
permits reasonable traffic but does not guarantee availability. For larger deployments use a
self-hosted or managed Photon-compatible service via **Place search endpoint** in the plugin settings.
Its URL must allow browser requests from the site's origin (CORS); use HTTPS on HTTPS sites.
Original image tiles still depend on the BnF IIIF service.

## Data interfaces

All paths are relative to the Omeka site root:

- `GET walking-tour/index/historical-maps`: catalog, control points, masks, annotations, editing
  availability, and a CSRF token for signed-in editors. Responses are private and not cached.
- `GET walking-tour/index/historical-map-estimate`: `map_id`, `side` (`image` or `modern`), `x`, `y`.
  Image coordinates use a top-left origin; modern coordinates use longitude, latitude.
- `POST walking-tour/index/historical-map-edit`: JSON with `map_id`, `revision`, `csrf_token`, and
  `operation`. A `mask` operation includes a closed `ring` in longitude/latitude order. An
  `annotation` operation includes `side`, `coordinates`, and a unique `request_id`.

Coordinates and geometry are validated on the server. Annotation estimates are calculated by the
server again when saving; a client cannot inject predicted coordinates or calibration points.
