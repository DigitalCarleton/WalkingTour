<?php

/** Retained upload path validation for recovery, admin inspection and configured file cleanup. */
class WalkingTour_HistoricalMapUpload
{
    public static function storagePath($snapshot)
    {
        $path = $snapshot['storage_path'] ?? '';
        if (!is_string($path) || !preg_match('~^original/walkingtour-[a-f0-9]{32}\.(?:jpg|png)$~D', $path)) {
            throw new InvalidArgumentException('Invalid uploaded image reference.');
        }
        return $path;
    }

}
