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

use PHPSymmetry\Support;
use PHPSymmetry\SymmetryOperation\AbstractSymmetryOperation;
use PHPSymmetry\SymmetryOperation\SymmetryOperation3D;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * Test the Support class.
 * @phpstan-type SymmetryOperationArray array<int, AbstractSymmetryOperation>
 */
class SupportTest extends TestCase
{
    /**
     * Tolerance for floating point comparisons.
     */
    protected const e = 1e-8;

    /**
     * @param int|float $number
     * @param int|float $expected
     * @return void
     */
    #[TestWith([1, 0])]
    #[TestWith([-1, 0])]
    #[TestWith([0, 0])]
    #[TestWith([2, 0])]
    #[TestWith([-2, 0])]
    #[TestWith([0.7, 0.7])]
    #[TestWith([-0.7, 0.3])]
    #[TestWith([1.7, 0.7])]
    #[TestWith([-1.7, 0.3])]
    #[TestDox("ReduceNumberPositive() method returns the expected value")]
    public function testReduceNumberPositive(int|float $number, int|float $expected): void
    {
        $this->assertEqualsWithDelta($expected, Support::reduceNumberPositive($number), self::e);
    }

    /**
     * @param int|float $number
     * @param int|float $expected
     * @return void
     */
    #[TestWith([1, 0])]
    #[TestWith([-1, 0])]
    #[TestWith([0, 0])]
    #[TestWith([2, 0])]
    #[TestWith([-2, 0])]
    #[TestWith([0.7, 0.7])]
    #[TestWith([-0.7, -0.7])]
    #[TestWith([1.7, 0.7])]
    #[TestWith([-1.7, -0.7])]
    #[TestDox("ReduceNumber() method returns the expected value")]
    public function testReduceNumber(int|float $number, int|float $expected): void
    {
        $this->assertEqualsWithDelta($expected, Support::reduceNumber($number), self::e);
    }

    /**
     * @param SymmetryOperation3D $symmetryOperation
     * @param SymmetryOperationArray $symmetryOperations
     * @param bool $expected
     * @return void
     */
    #[DataProvider("providerIsRotationPartInArray")]
    #[TestDox("IsRotationPartInArray() method returns true if the rotation part of a symmetry operation is part of an array of symmetry operations.")]
    public function testIsRotationPartInArray(SymmetryOperation3D $symmetryOperation, array $symmetryOperations, bool $expected): void
    {
        $this->assertEquals($expected, Support::isRotationPartInArray($symmetryOperation, $symmetryOperations));
    }

    /**
     * @return array<int, array<int, SymmetryOperation3D|SymmetryOperationArray|bool>>
     */
    public static function providerIsRotationPartInArray(): array
    {
        return [
            [
                SymmetryOperation3D::fromXYZ("x, y, z"),
                [
                    SymmetryOperation3D::fromXYZ("x, y, z"),
                    SymmetryOperation3D::fromXYZ("-x, -y, -z"),
                ],
                true,
            ],
            [
                SymmetryOperation3D::fromXYZ("-x, -y, -z"),
                [
                    SymmetryOperation3D::fromXYZ("x, y, z"),
                    SymmetryOperation3D::fromXYZ("-x, -y, -z"),
                    SymmetryOperation3D::fromXYZ("y, z, x"),
                ],
                true,
            ],
            [
                SymmetryOperation3D::fromXYZ("x, y, z"),
                [
                    SymmetryOperation3D::fromXYZ("y, z, x"),
                    SymmetryOperation3D::fromXYZ("-x, -y, -z"),
                ],
                false,
            ],
        ];
    }

    public function testGetJSONSpaceGroupData(): void
    {
        $spacegroups = Support::getJSONSpaceGroupData();
        $this->assertIsArray($spacegroups);
        $p1 = $spacegroups[0];
        $this->assertEquals(1, $p1['number']);
        $this->assertEquals('P 1', $p1['hermann_mauguin']);
    }
}
