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

namespace PHPSymmetry;

use PHPMathObjects\Math\Math;
use PHPSymmetry\SymmetryOperation\AbstractSymmetryOperation;

/**
 * Support class for common operations.
 * @phpstan-type SymmetryOperationArray array<int, AbstractSymmetryOperation>
 */
class Support
{
    /**
     * Tolerance for floating point comparisons.
     */
    protected const e = 1e-8;

    /**
     * Reduce a number so that it is between [0 and 1).
     *
     * @param int|float $number
     * @param float $tolerance
     * @return int|float
     */
    public static function reduceNumberPositive(int|float $number, float $tolerance = self::e): int|float
    {
        $sign = Math::sign($number);
        if ((int) $number !== (int) ($number + $sign * $tolerance)) {
            $number = (int) ($number + $sign * $tolerance);
        }

        if ($number >= 1) {
            $number -= (int) $number;
        } elseif ($number < 0) {
            if (Math::areEqual($number, (int) $number)) {
                $number = 0;
            } else {
                $number -= (int) $number - 1;
            }
        }
        return $number;
    }

    /**
     * Reduce a number so that it is between (0 and 1).
     *
     * @param int|float $number
     * @param float $tolerance
     * @return int|float
     */
    public static function reduceNumber(int|float $number, float $tolerance = self::e): int|float
    {
        $sign = Math::sign($number);
        if ((int) $number !== (int) ($number + $sign * $tolerance)) {
            $number = (int) ($number + $sign * $tolerance);
        }

        if ($number >= 1) {
            $number -= (int) $number;
        } elseif ($number <= -1) {
            $number -= (int) $number;
        }

        return $number;
    }

    /**
     * Checks if the rotation part of a symmetry operation is part of an array of symmetry operations.
     * @param AbstractSymmetryOperation $symmetryOperation
     * @param SymmetryOperationArray $symmetryOperations
     * @return bool
     */
    public static function isRotationPartInArray(AbstractSymmetryOperation $symmetryOperation, array $symmetryOperations): bool
    {
        $matrix = $symmetryOperation->rotationPart();
        foreach ($symmetryOperations as $symmetryOperation) {
            if ($matrix->isEqual($symmetryOperation->rotationPart())) {
                return true;
            }
        }
        return false;
    }
}
