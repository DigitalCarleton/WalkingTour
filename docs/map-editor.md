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
the saved version. Explicit snapshot refresh is planned in ticket 06.

The importer supports the current Allmaps `SpecificResource` representation with an
ImageService1/2/3 source object, dimensions, Point control features, one polygon SVG resource
mask, and declared `thinPlateSpline`, `polynomial` (orders 1–3), `helmert`, or `projective`
transformation. Unsupported options and degenerate calibration constraints are rejected.
The browser evaluates saved calibration using the pinned Allmaps transformation packages. It does not evaluate the retired
PHP spline as a fallback. Older canvas-target annotations require re-export from Allmaps.

## Stored data and compatibility

Each imported record in `<prefix>walking_tour_maps` stores the original annotation URL,
resolved document/map identity, selected annotation and original document JSON, normalized
calibration inputs, SHA-256 content hash, UTC import time, image service/dimensions, and
transformation settings in `source_snapshot`. Control points and masks stay inside that
snapshot; importing does not create legacy control, mask, annotation, or history rows.
External image pixels remain at the IIIF provider and load into the browser on demand.

**Edit map information** changes an imported map's title using revision checks. Its source
URL is fixed to its imported snapshot. **Delete map** retains the existing confirmation,
CSRF, revision checks, and transactional cleanup. An external Allmaps/IIIF source is not
deleted. Legacy uploaded images can still be inspected and deleted; their server files
are removed through Omeka's configured storage adapter.

The IIIF-only and direct-upload actions are retired. Legacy map records remain readable
for reviewed migration, but their control markers are hidden and public calibration writes
are disabled. Ticket 07 migrates reviewed legacy data; ticket 09 removes retired tables,
editor assets, and example/static files once their remaining consumers are replaced.
Five existing Historical Maps tables are temporarily retained for compatibility. This
slice does not claim that the final two-table storage contraction is complete.

## Deployment and initialization

The plugin version remains **0.2.3**. For a site already initialized with the previous
map storage, deploy the updated plugin code; no new database columns are needed by this
slice. Do not uninstall/reinstall: uninstall removes plugin-owned data.

For a fresh site or missing storage, run from the installed WalkingTour plugin directory
using the server's PHP CLI with MySQL support:

```sh
php scripts/initialize-map-storage.php
```

The installation/upgrade hooks and this command preserve existing records. Rerunning does
not seed the former BnF example, import masks, or recalibrate legacy records. Back up the
Omeka database and existing uploaded image files before later migration tickets.

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
  control/annotation/mask arrays, calibration counters, and undo state are omitted.
- `historical-map-edit` and `historical-map-estimate` at the same API base return **410 Gone**
  without executing the former calibration or mutation engine, including old direct requests.
- `admin/historical-maps/import` implements authorized, CSRF-protected preview and import.
  Admin routes are unavailable through the public page.

Place search remains on the modern map. It uses the configured Photon-compatible endpoint
(default `https://photon.komoot.io/api/`) and does not save locations or change calibration.
Live Geolocation Item markers and historical estimates are available. Clustering,
local overrides, periods, refresh, and storage contraction remain later tickets.

## Live places (ticket 02)

Historical Maps reads published Items directly from Geolocation in pages of 500. It never
requires tour membership or the old location export. Reloading reads current locations.
Select a place marker or **Find a place** to see its plain-text title, excerpt, Item link,
and counterpart. Modern markers remain available when a reliable historical counterpart
cannot be obtained. Ground control points are never drawn.

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
Original Walking Tour assets and layers remain separate.
