import {ProjectedGcpTransformer, lonLatToWebMercator} from '@allmaps/project';

const finite = p => Array.isArray(p) && p.length === 2 && p.every(Number.isFinite);
export function inside(point, ring) {
    let hit = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const a = ring[j], b = ring[i];
        const cross = (point[0]-a[0])*(b[1]-a[1])-(point[1]-a[1])*(b[0]-a[0]);
        if (Math.abs(cross)<1e-7 && point[0]>=Math.min(a[0],b[0]) && point[0]<=Math.max(a[0],b[0]) && point[1]>=Math.min(a[1],b[1]) && point[1]<=Math.max(a[1],b[1])) return true;
        if ((a[1]>point[1]) !== (b[1]>point[1]) && point[0] < (b[0]-a[0])*(point[1]-a[1])/(b[1]-a[1])+a[0]) hit = !hit;
    }
    return hit;
}
export function create(record) {
    if (record.source_kind !== 'allmaps' || !record.georeference) throw new Error('Import this legacy map from Allmaps to display estimated places.');
    const map = {...record.georeference};
    if (!map.resourceCrs) delete map.resourceCrs;
    const type = map.transformation.type === 'polynomial' ? 'polynomial'+(map.transformation.options?.order || 1) : map.transformation.type;
    const transformer = ProjectedGcpTransformer.fromGeoreferencedMap(map, {transformationType:type});
    const valid = p => finite(p) && p[0]>=0 && p[1]>=0 && p[0]<=record.image_width && p[1]<=record.image_height && inside(p,map.resourceMask);
    // Inverse fits are guesses, not proof of a unique inverse. Solve the imported
    // forward model from independent starts and reject disagreeing roots.
    const forward = p => transformer.transformToProjectedGeo(p);
    const estimate = geo => {
        if (!finite(geo) || Math.abs(geo[1])>=85.05112878) return null;
        try {
            const target = lonLatToWebMercator(geo);
            const starts = [transformer.transformToResource(target), ...[[.2,.2],[.8,.2],[.5,.5],[.2,.8],[.8,.8]].map(p=>[p[0]*record.image_width,p[1]*record.image_height])];
            const roots = [];
            for (let p of starts) {
                p = [...p];
                for (let i=0;i<25;i++) {
                    const q=forward(p), error=[q[0]-target[0],q[1]-target[1]];
                    if (!finite(q)) break;
                    if (Math.hypot(...error)<0.05) {if (valid(p) && !roots.some(r=>Math.hypot(r[0]-p[0],r[1]-p[1])<.5)) roots.push(p);break;}
                    const h=.5, x=forward([p[0]+h,p[1]]), y=forward([p[0],p[1]+h]);
                    const a=(x[0]-q[0])/h,b=(y[0]-q[0])/h,c=(x[1]-q[1])/h,d=(y[1]-q[1])/h, det=a*d-b*c;
                    if (!Number.isFinite(det)||Math.abs(det)<1e-12) break;
                    const step=[(d*error[0]-b*error[1])/det,(-c*error[0]+a*error[1])/det];
                    p=[p[0]-step[0],p[1]-step[1]];
                    if (!finite(p)||Math.abs(p[0])>record.image_width*4||Math.abs(p[1])>record.image_height*4) break;
                }
                if (roots.length>1) return null;
            }
            return roots.length === 1 ? roots[0] : null;
        } catch (_) { return null; }
    };
    // Densify the mask in image space: nonlinear transforms bend straight edges.
    // Work in metres when measuring chord error, then return Leaflet's lon/lat.
    let outline;
    const footprint = () => {
        if (outline !== undefined) return outline;
        outline = null;
        try {
            const ring = map.resourceMask, pixels = [];
            const append = (a, b, depth = 0) => {
                const middle = [(a[0]+b[0])/2, (a[1]+b[1])/2];
                const qa = forward(a), qb = forward(b), qm = forward(middle);
                if (![qa,qb,qm].every(finite) || pixels.length > 8192) throw new Error('Invalid mask footprint');
                const error = Math.hypot(qm[0]-(qa[0]+qb[0])/2, qm[1]-(qa[1]+qb[1])/2);
                if (depth < 2 || error > 5 || Math.hypot(qb[0]-qa[0],qb[1]-qa[1]) > 250) {
                    if (depth >= 12) throw new Error('Mask footprint did not converge');
                    append(a,middle,depth+1); append(middle,b,depth+1);
                } else pixels.push(a);
            };
            for (let i=0; i<ring.length; i++) {
                const a=ring[i], b=ring[(i+1)%ring.length];
                if (a[0] !== b[0] || a[1] !== b[1]) append(a,b);
            }
            const geo=pixels.map(p=>transformer.transformToGeo(p));
            if (geo.length < 3 || !geo.every(p=>finite(p) && Math.abs(p[0])<=180 && Math.abs(p[1])<85.05112878)) return null;
            outline=geo;
        } catch (_) { /* A failed outline never becomes an invented bounding box. */ }
        return outline;
    };
    return {estimate, valid, footprint, forward: p=>transformer.transformToGeo(p)};
}
window.WalkingTourAllmaps = {create, inside};
