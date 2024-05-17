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

use PHPMathObjects\Exception\MathObjectsException;
use PHPMathObjects\Exception\MatrixException;
use PHPMathObjects\LinearAlgebra\Matrix;
use PHPMathObjects\LinearAlgebra\Vector;
use PHPMathObjects\Math\Math;
use PHPMathObjects\Number\Rational;
use PHPSymmetry\Exception\InvalidArgumentException;

use PHPSymmetry\Support;
use function array_fill;
use function preg_match;
use function preg_match_all;
use function array_flip;
use function implode;
use function count;
use function explode;
use function ltrim;
use function rtrim;
use function abs;
use function array_keys;

/**
 * Abstract class for symmetry operations.
 *
 * References:
 * [1] International Tables for Crystallography, Vol. A, 2016.
 *
 * @phpstan-consistent-constructor
 * @phpstan-type MatrixArray array<int, array<int, int|float>>
 */
abstract class AbstractSymmetryOperation
{
    /**
     * Dimensionality of the symmetry operation (static property).
     */
    protected const DIMENSIONALITY = 0;

    /**
     * Dimensionality of the symmetry operation.
     *
     * @var int
     */
    protected readonly int $dimensionality;

    /**
     * Augmented matrix of the symmetry operation.
     * Reference: [1] p. 16.
     *
     * @var Matrix
     */
    protected readonly Matrix $matrix;

    /**
     * Rotation part of the symmetry operation.
     * Reference: [1] p. 13.
     *
     * @var Matrix|null
     */
    protected ?Matrix $rotationPart = null;

    /**
     * Translation part of the symmetry operation.
     * Reference: [1] p. 13.
     *
     * @var Vector|null
     */
    protected ?Vector $translationPart = null;

    /**
     * Intrinsic translation part of the symmetry operation.
     *
     * @var Vector|null
     */
    protected ?Vector $intrinsicTranslationPart = null;

    /**
     * Location part of the symmetry operation.
     *
     * @var Vector|null
     */
    protected ?Vector $locationPart = null;

    /**
     * Powers of the rotation matrix (from 0 to the order of the symmetry operation minus 1).
     *
     * @var array<int, Matrix>|null
     */
    protected ?array $powersOfW = null;

    /**
     * Powers of the rotation matrix added up together.
     *
     * @var Matrix|null
     */
    protected ?Matrix $YWMatrix = null;

    /**
     * Y(-W) matrix of the symmetry operation.
     *
     * @var Matrix|null
     */
    protected ?Matrix $YWMatrixNegative = null;

    /**
     * Order of the symmetry operation.
     *
     * @var int|null
     */
    protected ?int $order = null;

    /**
     * Contains the axes letters (e.g. 'x', 'y', 'z' for a 3D symmetry operation)
     *
     * @var array<int, string>
     */
    protected array $axes;

    /**
     * Contains the indices of the axes letters (e.g. ['x' => 0, 'y' => 1, 'z' => 2] for a 3D symmetry operation)
     *
     * @var array<string, int>
     */
    protected array $axesIndices;

    /**
     * Static version of $axesIndices. Used to instantiate with fromXYZ() method.
     *
     * @var array<string, int>
     * @see fromXYZ()
     */
    protected static array $axesIndicesStatic;

    /**
     * Class constructor for AbstractSymmetryOperation.
     *
     * @throws InvalidArgumentException if the matrix dimensions are incompatible with the dimensionality of the symmetry operation.
     */
    public function __construct(Matrix $matrix)
    {
        if ($matrix->rows() !== $matrix->columns() || $matrix->columns() !== static::DIMENSIONALITY + 1) {
            throw new InvalidArgumentException('The matrix dimensions are incompatible with the dimensionality of the symmetry operation.');
        }

        $this->dimensionality = static::DIMENSIONALITY;
        $this->matrix = $matrix;
        $this->axesIndices = array_flip($this->axes);
    }

    /**
     * Factory method to create a symmetry operation from an array.
     * @param MatrixArray $array
     * @return static
     * @throws InvalidArgumentException if the array is not of a valid matrix type/shape or if the array dimensions are incompatible with the dimensionality of the symmetry operation.
     */
    public static function fromArray(array $array): static
    {
        try {
            $m = new Matrix($array);
        } catch (MathObjectsException) {
            throw new InvalidArgumentException('Cannot instantiate a symmetry operation using the given array. The array is probably not of a valid matrix type/shape.');
        }

        try {
            $s = new static($m);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('The array dimensions are incompatible with the dimensionality of the symmetry operation.');
        }

        return $s;
    }

    /**
     * Factory method to create the identity symmetry operation.
     * @return static
     */
    public static function identity(): static
    {
        return new static(Matrix::identity(static::DIMENSIONALITY + 1));
    }

    /**
     * Factory method to create a symmetry operation from a rotation matrix and a translation vector.
     *
     * @param Matrix $rotation
     * @param Vector $translation
     * @return static
     * @throws InvalidArgumentException if the matrix or vector dimensions are incompatible with each other or with the dimensionality of the symmetry operation.
     */
    public static function fromRotationAndTranslation(Matrix $rotation, Vector $translation): static
    {
        $augmentation = array_fill(0, static::DIMENSIONALITY, 0);
        $augmentation[] = 1;
        try {
            return new static($rotation->joinRight($translation)->mJoinBottom(new Vector([$augmentation])));
        } catch (MathObjectsException) {
            throw new InvalidArgumentException('The matrix or vector dimensions are incompatible with each other or with the dimensionality of the symmetry operation.');
        }
    }

    public static function fromXYZ(string $string): static
    {
        // Split the string into terms and check if the number of terms is compatible with the dimensionality of the symmetry operation.
        $terms = explode(',', $string);
        if (count($terms) !== static::DIMENSIONALITY) {
            throw new InvalidArgumentException('The number of terms in the string is incompatible with the dimensionality of the symmetry operation.');
        }

        $matrixArray = [];

        // Define the pattern to match the axes letters and their coefficients.
        $pattern = '/^(.*)(' . implode("|", array_keys(static::$axesIndicesStatic)) . ')$/';

        // Iterate over the terms
        foreach ($terms as $term) {
            $term = trim($term);
            $row = array_fill(0, static::DIMENSIONALITY + 1, 0);
            $matches = [];
            // Split the term into individual coefficients and axes letters.
            $result = preg_match_all('/(-|\+|^)([^-+])+/', $term, $matches);
            if ($result === false || $result === 0) {
                throw new InvalidArgumentException('Cannot create a symmetry operation from the given string.');
            }

            // Iterate over the letters found in this term.
            foreach ($matches[0] as $index => $match) {
                $sign = $matches[1][$index] === '-' ? '-' : '';
                $match = ltrim($match, '+-');
                $result = preg_match($pattern, $match, $axis);
                if ($result === false || $result === 0) {
                    // If no letter is found, the term should be a translation term.
                    try {
                        $r = Rational::fromString($sign . $match);
                    } catch (MathObjectsException) {
                        throw new InvalidArgumentException('Cannot create a symmetry operation from the given string.');
                    }

                    // If the translation term is already set once, throw an exception.
                    if ($row[static::DIMENSIONALITY] !== 0) {
                        throw new InvalidArgumentException('Cannot create a symmetry operation from the given string.');
                    }

                    // Do reduction so that the value lies in the range [0, 1).
                    $row[static::DIMENSIONALITY] = Support::reduceNumber($r->toFloat());
                    continue;
                }

                // Try to convert the axis coefficient to a rational number.
                try {
                    $r = Rational::fromString($sign . ($axis[1] === "" ? '1' : $axis[1]));
                } catch (MathObjectsException) {
                    throw new InvalidArgumentException('Cannot create a symmetry operation from the given string.');
                }

                // If the corresponding term is already set once, throw an exception.
                if ($row[static::$axesIndicesStatic[$axis[2]]] !== 0) {
                    throw new InvalidArgumentException('Cannot create a symmetry operation from the given string.');
                }
                $row[static::$axesIndicesStatic[$axis[2]]] = $r->toFloat();
            }

            $matrixArray[] = $row;
        }

        $augmentation = array_fill(0, static::DIMENSIONALITY, 0);
        $augmentation[] = 1;
        $matrixArray[] = $augmentation;
        return new static(new Matrix($matrixArray));
    }

    /**
     * Returns the rotation part of the symmetry operation.
     *
     * @return Matrix
     * @api
     */
    public function rotationPart(): Matrix
    {
        if ($this->rotationPart === null) {
            $this->rotationPart = $this->matrix->submatrix(0, 0, $this->dimensionality - 1, $this->dimensionality - 1);
        }

        return $this->rotationPart;
    }

    /**
     * Returns the translation part of the symmetry operation.
     *
     * @return Vector
     * @api
     */
    public function translationPart(): Vector
    {
        if ($this->translationPart === null) {
            $this->translationPart = $this->matrix->submatrix(0, $this->dimensionality, $this->dimensionality - 1, $this->dimensionality)->toVector();
        }

        return $this->translationPart;
    }

    /**
     * Returns an array with the powers of the rotation matrix (from 0 to the order of the symmetry operation minus 1).
     *
     * @return array<int, Matrix>
     */
    protected function powersOfW(): array
    {
        if ($this->powersOfW === null) {
            $this->powersOfW = [];
            $this->order = 1;
            $identity = Matrix::identity($this->dimensionality);
            $this->powersOfW[0] = $identity;
            $product = $this->rotationPart();

            while (!$product->isEqual($identity)) {
                $this->powersOfW[] = $product;
                $product = $product->multiply($this->rotationPart());
                $this->order++;
            }
        }

        return $this->powersOfW;
    }

    /**
     * Returns the Y(W) matrix of the symmetry operation.
     *
     * @return Matrix
     */
    protected function YWMatrix(): Matrix
    {
        if ($this->YWMatrix === null) {
            $this->YWMatrix = Matrix::fill($this->dimensionality, $this->dimensionality, 0);
            $powersOfW = $this->powersOfW();
            foreach ($powersOfW as $power) {
                $this->YWMatrix->mAdd($power);
            }
        }

        return $this->YWMatrix;
    }

    /**
     * Returns the Y(-W) matrix of the symmetry operation.
     *
     * @return Matrix
     */
    protected function YWNegativeMatrix(): Matrix
    {
        if ($this->YWMatrixNegative === null) {
            $this->YWMatrixNegative = Matrix::fill($this->dimensionality, $this->dimensionality, 0);
            $powersOfW = $this->powersOfW();
            foreach ($powersOfW as $index => $power) {
                $this->YWMatrixNegative->mAdd($power->multiplyByScalar((-1) ** $index));
            }
        }

        return $this->YWMatrixNegative;
    }

    /**
     * Returns the intrinsic translation part of the symmetry operation.
     *
     * @return Vector
     * @api
     */
    public function intrinsicTranslationPart(): Vector
    {
        if ($this->intrinsicTranslationPart === null) {
            $this->intrinsicTranslationPart = $this->YWMatrix()->multiply($this->translationPart())->toVector()->mMultiplyByScalar(1 / $this->order());
        }

        return $this->intrinsicTranslationPart;
    }

    /**
     * Returns the location part of the symmetry operation.
     *
     * @return Vector
     * @api
     */
    public function locationPart(): Vector
    {
        if ($this->locationPart === null) {
            $this->locationPart = $this->translationPart()->subtract($this->intrinsicTranslationPart());
        }

        return $this->locationPart;
    }

    /**
     * Returns the order of the symmetry operation.
     *
     * @return int
     * @api
     */
    public function order(): int
    {
        if ($this->order === null) {
            $this->powersOfW();
        }

        /* @phpstan-ignore-next-line */
        return $this->order;
    }

    /**
     * Returns the augmented matrix of the symmetry operation.
     *
     * @return Matrix
     * @api
     */
    public function matrix(): Matrix
    {
        return $this->matrix;
    }

    /**
     * Returns the dimensionality of the symmetry operation.
     *
     * @return int
     */
    public function dimensionality(): int
    {
        return $this->dimensionality;
    }

    /**
     * Returns the string representation of the symmetry operation (e.g. in xyz form for a 3D operation).
     *
     * @return string
     * @api
     */
    public function __toString(): string
    {
        $string = "";
        $rotationArray = $this->rotationPart()->toArray();
        $translationArray = $this->translationPart()->toPlainArray();

        for ($i = 0; $i < $this->dimensionality(); $i++) {
            $term = "";
            foreach ($rotationArray[$i] as $index => $value) {
                if (Math::isNotZero($value)) {
                    $term .= $value >= 0 ? "+" : "-";
                    if (!Math::areEqual(abs($value), 1)) {
                        $term .= Rational::fromFloat(abs($value))->toString();
                    }
                    $term .= $this->axes[$index];
                }
            }

            if (Math::isNotZero($translationArray[$i])) {
                if (Math::sign($translationArray[$i]) === 1) {
                    $term .= "+";
                }
                $term .= Rational::fromFloat($translationArray[$i])->toString();
            }

            $term .= ",";
            $string .= ltrim($term, "+");
        }

        return rtrim($string, ",");
    }

    /**
     * Alias for __toString().
     *
     * @return string
     * @api
     */
    public function toXYZ(): string
    {
        return $this->__toString();
    }

    /**
     * Returns the fixed points matrix of the symmetry operation.
     *
     * @param bool $useLocationPart If true, the location part is used instead of the translation part.
     * @return Matrix
     */
    public function fixedPointsMatrix(bool $useLocationPart = false): Matrix
    {
        $vector = $useLocationPart ? $this->locationPart() : $this->translationPart();
        return $this->rotationPart()->subtract(Matrix::identity($this->dimensionality()))->mJoinRight($vector->changeSign())->mRref();
    }

    /**
     * Calculates a product of two symmetry operations.
     *
     * @param AbstractSymmetryOperation $anotherSymmetryOperation
     * @return static
     * @throws InvalidArgumentException if the product cannot be calculated.
     */
    public function product(self $anotherSymmetryOperation): static
    {
        try {
            $product = $this->matrix->multiply($anotherSymmetryOperation->matrix());
        } catch (MatrixException) {
            throw new InvalidArgumentException('Cannot calculate the product of the two symmetry operations.');
        }

        // Reduce the translation part of the product.
        for ($i = 0; $i < $this->dimensionality; $i++) {
            $product->set($i, $this->dimensionality, Support::reduceNumberPositive($product->get($i, $this->dimensionality)));
        }

        return new static($product);
    }
}
