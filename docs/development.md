# Local validation

Implementation base: `add-historic-maps1` (`c09ee1c`). The branch retains its
Tour model without a stored `route` column and its existing Omeka locations snapshot.
Snapshot markers appear on the modern map; historical projection of those locations
belongs to the later transformation work. The existing architecture artifacts describe
the pre-dual-map implementation and are retained as historical documentation.

The development environment runs Omeka Classic 3.1.2, Geolocation 3.3, PHP 8.2,
and MariaDB 10.11. Docker Compose binds the web server to localhost only. It mounts
this checkout read-only as the WalkingTour plugin; the production site's theme and
database are not used.

From the plugin root:

```sh
docker compose -f dev/compose.yml up -d --build
docker compose -f dev/compose.yml exec -T web php /var/www/html/plugins/WalkingTour/dev/bootstrap.php
```

Browse <http://localhost:8098/walking-tour>. The disposable admin login is
`developer` / `local-development-only`. These credentials are for this local
environment only. The bootstrap installs the core and both plugins, or upgrades
WalkingTour if needed. It never automatically resets an existing database.

Optionally add a synthetic two-stop tour and an empty tour to exercise existing
tour and detail interactions (these are test fixtures, not real walking directions):

```sh
docker compose -f dev/compose.yml exec -T web php /var/www/html/plugins/WalkingTour/dev/seed-demo-tour.php
```

## Checks

```sh
node --check views/public/javascripts/walking-tour.js
node --check views/public/javascripts/historical-maps.js
node --test tests/historical-image-tiles.test.cjs
docker compose -f dev/compose.yml exec -T web php /var/www/html/plugins/WalkingTour/tests/historical-map-storage.php
```

The storage test uses the actual Omeka adapter and MariaDB with a unique temporary
table prefix. It checks fresh install, upgrade from 0.2.3, provenance, coordinate
serialization, preservation of existing tours, and non-destructive repeated imports.
It removes only the temporary tables it creates.

The public read endpoint is `GET /walking-tour/index/historical-maps`, relative to
the site's base path. It returns `{ "maps": [...] }`; each map includes its original
image dimensions, IIIF service and manifest, imported source URL, revision,
transformation name, and ordered control points. Image pixels use a top-left origin;
geographic fields are explicitly named `longitude` and `latitude`. No write endpoint
or automatic calibration transform is introduced in batch 1.

On an existing installation, replace the plugin files and run the WalkingTour
upgrade in Omeka's Plugins page (version 1.1.1). Do not uninstall/reinstall to upgrade:
uninstallation deletes plugin-owned data, as with the existing tour tables.

## Browser verification

- Confirm the historical and modern maps render side by side on desktop and stack
  on a narrow viewport; pan and zoom each independently.
- Select an imported numbered control point and check both markers highlight and
  both maps center on the selected pair.
- Collapse and reopen the map list; confirm the maps resize correctly.
- Reload and confirm the ten seeded pairs are still present.
- Check a populated tour, an empty tour, filters, and existing stop details.
- Check a failed catalog request and failed image tiles show English feedback while
  the modern map remains usable.

The BnF original image is loaded directly as IIIF regions. The bundled Allmaps JSON
is a frozen calibration snapshot, not an image cache. Original-image rendering still
depends on BnF availability. A failed source must show an error and Retry action;
the application must not substitute a warped Allmaps tile layer.

Stop the local environment while retaining its database:

```sh
docker compose -f dev/compose.yml down
```
