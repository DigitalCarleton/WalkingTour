(function (global, $) {
    'use strict';
    /** Read every current public Item page. A failed page never returns a stale/partial layer. */
    function load(url) {
        var deferred = $.Deferred(), places = [], seen = Object.create(null), pages = 0;
        function page(after) {
            $.ajax({url: url, data: {after: after}, dataType: 'json', timeout: 15000}).done(function (result) {
                if (!result || !Array.isArray(result.places) || result.places.length > 500 || ++pages > 1000) {
                    deferred.reject(new Error('The place service returned an invalid page.')); return;
                }
                result.places.forEach(function (place) {
                    var point = place && place.coordinates;
                    if (!place || !Number.isInteger(place.item_id) || place.item_id < 1 || seen[place.item_id] ||
                        !Array.isArray(point) || point.length !== 2 || !point.every(Number.isFinite) ||
                        Math.abs(point[0]) > 180 || Math.abs(point[1]) > 90) { return; }
                    seen[place.item_id] = true; places.push(place);
                });
                if (result.next === null) { deferred.resolve(places); return; }
                if (!Number.isInteger(result.next) || result.next <= after) {
                    deferred.reject(new Error('The place service returned an invalid cursor.')); return;
                }
                page(result.next);
            }).fail(function () { deferred.reject(new Error('Live Omeka places are unavailable.')); });
        }
        page(0);
        return deferred.promise();
    }
    global.WalkingTourLivePlaces = {load: load};
})(window, jQuery);
