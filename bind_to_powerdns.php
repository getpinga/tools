<?php
/**
 * Bind Zonefile to PowerDNS converter
 *
 * Written in 2023 by Taras Kondratyuk (https://getpinga.com)
 *
 * @license MIT
 */

$dir = '/path/to/zone/files';
$sqlFile = '/path/to/sql/file.sql';

$zones = [];

// Scan the directory for BIND zone files
$iterator = new RecursiveDirectoryIterator($dir);
foreach (new RecursiveIteratorIterator($iterator) as $file) {
    if ($file->getExtension() === 'zone') {
        $zones[] = $file;
    }
}

// Generate the SQL file
$sql = "INSERT INTO domains (name, type) VALUES\n";
$domainId = 1;
foreach ($zones as $zone) {
    $zoneName = substr($zone->getBasename(), 0, -5);
    $sql .= "('$zoneName', 'NATIVE'),";

    // Parse the zone file for records
    $records = [];
    $fileContent = file_get_contents($zone->getPathname());
    preg_match_all('/([\w\d-]+)\s+([\d]+)\s+IN\s+([A-Z]+)\s+(.+)/', $fileContent, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $records[] = [
            'name' => $match[1],
            'ttl' => $match[2],
            'type' => $match[3],
            'content' => $match[4]
        ];
    }

    // Generate the SQL for the records
    $sql .= "INSERT INTO records (domain_id, name, type, content, ttl, prio) VALUES\n";
    foreach ($records as $record) {
        $prio = 0;
        if ($record['type'] === 'MX') {
            $parts = explode(' ', $record['content']);
            $prio = array_shift($parts);
            $record['content'] = implode(' ', $parts);
        }

        $sql .= "($domainId, '{$record['name']}', '{$record['type']}', '{$record['content']}', {$record['ttl']}, $prio),";
    }

    $sql = rtrim($sql, ',') . ";\n";
    $domainId++;
}

$sql = rtrim($sql, ',') . ";\n";

file_put_contents($sqlFile, $sql);
