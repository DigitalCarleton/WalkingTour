# Historical Maps migration and recovery

Run these commands in the installed WalkingTour plugin directory on the server that owns
Omeka's database. Use PHP with Omeka's MySQL/DOM/cURL/mbstring extensions. All paths below
are examples. Keep recovery directories outside every publicly served directory, restrict
them to the operator, and back up the complete database first. This plugin never runs a
production migration automatically during install or upgrade.

## Export and review

Run the storage initializer first. Then make a new, private recovery export:

```sh
php scripts/initialize-map-storage.php
php scripts/migrate-historical-maps.php --export=/private/wt-backup
```

The export includes table schemas and every row from the map, Item decision and four
legacy child tables, including orphan rows, original source snapshots, local control
edits, free points, masks, history and request receipts. Filesystem storage uploads are
copied into `files/original/`, with checksums. Their live files are never moved or deleted.
With another storage adapter, first copy its files to a private local storage tree using
that adapter's backup tools and supply `--files-root=/private/storage-copy`. A missing
upload stops export; an incomplete directory must not be used. External IIIF pixels
are references, not uploaded files, and are not downloaded by the export.

Read `inventory.txt` and `backup.json`. The exported controls are the locally saved
calibration, which can differ from the retained original Allmaps source. Re-enter desired
local control edits in Allmaps manually and export a new annotation URL. This tool never
publishes edits to Allmaps. It imports the explicitly selected, validated external
calibration, not a blend of external controls and the retired PHP estimator.

Create a private `plan.json`:

```json
{
  "maps": [
    {
      "map_id": 1,
      "action": "migrate",
      "annotation_url": "https://annotations.allmaps.org/images/IMAGE@VERSION",
      "annotation_id": "https://annotations.allmaps.org/maps/MAP",
      "accept_external_calibration": true,
      "reason": "Reviewed local control differences; accepted this Allmaps calibration.",
      "associations": [{"annotation_id": 42, "item_id": 123}]
    },
    {
      "map_id": 2,
      "action": "archive",
      "reason": "Uploaded image has no published IIIF/Allmaps source yet. Publish it through IIIF, georeference in Allmaps and import separately."
    }
  ]
}
```

Use the exact annotation ID from the exported Allmaps document. The preview checks the
same IIIF service and original dimensions, supported calibration, resource mask and live
image information/preview. An inaccessible provider prevents migration; resolve its access
problem and retry. A reviewed acceptance of differing controls requires both the explicit
flag and a reason. Uploaded records cannot masquerade as matching IIIF images.

`associations` are optional, human-reviewed links from legacy free-point IDs to public
Geolocation Item IDs. Each Item and free point occurs at most once per map. Only valid
original-image pixels become Item overrides. No association is inferred from proximity or
label text. Unmatched, ambiguous and unavailable points remain in the recovery export;
the preview reports the unmatched count. Existing Item decisions are never overwritten.
Archiving keeps the image file and its source record in admin, removes it from the public
catalog, and stores the reviewed reason. It can be inspected or explicitly deleted later.

```sh
php scripts/migrate-historical-maps.php --preview=/private/wt-backup --plan=/private/plan.json
php scripts/migrate-historical-maps.php --apply=/private/wt-backup --review=PREVIEW_SHA256
```

Review `preview.json` and its printed SHA-256 before applying. The exact preview and backup
are linked to each completion receipt. Applying rechecks image access and database state,
locks maps, and commits all selected records and Item associations together. IDs, titles,
periods and existing links survive. Repeating the same apply is a no-op, even after a later
refresh; it cannot replace newer snapshots. A changed map or legacy data requires a new
export/preview. Legacy tables are retained at this stage. No example map is recreated when
the original default was deleted. Original Tours, Items and Geolocation rows are untouched.

Verify the migrated map in admin and on `/historical-maps?map_id=ID`: both pink mask outlines,
public Item pins and balloons, hidden Allmaps controls, clustered counterpart selection,
position override/reset, visibility, period, refresh and reload. Inspect exported unmatched
points and archived uploads before storage cleanup. Recovery exports are never read by the
public viewer.

## Recovery

Keep the full pre-migration database dump, the private export and upload copies together.
A full database restore must follow the site's normal backup procedure and maintenance
window. For selective recovery, `backup.json` contains exact `schemas` and named `tables`
with every original field/row; restore into a separate database first and inspect before
replacing live records. Restore uploaded bytes to their recorded `path` through the
configured storage adapter. Do not roll back only a map while leaving newer Item decisions
or child tables at a different version. This tool intentionally does not expose a public
restore endpoint or silently overwrite a database from an export.
