<?php namespace ProcessWire;

if(!defined('PROCESSWIRE')) exit;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'POST request required']);
    exit;
}

$wirenps = $modules->get('WireNPS');
if(!$wirenps) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Service unavailable']);
    exit;
}

$wirenps->processSubmission();
