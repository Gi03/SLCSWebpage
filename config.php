<?php
// CHANGE THIS PASSWORD before uploading to your host.
const ADMIN_PASSWORD = 'change-this-password';

const TOUR_DIR   = __DIR__ . '/images/tour/';     // where photos are saved
const TOUR_URL   = 'images/tour/';                // how the website reaches them
const DATA_FILE  = __DIR__ . '/images/tour/photos.json';
const MAX_BYTES  = 5 * 1024 * 1024;               // 5 MB per photo

function load_photos(): array {
    if (!is_file(DATA_FILE)) return [];
    $d = json_decode(file_get_contents(DATA_FILE), true);
    return is_array($d) ? $d : [];
}

function save_photos(array $photos): void {
    file_put_contents(
        DATA_FILE,
        json_encode(array_values($photos), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}
