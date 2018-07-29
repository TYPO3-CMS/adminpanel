<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Adminpanel\Tests\Unit\Fixtures;

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

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Adminpanel\Modules\AdminPanelModuleInterface;
use TYPO3\CMS\Adminpanel\Modules\AdminPanelSubModuleInterface;

class MainModuleFixture implements AdminPanelModuleInterface
{

    /**
     * Identifier for this module,
     * for example "preview" or "cache"
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return 'example';
    }

    /**
     * Module label
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Example Label';
    }

    /**
     * Module Icon identifier - needs to be registered in iconRegistry
     *
     * @return string
     */
    public function getIconIdentifier(): string
    {
        return 'actions-document-info';
    }

    /**
     * Displayed directly in the bar if module has content
     *
     * @return string
     */
    public function getShortInfo(): string
    {
        return 'short info';
    }

    /**
     * @return string
     */
    public function getSettings(): string
    {
        return 'example settings';
    }

    /**
     * Initialize the module - runs early in a TYPO3 request
     *
     * @param ServerRequestInterface $request
     */
    public function initializeModule(ServerRequestInterface $request): void
    {
    }

    /**
     * Module is enabled
     * -> should be initialized
     * A module may be enabled but not shown
     * -> only the initializeModule() method
     * will be called
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Executed on saving / submit of the configuration form
     * Can be used to react to changed settings
     * (for example: clearing a specific cache)
     *
     * @param array $configurationToSave
     * @param ServerRequestInterface $request
     */
    public function onSubmit(array $configurationToSave, ServerRequestInterface $request): void
    {
    }

    /**
     * Returns a string array with javascript files that will be rendered after the module
     *
     * @return array
     */
    public function getJavaScriptFiles(): array
    {
        return [];
    }

    /**
     * Returns a string array with css files that will be rendered after the module
     *
     * @return array
     */
    public function getCssFiles(): array
    {
        return [];
    }

    /**
     * Set SubModules for current module
     *
     * @param AdminPanelSubModuleInterface[] $subModules
     */
    public function setSubModules(array $subModules): void
    {
    }

    /**
     * Get SubModules for current module
     *
     * @return AdminPanelSubModuleInterface[]
     */
    public function getSubModules(): array
    {
        return [];
    }

    /**
     * Returns true if submodule has own settings
     *
     * @return bool
     */
    public function getHasSubmoduleSettings(): bool
    {
        return false;
    }
}
