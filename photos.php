<?php
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$out = [];
foreach (load_photos() as $p) {
    if (is_file(TOUR_DIR . $p['file'])) {
        $out[] = ['file' => TOUR_URL . $p['file'], 'caption' => $p['caption'], 'type' => $p['type'] ?? 'photo'];
    }
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
