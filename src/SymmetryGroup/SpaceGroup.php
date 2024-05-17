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

declare(strict_types=1);

namespace PHPSymmetry\SymmetryGroup;

use PHPMathObjects\LinearAlgebra\Matrix;
use PHPMathObjects\LinearAlgebra\Vector;
use PHPSymmetry\Exception\InvalidArgumentException;
use PHPSymmetry\Support;
use PHPSymmetry\SymmetryOperation\SymmetryOperation3D;

use function array_values;
use function count;
use function explode;
use function preg_match;
use function strlen;
use function strtoupper;
use function trim;
use function array_key_exists;
use function intval;
use function array_shift;
use function array_map;

/**
 * Class for space groups.
 *
 *  References:
 *  [1] International Tables for Crystallography, Vol. A, 2016.
 *
 * @extends AbstractSymmetryGroup<SymmetryOperation3D>
 * @phpstan-type VectorArray array<int, int|float>
 * @phpstan-type MatrixArray array<int, array<int, int|float>>
 * @phpstan-type IntMatrixArray array<int, array<int, int>>
 */
class SpaceGroup extends AbstractSymmetryGroup
{
    /**
     * Dimensionality of the space where the space group exists.
     */
    public const DIMENSIONALITY = 3;

    /**
     * Class of the symmetry operation to be used in the space group.
     */
    protected const SYMMETRY_OPERATION_CLASS = SymmetryOperation3D::class;

    /**
     * Primary translations of the space group.
     *
     * @var IntMatrixArray
     */
    protected static array $primaryTranslationsArray = [
        [1, 0, 0],
        [0, 1, 0],
        [0, 0, 1],
    ];

    /**
     * Centering translations of the space group. The list includes the S and T translations used in Hall symbols.
     * @var array<string, MatrixArray>
     */
    protected static array $centeringTranslationsArray = [
        "P" => [],
        "I" => [[0.5, 0.5, 0.5]],
        "A" => [[0.5, 0, 0]],
        "B" => [[0, 0.5, 0]],
        "C" => [[0, 0, 0.5]],
        "F" => [[0, 0.5, 0.5], [0.5, 0, 0.5], [0.5, 0.5, 0]],
        "R" => [[2 / 3, 1 / 3, 1 / 3], [1 / 3, 2 / 3, 2 / 3]],
        "S" => [[1 / 3, 1 / 3, 2 / 3], [2 / 3, 2 / 3, 1 / 3]],
        "T" => [[1 / 3, 2 / 3, 1 / 3], [2 / 3, 1 / 3, 2 / 3]],
    ];

    /**
     * Possible rotations for generating space groups from Hall symbols.
     *
     * @var array<string, MatrixArray>
     */
    protected static array $hallRotations = [
        "1X" => [
            [1, 0, 0],
            [0, 1, 0],
            [0, 0, 1],
        ],
        "1Y" => [
            [1, 0, 0],
            [0, 1, 0],
            [0, 0, 1],
        ],
        "1Z" => [
            [1, 0, 0],
            [0, 1, 0],
            [0, 0, 1],
        ],
        "2X" => [
            [1, 0, 0],
            [0, -1, 0],
            [0, 0, -1],
        ],
        "2Y" => [
            [-1, 0, 0],
            [0, 1, 0],
            [0, 0, -1],
        ],
        "2Z" => [
            [-1, 0, 0],
            [0, -1, 0],
            [0, 0, 1],
        ],
        "3X" => [
            [1, 0, 0],
            [0, 0, -1],
            [0, 1, -1],
        ],
        "3Y" => [
            [-1, 0, 1],
            [0, 1, 0],
            [-1, 0, 0],
        ],
        "3Z" => [
            [0, -1, 0],
            [1, -1, 0],
            [0, 0, 1],
        ],
        "4X" => [
            [1, 0, 0],
            [0, 0, -1],
            [0, 1, 0],
        ],
        "4Y" => [
            [0, 0, 1],
            [0, 1, 0],
            [-1, 0, 0],
        ],
        "4Z" => [
            [0, -1, 0],
            [1, 0, 0],
            [0, 0, 1],
        ],
        "6X" => [
            [1, 0, 0],
            [0, 1, -1],
            [0, 1, 0],
        ],
        "6Y" => [
            [0, 0, 1],
            [0, 1, 0],
            [-1, 0, 1],
        ],
        "6Z" => [
            [1, -1, 0],
            [1, 0, 0],
            [0, 0, 1],
        ],
        "2'X" => [
            [-1, 0, 0],
            [0, 0, -1],
            [0, -1, 0],
        ],
        '2"X' => [
            [-1, 0, 0],
            [0, 0, 1],
            [0, 1, 0],
        ],
        "2'Y" => [
            [0, 0, -1],
            [0, -1, 0],
            [-1, 0, 0],
        ],
        '2"Y' => [
            [0, 0, 1],
            [0, -1, 0],
            [1, 0, 0],
        ],
        "2'Z" => [
            [0, -1, 0],
            [-1, 0, 0],
            [0, 0, -1],
        ],
        '2"Z' => [
            [0, 1, 0],
            [1, 0, 0],
            [0, 0, -1],
        ],
        "3*" => [
            [0, 0, 1],
            [1, 0, 0],
            [0, 1, 0],
        ],
    ];

    /**
     * Possible rotation matrices for generating space groups from explicit symbols.
     *
     * @var array<string, MatrixArray>
     */
    protected static array $rotationMatrices = [
        '1A' => [
            [1, 0, 0],
            [0, 1, 0],
            [0, 0, 1],
        ],
        '2A' => [
            [1, 0, 0],
            [0, -1, 0],
            [0, 0, -1],
        ],
        '2B' => [
            [-1, 0, 0],
            [0, 1, 0],
            [0, 0, -1],
        ],
        '2C' => [
            [-1, 0, 0],
            [0, -1, 0],
            [0, 0, 1],
        ],
        '2D' => [
            [0, 1, 0],
            [1, 0, 0],
            [0, 0, -1],
        ],
        '2E' => [
            [0, -1, 0],
            [-1, 0, 0],
            [0, 0, -1],
        ],
        '2F' => [
            [1, -1, 0],
            [0, -1, 0],
            [0, 0, -1],
        ],
        '2G' => [
            [1, 0, 0],
            [1, -1, 0],
            [0, 0, -1],
        ],
        '2H' => [
            [0, -1, 0],
            [-1, 0, 0],
            [0, 0, -1],
        ],
        '2I' => [
            [0, 1, 0],
            [1, 0, 0],
            [0, 0, -1],
        ],
        '3Q' => [
            [0, 0, 1],
            [1, 0, 0],
            [0, 1, 0],
        ],
        '3C' => [
            [0, -1, 0],
            [1, -1, 0],
            [0, 0, 1],],
        '4C' => [
            [0, -1, 0],
            [1, 0, 0],
            [0, 0, 1],
        ],
        '6C' => [
            [1, -1, 0],
            [1, 0, 0],
            [0, 0, 1],
        ],
    ];

    /**
     * Possible translations for generating space groups from Hall symbols.
     *
     * @var array<string, VectorArray>
     */
    protected static array $hallTranslations = [
        "A" => [0.5, 0, 0],
        "B" => [0, 0.5, 0],
        "C" => [0, 0, 0.5],
        "N" => [0.5, 0.5, 0.5],
        "U" => [0.25, 0, 0],
        "V" => [0, 0.25, 0],
        "W" => [0, 0, 0.25],
        "D" => [0.25, 0.25, 0.25],
    ];

    /**
     * Generates a space group from a string with explicit form (ITC B), e.g. ICC$I3Q000$P4C393$P2D933
     * @param string $string
     * @param bool $generateGroup Whether to generate the full group or not.
     * @return self
     */
    public static function makeFromExplicitSymbol(string $string, bool $generateGroup = true): self
    {
        $entries = explode('$', strtoupper(trim($string)));
        if (count($entries) < 2 || strlen($entries[0]) !== 3 || !array_key_exists($entries[0][0], self::$centeringTranslationsArray)) {
            throw new InvalidArgumentException("Invalid space group string.");
        }

        $centeringTranslations = self::$centeringTranslationsArray[array_shift($entries)[0]];
        $symmetryOperations = [];

        foreach ($entries as $entry) {
            $parts = [];
            $result = preg_match("/^([P|I])([1-4|6][A-Z])([0-9])([0-9])([0-9])$/", $entry, $parts);
            if ($result !== 1 || count($parts) !== 6 || !array_key_exists($parts[2], self::$rotationMatrices)) {
                throw new InvalidArgumentException("Invalid space group string.");
            }

            $rotationPart = new Matrix(self::$rotationMatrices[$parts[2]]);
            if ($parts[1] === 'I') {
                $rotationPart->mChangeSign();
            }

            $translationArray = [];
            for ($i = 3; $i < 6; $i++) {
                $integer = $parts[$i] === '5' ? 10 : intval($parts[$i]);
                $translationArray[] = [$integer / 12];
            }

            $symmetryOperations[] = SymmetryOperation3D::fromRotationAndTranslation($rotationPart, new Vector($translationArray));
        }

        $result = new self($symmetryOperations, self::$primaryTranslationsArray, $centeringTranslations);

        return $generateGroup ? $result->generateGroup() : $result;
    }

    /**
     * Generates a space group from a Hall symbol (ITC B), e.g. p -2y
     *
     * @param string $hallSymbol
     * @param bool $generateGroup Whether to generate the full group or not.
     * @return self
     * @throws InvalidArgumentException if the Hall symbol is invalid.
     */
    public static function makeFromHallSymbol(string $hallSymbol, bool $generateGroup = true): self
    {
        $hallSymbol = strtoupper(trim($hallSymbol));
        $parts = explode(" ", $hallSymbol);
        if (count($parts) < 2) {
            throw new InvalidArgumentException("Cannot create a space group. Invalid Hall symbol.");
        }

        $centrosymmetric = false;
        if ($parts[0][0] === "-") {
            $centrosymmetric = true;
            $parts[0] = substr($parts[0], 1);
        }

        if (!array_key_exists($parts[0], self::$centeringTranslationsArray)) {
            throw new InvalidArgumentException("Cannot create a space group. Invalid Hall symbol.");
        }

        $centeringTranslations = self::$centeringTranslationsArray[array_shift($parts)];
        $symmetryOperations = [];
        $precedingRotationAxis = "";
        $precedingRotationNumber = "";
        foreach ($parts as $index => $part) {
            //(\((?:-?\d\s?){3}\))?
            $result = preg_match("/^(-?)([1-4|6])([1-5]?)([XYZ*'\"]?)([ABCNUVWD]*)$/", $part, $matches);
            if ($result !== 1) {
                throw new InvalidArgumentException("Cannot create a space group. Invalid Hall symbol.");
            }
            $rotationSymbol = $matches[2];

            if ($matches[4] === "") {
                if ($index === 0) {
                    $matches[4] = "Z";
                } elseif ($index === 1) {
                    $matches[4] = match ($precedingRotationNumber) {
                        "2", "4" => "X",
                        default => "'Z",
                    };
                } else {
                    $matches[4] = "*";
                }
                $rotationSymbol .= $matches[4];
            } else {
                $rotationSymbol .= match ($matches[4]) {
                    "'", "\"" => $matches[4] . $precedingRotationAxis,
                    default => $matches[4],
                };
            }

            if (!array_key_exists($rotationSymbol, self::$hallRotations)) {
                throw new InvalidArgumentException("Cannot create a space group. Invalid Hall symbol.");
            }

            $rotationMatrix = $matches[1] === "-" ? (new Matrix(self::$hallRotations[$rotationSymbol]))->mChangeSign() : new Matrix(self::$hallRotations[$rotationSymbol]);

            $translationArray = [
                "X" => 0,
                "Y" => 0,
                "Z" => 0
            ];

            if ($matches[3] !== "") {
                if (!array_key_exists($matches[4], $translationArray)) {
                    throw new InvalidArgumentException("Cannot create a space group. Invalid Hall symbol.");
                }
                $term = match ($matches[2] . $matches[3]) {
                    "31", "62" => 1 / 3,
                    "32", "64" => 2 / 3,
                    "41" => 1 / 4,
                    "43" => 3 / 4,
                    "61" => 1 / 6,
                    "65" => 5 / 6,
                    default => -1,
                };

                if ($term === -1) {
                    throw new InvalidArgumentException("Cannot create a space group. Invalid Hall symbol.");
                }
                $translationArray[$matches[4]] = $term;
            }

            for ($i = 0; $i < strlen($matches[5]); $i++) {
                if (!array_key_exists($matches[5][$i], self::$hallTranslations)) {
                    throw new InvalidArgumentException("Cannot create a space group. Invalid Hall symbol.");
                }
                $translationArray = array_map(function ($a, $b) {
                    return $a + $b;
                }, $translationArray, self::$hallTranslations[$matches[5][$i]]);
            }

            $translationVector = Vector::fromArray(array_map(fn ($value) => Support::reduceNumberPositive($value), array_values($translationArray)));

            $symmetryOperations[] = SymmetryOperation3D::fromRotationAndTranslation($rotationMatrix, $translationVector);

            if ($centrosymmetric) {
                $symmetryOperations[] = SymmetryOperation3D::fromRotationAndTranslation($rotationMatrix->changeSign(), $translationVector);
            }

            $precedingRotationNumber = $matches[2];
            $precedingRotationAxis = $matches[4];
        }

        $result = new self($symmetryOperations, self::$primaryTranslationsArray, $centeringTranslations);
        return $generateGroup ? $result->generateGroup() : $result;
    }
}
