<?php

declare(strict_types=1);

use PHPSymmetry\SymmetryGroup\SpaceGroup;

require_once __DIR__ . '/../vendor/autoload.php';

echo 'This script will perform the integrity check of the spacegroups.json file.' . PHP_EOL;
echo PHP_EOL;

$f = file_get_contents(__DIR__ . '/../data/spacegroups.json');
if ($f === false) {
    echo 'Error: Cannot read the spacegroups.json file.' . PHP_EOL;
    exit(1);
}

$spacegroups = json_decode($f, true);
if ($spacegroups === null) {
    echo 'Error: Cannot decode the spacegroups.json file.' . PHP_EOL;
    exit(1);
}

foreach ($spacegroups as $s) {
    echo 'Checking space group ' . $s['number'] . '... ' . PHP_EOL;
    echo 'Generating the space group from the Hall symbol: ' . $s['hall'] . '... ';
    $sHall = SpaceGroup::makeFromHallSymbol($s['hall'])->expandGroup();
    echo 'done.' . PHP_EOL;
    echo $sHall . PHP_EOL;
    //foreach ($sHall->symmetryOperations() as $i => $op) {
    //    echo '  ' . ($i + 1) . '. ' . $op->toSymbol() . PHP_EOL;
    //}

    echo 'Generating the space group from the explicit symbol: ' . $s['explicit'] . '... ';
    $sExplicit = SpaceGroup::makeFromExplicitSymbol($s['explicit'])->expandGroup();
    echo 'done.' . PHP_EOL;
    echo $sExplicit . PHP_EOL;

    echo 'Generating the space group from the generators: ' . $s['generators'] . '... ';
    $sGenerators = SpaceGroup::makeFromExplicitSymbol($s['generators'])->expandGroup();
    echo 'done.' . PHP_EOL;
    echo $sGenerators . PHP_EOL;

    echo 'Comparing the space group operations from the Hall symbol and from the explicit symbol... ';
    if (!$sHall->isEqual($sExplicit)) {
        echo 'Error: The space group operations do not match.' . PHP_EOL;
        exit(1);
    }
    echo 'done.' . PHP_EOL;

    echo 'Comparing the space group operations from the Hall symbol and from the generators... ';
    if (!$sHall->isEqual($sGenerators)) {
        echo 'Error: The space group operations do not match.' . PHP_EOL;
        exit(1);
    }
    echo 'done.' . PHP_EOL;

    echo 'Comparing the space group operations from the explicit symbol and from the generators... ';
    if (!$sExplicit->isEqual($sGenerators)) {
        echo 'Error: The space group operations do not match.' . PHP_EOL;
        exit(1);
    }
    echo 'done.' . PHP_EOL;

    echo PHP_EOL;
}
