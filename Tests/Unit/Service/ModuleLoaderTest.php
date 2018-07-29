<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Adminpanel\Tests\Unit\Service;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Adminpanel\Exceptions\InvalidConfigurationException;
use TYPO3\CMS\Adminpanel\Service\ModuleLoader;
use TYPO3\CMS\Adminpanel\Tests\Unit\Fixtures\DisabledMainModuleFixture;
use TYPO3\CMS\Adminpanel\Tests\Unit\Fixtures\MainModuleFixture;
use TYPO3\CMS\Adminpanel\Tests\Unit\Fixtures\SubModuleFixture;
use TYPO3\CMS\Core\Service\DependencyOrderingService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ModuleLoaderTest extends UnitTestCase
{

    /**
     * @test
     */
    public function validateSortAndInitializeModulesReturnsEmptyArrayIfNoModulesAreConfigured()
    {
        $moduleLoader = new ModuleLoader();
        $result = $moduleLoader->validateSortAndInitializeModules([]);

        self::assertSame([], $result);
    }

    public function missingConfigurationDataProvider(): array
    {
        return [
            'empty' => [['modulename' => []]],
            'no array' => [['modulename' => '']],
        ];
    }

    /**
     * @test
     * @dataProvider missingConfigurationDataProvider
     */
    public function validateSortAndInitializeModulesThrowsExceptionIfModuleHasMissingConfiguration($configuration): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(1519490105);

        $moduleLoader = new ModuleLoader();
        $moduleLoader->validateSortAndInitializeModules($configuration);
    }

    public function invalidConfigurationDataProvider(): array
    {
        return [
            'module class name is no string' => [
                [
                    'modulename' => ['module' => []],
                ],
            ],
            'module class name is empty' => [
                [
                    'modulename' => ['module' => ''],
                ],
            ],
            'module class name is no valid class' => [
                [
                    'modulename' => ['module' => 'nonExistingClassName'],
                ],
            ],
            'module class name does not implement AdminPanelModuleInterface' => [
                [
                    'modulename' => ['module' => \stdClass::class],
                ],
            ],
            'submodule class name given when main modules requested' => [
                [
                    'modulename' => ['module' => SubModuleFixture::class]
                ]
            ]
        ];
    }

    /**
     * @test
     * @dataProvider  invalidConfigurationDataProvider
     */
    public function validateSortAndInitializeModulesThrowsExceptionIfModuleHasInvalidConfiguration($configuration): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(1519490112);

        $moduleLoader = new ModuleLoader();
        $moduleLoader->validateSortAndInitializeModules($configuration);
    }

    /**
     * @test
     * @dataProvider  invalidConfigurationDataProvider
     */
    public function validateSortAndInitializeModulesThrowsExceptionIfSubModuleRequestedButMainModuleGiven($configuration): void
    {
        $config = [
            'module1' => [
                'module' => MainModuleFixture::class
            ]
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(1519490112);

        $moduleLoader = new ModuleLoader();
        $moduleLoader->validateSortAndInitializeModules($config, 'sub');
    }

    /**
     * @test
     */
    public function validateSortAndInitializeModulesOrdersModulesWithDependencyOrderingService(): void
    {
        $config = [
            'module1' => [
                'module' => MainModuleFixture::class
            ]
        ];

        $dependencyOrderingServiceProphecy = $this->prophesize(DependencyOrderingService::class);
        GeneralUtility::addInstance(DependencyOrderingService::class, $dependencyOrderingServiceProphecy->reveal());
        $dependencyOrderingServiceProphecy->orderByDependencies($config)->willReturn($config);

        $moduleLoader = new ModuleLoader();
        $moduleLoader->validateSortAndInitializeModules($config);

        $dependencyOrderingServiceProphecy->orderByDependencies($config)->shouldHaveBeenCalled();
    }

    /**
     * @test
     */
    public function validateSortAndInitializeModulesInstantiatesMainModulesOnlyIfEnabled(): void
    {
        $config = [
            'module1' => [
                'module' => MainModuleFixture::class
            ],
            'module2' => [
                'module' => DisabledMainModuleFixture::class
            ]
        ];

        $dependencyOrderingServiceProphecy = $this->prophesize(DependencyOrderingService::class);
        GeneralUtility::addInstance(DependencyOrderingService::class, $dependencyOrderingServiceProphecy->reveal());
        $dependencyOrderingServiceProphecy->orderByDependencies($config)->willReturn($config);

        $moduleLoader = new ModuleLoader();
        $result = $moduleLoader->validateSortAndInitializeModules($config);

        self::assertCount(1, $result);
        self::assertInstanceOf(MainModuleFixture::class, $result[0]);
        self::assertNotInstanceOf(DisabledMainModuleFixture::class, $result[0]);
    }

    /**
     * @test
     */
    public function validateSortAndInitializeSubModulesInstantiatesSubModules(): void
    {
        $config = [
            'module1' => [
                'module' => SubModuleFixture::class
            ],
            'module2' => [
                'module' => SubModuleFixture::class
            ]
        ];

        $dependencyOrderingServiceProphecy = $this->prophesize(DependencyOrderingService::class);
        GeneralUtility::addInstance(DependencyOrderingService::class, $dependencyOrderingServiceProphecy->reveal());
        $dependencyOrderingServiceProphecy->orderByDependencies($config)->willReturn($config);

        $moduleLoader = new ModuleLoader();
        $result = $moduleLoader->validateSortAndInitializeSubModules($config);

        self::assertCount(2, $result);
        self::assertInstanceOf(SubModuleFixture::class, $result[0]);
        self::assertInstanceOf(SubModuleFixture::class, $result[1]);
    }
}
