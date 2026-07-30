<?php namespace ProcessWire;

if(!defined('PROCESSWIRE')) exit;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');

$wirenps = $modules->get('WireNPS');
if(!$wirenps) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Service unavailable']);
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'GET' && $input->get('action') === 'fragment') {
    $payload = json_encode(
        $wirenps->renderFragment((int)$input->get('page_id')),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    // A read-only fragment must not persist a new ProcessWire session.
    // Otherwise the wire(s) cookie makes later navigation bypass CloudCache.
    header_remove('Set-Cookie');
    echo $payload;
    exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    echo json_encode(['success' => false, 'error' => 'Unsupported request']);
    exit;
}

$wirenps->processSubmission();
