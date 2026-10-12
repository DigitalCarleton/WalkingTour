# Historical Maps: Allmaps import and browsing

Historical Maps now uses calibration completed in [Allmaps Editor](https://editor.allmaps.org/).
The public `historical-maps` page displays the original unwarped image beside a modern
map. Both panes support independent pan and zoom. Control points, calibration tools,
mask drawing, counters, and calibration undo are retired on this site. The separate
`walking-tour` page retains its existing tour map, filters, stops, and details.

## Import a map

1. Find a publicly accessible IIIF image source and georeference it in Allmaps.
2. Copy its exported georeference Annotation or AnnotationPage URL, such as
   `https://annotations.allmaps.org/images/f5301521e08412d3@660ce3ebc88b3010`.
3. Sign in to Omeka with the existing WalkingTour editing permission. Open
   **Historical Maps → Import from Allmaps**, paste the URL, and choose **Preview maps**.
4. Review the map identities, dimensions, transformation, and titles. A page can contain
   several maps; explicitly select the maps to import. A single map is preselected.
5. Choose **Import selected maps**. The server checks each selected image's information
   and a preview before publishing the selection in one database transaction.
6. Open **View maps** from the map detail page, or choose it in the public map library.

Preview data stays in the editor's server-side session for 15 minutes. Up to three previews
are retained. An expired preview must be recreated. Invalid requests retain useful form
feedback; no map is published when validation or an image check fails.

Both version-qualified and unqualified URLs are supported. Imported calibration is a
snapshot: updating Allmaps does not silently update this site. Reimporting an unchanged
source map returns its existing record. A changed annotation is rejected while keeping
the saved version. Use Refresh from Allmaps on the existing admin record to review and publish its update.

The importer supports the current Allmaps `SpecificResource` representation with an
ImageService1/2/3 source object, dimensions, Point control features, one polygon SVG resource
mask, and declared `thinPlateSpline`, `polynomial` (orders 1–3), `helmert`, or `projective`
transformation. Unsupported options and degenerate calibration constraints are rejected.
The browser evaluates saved calibration using the pinned Allmaps transformation packages. It does not evaluate the retired
PHP spline as a fallback. Older canvas-target annotations require re-export from Allmaps.

Allmaps SVG mask vertices can extend beyond the image edges. The importer preserves
those original pixel coordinates, including negative values, rather than clipping or
rejecting them. Masks still require a finite, simple polygon with coordinates within
±1,000,000 pixels. Place estimates and local position overrides must remain inside both
the original image dimensions and its mask.

## Stored data and compatibility

Each imported record in `<prefix>walking_tour_maps` stores the original annotation URL,
resolved document/map identity, selected annotation and original document JSON, normalized
calibration inputs, SHA-256 content hash, UTC import time, image service/dimensions, and
transformation settings in `source_snapshot`. Control points and masks stay inside that
snapshot; importing does not create legacy control, mask, annotation, or history rows.
External image pixels remain at the IIIF provider and load into the browser on demand.

**Edit map information** changes an imported map's title and optional represented period using revision checks. Its source
URL changes only through an explicit reviewed refresh. **Delete map** retains the existing confirmation,
CSRF, revision checks, and transactional cleanup. An external Allmaps/IIIF source is not
deleted. Legacy uploaded images can still be inspected and deleted; their server files
are removed through Omeka's configured storage adapter.

The IIIF-only and direct-upload actions are retired. Pending/archived records are available
in admin for source/image inspection and deletion, but only validated imported Allmaps
records enter the public catalog. The public runtime never reads retired controls, free
points, mask rows, history or recovery exports. Imported masks and calibration live inside
the map snapshot. Use [the migration guide](historical-map-migration.md) to export and review
old records, preserve unmatched points/uploads, migrate stable map IDs, and explicitly
contract the old storage. No migration or table drop runs automatically during upgrade.

Final Historical Maps storage has two business tables: `walking_tour_maps` and
`walking_tour_place_annotations`. Fresh installation creates only these two; populated
upgrades retain old tables until the reviewed cleanup command removes them. Existing Omeka,
Geolocation, Tour and TourItem storage is independent and remains in place.

## Deployment and initialization

The plugin version remains **0.2.3**. Deploy the complete plugin code and run the storage
initializer from the installed plugin directory. Because the version is unchanged, an
existing 0.2.3 installation does not trigger Omeka's Upgrade action automatically:

```sh
php scripts/initialize-map-storage.php
```

Both schema paths add missing new storage/metadata without modifying maps or Item decisions.
The standalone initializer preserves Omeka's installed plugin version. If Omeka reports
a version mismatch, check the installed version before proceeding.
Rerunning never creates retired tables/columns or seeds an example. Do not uninstall/reinstall:
uninstall removes plugin-owned data. For a populated upgrade, follow export → preview →
reviewed migration/archive → viewer verification → cleanup preview → reviewed cleanup in
[the migration guide](historical-map-migration.md). Do not drop tables manually. The uploaded
files stay at their configured storage locations until their own map is explicitly deleted.

Import requires PHP cURL, DOM, JSON, and mbstring, plus outbound HTTPS. Fetches accept only
public HTTPS destinations on port 443, pin resolved public IPs, revalidate redirects, disable
proxy forwarding, cap responses at 3 MiB, and limit request time and redirect count.
Image information must match the reviewed dimensions/identity and support level 1/2 regions;
a preview must decode as JPEG/PNG. Private and authenticated sources are unsupported.
A successful import cannot guarantee future provider availability or browser CORS access;
image failures display retry feedback while the modern map remains available.

The supplied BnF annotation was checked from local development on 2026-10-11: both annotation
URLs returned HTTP 200, but the BnF `info.json` request returned HTTP 403. The importer reports
that failure without publishing. Saved-fixture validation does not establish live image
availability. Repeat the connectivity check on the deployment server before relying on
that source; this change has not itself been deployed or checked from that server.

## Public interfaces

Paths are relative to the Omeka site root:

- `GET walking-tour/index/historical-maps` returns the read-only catalog, image metadata,
  provenance, geographic viewport bounds, and normalized Allmaps calculation inputs.
  It returns `can_edit: false`, `workflow: allmaps`, and no editor CSRF token. Legacy editable
  control/annotation/mask editing state, calibration counters, and undo state are omitted.
  Pending and archived records are omitted; imported masks are inside their source snapshots.
- `historical-map-edit` and `historical-map-estimate` at the same API base return **410 Gone**
  without executing the former calibration or mutation engine, including old direct requests.
- `admin/historical-maps/import` implements authorized, CSRF-protected preview and import.
  Admin routes are unavailable through the public page.

Place search remains on the modern map. It uses the configured Photon-compatible endpoint
(default `https://photon.komoot.io/api/`) and does not save locations or change calibration.
Live Geolocation Item markers, historical estimates, clustering, local overrides,
periods, visibility, manual refresh and reviewed migration/cleanup are available. Both
public pages read current Geolocation places; neither uses bundled location exports.

## Live places (ticket 02)

Historical Maps reads published Items directly from Geolocation in pages of 500. It never
requires tour membership or the old location export. Reloading reads current locations.
Only Items with a reliable automatic historical position inside the imported mask are
included in either pane or the place selector. Out-of-coverage, invalid and ambiguous
positions are omitted from both panes. Ground control points are never drawn.
Select a blue Leaflet pin or **Find a place** to see a Geolocation-style balloon with
the linked Item title, first available Omeka thumbnail and a plain-text description
excerpt. Missing thumbnails are omitted. Both panes show the same Item information.

The imported resource mask is outlined in pink on the original image. The modern pane
outlines its transformed footprint, including samples along each edge because nonlinear
transformations bend straight image edges. Sampling refines chords to a 5-metre midpoint
tolerance and 250-metre maximum segment length, with bounded subdivision. View map area
fits this footprint rather than the control-point bounding box. The image remains unwarped
and unclipped. No mask-editing tools or control markers are shown. Filtering uses the checked
inverse inside the original mask, not the footprint's bounding box or the current viewport.
An outline that cannot be transformed safely is omitted instead of displaying a fabricated extent.

The local `allmaps-transform.js` bundle uses pinned `@allmaps/project` and
`@allmaps/transform` packages with the declared transformation (including polynomial
order). Default calibration projection is Web Mercator; explicitly declared EPSG:4326
and EPSG:3857 resource projections are supported. Other projections are rejected on import.
Image coordinates have a top-left origin; Leaflet Simple CRS uses `[-y, x]`.
Automatic estimates must be finite, inside the original dimensions and resource mask,
and pass a forward-model inverse check from multiple starting positions. Multiple detected
roots are rejected. This numerical check cannot prove global uniqueness or historical
accuracy; estimates remain estimates. Automatic positions are computed on demand and
never create database rows. Legacy maps require reviewed Allmaps migration for estimates.

Rebuild bundled assets only during development: run `npm ci` then `npm run build` in
`scripts/map-assets`. Commit the generated bundle with its source and lockfile. Production
PHP hosts need neither npm nor a Node service. Dependency notices are included in that folder.
The dedicated `GET walking-tour/index/historical-map-places?after=<Item ID>` interface
returns public Item positions and plain-text excerpts with no shared response cache.

### Clustered place browsing

Historical Maps uses the existing Leaflet 1.6 instance and locally bundled
Leaflet.markercluster 1.5.3 (rebuilt by the same asset command). Each pane clusters
its own positions, including negative image Y values and negative image zooms.
Click a cluster to expand or spiderfy coincident places. The Find a place selector
and Locate on both maps button reveal a selected Item inside either cluster.
Thirty-place batches yield between conversion passes; old groups are detached
when the map changes, so obsolete asynchronous work cannot repaint the viewer.
Cluster grids are created after each image's minimum zoom has been configured,
including very large images. Locating uses the actual Item/pixel coordinates,
not temporary spiderfy display offsets.
Original Walking Tour assets and layers remain separate.

### Item position overrides and notes

Authorized map editors select a public Item in Historical Maps, then use Adjust
historical position. Click/drag in the historical pane or enter original-image
pixels; Save position persists the correction. Cancel, switching Items, and
switching maps discard the position draft. Failed writes keep it. Reset to
estimated position clears only the override; notes and display decisions remain.
Coordinates can be zero and can fall outside the Allmaps resource mask when
explicitly reviewed, but must lie inside original-image dimensions. An override
never changes Geolocation, Allmaps controls, the source snapshot, or other Items.
Overrides only reposition Items whose modern location already qualifies for map coverage;
an override cannot bring an outside Item into the viewer.
Notes are plain text and accompany public Items in the decision API.

After deploying this slice, run `php scripts/initialize-map-storage.php` from the
installed WalkingTour plugin directory. This idempotently adds
`walking_tour_place_annotations` with one row per `(map_id, item_id)`; only local
decisions are stored, and empty rows are removed. Only the map snapshot and Item decisions are runtime storage. Deleting a map removes its
Item decision rows. Old free annotations are preserved in the private migration export
before the explicit operator cleanup removes their retired table.

GET `walking-tour/index/historical-map-decisions?map_id=…` reads public Item
decisions and returns an editing token only to authorized editors. POST
`historical-map-decision` accepts bounded JSON, a session CSRF token, map revision,
Item ID, operation (`position` or `note`) and value. Every write locks the map and
rejects stale revisions. A failed or lost response can be checked with Reload
latest decisions; that explicit page reload discards the draft for review.

### Period metadata and map-specific visibility

Edit map information in the Omeka admin to set an optional period label and start
or end year. Negative years mean BCE, positive years mean CE; year zero, fractional
years, reversed intervals and values outside -9999…9999 are rejected. Unknown years
stay blank. The library shows the label and available years. No timeline or Dublin
Core date inference is performed.

The default display rule shows current public Geolocation Items only when the imported
calibration yields a reliable position inside the mask. Both panes share this coverage
rule. Reviewed overrides replace historical positions after this filter. An explicit Show
uses the same coverage rule; it records
the editorial intention without bypassing publication or inventing positions. Hide
removes that Item from both panes and the visitor picker. Editors can check Include
hidden places to find a hidden Item and restore Default or Show. Saving Hide keeps
the selected Item available in that editor picker. Visibility is scoped to the map
and shares its annotation row with position and note; reset/delete never touches the
source Item. Visibility is saved with operation `visibility` and value `default`,
`show`, or `hide` through the Item decision endpoint.

Run the storage initializer after deployment to add the three map period fields.
The same revision lock protects period edits, display decisions and positions.

### Manual Allmaps refresh

From the existing admin map record choose Refresh from Allmaps. Preview the saved
source URL or deliberately enter another version of the same map. Unqualified URLs
fetch the current source; qualified URLs retain their `@version`. The preview shows
selected map identity, requested/resolved source, both hashes, image identity/dimensions,
resource masks and transformation settings. A multi-map page is matched by the original
Allmaps map ID, regardless of source ordering; a missing/different/ambiguous map is rejected.

Only the server-side reviewed payload can be published within 15 minutes. Both preview
and publish validate authorization and session CSRF. The image information and preview
must be available and agree with the annotation; publishing rechecks them. Source errors,
unsupported data and stale map revisions preserve the active snapshot and local decisions.
Publishing is atomic under the same map revision lock used by all other edits.

An identical annotation and source URL is a complete no-op, including revision/import time.
An explicitly changed source URL is recorded even when its annotation hash is identical.
A new calibration updates automatic browser estimates on reload, retaining the local map
ID, title, period, notes and Item visibility. If image identity or pixel dimensions changed,
publication requires explicit acknowledgment and flags saved position overrides as needing
review. Their old pixels are retained for the editor, but visitors see the current automatic
estimate until Adjust historical position is reviewed and saved. If its modern location
no longer qualifies for coverage, it is omitted from both panes while its decision remains stored.
Reset can instead discard the stale override while preserving visibility and notes.

Image-space equality means the IIIF service identity and dimensions are unchanged. If a
provider silently replaces or rotates pixels under the same identity/dimensions, that change
cannot be detected by this comparison; editors must review or reset affected local positions.
Refresh never copies Item overrides into GCPs or writes the retired calibration tables.

### Local verification of tickets 02–06

Verified against a disposable localhost Omeka 3.1.2 installation with Geolocation,
PHP 8.3 and MySQL. Independent pixel points covered all supported transform types,
polynomial orders and a folded model with ambiguous inverse roots; the BnF saved
fixture covered the default projection. Live HTTP checks covered public/private
Items, pagination, changed Geolocation coordinates without exports, zero pixels,
invalid inputs, position/note/visibility preservation, map isolation, CSRF and
revision conflicts. A 250-place coincident fixture covered independent clustering,
spiderfying, counterpart selection and map switching; a large-image fixture covered
minimum zoom changes. Desktop and 390-pixel layouts were checked, with stacked panes
and no horizontal overflow on the narrow viewport.

Manual refresh storage and HTTP checks covered unchanged and new snapshots,
reordered multi-map sources, deliberately qualified source URLs, retained local
decisions, image-space review, failure rollback and stale publication. Browser checks
covered refresh preview/publish, no-op feedback, override re-review and a two-session
conflict preserving the editor draft. Original Walking Tour routes, controls and
assets and Allmaps import validation were checked for regressions. These are local
checks; they do not confirm deployment or connectivity on the remote Omeka server.

### Original Walking Tour live location layer (ticket 08)

The separate Walking Tour page now loads all public Item locations through the same
`historical-map-places` read service. Pagination must finish successfully before the
additional orange location layer is published. Popups retain the Item ID and current
Geolocation address; text is inserted safely. Reloading reads updated coordinates and
publication state. A failed page gives explicit feedback and leaves tour stops usable;
there is no stale or partial location fallback. The original tour image conversion,
filters, routes, numbered stops and Item details remain separate from Historical Maps.
Bundled location JSON/GeoJSON and their offline converters have been removed.
