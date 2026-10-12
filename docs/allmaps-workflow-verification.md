# Allmaps replacement verification and deployment receipt

Verified locally on 2026-10-11 using a disposable Omeka 3.1.2 site, PHP 8.3.30 and
MySQL 9.4.0. Tickets 07–09 were implemented as separate commits. No remote database
migration, table cleanup, deployment or Git push was performed by this batch.

## Passed checks

- 35 populated/fresh storage checks: exactly two fresh Historical Maps tables; verbatim
  recovery rows/schemas; uploaded-byte copies/checksums; reviewed differing controls;
  explicit Item associations; unmatched points; stable IDs; archive/public separation;
  stale-preview rollback; repeat/no-overwrite behavior; protected orphan rows; original
  recovery-export retention; cleanup review/conflicts; interrupted/repeated DDL cleanup;
  repeated initialization without retired tables/columns; retained snapshots/overrides;
  imported and archived-upload deletion; no default/example reseeding; source Item and
  Geolocation preservation. These checks used isolated table prefixes and removed their
  synthetic storage afterward.
- Before restoring the declared version to 0.2.3, the actual Omeka plugin installer
  upgraded the populated localhost site to 0.3.0 without uninstalling. Historical Maps
  and the original Walking Tour routes loaded afterward. This is a historical test
  result, not the current deployment procedure.
- 18 Allmaps numerical tests covered all supported models, independent interior points,
  checked inverses, ambiguous folded mappings, concave/reduced masks and curved footprints.
  Three IIIF tile tests and five live-loader tests passed.
- Real HTTP checks covered public/private Item filtering, live Geolocation coordinate
  updates, Item links, pagination, position/note/reset, visibility and period validation,
  map isolation, CSRF/authentication, stale revisions, refresh preview/publication,
  image-space acknowledgment and override re-review. Eleven additional refresh storage
  cases checked atomic no-op/update/failure behavior.
- Live Allmaps annotation preview, explicit selection, source/image validation and import
  succeeded using the accessible Delft IIIF provider. Retired calibration endpoints returned
  410 for authenticated/anonymous GET/POST without mutating the catalog. IIIF-only and upload
  actions remained retired; old import bookmarks redirected to Allmaps import.
- Browser checks showed two pink mask paths, two selected blue Item pins and two paired
  balloons, zero control markers and no old editor/export startup. Coincident Items clustered
  separately on each pane and selecting a place revealed both counterparts. The original
  Walking Tour loaded live orange locations, preserved Item details/filters and showed its
  empty-tour state correctly. Screenshots and local test scripts are retained in ignored
  development storage; synthetic Items/maps were removed after verification.
- PHP syntax, changed JavaScript syntax and Git whitespace checks passed. Runtime callers
  no longer reference the PHP transform, legacy calibration tables or static location
  exporters; the only legacy-storage reader is the private operator recovery/migration tool.

## Live source limitation

The supplied BnF Allmaps annotation URL preview returned its calibration successfully.
Publishing was rejected because BnF image information returned HTTP 403 from this local
host. No record was published by that failed import. Saved-fixture calibration tests do
not establish provider availability on the Carleton server. Check its access from that
server before migrating the Rome record; the importer must validate the actual image.

## Deployment order

1. Back up the complete site database and configured uploaded-image storage.
2. Deploy all plugin files. The declared version remains 0.2.3; an existing 0.2.3
   installation does not trigger an automatic Upgrade action. Do not uninstall/reinstall.
3. Run the storage initializer, then create a private recovery export. Review a migration
   plan for each legacy record, accept the intended external calibration explicitly, and
   apply the preview hash. Archive unsupported/unmatched uploads with a documented next step.
4. Verify both imported masks, live Item markers/details, clusters, overrides, visibility,
   period, refresh and reload. Only validated Allmaps imports enter the public library;
   pending/archive metadata and image inspection remain available in admin.
5. Review the cleanup preview and every retained recovery export, then explicitly apply
   cleanup. Four retired child tables and two obsolete map columns are removed. Uploaded
   files and original Items, Geolocation, Tours and TourItems are preserved.
6. Repeat initialization and inspect actual table names. The Historical Maps business
   tables are `walking_tour_maps` and `walking_tour_place_annotations`, with your site's
   prefix. Verify the original Walking Tour again.

See [migration commands, plan format and recovery](historical-map-migration.md) for exact
operator steps and interrupted-cleanup handling, and [the usage guide](map-editor.md) for
import, refresh and Item decisions. Source URLs, map IDs and reviewed hashes must come
from the site's actual exports and previews, not example placeholders.
