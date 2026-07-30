<?php

$root = dirname(__DIR__);
$main = file_get_contents($root . '/WireNPS.module.php');
$process = file_get_contents($root . '/ProcessWireNPS.module.php');

$checks = [
	'WireNPS declares ProcessWireNPS as a companion module' =>
		str_contains($main, "'installs' => ['ProcessWireNPS']"),
	'WireNPS release version is 1.5.2' =>
		str_contains($main, "'version' => '1.5.2'"),
	'ProcessWireNPS release version is 1.5.2' =>
		str_contains($process, "'version' => '1.5.2'"),
	'WireNPS upgrades repair the missing admin module' =>
		str_contains($main, 'public function ___upgrade')
		&& str_contains($main, '$this->installAdminModule();'),
];

foreach ($checks as $label => $passed) {
	if ($passed) continue;
	fwrite(STDERR, "FAIL: {$label}\n");
	exit(1);
}

echo "WireNPS module info smoke test passed.\n";
