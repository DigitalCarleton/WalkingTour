// Isolated HTTP fixture: real PHP templates, Leaflet, Allmaps and viewer scripts.
// Requires playwright and @php-wasm/node via NODE_PATH; never contacts a real Omeka DB.
const fs = require('node:fs'), path = require('node:path'), http = require('node:http');
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {PHP} = require('@php-wasm/universal'), {loadNodeRuntime} = require('@php-wasm/node');
const root = path.resolve(__dirname, '..');
const output = path.join(root, '.scratch/historical-map-browser'); fs.mkdirSync(output, {recursive: true});
const source = file => fs.readFileSync(path.join(root, file), 'utf8');
function record(id, mask) {
    return {id, revision:1, title:['','Map A','Map B','Map C'][id],source_kind:'allmaps', image_width:1000,image_height:1000,
        image_service:'/iiif/'+id, image_quality:'default', source_url:'https://example.test/annotation/'+id,
        georeference:{transformation:{type:'polynomial',options:{order:1}},resourceCrs:{definition:'EPSG:4326'},resourceMask:mask,
            gcps:[[0,0],[1000,0],[0,1000],[1000,1000]].map(resource=>({resource,geo:[12+resource[0]/100000,42-resource[1]/100000]}))}};
}
const serverData = {maps:[record(1,[[0,0],[1000,0],[1000,1000],[0,1000]]),
    record(2,[[300,300],[900,300],[900,900],[300,900]]),
    record(3,[[0,0],[1000,0],[1000,300],[300,300],[300,1000],[0,1000]])],
    places:[{item_id:10,title:'Church of the Test Site',coordinates:[12.006,41.994],description:'A public church description from Omeka. Explore the same place on another historical map.',thumbnail_url:'/thumbnail.svg',url:'/items/show/10'},
        {item_id:11,title:'Northwest place',coordinates:[12.001,41.999],description:'A different place.',url:'/items/show/11'}],
    decisions:{}, failImages:false, slow:false};
let server, browser, php;
async function render(editor) {
    const result=await php.run({code:`<?php
        function public_url($path) { return '/' . $path; }
        function html_escape($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
        function get_option($name) { return null; }
        $editorMode = ${editor?'true':'false'}; $map = ['id'=>1];
        include '/app/historical-map-viewer.php';
    `});
    assert.equal(result.exitCode,0,result.errors);
    const scripts=editor?['allmaps-transform','historical-map-cluster','map-place-search','historical-map-places','historical-maps','historical-map-page']:
        ['allmaps-transform','historical-map-cluster','live-map-places','historical-map-browser-data','historical-map-browser'];
    const css=editor?'historical-maps':'historical-map-browser';
    return `<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Local viewer fixture</title>
        <link rel="stylesheet" href="/assets/leaflet/leaflet.css"><link rel="stylesheet" href="/css/historical-map-cluster.css"><link rel="stylesheet" href="/css/${css}.css">
        <style>body{margin:0;background:#fff} .screen-reader-text{position:absolute;left:-9999px}</style>
        <p style="margin:8px 16px;font:12px sans-serif">Local test fixture — synthetic maps and places</p>${result.text}
        <script src="/jquery.js"></script><script src="/assets/leaflet/leaflet.js"></script>
        ${scripts.map(s=>`<script src="/assets/${s}.js"></script>`).join('')}</html>`;
}
async function main() {
    const wasmDir=path.dirname(require.resolve('@php-wasm/node-8-2'));
    const asyncDir=path.join(wasmDir,'asyncify');
    const binaryDir=fs.readdirSync(asyncDir).find(n=>/^8_2_/.test(n));
    php=new PHP(await loadNodeRuntime('8.2',{emscriptenOptions:{processId:process.pid,wasmBinary:fs.readFileSync(path.join(asyncDir,binaryDir,'php_8_2.wasm'))}}));
    php.mkdir('/app');
    for(const name of ['historical-map-viewer.php','historical-map-browser.php']) { php.writeFile('/app/'+name,source('views/shared/'+name)); }
    const syntax=await php.run({code:'<?php if(false){'+source('controllers/IndexController.php').replace(/^<\?php/,'')+'}'});
    assert.equal(syntax.exitCode,0,syntax.errors);
    const publicHtml=await render(false), adminHtml=await render(true);
    assert.ok(publicHtml.includes('id="historical-map-browser"')&&!publicHtml.includes('id="map"'));
    assert.ok(adminHtml.includes('id="historical-map"')&&adminHtml.includes('id="map"')&&!adminHtml.includes('historical-map-browser.js'));
    console.log('PASS: PHP renders a public single-map browser and the separate admin dual-map editor');
    server=http.createServer(async(req,res)=>{
        const url=new URL(req.url,'http://fixture'),pathname=url.pathname;
        const send=(type,body,status=200)=>{res.writeHead(status,{'Content-Type':type,'Cache-Control':'no-store'});res.end(body);};
        const json=value=>send('application/json',JSON.stringify(value));
        if(pathname==='/public')return send('text/html',publicHtml);
        if(pathname==='/admin')return send('text/html',adminHtml);
        if(pathname==='/jquery.js')return send('text/javascript',fs.readFileSync(require.resolve('jquery/jquery.js')));
        if(pathname.startsWith('/assets/')) {
            const file=path.join(root,'views/public/javascripts',pathname.slice(8));
            if(fs.existsSync(file))return send(pathname.endsWith('.css')?'text/css':pathname.endsWith('.png')?'image/png':'text/javascript',fs.readFileSync(file));
        }
        if(pathname.startsWith('/css/'))return send('text/css',source('views/public/css/'+pathname.slice(5)));
        if(pathname.includes('/iiif/')) {
            if(serverData.failImages)return send('text/plain','Image unavailable',503);
            return send('image/svg+xml','<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#e7dcc2"/><path d="M0 60L256 180M70 0L140 256M0 210L256 30" stroke="#9d8d6a" stroke-width="8"/><text x="16" y="32" fill="#6b5d43">Historical map fixture</text></svg>');
        }
        if(pathname==='/thumbnail.svg')return send('image/svg+xml','<svg xmlns="http://www.w3.org/2000/svg" width="200" height="110"><rect width="200" height="110" fill="#edf4f5"/><path d="M60 90V42L100 15L140 42V90Z" fill="#8f798d"/><text x="48" y="105">Test church</text></svg>');
        if(pathname.endsWith('/map-config'))return json({walking_tour_center:'41.994,12.006',walking_tour_default_zoom:13,walking_tour_max_zoom:19});
        if(pathname.endsWith('/historical-maps'))return json({maps:serverData.maps});
        if(pathname.endsWith('/historical-map-places'))return json({places:serverData.places,next:null});
        if(pathname.endsWith('/historical-map-decisions')) {
            if(serverData.slow)await new Promise(r=>setTimeout(r,100));
            const id=Number(url.searchParams.get('map_id')),map=serverData.maps.find(m=>m.id===id);
            return json({revision:map.revision,decisions:serverData.decisions[id]||[],can_edit:true,csrf_token:'fixture-token'});
        }
        if(pathname.endsWith('/historical-map-decision')) {
            let body='';for await(const part of req)body+=part;
            const value=JSON.parse(body),map=serverData.maps.find(m=>m.id===value.map_id);
            const rows=serverData.decisions[map.id]||(serverData.decisions[map.id]=[]);
            let decision=rows.find(d=>d.item_id===value.item_id);
            if(!decision){decision={item_id:value.item_id,pixel:null,visibility:'default',note:'',needs_review:false};rows.push(decision);}
            if(value.operation==='position')decision.pixel=value.value;
            else decision[value.operation]=value.value;
            map.revision++;
            return json({revision:map.revision,decision});
        }
        if(pathname==='/favicon.ico')return send('text/plain','',204);
        return send('text/plain','Not found',404);
    });
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    const base='http://127.0.0.1:'+server.address().port;
    const chrome=process.env.WT_TEST_CHROME||(process.platform==='darwin'?'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome':undefined);
    browser=await chromium.launch({headless:true,...(chrome?{executablePath:chrome}:{})});
    const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];
    page.on('pageerror',e=>{errors.push({surface:'public',stack:e.stack});console.error('Public page error:',e.stack);});
    const library=()=>page.locator('#browser-map-list button').evaluateAll(es=>es.map(e=>Number(e.dataset.mapId)));
    const waitLibrary=async(ids)=>{await page.waitForFunction(expected=>JSON.stringify(Array.from(document.querySelectorAll('#browser-map-list button'),e=>Number(e.dataset.mapId)))===JSON.stringify(expected),ids);};
    await page.goto(base+'/public');await waitLibrary([1,2,3]);
    await page.locator('.historical-browser-marker[title="Church of the Test Site"]').click();await waitLibrary([1,2]);
    await page.waitForSelector('.historical-browser-marker.is-selected');
    assert.match(await page.locator('#browser-place-description').textContent(),/public church description/);
    assert.equal(await page.locator('#browser-place-image').isVisible(),true);
    await page.locator('[data-map-id="2"]').click();
    await page.waitForSelector('.historical-browser-marker.is-selected');
    assert.equal(await page.locator('#browser-place').inputValue(),'10');
    assert.equal(await page.locator('#browser-map-title').textContent(),'Map B');
    await page.screenshot({path:path.join(output,'public-desktop.png'),fullPage:true});
    console.log('PASS: Three-map library filters to A/B, details display, and switching to B preserves the same site');
    await page.locator('#browser-clear').click();await waitLibrary([1,2,3]);
    await page.selectOption('#browser-place','11');await waitLibrary([1,3]);
    assert.equal(await page.locator('#browser-place-title').textContent(),'Northwest place');
    serverData.places[0].coordinates=[12.001,41.999];
    await page.selectOption('#browser-place','10');await waitLibrary([1,2]); // Current snapshot is intentionally cached.
    await page.locator('#browser-reload').click();await waitLibrary([1,3]);
    console.log('PASS: Place selection is independent of the current map and reload recomputes changed geographic relationships');
    serverData.places[0].coordinates=[20,20];await page.locator('#browser-reload').click();await waitLibrary([]);
    await page.waitForFunction(()=>document.querySelector('#browser-library-status').textContent.includes('No available'));
    assert.equal(await page.locator('#browser-place').inputValue(),'10');
    assert.equal(await page.locator('.historical-browser-marker.is-selected').count(),0);
    assert.equal(await page.locator('#browser-place-details').isVisible(),true);
    serverData.places[0].coordinates=[12.006,41.994];await page.locator('#browser-reload').click();await waitLibrary([1,2]);
    await page.setViewportSize({width:390,height:844});
    await page.waitForSelector('.historical-browser-marker.is-selected');
    await page.screenshot({path:path.join(output,'public-mobile.png'),fullPage:true});
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth));
    console.log('PASS: No-match selection remains readable without a stale marker; 390px layout has no horizontal overflow');
    await page.evaluate(()=>{window.fixtureCreate=window.WalkingTourAllmaps.create;window.WalkingTourAllmaps.create=record=>{const model=window.fixtureCreate(record);return record.id===2?{...model,estimate:()=>null}:model;};});
    await page.locator('#browser-reload').click();await waitLibrary([1,2]);
    await page.locator('[data-map-id="2"]').click();
    await page.waitForFunction(()=>document.querySelector('#browser-position-status').textContent.includes('No reliable'));
    assert.equal(await page.locator('.historical-browser-marker.is-selected').count(),0);
    assert.equal(await page.locator('#browser-place').inputValue(),'10');
    await page.evaluate(()=>{window.WalkingTourAllmaps.create=window.fixtureCreate;});
    serverData.slow=true;
    await page.locator('#browser-reload').click();await waitLibrary([1,2]);
    await page.selectOption('#browser-place','10');await page.selectOption('#browser-place','11');await waitLibrary([1,3]);
    assert.equal(await page.locator('#browser-place-title').textContent(),'Northwest place');
    serverData.slow=false;
    serverData.failImages=true;
    await page.locator('#browser-reload').click();await page.waitForSelector('#browser-image-retry:not([hidden])');
    serverData.failImages=false;await page.locator('#browser-image-retry').click();
    await page.waitForFunction(()=>document.querySelector('#browser-image-retry').hidden);
    console.log('PASS: Unavailable estimates do not remove covered maps or leave stale pins; rapid selection and failed-image retry work');
    const admin=await browser.newPage({viewport:{width:1440,height:1000}});admin.on('pageerror',e=>{errors.push({surface:'admin',stack:e.stack});console.error('Admin page error:',e.stack);});
    await admin.route('https://server.arcgisonline.com/**',route=>route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#e5ece5"/></svg>'}));
    await admin.goto(base+'/admin');await admin.waitForFunction(()=>document.querySelector('#historical-place-select').options.length>1);
    assert.ok(await admin.locator('#map').isVisible()&&await admin.locator('#historical-map').isVisible());
    await admin.selectOption('#historical-place-select','10');
    await admin.locator('#historical-place-adjust').click();
    await admin.locator('summary').filter({hasText:'Image coordinates'}).click();
    await admin.locator('#historical-pixel-x').fill('650');await admin.locator('#historical-pixel-y').fill('620');
    await admin.locator('#historical-position-save').click();
    await admin.waitForFunction(()=>document.querySelector('#historical-decision-status').textContent==='Decision saved.');
    assert.deepEqual(serverData.decisions[1].find(d=>d.item_id===10).pixel,[650,620]);
    assert.deepEqual(serverData.places[0].coordinates,[12.006,41.994]);
    await admin.screenshot({path:path.join(output,'admin-dual-editor.png'),fullPage:true});
    await admin.selectOption('#historical-place-visibility','hide');await admin.locator('#historical-visibility-save').click();
    await admin.waitForFunction(()=>document.querySelector('#historical-decision-status').textContent==='Decision saved.');
    await page.selectOption('#browser-place','10');await waitLibrary([1,2]);
    await page.locator('#browser-reload').click();await waitLibrary([2]);
    console.log('PASS: Admin keeps both panes, saves an image-position override without changing Geolocation, and saved visibility applies after public reload');
    assert.deepEqual(errors,[]);console.log('PASS: No browser JavaScript errors');
    console.log('Screenshots:',output);
}
main().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();if(server)await new Promise(r=>server.close(r));if(php)php.exit();});
