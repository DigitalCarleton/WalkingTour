<?php

/** Decode uploads before publishing them through Omeka's configured storage adapter. */
class WalkingTour_HistoricalMapUpload
{
    const MAX_BYTES = 20971520;
    const MAX_PIXELS = 25000000;

    public static function storagePath($snapshot)
    {
        $path = $snapshot['storage_path'] ?? '';
        if (!is_string($path) || !preg_match('~^original/walkingtour-[a-f0-9]{32}\.(?:jpg|png)$~D', $path)) {
            throw new InvalidArgumentException('Invalid uploaded image reference.');
        }
        return $path;
    }

    /** Inspect before decoding so oversized compressed images cannot exhaust server memory. */
    public function inspect($path)
    {
        $bytes = is_file($path) ? filesize($path) : false;
        if (!$bytes || $bytes > self::MAX_BYTES) { throw new InvalidArgumentException('Choose an image of up to 20 MB.'); }
        $info = @getimagesize($path);
        if (!$info || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG), true)) {
            throw new InvalidArgumentException('Choose a valid JPEG or PNG image.');
        }
        if ($info[0] < 1 || $info[1] < 1 || $info[0] > 20000 || $info[1] > 20000 || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new InvalidArgumentException('Use an image of up to 25 million pixels and 20,000 pixels per side.');
        }
        $limit = trim(ini_get('memory_limit'));
        if ($limit !== '-1') {
            $memory = (float) $limit;
            $unit = strtolower(substr($limit, -1));
            if ($unit === 'g') { $memory *= 1073741824; }
            elseif ($unit === 'm') { $memory *= 1048576; }
            elseif ($unit === 'k') { $memory *= 1024; }
            // Allow for decoding, JPEG orientation, and encoding buffers.
            if (memory_get_usage(true) + $info[0] * $info[1] * 12 + $bytes * 2 + 16777216 > $memory) {
                throw new InvalidArgumentException('This image is too large for the server to process. Upload a smaller copy.');
            }
        }
        return $info;
    }

    private function orient($image, $path, $type)
    {
        if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) { return $image; }
        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        if (in_array($orientation, array(2, 4, 5, 7), true)) { imageflip($image, IMG_FLIP_HORIZONTAL); }
        $angle = array(3 => 180, 4 => 180, 5 => -90, 6 => -90, 7 => 90, 8 => 90)[$orientation] ?? 0;
        if (!$angle) { return $image; }
        $rotated = imagerotate($image, $angle, 0);
        if (!$rotated) { throw new RuntimeException('The image could not be oriented.'); }
        imagedestroy($image);
        return $rotated;
    }

    public function save($upload, $title, $source, $maps, $storage)
    {
        if (!is_string($title) || trim($title) === '' || mb_strlen(trim($title)) > 255) {
            throw new InvalidArgumentException('Enter a title of 1 to 255 characters.');
        }
        if ($source !== '') { WalkingTour_HistoricalMapIiif::url($source); }
        if (!is_array($upload) || !isset($upload['error']) || is_array($upload['error'])) {
            throw new InvalidArgumentException('Choose a JPEG or PNG image to upload.');
        }
        if (in_array($upload['error'], array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
            throw new InvalidArgumentException('The image exceeds the server upload limit. Choose a smaller copy.');
        }
        if ($upload['error'] !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name'])) {
            throw new InvalidArgumentException('The upload did not complete. Choose the image again and retry.');
        }
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg') || !function_exists('imagepng')) {
            throw new RuntimeException('Image uploads require PHP GD with JPEG and PNG support.');
        }
        $info = $this->inspect($upload['tmp_name']);
        $image = @imagecreatefromstring(file_get_contents($upload['tmp_name']));
        if (!$image) { throw new InvalidArgumentException('The image is damaged or cannot be decoded. Choose another JPEG or PNG.'); }
        $temporary = null;
        $destination = null;
        try {
            $image = $this->orient($image, $upload['tmp_name'], $info[2]);
            $width = imagesx($image); $height = imagesy($image);
            $extension = $info[2] === IMAGETYPE_JPEG ? 'jpg' : 'png';
            $temporary = tempnam($storage->getTempDir(), 'wt-image-');
            if (!$temporary) { throw new RuntimeException('Image processing storage is unavailable.'); }
            // Publish decoded pixels only; discard original metadata and any appended content.
            if ($extension === 'png') { imagealphablending($image, false); imagesavealpha($image, true); }
            $encoded = $extension === 'jpg' ? imagejpeg($image, $temporary, 95) : imagepng($image, $temporary);
            if (!$encoded) { throw new RuntimeException('The image could not be saved.'); }
            $checksum = hash_file('sha256', $upload['tmp_name']);
            $destination = 'original/walkingtour-' . bin2hex(random_bytes(16)) . '.' . $extension;
            $storage->store($temporary, $destination);
            $map = $maps->createMap(array('title' => trim($title), 'source_url' => $source,
                'image_service' => 'upload:' . $checksum, 'manifest_url' => '', 'image_width' => $width, 'image_height' => $height,
                'source_snapshot' => json_encode(array('source_kind' => 'upload', 'storage_path' => $destination, 'sha256' => $checksum))));
            $destination = null;
            return $map;
        } finally {
            imagedestroy($image);
            if ($temporary && is_file($temporary)) { unlink($temporary); }
            if ($destination) {
                try { $storage->delete($destination); }
                catch (Exception $error) { _log($error, Zend_Log::ERR); }
            }
        }
    }
}
