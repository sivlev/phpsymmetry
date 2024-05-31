<?php
/*
 * PHPSymmetry Library
 *
 * @see https://github.com/sivlev/phpsymmetry
 *
 * @author Sergei Ivlev <sergei.ivlev@chemie.uni-marburg.de>
 * @copyright (c) 2024 Sergei Ivlev
 * @license https://opensource.org/license/mit The MIT License
 *
 * @note This software is distributed "as is", with no warranty expressed or implied, and no guarantee for accuracy or applicability to any purpose. See the license text for details.
 */

/**
 * This is a template to create a script that will perform some actions of the spacegroups.json file.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PHPSymmetry\SymmetryGroup\SpaceGroup;

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

foreach ($spacegroups as $index => &$spacegroup) {

}

$f = fopen(__DIR__ . '/../data/spacegroups.json', 'w');
if ($f === false) {
    echo 'Error: Cannot write the spacegroups.json file.' . PHP_EOL;
    exit(1);
}
fwrite($f, json_encode($spacegroups, JSON_PRETTY_PRINT) ?? "");
fclose($f);
echo 'The spacegroups.json file has been updated.' . PHP_EOL;
exit(0);
