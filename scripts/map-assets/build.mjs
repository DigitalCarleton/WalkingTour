import {build} from 'esbuild';
import {fileURLToPath} from 'node:url';
await build({entryPoints:[fileURLToPath(new URL('./allmaps.js',import.meta.url))],bundle:true,format:'iife',target:['es2020'],minify:true,legalComments:'eof',outfile:fileURLToPath(new URL('../../views/public/javascripts/allmaps-transform.js',import.meta.url))});
