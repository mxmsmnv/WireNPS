<?php

$root = dirname(__DIR__);
$main = file_get_contents($root . '/WireNPS.module.php');
$process = file_get_contents($root . '/ProcessWireNPS.module.php');

$checks = [
	'WireNPS declares ProcessWireNPS as a companion module' =>
		str_contains($main, "'installs' => ['ProcessWireNPS']"),
	'WireNPS release version is 1.5.3' =>
		str_contains($main, "'version' => '1.5.3'"),
	'ProcessWireNPS release version is 1.5.3' =>
		str_contains($process, "'version' => '1.5.3'"),
	'WireNPS upgrades repair the missing admin module' =>
		str_contains($main, 'public function ___upgrade')
		&& str_contains($main, '$this->installAdminModule();'),
	'Monthly NPS uses portable fractional division' =>
		str_contains($process, 'ROUND(100.0 * (SUM(CASE WHEN')
		&& str_contains($process, '/ NULLIF(COUNT(*), 0), 1)'),
];

foreach ($checks as $label => $passed) {
	if ($passed) continue;
	fwrite(STDERR, "FAIL: {$label}\n");
	exit(1);
}

if (in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
	$sqlite = new \PDO('sqlite::memory:');
	$sqlite->exec('CREATE TABLE ratings (score INTEGER NOT NULL)');
	$sqlite->exec('INSERT INTO ratings (score) VALUES (10), (8), (2)');
	$nps = $sqlite->query('SELECT ROUND(100.0 * (SUM(CASE WHEN score >= 9 THEN 1 ELSE 0 END) - SUM(CASE WHEN score <= 6 THEN 1 ELSE 0 END)) / NULLIF(COUNT(*), 0), 1) FROM ratings')->fetchColumn();
	if ((float)$nps !== 0.0) {
		fwrite(STDERR, "FAIL: SQLite fractional NPS regression fixture\n");
		exit(1);
	}
	$sqlite->exec('DELETE FROM ratings WHERE score = 2');
	$nps = $sqlite->query('SELECT ROUND(100.0 * (SUM(CASE WHEN score >= 9 THEN 1 ELSE 0 END) - SUM(CASE WHEN score <= 6 THEN 1 ELSE 0 END)) / NULLIF(COUNT(*), 0), 1) FROM ratings')->fetchColumn();
	if ((float)$nps !== 50.0) {
		fwrite(STDERR, "FAIL: SQLite monthly NPS used integer division\n");
		exit(1);
	}
}

echo "WireNPS module info smoke test passed.\n";
