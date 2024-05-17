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

namespace PHPSymmetry\SymmetryOperation;

use PHPMathObjects\LinearAlgebra\Matrix;
use PHPMathObjects\LinearAlgebra\Vector;
use PHPMathObjects\Math\Math;
use PHPMathObjects\Number\Rational;
use PHPSymmetry\Exception\InvalidArgumentException;
use PHPSymmetry\Exception\RuntimeException;

use function array_map;
use function implode;
use function abs;
use function preg_match;

/**
 * Class for symmetry operations in 3D space.
 *
 *  References:
 *  [1] International Tables for Crystallography, Vol. A, 2016.
 *
 * @phpstan-type VectorArray array<int, int|float>
 * @phpstan-type MatrixArray array<int, array<int, int|float>>
 */
class SymmetryOperation3D extends AbstractSymmetryOperation
{
    /**
     * Dimensionality of the symmetry operation in 3D.
     */
    protected const DIMENSIONALITY = 3;

    /**
     * The indices of the axes in the symmetry operation.
     *
     * @var array<string, int>
     */
    protected static array $axesIndicesStatic = [
        "x" => 0,
        "y" => 1,
        "z" => 2,
    ];

    /**
     * The arbitrary direction used for instantiation of the $vVector.
     * Reference: [1], p. 16.
     *
     * @var MatrixArray
     */
    protected static array $arbitraryDirection = [[3], [5], [7]];

    /**
     * An arbitrary vector used for the calculation of the characteristic axis of the symmetry operation (v vector).
     * Reference: [1], p. 16.
     *
     * @var Vector|null
     */
    protected ?Vector $vVector = null;

    /**
     * Characteristic axis of the symmetry operation (u vector).
     * Reference: [1], p. 16.
     *
     * @var Vector|null
     */
    protected ?Vector $uVector = null;

    /**
     * The Z matrix of the symmetry operation used for the calculation of the sense of rotation.
     * Reference: [1], p. 16.
     *
     * @var Matrix|null
     */
    protected ?Matrix $ZMatrix = null;

    /**
     * @param Matrix $matrix
     * @throws InvalidArgumentException if the matrix dimensions are incompatible with the dimensionality of the symmetry operation.
     */
    public function __construct(Matrix $matrix)
    {
        $this->axes = ["x", "y", "z"];

        parent::__construct($matrix);
    }

    /**
     * Returns a vector with a preset arbitrary direction.
     * Reference: [1], p. 16.
     *
     * @return Vector
     */
    protected function vVector(): Vector
    {
        if ($this->vVector === null) {
            $this->vVector = new Vector(static::$arbitraryDirection, false);
        }

        return $this->vVector;
    }

    /**
     * Returns the characteristic axis of the symmetry operation.
     * Reference: [1], p. 16.
     *
     * @return Vector
     */
    protected function uVector(): Vector
    {
        if ($this->uVector === null) {
            if ($this->rotationPart()->determinant() < 0) {
                $this->uVector = $this->YWNegativeMatrix()->multiply($this->vVector())->toVector();
            } else {
                $this->uVector = $this->YWMatrix()->multiply($this->vVector())->toVector();
            }

            // Divide the components of the $uVector by their greatest common divisor.
            $gcd = Math::gcd((int)$this->uVector[0], (int)$this->uVector[1]);
            $gcd = Math::gcd($gcd, (int)$this->uVector[2]);
            $this->uVector->mMultiplyByScalar(1 / $gcd);

            // By convention(?) if all components of the u vector are non-zero and one of them is negative, the u vector should be inverted. This is the case in cubic space groups.
            if (Math::isZero($this->rotationPart()->trace())) {
                $negativeCount = 0;
                foreach ($this->uVector->toPlainArray() as $element) {
                    if ($element < 0) {
                        $negativeCount++;
                    }
                }
                if ($negativeCount === 1) {
                    $this->uVector->mChangeSign();
                }
            }
        }

        return $this->uVector;
    }

    /**
     * Returns the Z matrix of the symmetry operation.
     * Reference: [1], p. 16.
     *
     * @return Matrix
     */
    protected function ZMatrix(): Matrix
    {
        if ($this->ZMatrix === null) {
            $xVector = new Vector([[1], [0], [0]], false);
            if ($xVector->crossProduct($this->uVector())->isZero()) {
                $xVector = new Vector([[0], [1], [0]], false);
            }

            $Wx = $this->rotationPart()->multiply($xVector);
            if ($this->rotationPart()->determinant() < 0) {
                $Wx->mChangeSign();
            }

            $this->ZMatrix = $this->uVector()->joinRight($xVector)->mJoinRight($Wx);
        }

        return $this->ZMatrix;
    }

    /**
     * Returns the symbol of the symmetry operation, e.g. 2(1/2,0,0) x,0,1/4.
     *
     * @return string
     */
    public function toSymbol(): string
    {
        if ($this->rotationPart()->determinant() > 0) {
            switch ((int)$this->rotationPart()->trace()) {
                case 3:
                    // Identity operation: 1
                    if (Math::areArraysEqual($this->translationPart()->toPlainArray(), [0, 0, 0])) {
                        return "1";
                    }

                    // Translation operation: t (x,y,z)
                    return "t (" . $this->intrinsicTranslationToString() . ")";

                case -1:
                case 0:
                case 1:
                case 2:
                    // Rotation and screw axes, e.g. 2(1/2,0,0) x,0,1/4
                    $symbol = match ((int)$this->rotationPart()->trace()) {
                        -1 => "2",
                        0 => "3",
                        1 => "4",
                        2 => "6",
                    };

                    $intrinsicTranslation = $this->intrinsicTranslationToString() === "0,0,0" ? "" : "({$this->intrinsicTranslationToString()})";
                    return $symbol . $this->rotationSense() . $intrinsicTranslation . " " . $this->lineLocationToString($intrinsicTranslation !== "0,0,0");
            }
        } else {
            switch ($this->rotationPart()->trace()) {
                case -3:
                    // Inversion operation: -1 (x,y,z)
                    return "-1 (" . $this->pointLocationToString(false) . ")";

                case 1:
                    // Mirror or glide reflection operation: e.g. m x,y,z or g(u,v,w) x,y,z

                    // Check if the intrinsic translation part is zero.
                    if ($this->intrinsicTranslationPart()->isZero()) {
                        // Mirror operation: m x,y,z
                        return "m " . $this->planeLocationToString();
                    }

                    // Glide reflection operation: g(u,v,w) x,y,z
                    $glideVectorString = $this->intrinsicTranslationToString();
                    $letter = $this->getGlideLetter($glideVectorString);
                    if ($letter === "g" || $letter === "n" || $letter === "d") {
                        $letter .= "(" . $glideVectorString . ")";
                    }
                    return $letter . " " . $this->planeLocationToString(true);

                case -1:
                case 0:
                case -2:
                    // Rotoinversion axes, e.g. -6+ 0,0,z; 0,0,1/4
                    /* @phpstan-ignore-next-line */
                    $symbol = match ((int)$this->rotationPart()->trace()) {
                        -2 => "-6",
                        -1 => "-4",
                        0 => "-3",
                    };
                    $intrinsicTranslation = $this->intrinsicTranslationToString() === "0,0,0" ? "" : "({$this->intrinsicTranslationToString()})";
                    return $symbol . $this->rotationSense() . $intrinsicTranslation . " " . $this->lineLocationToString($intrinsicTranslation !== "") . "; " . $this->pointLocationToString($intrinsicTranslation !== "0,0,0");
            }
        }

        // If something went wrong, throw an exception.
        // @codeCoverageIgnoreStart
        throw new RuntimeException("Cannot convert the symmetry operation to a symbol.");
        // @codeCoverageIgnoreEnd
    }

    /**
     * Returns the letter for the glide plane calculated using its characteristic axis (u vector) and its glide vector.
     * Reference: [1], p. 145, Table 2.1.2.1.
     *
     * @param string $glideVectorString
     * @return string
     */
    protected function getGlideLetter(string $glideVectorString): string
    {
        // Possible normals of the glide planes according to Table 2.1.2.1 of [1], p. 145.
        $normal1 = [1, 0, 0];
        $normal2 = [0, 1, 0];
        $normal3 = [0, 0, 1];
        $normal4 = [1, -1, 0];
        $normal5 = [0, 1, -1];
        $normal6 = [-1, 0, 1];
        $normal7 = [1, 1, 0];
        $normal8 = [0, 1, 1];
        $normal9 = [1, 0, 1];

        $normal = $this->uVector()->toPlainArray();

        $candidate = match ($glideVectorString) {
            "1/2,0,0" => "a",
            "0,1/2,0" => "b",
            "0,0,1/2" => "c",
            default => "g",
        };

        if ($candidate === "g") {
            if ($glideVectorString == "1/2,1/2,0" || $glideVectorString == "1/2,0,1/2" || $glideVectorString == "0,1/2,1/2") {
                if (Math::areArraysEqual($normal, $normal1) || Math::areArraysEqual($normal, $normal2) || Math::areArraysEqual($normal, $normal3)) {
                    return 'n';
                }
            }
            if ($glideVectorString == "1/2,1/2,1/2") {
                if (Math::areArraysEqual($normal, $normal4) || Math::areArraysEqual($normal, $normal5) || Math::areArraysEqual($normal, $normal6)) {
                    return 'n';
                }
            }
            if ($glideVectorString == "-1/2,1/2,1/2" || $glideVectorString == "1/2,-1/2,1/2" || $glideVectorString == "1/2,1/2,-1/2") {
                if (Math::areArraysEqual($normal, $normal7) || Math::areArraysEqual($normal, $normal8) || Math::areArraysEqual($normal, $normal9)) {
                    return 'n';
                }
            }
            if (preg_match("#^([13]/4,-?[13]/4,0)|(0,[13]/4,-?[13]/4)|(-?[13]/4,0,[13]/4)$#", $glideVectorString)) {
                if (Math::areArraysEqual($normal, $normal1) || Math::areArraysEqual($normal, $normal2) || Math::areArraysEqual($normal, $normal3)) {
                    return 'd';
                }
            }
            if (preg_match("#^(([13])/4,-?[13]/4,[13]/4)|([13]/4,[13]/4,-?[13]/4)|(-?[13]/4,[13]/4,[13]/4)$#", $glideVectorString)) {
                if (Math::areArraysEqual($normal, $normal4) || Math::areArraysEqual($normal, $normal5) || Math::areArraysEqual($normal, $normal6)) {
                    return 'd';
                }
            }
            if (preg_match("#^([13]/4,-?[13]/4,-[13]/4)|(-[13]/4,[13]/4,-?[13]/4)|(-?[13]/4,-[13]/4,[13]/4)$#", $glideVectorString)) {
                if (Math::areArraysEqual($normal, $normal7) || Math::areArraysEqual($normal, $normal8) || Math::areArraysEqual($normal, $normal9)) {
                    return 'd';
                }
            }
        }

        return $candidate;
    }

    /**
     * Returns the sense of rotation of the symmetry operation (if not applicable, an empty string is returned).
     * @return string
     */
    protected function rotationSense(): string
    {
        return $this->order() > 2 ? ($this->ZMatrix()->determinant() < 0 ? '-' : '+') : '';
    }

    protected function intrinsicTranslationToString(): string
    {
        return implode(",", array_map(fn ($value) => Rational::fromFloat($value), $this->intrinsicTranslationPart()->toPlainArray()));
    }

    protected function pointLocationToString(bool $useLocationPart): string
    {
        $points = $this->fixedPointsMatrix($useLocationPart)->toArray();
        return Rational::fromFloat($points[0][3]) . "," . Rational::fromFloat($points[1][3]) . "," . Rational::fromFloat($points[2][3]);
    }

    protected function lineLocationToString(bool $useLocationPart): string
    {
        $u = $this->uVector()->toPlainArray();
        $point = $this->fixedPointsMatrix($useLocationPart)->toArray();

        $lastAxisIndex = -1;
        foreach ($u as $index => $element) {
            if (Math::isNotZero($element)) {
                $lastAxisIndex = $index;
            }
        }

        // Find the axis letter
        if (Math::isNotZero($u[0])) {
            $axis = "x";
        } elseif (Math::isNotZero($u[1])) {
            $axis = "y";
        } else {
            $axis = "z";
        }

        // By convention the z axis (or the last axis in the u vector) must have a zero term
        if (Math::isNotZero($point[2][3]) && $lastAxisIndex !== -1 && Math::isNotZero($u[$lastAxisIndex])) {
            $term = $u[$lastAxisIndex] < 0 ? $point[$lastAxisIndex][3] : -$point[$lastAxisIndex][3];
            $point[$lastAxisIndex][3] = 0;
            for ($i = 0; $i < $lastAxisIndex; $i++) {
                if (Math::isNotZero($u[$i])) {
                    $point[$i][3] += $term * Math::sign($u[$i]);
                }
            }
        }

        $array = [];
        foreach ($u as $index => $value) {
            $term = "";
            if (Math::isNotZero($value)) {
                $term = ($value < 0 ? "-" : "") . $axis;
            }

            if (Math::isNotZero($point[$index][3])) {
                $term .= ($point[$index][3] < 0 ? "-" : ($term !== "" ? "+" : "")) . Rational::fromFloat(abs($point[$index][3]));
            }

            $array[] = ($term === "" ? "0" : $term);
        }

        return implode(",", $array);
    }

    protected function planeLocationToString(bool $useLocationPart = false): string
    {
        $array = ["x", "y", "z"];
        $points = $this->fixedPointsMatrix($useLocationPart)->toArray();

        // Go through the rows of the fixed points matrix.
        for ($rowIndex = 0; $rowIndex < 3; $rowIndex++) {
            $row = $points[$rowIndex];
            $axis = [];
            $nonZeroCount = 0;

            // Count the non-zero elements in the row.
            for ($columnIndex = 0; $columnIndex < 3; $columnIndex++) {
                if (Math::isNotZero($row[$columnIndex])) {
                    $axis[] = $columnIndex;
                    $nonZeroCount++;
                }
            }

            // If there is only one non-zero element, it means the corresponding coordinate is fixed, e.g. x = 1/2.
            if ($nonZeroCount === 1) {
                $array[$axis[0]] = Rational::fromFloat($row[3]);
                continue;
            }

            // If there are two non-zero elements, it means the corresponding coordinates are dependent on each other, e.g. x + y = 1/2.
            if ($nonZeroCount === 2) {
                // First check the case when both axes have a coefficient of 1.
                if (Math::areEqual(abs($row[$axis[1]]), 1)) {
                    if (Math::isNotZero($row[3])) {
                        $array[$axis[0]] .= ($row[3] > 0 ? "+" : "-") . Rational::fromFloat(abs($row[3]));
                    }

                    // By convention in ITC, the z-axis by a mirror (glide) plane if it is dependent on the x-axis, should always be positive.
                    // We need to hard-code this case. Do an exception for 3-fold rotation axes in cubic space groups (trace === 0).
                    if ($axis[1] === 2 && $axis[0] === 0 && $row[2] > 0) {
                        $array[2] = "x";
                        $array[0] = "-x";
                        if (Math::isNotZero($row[3])) {
                            $array[0] .= ($row[3] > 0 ? "+" : "-") . Rational::fromFloat(abs($row[3]));
                        }
                    } else {
                        $array[$axis[1]] = (($row[$axis[1]] > 0) ? "-" : "") . $this->axes[$axis[0]];
                    }
                } else {
                    // If the coefficient of the second axis is not 1, we need to handle the case according to the conventions in ITC A.
                    if (abs($row[$axis[1]]) > 1) {
                        $array[$axis[0]] = Rational::fromFloat(abs($row[$axis[1]])) . $this->axes[$axis[0]];
                        $array[$axis[1]] = (($row[$axis[1]] > 0) ? "-" : "") . $this->axes[$axis[0]];
                    } else {
                        $array[$axis[1]] = Rational::fromFloat(-1 / $row[$axis[1]]) . $this->axes[$axis[0]];
                    }
                    if (Math::isNotZero($row[3])) {
                        $array[$axis[0]] .= ($row[3] > 0 ? "+" : "-") . Rational::fromFloat(abs($row[3]));
                    }
                }
            }
        }

        return implode(",", $array);
    }
}
