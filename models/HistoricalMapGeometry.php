<?php

/** Validate the single, simple GeoJSON polygon supported by the map editor. */
class WalkingTour_HistoricalMapGeometry
{
    public static function coordinate($value, $xLimit, $yLimit)
    {
        if (!is_array($value) || array_keys($value) !== array(0, 1)) {
            throw new InvalidArgumentException('A position must contain two coordinates.');
        }
        foreach ($value as $number) {
            if ((!is_int($number) && !is_float($number)) || !is_finite((float) $number)) {
                throw new InvalidArgumentException('Coordinates must be finite numbers.');
            }
        }
        if (abs($value[0]) > $xLimit || abs($value[1]) > $yLimit) {
            throw new InvalidArgumentException('The position is outside the supported coordinate range.');
        }
        return array((float) $value[0], (float) $value[1]);
    }

    public static function ring($ring)
    {
        if (!is_array($ring) || count($ring) < 4 || count($ring) > 501 || array_keys($ring) !== range(0, count($ring)-1)) {
            throw new InvalidArgumentException('Draw a closed polygon with 3 to 500 vertices.');
        }
        $ring = array_map(function ($p) { return self::coordinate($p, 180, 85); }, $ring);
        if ($ring[0] !== $ring[count($ring)-1]) { throw new InvalidArgumentException('Close the mask by selecting its first point.'); }
        $n = count($ring) - 1;
        $area = 0;
        $seen = array();
        for ($i = 0; $i < $n; $i++) {
            $key = json_encode($ring[$i]);
            if (isset($seen[$key])) { throw new InvalidArgumentException('Mask vertices must be distinct.'); }
            $seen[$key] = true;
            $a = $ring[$i]; $b = $ring[$i + 1];
            if (abs($a[0] - $b[0]) > 180) { throw new InvalidArgumentException('Masks crossing the date line are not supported.'); }
            // Translate to the first vertex to avoid cancellation for small polygons.
            $area += ($a[0]-$ring[0][0])*($b[1]-$ring[0][1])-($b[0]-$ring[0][0])*($a[1]-$ring[0][1]);
            for ($j = $i + 1; $j < $n; $j++) {
                if ($j === $i + 1 || ($i === 0 && $j === $n - 1)) { continue; }
                if (self::intersects($a, $b, $ring[$j], $ring[$j+1])) {
                    throw new InvalidArgumentException('Mask edges must not cross or touch each other.');
                }
            }
        }
        if (abs($area) < 1e-12) { throw new InvalidArgumentException('The mask must enclose an area.'); }
        return $ring;
    }

    private static function cross($a, $b, $c)
    {
        return ($b[0]-$a[0])*($c[1]-$a[1])-($b[1]-$a[1])*($c[0]-$a[0]);
    }

    private static function intersects($a, $b, $c, $d)
    {
        if (max($a[0],$b[0]) < min($c[0],$d[0]) || max($c[0],$d[0]) < min($a[0],$b[0]) ||
            max($a[1],$b[1]) < min($c[1],$d[1]) || max($c[1],$d[1]) < min($a[1],$b[1])) { return false; }
        return self::cross($a,$b,$c)*self::cross($a,$b,$d) <= 0 && self::cross($c,$d,$a)*self::cross($c,$d,$b) <= 0;
    }
}
