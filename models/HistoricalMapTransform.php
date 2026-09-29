<?php

/** Normalized thin-plate spline from original image pixels to longitude/latitude. */
class WalkingTour_HistoricalMapTransform
{
    private $nodes = array();
    private $weights;
    private $width;
    private $height;
    private $scale;

    public function __construct(array $map)
    {
        $this->width = $map['image_width'];
        $this->height = $map['image_height'];
        $this->scale = max($this->width, $this->height);
        $points = $map['control_points'];
        $n = count($points);
        if ($n < 3 || $n > 200 || $this->scale <= 0) {
            throw new RuntimeException('Calibration is unavailable.');
        }
        foreach ($points as $point) {
            $this->nodes[] = array($point['image_x'] / $this->scale, $point['image_y'] / $this->scale);
        }
        $matrix = array_fill(0, $n + 3, array_fill(0, $n + 5, 0.0));
        foreach ($this->nodes as $i => $node) {
            foreach ($this->nodes as $j => $other) {
                $matrix[$i][$j] = self::kernel($node[0] - $other[0], $node[1] - $other[1]);
            }
            $p = array(1.0, $node[0], $node[1]);
            foreach ($p as $j => $v) { $matrix[$i][$n + $j] = $matrix[$n + $j][$i] = $v; }
            $matrix[$i][$n + 3] = $points[$i]['longitude'];
            $matrix[$i][$n + 4] = $points[$i]['latitude'];
        }
        // Partial pivoting rejects duplicate/collinear controls instead of inventing a fit.
        $size = $n + 3;
        for ($k = 0; $k < $size; $k++) {
            $pivot = $k;
            for ($i = $k + 1; $i < $size; $i++) {
                if (abs($matrix[$i][$k]) > abs($matrix[$pivot][$k])) { $pivot = $i; }
            }
            if (abs($matrix[$pivot][$k]) < 1e-12) { throw new RuntimeException('Calibration is unstable.'); }
            $row = $matrix[$k]; $matrix[$k] = $matrix[$pivot]; $matrix[$pivot] = $row;
            $divisor = $matrix[$k][$k];
            for ($j = $k; $j < $size + 2; $j++) { $matrix[$k][$j] /= $divisor; }
            for ($i = 0; $i < $size; $i++) {
                if ($i === $k) { continue; }
                $factor = $matrix[$i][$k];
                for ($j = $k; $j < $size + 2; $j++) { $matrix[$i][$j] -= $factor * $matrix[$k][$j]; }
            }
        }
        $this->weights = array_map(function ($row) use ($size) {
            return array($row[$size], $row[$size + 1]);
        }, $matrix);
    }

    private static function kernel($x, $y)
    {
        $r = $x * $x + $y * $y;
        return $r > 0 ? $r * log($r) : 0;
    }

    public function forward(array $pixel)
    {
        $x = $pixel[0] / $this->scale; $y = $pixel[1] / $this->scale;
        $n = count($this->nodes);
        $out = array(0.0, 0.0);
        for ($axis = 0; $axis < 2; $axis++) {
            $out[$axis] = $this->weights[$n][$axis] + $x * $this->weights[$n + 1][$axis] + $y * $this->weights[$n + 2][$axis];
            foreach ($this->nodes as $i => $node) {
                $out[$axis] += $this->weights[$i][$axis] * self::kernel($x - $node[0], $y - $node[1]);
            }
        }
        if (!is_finite($out[0]) || !is_finite($out[1]) || abs($out[0]) > 180 || abs($out[1]) > 85) { return null; }
        return $out;
    }

    /** Solve the same forward model, rather than fitting an inconsistent second spline. */
    public function inverse(array $geo)
    {
        $solutions = array();
        // Multiple starting positions detect common folds and non-unique image locations.
        for ($gx = 0; $gx <= 4; $gx++) {
            for ($gy = 0; $gy <= 4; $gy++) {
                $p = array($this->width * $gx / 4, $this->height * $gy / 4);
                for ($iteration = 0; $iteration < 35; $iteration++) {
                    $f = $this->forward($p);
                    $fx = $this->forward(array($p[0] + 1, $p[1]));
                    $fy = $this->forward(array($p[0], $p[1] + 1));
                    if (!$f || !$fx || !$fy) { break; }
                    $a = $fx[0] - $f[0]; $b = $fy[0] - $f[0];
                    $c = $fx[1] - $f[1]; $d = $fy[1] - $f[1];
                    $det = $a * $d - $b * $c;
                    if (abs($det) < 1e-16) { break; }
                    $rx = $geo[0] - $f[0]; $ry = $geo[1] - $f[1];
                    if (hypot($rx, $ry) < 1e-9) {
                        if ($p[0] >= -.01 && $p[0] <= $this->width + .01 && $p[1] >= -.01 && $p[1] <= $this->height + .01) {
                            $p = array(max(0, min($this->width, $p[0])), max(0, min($this->height, $p[1])));
                            foreach ($solutions as $solution) {
                                if (hypot($p[0] - $solution[0], $p[1] - $solution[1]) > 1) { return null; }
                            }
                            $solutions[] = $p;
                        }
                        break;
                    }
                    $dx = ($d * $rx - $b * $ry) / $det; $dy = (-$c * $rx + $a * $ry) / $det;
                    $damping = min(1, $this->scale / 2 / max(1, hypot($dx, $dy)));
                    $p[0] += $dx * $damping; $p[1] += $dy * $damping;
                    if (abs($p[0]) > $this->scale * 3 || abs($p[1]) > $this->scale * 3) { break; }
                }
            }
        }
        return $solutions ? $solutions[0] : null;
    }

    public function footprint(array $ring)
    {
        $result = array();
        for ($i = 0; $i < count($ring) - 1; $i++) {
            $a = $ring[$i]; $b = $ring[$i + 1];
            // TPS edges are curved: transforming only the corners loses the footprint.
            $steps = max(1, (int) ceil(hypot($a[0] - $b[0], $a[1] - $b[1]) / ($this->scale / 32)));
            for ($j = 0; $j < $steps; $j++) {
                $point = $this->forward(array($a[0] + ($b[0] - $a[0]) * $j / $steps, $a[1] + ($b[1] - $a[1]) * $j / $steps));
                if (!$point) { return null; }
                $result[] = $point;
            }
        }
        if ($result) { $result[] = $result[0]; }
        return $result;
    }

    public function covered(array $pixel)
    {
        $points = $this->nodes;
        usort($points, function ($a, $b) { return $a[0] == $b[0] ? $a[1] <=> $b[1] : $a[0] <=> $b[0]; });
        $lower = array(); $upper = array();
        foreach ($points as $p) {
            while (count($lower) > 1 && self::cross($lower[count($lower)-2], end($lower), $p) <= 0) { array_pop($lower); }
            $lower[] = $p;
        }
        foreach (array_reverse($points) as $p) {
            while (count($upper) > 1 && self::cross($upper[count($upper)-2], end($upper), $p) <= 0) { array_pop($upper); }
            $upper[] = $p;
        }
        array_pop($lower); array_pop($upper);
        $hull = array_merge($lower, $upper);
        $p = array($pixel[0] / $this->scale, $pixel[1] / $this->scale);
        for ($i = 0; $i < count($hull); $i++) {
            if (self::cross($hull[$i], $hull[($i + 1) % count($hull)], $p) < -1e-9) { return false; }
        }
        return true;
    }

    private static function cross($a, $b, $c)
    {
        return ($b[0] - $a[0]) * ($c[1] - $a[1]) - ($b[1] - $a[1]) * ($c[0] - $a[0]);
    }
}
