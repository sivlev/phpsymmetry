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
use PHPSymmetry\Exception\InvalidArgumentException;
use PHPSymmetry\Support;

/**
 * Class AbstractSymmetryGroup
 *
 * @template T
 * @phpstan-type MatrixArray array<int, array<int, int|float>>
 * @phpstan-type IntMatrixArray array<int, array<int, int>>
 * @phpstan-consistent-constructor
 */
class AbstractSymmetryGroup
{
    /**
     * Dimensionality of the space where the symmetry group acts (static property).
     */
    protected const DIMENSIONALITY = 0;

    /**
     * Dimensionality of the space where the symmetry group acts.
     *
     * @var int
     */
    protected readonly int $dimensionality;

    /**
     * Class of the symmetry operation to be used in the symmetry group (static property).
     * @var class-string
     * @phpstan-ignore-next-line This is a placeholder for the actual class name.
     */
    protected const SYMMETRY_OPERATION_CLASS = '';

    /**
     * Class of the symmetry operation to be used in the symmetry group.
     *
     * @var class-string
     */
    protected readonly string $symmetryOperationClass;

    /**
     * Symmetry operations of the symmetry group.
     *
     * @var array<int, T>
     */
    protected readonly array $symmetryOperations;

    /**
     * Primary translations of the symmetry group (e.g. [1, 0, 0]).
     *
     * @var IntMatrixArray
     */
    protected readonly array $primaryTranslations;

    /**
     * Primary translations of the symmetry group (static property).
     *
     * @var array<int, array<int, int>>
     */
    protected static array $primaryTranslationsArray = [];

    /**
     * Centering translations of the symmetry group (e.g. [1/2, 1/2, 1/2]).
     * @var MatrixArray
     */
    protected readonly array $centeringTranslations;

    /**
     * Order of the symmetry group.
     *
     * @var int|null
     */
    protected ?int $order = null;

    /**
     * AbstractSymmetryGroup constructor.
     * @param array<int, T> $symmetryOperations
     * @param IntMatrixArray $primaryTranslations
     * @param MatrixArray $centeringTranslations
     */
    protected function __construct(array $symmetryOperations, array $primaryTranslations, array $centeringTranslations)
    {
        $this->dimensionality = static::DIMENSIONALITY;
        $this->symmetryOperations = $symmetryOperations;
        $this->primaryTranslations = $primaryTranslations;
        $this->centeringTranslations = $centeringTranslations;
        $this->symmetryOperationClass = static::SYMMETRY_OPERATION_CLASS;
    }

    /**
     * @param array<int, T> $symmetryOperations
     * @param MatrixArray $centeringTranslations
     * @return static
     * @throws InvalidArgumentException if the dimensionality of the symmetry operations or translations is wrong.
     */
    public static function makeManually(array $symmetryOperations, array $centeringTranslations): static
    {
        foreach ($symmetryOperations as $symmetryOperation) {
            if ($symmetryOperation->dimensionality() !== static::DIMENSIONALITY) {
                throw new InvalidArgumentException("Symmetry operation $symmetryOperation has wrong dimensionality.");
            }
        }

        $centeringTranslations = array_values($centeringTranslations);
        foreach ($centeringTranslations as $centeringTranslation) {
            if (!is_array($centeringTranslation) || count($centeringTranslation) !== static::DIMENSIONALITY) {
                throw new InvalidArgumentException("The centering translation array is of invalid format.");
            }
            foreach ($centeringTranslation as $value) {
                /* @phpstan-ignore-next-line */
                if (!is_float($value) && !is_int($value)) {
                    throw new InvalidArgumentException("The centering translation array is of invalid format.");
                }
            }
        }

        return new static($symmetryOperations, self::$primaryTranslationsArray, $centeringTranslations);
    }

    /**
     * Returns the dimensionality of the space where the symmetry group acts.
     *
     * @return int
     */
    public function dimensionality(): int
    {
        return $this->dimensionality;
    }

    /**
     * Returns the symmetry operations of the symmetry group.
     *
     * @return array<int, T>
     */
    public function symmetryOperations(): array
    {
        return $this->symmetryOperations;
    }

    /**
     * Returns the primary translations of the symmetry group.
     *
     * @return IntMatrixArray
     */
    public function primaryTranslations(): array
    {
        return $this->primaryTranslations;
    }

    /**
     * Returns the centering translations of the symmetry group.
     *
     * @return MatrixArray
     */
    public function centeringTranslations(): array
    {
        return $this->centeringTranslations;
    }

    /**
     * Returns the order of the group, or null if the order is not yet calculated by the generateGroup() method.
     *
     * @return int|null
     * @see self::generateGroup()
     */
    public function order(): ?int
    {
        return $this->order;
    }

    /**
     * Returns a string representation of the symmetry group.
     *
     * @return string
     */
    public function __toString(): string
    {
        $string = "";
        foreach ($this->symmetryOperations as $index => $symmetryOperation) {
            $string .= "(" . ($index + 1) . ") " . $symmetryOperation . PHP_EOL;
        }
        return $string;
    }

    /**
     * Alias for the __toString() method.
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->__toString();
    }

    /**
     * Generate the full group from the current symmetry operations.
     * Algorithm is given in [1], p. 60, Table 1.4.3.2.
     *
     * @return static
     */
    public function generateGroup(): static
    {
        $result = [];
        $identity = $this->symmetryOperationClass::identity();
        $result[] = $identity;

        foreach ($this->symmetryOperations as $symmetryOperation) {
            $gPower = $symmetryOperation;
            while (true) {
                if ($gPower->rotationPart()->isEqual($identity->rotationPart())) {
                    break;
                }

                $temp = $result;
                foreach ($result as $resultSymmetryOperation) {
                    $product = $gPower->product($resultSymmetryOperation);
                    if (Support::isRotationPartInArray($product, $temp)) {
                        continue;
                    }
                    $temp[] = $product;
                }
                $result = $temp;
                $gPower = $gPower->product($symmetryOperation);
            }
        }

        $group = new static($result, $this->primaryTranslations, $this->centeringTranslations);
        $group->order = count($result) * (count($this->centeringTranslations) + 1);
        return $group;
    }

    /**
     * Expand the group from centered to primitive.
     *
     * @return static
     */
    public function expandGroup(): static
    {
        $result = $this->symmetryOperations;
        foreach ($this->centeringTranslations as $centeringTranslation) {
            foreach ($this->symmetryOperations as $symmetryOperation) {
                $array = $symmetryOperation->matrix()->toArray();
                foreach ($centeringTranslation as $index => $component) {
                    $array[$index][$this->dimensionality] = Support::reduceNumberPositive($array[$index][$this->dimensionality] + $component);
                }
                $result[] = new $this->symmetryOperationClass(new Matrix($array, false));
            }
        }
        $expandedGroup = new static($result, $this->primaryTranslations, []);
        $expandedGroup->order = count($result);
        return $expandedGroup;
    }

    /**
     * Check if the symmetry group is equal to another symmetry group (ignoring primary and centering translations).
     *
     * @param AbstractSymmetryGroup<T> $anotherGroup
     * @return bool
     */
    public function isEqualIgnoreTranslations(AbstractSymmetryGroup $anotherGroup): bool
    {
        if (count($this->symmetryOperations) !== count($anotherGroup->symmetryOperations())) {
            return false;
        }

        $temp = $anotherGroup->symmetryOperations();
        foreach ($this->symmetryOperations as $symmetryOperation) {
            $result = false;
            $matrix = $symmetryOperation->matrix();
            foreach ($temp as $index => $anotherSymmetryOperation) {
                if ($matrix->isEqual($anotherSymmetryOperation->matrix())) {
                    $result = true;
                    unset($temp[$index]);
                    break;
                }
            }
            if (!$result) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the symmetry group is equal to another symmetry group (including the primary and centering translations).
     *
     * @param AbstractSymmetryGroup<T> $anotherGroup
     * @return bool
     */
    public function isEqual(AbstractSymmetryGroup $anotherGroup): bool
    {
        if (count($this->primaryTranslations) !== count($anotherGroup->primaryTranslations) || count($this->centeringTranslations) !== count($anotherGroup->centeringTranslations)) {
            return false;
        }

        foreach ($this->centeringTranslations as $index => $centeringTranslation) {
            if ($centeringTranslation !== $anotherGroup->centeringTranslations[$index]) {
                return false;
            }
        }

        foreach ($this->primaryTranslations as $index => $primaryTranslation) {
            if ($primaryTranslation !== $anotherGroup->primaryTranslations[$index]) {
                return false;
            }
        }

        return $this->isEqualIgnoreTranslations($anotherGroup);
    }
}
