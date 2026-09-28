# 01 — Browse the seeded historical map beside existing walking tours

**What to build:** Replace the WalkingTour map area with an independently navigable
historical image and modern map. Persist the supplied BnF map and ten imported
control-point pairs, expose a collapsible catalog, and preserve existing tour behavior.
All added interface text is English.

**Implementation base:** `add-historic-maps1` (`c09ee1c`). The earlier master-based
commit is preserved on `backup/batch1-master`; it is not an ancestor of this work.
The branch has no stored route field: tour stops/details and the independent
Omeka locations snapshot are retained on the modern map.

**Blocked by:** None — can start immediately.

**Status:** implemented; live original-image verification is limited by BnF availability.

- [x] Fresh installation and upgrade persist the map, source snapshot, and ten numbered pairs.
- [x] Repeated installation/upgrade preserves existing map edits, deleted points, and tour data.
- [x] The public catalog is read from the database through a GET interface.
- [x] Desktop displays two independent viewers; narrow screens stack them without horizontal overflow.
- [x] Matching control points highlight together and locate their counterparts.
- [x] The map list can be collapsed and reopened without breaking the map sizes.
- [x] Existing tour stop markers, filters, details, and the Omeka locations snapshot remain available.
- [x] Original-image failures show English feedback and a Retry action; the modern map remains usable.
- [x] A reproducible Omeka/database environment and focused storage/tile tests are included.
- [ ] Recheck successful live rendering of the original image when BnF's IIIF service recovers.

Validation: Omeka 3.1.2 / Geolocation 3.3 / PHP 8.2 / MariaDB 10.11. Nine storage checks
and three image-region tests passed. Browser checks covered desktop, a 390px viewport,
paired selection, list collapse, populated/empty tour filtering, and existing stop details.
The same source currently fails in Allmaps Editor; no warped image or unrelated image was substituted.
