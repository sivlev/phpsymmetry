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

namespace PHPSymmetry\Tests\Unit\SymmetryOperation;

use PHPMathObjects\Exception\InvalidArgumentException;
use PHPMathObjects\Exception\MatrixException;
use PHPMathObjects\Exception\OutOfBoundsException;
use PHPMathObjects\LinearAlgebra\Matrix;
use PHPMathObjects\LinearAlgebra\Vector;
use PHPSymmetry\SymmetryOperation\AbstractSymmetryOperation;
use PHPSymmetry\SymmetryOperation\SymmetryOperation3D;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Test class for the SymmetryOperation3D class.
 *
 * @phpstan-type MatrixArray array<int, array<int, int|float>>
 * @phpstan-type VectorArray array<int, int|float>
 */
class SymmetryOperation3DTest extends TestCase
{
    /**
     * Tolerance for floating point comparisons.
     */
    protected const e = 1e-8;

    /**
     * @return void
     * @throws InvalidArgumentException
     * @throws \PHPSymmetry\Exception\InvalidArgumentException
     */
    #[TestDox("SymmetryOperation3D class constructor creates an instance of AbstractSymmetryOperation and SymmetryOperation3D.")]
    public function testSymmetryOperation3D(): void
    {
        $m = new Matrix([
            [1, 0, 0, 0],
            [0, 1, 0, 0],
            [0, 0, 1, 0],
            [0, 0, 0, 1],
        ]);
        $symmetryOperation3D = new SymmetryOperation3D($m);
        $this->assertInstanceOf(AbstractSymmetryOperation::class, $symmetryOperation3D);
        $this->assertInstanceOf(SymmetryOperation3D::class, $symmetryOperation3D);
    }

    /**
     * @param MatrixArray $array
     * @return void
     * @throws InvalidArgumentException
     * @throws \PHPSymmetry\Exception\InvalidArgumentException
     */
    #[TestWith([[[1, 0, 0], [0, 1, 0], [0, 0, 1]]])]
    #[TestWith([[[1, 0, 0], [0, 1, 0], [0, 0, 1], [0, 0, 1]]])]
    #[TestDox("SymmetryOperation3D class constructor throws an InvalidArgumentException if the matrix dimensions are incompatible with the dimensionality of the symmetry operation.")]
    public function testSymmetryOperation3DException(array $array): void
    {
        $this->expectException(\PHPSymmetry\Exception\InvalidArgumentException::class);
        $m = new Matrix($array);
        new SymmetryOperation3D($m);
    }

    /**
     * @param MatrixArray $array
     * @param bool $exception
     * @return void
     * @throws \PHPSymmetry\Exception\InvalidArgumentException
     */
    #[DataProvider("providerFromArray")]
    #[TestDox("SymmetryOperation3D class fromArray() method creates a symmetry operation from the given array.")]
    public function testFromArray(array $array, bool $exception = false): void
    {
        if ($exception) {
            $this->expectException(\PHPSymmetry\Exception\InvalidArgumentException::class);
        }
        $s = SymmetryOperation3D::fromArray($array);
        $this->assertEquals($array, $s->matrix()->toArray());
    }

    /**
     * @return array<int, array<int, array<int, array<int, int|float|string>>|bool>>
     */
    public static function providerFromArray(): array
    {
        return [
            [
                [
                    [1, 0, 0, 0],
                    [0, 1, 0, 0],
                    [0, 0, 1, 0],
                    [0, 0, 0, 1],
                ],
            ],
            [
                [
                    [1, "0", 0, 0],
                    [0, 1, 0, 0],
                    [0, 0, 1, 0],
                    [0, 0, 0, 1],
                ], true,
            ],
            [
                [
                    [1, 0, 0],
                    [0, 1, 0],
                    [0, 0, 1],
                ], true
            ],
            [
                [
                    [1, 0, 0, 0],
                    [0, 1, 0, 0],
                    [0, 0, 1, 0],
                ],
                true,
            ],
        ];
    }

    /**
     * @return void
     * @throws OutOfBoundsException
     */
    #[TestDox("SymmetryOperation3D class identity() method creates an identity symmetry operation.")]
    public function testIdentity(): void
    {
        $s = SymmetryOperation3D::identity();
        $this->assertEquals(Matrix::identity(4)->toArray(), $s->matrix()->toArray());
    }

    /**
     * @param MatrixArray $rotation
     * @param VectorArray $translation
     * @param MatrixArray $matrix
     * @param class-string<Throwable>|null $exception
     * @return void
     * @throws InvalidArgumentException
     * @throws MatrixException
     * @throws \PHPSymmetry\Exception\InvalidArgumentException
     */
    #[DataProvider("providerFromRotationAndTranslation")]
    #[TestDox("TestFromRotationAndTranslation() method returns the expected values.")]
    public function testFromRotationAndTranslation(array $rotation, array $translation, array $matrix, ?string $exception = null): void
    {
        if (isset($exception)) {
            $this->expectException($exception);
        }
        $m = new Matrix($rotation);
        $v = Vector::fromArray($translation);
        $s = SymmetryOperation3D::fromRotationAndTranslation($m, $v);
        $this->assertEquals($rotation, $s->rotationPart()->toArray());
        $this->assertEquals($translation, $s->translationPart()->toVector()->toPlainArray());
        $this->assertEquals($matrix, $s->matrix()->toArray());
    }

    /**
     * @return array<int, array<int, MatrixArray|VectorArray|string>>
     */
    public static function providerFromRotationAndTranslation(): array
    {
        return [
            [
                [
                    [1, 0, 0],
                    [0, 1, 0],
                    [0, 0, 1],
                ],
                [0, 0, 0],
                [
                    [1, 0, 0, 0],
                    [0, 1, 0, 0],
                    [0, 0, 1, 0],
                    [0, 0, 0, 1],
                ],
            ],
            [
                [
                    [3, 2, 1],
                    [-5, -4, -3],
                    [6, 8, 1],
                ],
                [1, 2, 3],
                [
                    [3, 2, 1, 1],
                    [-5, -4, -3, 2],
                    [6, 8, 1, 3],
                    [0, 0, 0, 1],
                ],
            ],
            [
                [
                    [1, 0, 0],
                    [0, 1, 0],
                    [0, 0, 1],
                    [0, 0, 1],
                ],
                [0, 0, 0],
                [
                    [1, 0, 0, 0],
                    [0, 1, 0, 0],
                    [0, 0, 1, 0],
                    [0, 0, 0, 1],
                ],
                \PHPSymmetry\Exception\InvalidArgumentException::class,
            ],
            [
                [
                    [1, 0, 0],
                    [0, 1, 0],
                    [0, 0, 1],
                ],
                [0, 0],
                [
                    [1, 0, 0, 0],
                    [0, 1, 0, 0],
                    [0, 0, 1, 0],
                    [0, 0, 0, 1],
                ],
                \PHPSymmetry\Exception\InvalidArgumentException::class,
            ],
        ];
    }

    /**
     * @param string $string
     * @param MatrixArray $expectedMatrix
     * @param bool $exception
     * @return void
     * @throws \PHPSymmetry\Exception\InvalidArgumentException
     */
    #[DataProvider("providerFromXYZ")]
    #[TestDox("TestFromXYZ() method creates a symmetry operation from the given string.")]
    public function testFromXYZ(string $string, array $expectedMatrix, bool $exception = false): void
    {
        if ($exception) {
            $this->expectException(\PHPSymmetry\Exception\InvalidArgumentException::class);
        }
        $s = SymmetryOperation3D::fromXYZ($string);
        $this->assertEqualsWithDelta($expectedMatrix, $s->matrix()->toArray(), self::e);
    }

    /**
     * @return array<int, array<int, string|MatrixArray|bool>>
     */
    public static function providerFromXYZ(): array
    {
        return [
            [
                "x,y,z",
                [
                    [1, 0, 0, 0],
                    [0, 1, 0, 0],
                    [0, 0, 1, 0],
                    [0, 0, 0, 1],
                ],
            ],
            [
                "0x,0y,0z",
                [
                    [0, 0, 0, 0],
                    [0, 0, 0, 0],
                    [0, 0, 0, 0],
                    [0, 0, 0, 1],
                ],
            ],
            [
                "2 1/3x+6y-z+1 8/9, -z+1/3, 0x+0y+0z-16/11",
                [
                    [2 + 1 / 3, 6, -1, 8 / 9],
                    [0, 0, -1, 1 / 3],
                    [0, 0, 0, -5 / 11],
                    [0, 0, 0, 1],
                ],
            ],
            [
                "-y+1/2,-x+1/2,z+1/2",
                [
                    [0, -1, 0, 1 / 2],
                    [-1, 0, 0, 1 / 2],
                    [0, 0, 1, 1 / 2],
                    [0, 0, 0, 1],
                ],
            ],
            [
                "x,x-y,-z+2/3",
                [
                    [1, 0, 0, 0],
                    [1, -1, 0, 0],
                    [0, 0, -1, 2 / 3],
                    [0, 0, 0, 1],
                ],
            ],
            [
                "x,y",
                [], true,
            ],
            [
                "+,+y,+z",
                [], true,
            ],
            [
                "x+1*1/2,y,z",
                [], true,
            ],
            [
                "x+1+1,y,z",
                [], true,
            ],
            [
                "1/1/1x,y,z",
                [], true,
            ],
            [
                "x+x,y,z",
                [], true,
            ],
        ];
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     * @throws \PHPSymmetry\Exception\InvalidArgumentException
     */
    #[TestDox("Main getters of the SymmetryOperation3D class return the property values.")]
    public function testGetters(): void
    {
        $m = new Matrix([
            [1, 0, 0, 0],
            [0, 1, 0, 0],
            [0, 0, 1, 0],
            [0, 0, 0, 1],
        ]);
        $rotationPart = new Matrix([
            [1, 0, 0],
            [0, 1, 0],
            [0, 0, 1],
        ]);
        $translationPart = Vector::fromArray([0, 0, 0]);
        $s = new SymmetryOperation3D($m);

        $this->assertEquals(3, $s->dimensionality());
        $this->assertEquals($m, $s->matrix());
        $this->assertEquals($rotationPart->toArray(), $s->rotationPart()->toArray());
        $this->assertEquals($translationPart->toArray(), $s->translationPart()->toArray());
    }

    /**
     * @param MatrixArray $array
     * @param string $expected
     * @return void
     * @throws InvalidArgumentException
     * @throws \PHPSymmetry\Exception\InvalidArgumentException
     */
    #[DataProvider("providerToString")]
    #[TestDox("SymmetryOperation3D class toXYZ() and __toString() methods return the expected string.")]
    public function testToString(array $array, string $expected): void
    {
        $m = new Matrix($array);
        $s = new SymmetryOperation3D($m);
        $this->assertEquals($expected, (string)$s);
        $this->assertEquals($expected, $s->toXYZ());
    }

    /**
     * @return array<int, array<int, MatrixArray|string>>
     */
    public static function providerToString(): array
    {
        return [
            [
                [
                    [1, 0, 0, 0],
                    [0, 1, 0, 0],
                    [0, 0, 1, 0],
                    [0, 0, 0, 1],
                ],
                "x,y,z",
            ],
            [
                [
                    [0, -1, 0, 0],
                    [1, 0, 0, 0],
                    [0, 0, -1, 0],
                    [0, 0, 0, 1],
                ],
                "-y,x,-z",
            ],
            [
                [
                    [0, 0, 0, 0],
                    [0, 0, 0, 0],
                    [0, 0, 0, 0],
                    [0, 0, 0, 0],
                ],
                "",
            ],
            [
                [
                    [0, 0, 1, 1 / 3],
                    [0, 1, 0, -1 / 3],
                    [-1, 0, 0, 2 / 3],
                    [0, 0, 0, 1],
                ],
                "z+1/3,y-1/3,-x+2/3",
            ],
            [
                [
                    [1, 1, 0, 4 / 3],
                    [0, 1, 1, -5 / 3],
                    [-1, 0, -1, 9 / 3],
                    [0, 0, 0, 1],
                ],
                "x+y+1 1/3,y+z-1 2/3,-x-z+3",
            ],
            [
                [
                    [3, 2, 1, 2],
                    [-1, -2, -3, -7],
                    [2 / 3, 11, -1 / 3, 6],
                    [0, 0, 0, 1],
                ],
                "3x+2y+z+2,-x-2y-3z-7,2/3x+11y-1/3z+6",
            ],
        ];
    }

    /**
     * @param string $string
     * @param int $expected
     * @return void
     * @throws \PHPSymmetry\Exception\InvalidArgumentException
     */
    #[TestWith(["x,y,z", 1])]
    #[TestWith(["-x,y,-z", 2])]
    #[TestWith(["-x,y,z", 2])]
    #[TestWith(["x-y,x,z", 6])]
    #[TestWith(["-x+y,-x,-z", 6])]
    #[TestWith(["x-y,x,-z", 6])]
    #[TestWith(["-x,z+1/2,-y+1/2", 4])]
    #[TestDox("testOrder() method returns the expected order of symmetry operation.")]
    public function testOrder(string $string, int $expected): void
    {
        $s = SymmetryOperation3D::fromXYZ($string);
        $this->assertEquals($expected, $s->order());
    }

    /**
     * @param string $string
     * @param VectorArray $expected
     * @return void
     * @throws \PHPSymmetry\Exception\InvalidArgumentException
     */
    #[TestWith(["x,y,z", [0, 0, 0]])]
    #[TestWith(["-x,-y,z", [0, 0, 0]])]
    #[TestWith(["y+1/4,-x+1/4,z+3/4", [0, 0, 3/4]])]
    #[TestWith(["-y+3/4,-x+1/4,z+1/4", [1/4, -1/4, 1/4]])]
    #[TestDox("testIntrinsicTranslationPart() method returns the expected intrinsic translation part of symmetry operation.")]
    public function testIntrinsicTranslationPart(string $string, array $expected): void
    {
        $s = SymmetryOperation3D::fromXYZ($string);
        $this->assertEquals($expected, $s->intrinsicTranslationPart()->toPlainArray());
    }

    /**
     * @param string $string
     * @param VectorArray $expected
     * @return void
     * @throws \PHPSymmetry\Exception\InvalidArgumentException
     */
    #[TestWith(["x,y,z", [0, 0, 0]])]
    #[TestWith(["x,y,-z", [0, 0, 0]])]
    #[TestWith(["y+1/4,-x+1/4,z+3/4", [1/4, 1/4, 0]])]
    #[TestWith(["-y+3/4,-x+1/4,z+1/4", [1/2, 1/2, 0]])]
    #[TestDox("testLocationPart() method returns the expected location part of symmetry operation.")]
    public function testLocationPart(string $string, array $expected): void
    {
        $s = SymmetryOperation3D::fromXYZ($string);
        $this->assertEquals($expected, $s->locationPart()->toPlainArray());
    }

    /**
     * @param string $string
     * @param string $expected
     * @return void
     * @throws \PHPSymmetry\Exception\InvalidArgumentException
     */
    #[DataProvider("providerToSymbol")]
    #[TestDox("testToSymbol() method returns the expected symbol of symmetry operation.")]
    public function testToSymbol(string $string, string $expected): void
    {
        $s = SymmetryOperation3D::fromXYZ($string);
        $this->assertEquals($expected, $s->toSymbol());
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function providerToSymbol(): array
    {
        return [
            ["x,y,z", "1"],
            ["x+1/2,y+1/2,z-1/4", "t (1/2,1/2,-1/4)"],
            ["-x,-y,-z", "-1 (0,0,0)"],
            ["-x+1/2,-y+1/2,-z+1/2", "-1 (1/4,1/4,1/4)"],
            ["x,-y+1/2,z", "m x,1/4,z"],
            ["-x,y,z", "m 0,y,z"],
            ["y,x,z", "m x,x,z"],
            ["-y,-x,z", "m x,-x,z"],
            ["-x+y,y,z", "m x,2x,z"],
            ["x,x-y,z", "m 2x,x,z"],
            ["-x+y+2/3,y+1/3,z+5/6", "g(1/6,1/3,5/6) x+1/4,2x,z"],
            ["-y,-x,z+1/2", "c x,-x,z"],
            ["y+1/2,x+1/2,z+1/2", "n(1/2,1/2,1/2) x,x,z"],
            ["-x,y+1/2,z+1/2", "n(0,1/2,1/2) 0,y,z"],
            ["x+1/4,-y+3/4,z+3/4", "d(1/4,0,3/4) x,3/8,z"],
            ["x+1/2,y,-z+1/2", "a x,y,1/4"],
            ["-x+1/2,y+1/2,z", "b 1/4,y,z"],
            ["x,z,y", "m x,y,y"],
            ["z,y,x", "m x,y,x"],
            ["x,-z,-y", "m x,y,-y"],
            ["-z,y,-x", "m -x,y,x"],
            ["-z+1/2,y+1/2,-x", "g(1/4,1/2,-1/4) -x+1/4,y,x"],
            ["x+1/2,-z,-y+1/2", "g(1/2,-1/4,1/4) x,y+1/4,-y"],
            ["x+1/2,z,y+1/2", "g(1/2,1/4,1/4) x,y-1/4,y"],
            ["z,y,x+1/2", "g(1/4,0,1/4) x-1/4,y,x"],
            ["x+1/3,x-y+2/3,z+7/6", "g(1/3,1/6,1/6) 2x-1/2,x,z"],
            ["-x,y,-z", "2 0,y,0"],
            ["-x+1/4,-z+1/4,-y+1/4", "2 1/8,y+1/4,-y"],
            ["z+1/4,-y+1/4,x+3/4", "2(1/2,0,1/2) x-1/4,1/8,x"],
            ["y,-x+1/2,z", "4- 1/4,1/4,z"],
            ["-y,x+1/2,z+1/2", "4+(0,0,1/2) -1/4,1/4,z"],
            ["x-y,x,z", "6+ 0,0,z"],
            ["y,-x+y,z", "6- 0,0,z"],
            ["-x+y+1/3,-x+2/3,z+2/3", "3-(0,0,2/3) 1/3,1/3,z"],
            ["-y,x-y,z+2/3", "3+(0,0,2/3) 0,0,z"],
            ["-x+y+1/3,-x+2/3,z+2/3", "3-(0,0,2/3) 1/3,1/3,z"],
            ["z,x,y", "3+ x,x,x"],
            ["-z,x,-y", "3+ -x,-x,x"],
            ["-y,-z,x", "3- -x,x,-x"],
            ["-z+1/2,-x,y+1/2", "3+ x+1/2,-x-1/2,-x"],
            ["-z+1/2,x,-y+1/2", "3+ -x+1/2,-x+1/2,x"],
            ["-z+1/2,x+1/2,y", "-3+ -x-1/2,x+1,-x; 0,1/2,1/2"],
            ["-y,x+1/2,-z", "-4- -1/4,1/4,z; -1/4,1/4,0"],
            ["-x+y,-x,-z+1/2", "-6+ 0,0,z; 0,0,1/4"],
            ["z,-x+1/2,y+1/2", "-3+ -x+1,-x+1/2,x; 1/2,0,1/2"],
            ["-z,-x+1/2,y", "3+(-1/6,1/6,1/6) x+1/6,-x+1/6,-x"],
            ["-x+3/4,z+3/4,-y+1/4", "-4+ x,1/2,-1/4; 3/8,1/2,-1/4"],
        ];
    }

    /**
     * @param string $string1
     * @param string $string2
     * @param string $expected
     * @param bool $exception
     * @return void
     * @throws \PHPSymmetry\Exception\InvalidArgumentException
     */
    #[TestWith(["x,y,z", "-x,-y,-z", "-x,-y,-z"])]
    #[TestWith(["-x,-y,-z", "-x,-y,-z", "x,y,z"])]
    #[TestWith(["x,y,z", "-x,-y,-z", "-x,-y,-z"])]
    #[TestWith(["-x+1/2,-y,z+1/2", "-x,y+1/2,-z+1/2", "x+1/2,-y+1/2,-z"])]
    #[TestDox("testProduct() method returns the expected product of two symmetry operations.")]
    public function testProduct(string $string1, string $string2, string $expected, bool $exception = false): void
    {
        if ($exception) {
            $this->expectException(\PHPSymmetry\Exception\InvalidArgumentException::class);
        }
        $s1 = SymmetryOperation3D::fromXYZ($string1);
        $s2 = SymmetryOperation3D::fromXYZ($string2);
        $s = $s1->product($s2);
        $this->assertEquals($expected, $s->toXYZ());
    }
}
