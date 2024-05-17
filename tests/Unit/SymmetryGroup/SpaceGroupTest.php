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

namespace PHPSymmetry\Tests\Unit\SymmetryGroup;

use PHPSymmetry\Exception\InvalidArgumentException;
use PHPSymmetry\SymmetryGroup\AbstractSymmetryGroup;
use PHPSymmetry\SymmetryGroup\SpaceGroup;
use PHPSymmetry\SymmetryOperation\SymmetryOperation3D;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * Test class for SpaceGroup class.
 *
 * @phpstan-type MatrixArray array<int, array<int, int|float>>
 * @phpstan-type IntMatrixArray array<int, array<int, int>>
 * @phpstan-type VectorArray array<int, int|float>
 * @phpstan-type SymmetryOperationArray array<int, SymmetryOperation3D>
 */
class SpaceGroupTest extends TestCase
{
    /**
     * Tolerance for floating point comparisons.
     */
    protected const e = 1e-8;

    /**
     * @param SymmetryOperationArray $symmetryOperations
     * @param MatrixArray $centeringTranslations
     * @param bool $exception
     * @return void
     * @throws InvalidArgumentException
     */
    #[DataProvider('providerMakeManually')]
    #[TestDox('MakeManually() factory method creates a SpaceGroup object with the given symmetry operations and centering translations.')]
    public function testMakeManually(array $symmetryOperations, array $centeringTranslations, bool $exception = false): void
    {
        if ($exception) {
            $this->expectException(InvalidArgumentException::class);
        }
        $s = SpaceGroup::makeManually($symmetryOperations, $centeringTranslations);
        $this->assertInstanceOf(SpaceGroup::class, $s);
        $this->assertInstanceOf(AbstractSymmetryGroup::class, $s);
        $this->assertEquals(3, $s->dimensionality());
        $this->assertEquals($symmetryOperations, $s->symmetryOperations());
        $this->assertEquals($centeringTranslations, $s->centeringTranslations());

        $primaryTranslationsArray = [
            [1, 0, 0],
            [0, 1, 0],
            [0, 0, 1],
        ];
        $this->assertEquals($primaryTranslationsArray, $s->primaryTranslations());
    }

    /**
     * @return array<int, array<int, array<int, VectorArray|SymmetryOperation3D>>>
     * @throws InvalidArgumentException
     */
    public static function providerMakeManually(): array
    {
        return [
            [
                [
                    SymmetryOperation3D::fromXYZ("x, y, z"),
                    SymmetryOperation3D::fromXYZ("-x, -y, -z"),
                ],
                [

                ],
            ],
            [
                [
                    SymmetryOperation3D::fromXYZ("x, y, z"),
                    SymmetryOperation3D::fromXYZ("-x, -y, -z"),
                ],
                [
                    [1 / 2, 1 / 2, 1 / 2],
                ],
            ],
        ];
    }

    /**
     * @param SymmetryOperationArray $symmetryOperations
     * @param string $expected
     * @return void
     * @throws InvalidArgumentException
     */
    #[DataProvider('providerToString')]
    #[TestDox('ToString() method returns a string representation of the symmetry operations in the space group.')]
    public function testToString(array $symmetryOperations, string $expected): void
    {
        $s = SpaceGroup::makeManually($symmetryOperations, []);
        $this->assertEquals($expected, $s->toString());
        $this->assertEquals($expected, (string)$s);
    }

    /**
     * @return array<int, array<int, SymmetryOperationArray|string>>
     * @throws InvalidArgumentException
     */
    public static function providerToString(): array
    {
        return [
            [
                [
                    SymmetryOperation3D::fromXYZ("x, y, z"),
                    SymmetryOperation3D::fromXYZ("-x, -y, -z"),
                ],
                "(1) x,y,z" . PHP_EOL .
                "(2) -x,-y,-z" . PHP_EOL,
            ],
            [
                [
                    SymmetryOperation3D::fromXYZ("x, y, z"),
                    SymmetryOperation3D::fromXYZ("-x, -y, -z"),
                    SymmetryOperation3D::fromXYZ("-x+1/2, y+1/2, -z+1/2"),
                    SymmetryOperation3D::fromXYZ("x, -y+1/2, z"),
                ],
                "(1) x,y,z" . PHP_EOL .
                "(2) -x,-y,-z" . PHP_EOL .
                "(3) -x+1/2,y+1/2,-z+1/2" . PHP_EOL .
                "(4) x,-y+1/2,z" . PHP_EOL,
            ],
        ];
    }

    /**
     * @param SymmetryOperationArray $symmetryOperations
     * @param SymmetryOperationArray $expected
     * @return void
     * @throws InvalidArgumentException
     */
    #[TestDox('GenerateGroup() method generates the full group from the current symmetry operations.')]
    #[DataProvider('providerGenerateGroup')]
    public function testGenerateGroup(array $symmetryOperations, array $expected): void
    {
        $group = SpaceGroup::makeManually($symmetryOperations, []);
        $fullGroup = $group->generateGroup();
        foreach ($fullGroup->symmetryOperations() as $index => $symmetryOperation) {
            $this->assertEquals($expected[$index]->toXYZ(), $symmetryOperation->toXYZ());
        }
    }

    /**
     * @return array<int, array<int, SymmetryOperationArray>>
     * @throws InvalidArgumentException
     */
    public static function providerGenerateGroup(): array
    {
        return [
            [
                [
                    SymmetryOperation3D::fromXYZ("x,y,z"),
                    SymmetryOperation3D::fromXYZ("-x,-y,-z"),
                ],
                [
                    SymmetryOperation3D::fromXYZ("x,y,z"),
                    SymmetryOperation3D::fromXYZ("-x,-y,-z"),
                ],
            ],
            [
                [
                    SymmetryOperation3D::fromXYZ("x,y,z"),
                ],
                [
                    SymmetryOperation3D::fromXYZ("x,y,z"),
                ],
            ],
            [
                [
                    SymmetryOperation3D::fromXYZ("x,y,z"),
                    SymmetryOperation3D::fromXYZ("-x,-y,z"),
                    SymmetryOperation3D::fromXYZ("x,-y,z+1/2"),
                ],
                [
                    SymmetryOperation3D::fromXYZ("x,y,z"),
                    SymmetryOperation3D::fromXYZ("-x,-y,z"),
                    SymmetryOperation3D::fromXYZ("x,-y,z+1/2"),
                    SymmetryOperation3D::fromXYZ("-x,y,z+1/2"),
                ],
            ],
            [
                [
                    SymmetryOperation3D::fromXYZ("x,y,z"),
                    SymmetryOperation3D::fromXYZ("-x,-y,z"),
                    SymmetryOperation3D::fromXYZ("-x,y,-z"),
                    SymmetryOperation3D::fromXYZ("z,x,y"),
                    SymmetryOperation3D::fromXYZ("y+1/2,x+1/2,z+1/2"),
                ],
                [
                    SymmetryOperation3D::fromXYZ("x,y,z"),
                    SymmetryOperation3D::fromXYZ("-x,-y,z"),
                    SymmetryOperation3D::fromXYZ("-x,y,-z"),
                    SymmetryOperation3D::fromXYZ("x,-y,-z"),
                    SymmetryOperation3D::fromXYZ("z,x,y"),
                    SymmetryOperation3D::fromXYZ("z,-x,-y"),
                    SymmetryOperation3D::fromXYZ("-z,-x,y"),
                    SymmetryOperation3D::fromXYZ("-z,x,-y"),
                    SymmetryOperation3D::fromXYZ("y,z,x"),
                    SymmetryOperation3D::fromXYZ("-y,z,-x"),
                    SymmetryOperation3D::fromXYZ("y,-z,-x"),
                    SymmetryOperation3D::fromXYZ("-y,-z,x"),
                    SymmetryOperation3D::fromXYZ("y+1/2,x+1/2,z+1/2"),
                    SymmetryOperation3D::fromXYZ("-y+1/2,-x+1/2,z+1/2"),
                    SymmetryOperation3D::fromXYZ("y+1/2,-x+1/2,-z+1/2"),
                    SymmetryOperation3D::fromXYZ("-y+1/2,x+1/2,-z+1/2"),
                    SymmetryOperation3D::fromXYZ("x+1/2,z+1/2,y+1/2"),
                    SymmetryOperation3D::fromXYZ("-x+1/2,z+1/2,-y+1/2"),
                    SymmetryOperation3D::fromXYZ("-x+1/2,-z+1/2,y+1/2"),
                    SymmetryOperation3D::fromXYZ("x+1/2,-z+1/2,-y+1/2"),
                    SymmetryOperation3D::fromXYZ("z+1/2,y+1/2,x+1/2"),
                    SymmetryOperation3D::fromXYZ("z+1/2,-y+1/2,-x+1/2"),
                    SymmetryOperation3D::fromXYZ("-z+1/2,y+1/2,-x+1/2"),
                    SymmetryOperation3D::fromXYZ("-z+1/2,-y+1/2,x+1/2"),
                ],
            ],
        ];
    }

    /**
     * @param string $string
     * @param bool $generateGroup
     * @param SymmetryOperationArray $expected
     * @param bool $exception
     * @return void
     */
    #[DataProvider('providerMakeFromExplicitSymbol')]
    #[TestDox('MakeFromExplicitSymbol() factory method creates a SpaceGroup object from a string with explicit form (ITC B).')]
    public function testMakeFromExplicitSymbol(string $string, bool $generateGroup, array $expected, bool $exception = false): void
    {
        if ($exception) {
            $this->expectException(InvalidArgumentException::class);
        }
        $group = SpaceGroup::makeFromExplicitSymbol($string, $generateGroup);
        foreach ($group->symmetryOperations() as $index => $symmetryOperation) {
            $this->assertEquals($expected[$index]->toXYZ(), $symmetryOperation->toXYZ());
        }
    }

    /**
     * @return array<int, array<int, string|bool|SymmetryOperationArray>>
     * @throws InvalidArgumentException
     */
    public static function providerMakeFromExplicitSymbol(): array
    {
        return [
            [
                'PAN$P1A000', false,
                [
                    SymmetryOperation3D::fromXYZ("x,y,z"),
                ],
            ],
            [
                'PAC$I1A000', false,
                [
                    SymmetryOperation3D::fromXYZ("-x,-y,-z"),
                ],
            ],
            [
                'PAC$I1A000', true,
                [
                    SymmetryOperation3D::fromXYZ("x,y,z"),
                    SymmetryOperation3D::fromXYZ("-x,-y,-z"),
                ],
            ],
            [
                'FCN$P2C000$P2B000$P3Q000$I2E666', false,
                [
                    SymmetryOperation3D::fromXYZ("-x,-y,z"),
                    SymmetryOperation3D::fromXYZ("-x,y,-z"),
                    SymmetryOperation3D::fromXYZ("z,x,y"),
                    SymmetryOperation3D::fromXYZ("y+1/2,x+1/2,z+1/2"),
                ],
            ],
            [
                'FCN$P2C000$P2B000$P3Q000$I2E666', true,
                [
                    SymmetryOperation3D::fromXYZ("x,y,z"),
                    SymmetryOperation3D::fromXYZ("-x,-y,z"),
                    SymmetryOperation3D::fromXYZ("-x,y,-z"),
                    SymmetryOperation3D::fromXYZ("x,-y,-z"),
                    SymmetryOperation3D::fromXYZ("z,x,y"),
                    SymmetryOperation3D::fromXYZ("z,-x,-y"),
                    SymmetryOperation3D::fromXYZ("-z,-x,y"),
                    SymmetryOperation3D::fromXYZ("-z,x,-y"),
                    SymmetryOperation3D::fromXYZ("y,z,x"),
                    SymmetryOperation3D::fromXYZ("-y,z,-x"),
                    SymmetryOperation3D::fromXYZ("y,-z,-x"),
                    SymmetryOperation3D::fromXYZ("-y,-z,x"),
                    SymmetryOperation3D::fromXYZ("y+1/2,x+1/2,z+1/2"),
                    SymmetryOperation3D::fromXYZ("-y+1/2,-x+1/2,z+1/2"),
                    SymmetryOperation3D::fromXYZ("y+1/2,-x+1/2,-z+1/2"),
                    SymmetryOperation3D::fromXYZ("-y+1/2,x+1/2,-z+1/2"),
                    SymmetryOperation3D::fromXYZ("x+1/2,z+1/2,y+1/2"),
                    SymmetryOperation3D::fromXYZ("-x+1/2,z+1/2,-y+1/2"),
                    SymmetryOperation3D::fromXYZ("-x+1/2,-z+1/2,y+1/2"),
                    SymmetryOperation3D::fromXYZ("x+1/2,-z+1/2,-y+1/2"),
                    SymmetryOperation3D::fromXYZ("z+1/2,y+1/2,x+1/2"),
                    SymmetryOperation3D::fromXYZ("z+1/2,-y+1/2,-x+1/2"),
                    SymmetryOperation3D::fromXYZ("-z+1/2,y+1/2,-x+1/2"),
                    SymmetryOperation3D::fromXYZ("-z+1/2,-y+1/2,x+1/2"),
                ],
            ],
        ];
    }

    /**
     * @param string $generators
     * @param bool $generateGroup
     * @param int|null $expected
     * @return void
     * @throws InvalidArgumentException
     */
    #[TestWith(['PAC$I1A000', false, null])]
    #[TestWith(['PAC$I1A000', true, 2])]
    #[TestWith(['FCN$P2C000$P2B000$P3Q000$I2E666', true, 96])]
    #[TestWith(['FCC$I3Q000$P4C693$P2D936', true, 192])]
    #[TestWith(['FCC$P2C936$P2B369$P3Q000$P2D936$I1A000', true, 192])]
    #[TestDox('Order() method returns the order of the space group.')]
    public function testOrder(string $generators, bool $generateGroup, ?int $expected): void
    {
        $group = SpaceGroup::makeFromExplicitSymbol($generators, $generateGroup);
        $this->assertEquals($expected, $group->order());
    }

    /**
     * @param string $s1
     * @param string $s2
     * @param bool $expected
     * @param bool $expandGroups
     * @return void
     * @throws InvalidArgumentException
     */
    #[TestWith(['PAC$I1A000', 'PAC$I1A000', true])]
    #[TestWith(['PAC$I1A000', 'PAC$P1A000', false])]
    #[TestWith(['FCC$I3Q000$P4C693$P2D936', 'FCC$P2C936$P2B369$P3Q000$P2D936$I1A000', true, true])]
    #[TestWith(['PMN$P2B000', 'CMN$P2B000', true])]
    #[TestDox('IsEqualIgnoreTranslations() method checks if two space groups are equal, ignoring translations.')]
    public function testIsEqualIgnoreTranslations(string $s1, string $s2, bool $expected, bool $expandGroups = false): void
    {
        $group1 = SpaceGroup::makeFromExplicitSymbol($s1);
        $group2 = SpaceGroup::makeFromExplicitSymbol($s2);
        if ($expandGroups) {
            $group1 = $group1->expandGroup();
            $group2 = $group2->expandGroup();
        }
        $this->assertEquals($expected, $group1->isEqualIgnoreTranslations($group2));
    }

    /**
     * @param string $s1
     * @param string $s2
     * @param bool $expected
     * @param bool $expandGroups
     * @return void
     * @throws InvalidArgumentException
     */
    #[TestWith(['PAC$I1A000', 'PAC$I1A000', true])]
    #[TestWith(['PAC$I1A000', 'PAC$P1A000', false])]
    #[TestWith(['FCC$I3Q000$P4C693$P2D936', 'FCC$P2C936$P2B369$P3Q000$P2D936$I1A000', true, true])]
    #[TestWith(['PMN$P2B000', 'CMN$P2B000', false])]
    #[TestWith(['PMN$P2B000', 'CMN$P2B000', false, true])]
    #[TestDox('IsEqual() method checks if two space groups are equal.')]
    public function testIsEqual(string $s1, string $s2, bool $expected, bool $expandGroups = false): void
    {
        $group1 = SpaceGroup::makeFromExplicitSymbol($s1);
        $group2 = SpaceGroup::makeFromExplicitSymbol($s2);
        if ($expandGroups) {
            $group1 = $group1->expandGroup();
            $group2 = $group2->expandGroup();
        }
        $this->assertEquals($expected, $group1->isEqual($group2));
    }

    /**
     * @param string $hallSymbol
     * @param bool $generateGroup
     * @param SymmetryOperationArray $expected
     * @param bool $exception
     * @return void
     * @throws InvalidArgumentException
     */
    #[DataProvider('providerMakeFromHallSymbol')]
    #[TestDox('MakeFromHallSymbol() factory method creates a SpaceGroup object from a Hall symbol.')]
    public function testMakeFromHallSymbol(string $hallSymbol, bool $generateGroup, array $expected, bool $exception): void
    {
        if ($exception) {
            $this->expectException(InvalidArgumentException::class);
        }
        $group = SpaceGroup::makeFromHallSymbol($hallSymbol, $generateGroup);
        foreach ($group->symmetryOperations() as $index => $symmetryOperation) {
            $this->assertEquals($expected[$index]->toXYZ(), $symmetryOperation->toXYZ());
        }
    }

    /**
     * @return array<int, array<int, string|bool|SymmetryOperationArray>>
     * @throws InvalidArgumentException
     */
    public static function providerMakeFromHallSymbol(): array
    {
        return [
            [
                'p 1', false,
                [
                    SymmetryOperation3D::fromXYZ("x,y,z"),
                ],
                false,
            ],
            [
                'p 1', true,
                [
                    SymmetryOperation3D::fromXYZ("x,y,z"),
                ],
                false,
            ],
            [
                '-p 1', true,
                [
                    SymmetryOperation3D::fromXYZ("x,y,z"),
                    SymmetryOperation3D::fromXYZ("-x,-y,-z"),
                ],
                false,
            ],
            [
                'P 2ac 2ab', true,
                [
                    SymmetryOperation3D::fromXYZ("x,y,z"),
                    SymmetryOperation3D::fromXYZ("-x+1/2,-y,z+1/2"),
                    SymmetryOperation3D::fromXYZ("x+1/2,-y+1/2,-z"),
                    SymmetryOperation3D::fromXYZ("-x,y+1/2,-z+1/2"),
                ],
                false,
            ],
        ];
    }

    /**
     * @param string $hallSymbol
     * @param string $explicitSymbol
     * @param bool $expected
     * @return void
     * @throws InvalidArgumentException
     */
    #[TestWith(['p 1', 'PAN$P1A000', true])]
    #[TestWith(['p 1', 'PAC$I1A000', false])]
    #[TestWith(['P 2ac 2ab', 'FCN$P2C000$P2B000$P3Q000$I2E666', false])]
    #[TestWith(['P 2ac 2ab', 'PON$P2C606$P2A660', true])]
    #[TestWith(['f -4a 2 3', 'FCN$P2C000$P2B000$P3Q000$I2E666', true])]
    #[TestWith(['-r 3 2"c', 'RRC$I3C000$P2F006', true])]
    #[TestWith(['-i 4bd 2c 3', 'ICC$I3Q000$P4C393$P2D933', true])]
    #[TestDox('CompareHallAndExplicit() test checks if a space group created from a Hall symbol is equal to a space group created from an explicit symbol.')]
    public function testCompareHallAndExplicit(string $hallSymbol, string $explicitSymbol, bool $expected): void
    {
        $group1 = SpaceGroup::makeFromHallSymbol($hallSymbol)->expandGroup();
        $group2 = SpaceGroup::makeFromExplicitSymbol($explicitSymbol)->expandGroup();
        $this->assertEquals($expected, $group1->isEqual($group2));
    }
}
