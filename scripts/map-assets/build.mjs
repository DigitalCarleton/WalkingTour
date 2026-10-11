import {build} from 'esbuild';
import {fileURLToPath} from 'node:url';
await build({entryPoints:[fileURLToPath(new URL('./allmaps.js',import.meta.url))],bundle:true,format:'iife',target:['es2020'],minify:true,legalComments:'eof',outfile:fileURLToPath(new URL('../../views/public/javascripts/allmaps-transform.js',import.meta.url))});

import {copyFile, readFile, writeFile} from 'node:fs/promises';
const cluster = new URL('./node_modules/leaflet.markercluster/', import.meta.url);
await writeFile(new URL('../../views/public/javascripts/historical-map-cluster.js', import.meta.url), '/* Leaflet.markercluster 1.5.3: register on the final host Leaflet instance. */\njQuery(function () {\n' + await readFile(new URL('dist/leaflet.markercluster.js', cluster), 'utf8') + '\n});\n');
await copyFile(new URL('dist/MarkerCluster.css', cluster), new URL('../../views/public/css/historical-map-cluster.css', import.meta.url));
await writeFile(new URL('../../views/public/javascripts/historical-map-cluster.LICENSE', import.meta.url), await readFile(new URL('MIT-LICENCE.txt', cluster)));
