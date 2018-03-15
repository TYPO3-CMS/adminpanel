<?php
declare(strict_types=1);

namespace TYPO3\CMS\Adminpanel\Modules;

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

use TYPO3\CMS\Backend\FrontendBackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * Abstract base class for Core Admin Panel Modules containing helper methods
 *
 * @internal
 */
abstract class AbstractModule implements AdminPanelModuleInterface
{
    /**
     * @var string
     */
    protected $extResources = 'EXT:adminpanel/Resources/Private';

    /**
     * @inheritdoc
     */
    public function getAdditionalJavaScriptCode(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function initializeModule(): void
    {
    }

    /**
     * Returns true if the module is
     * -> either enabled via tsconfig admPanel.enable
     * -> or any setting is overridden
     * override is a way to use functionality of the admin panel without displaying the admin panel to users
     * for example: hidden records or pages can be displayed by default
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        $identifier = $this->getIdentifier();
        $result = $this->isEnabledViaTsConfig();
        if ($this->getBackendUser()->extAdminConfig['override.'][$identifier] ?? false) {
            $result = (bool)$this->getBackendUser()->extAdminConfig['override.'][$identifier];
        }
        return $result;
    }

    /**
     * Uses the backend user session to determine if the module is open
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        $option = 'display_' . $this->getIdentifier();
        return isset($this->getBackendUser()->uc['TSFE_adminConfig'][$option])
            ? (bool)$this->getBackendUser()->uc['TSFE_adminConfig'][$option]
            : false;
    }

    /**
     * Determines if the panel for this module is shown
     * -> returns true if panel is enabled in TSConfig
     *
     * @see isEnabled()
     * @return bool
     */
    public function isShown(): bool
    {
        return $this->isEnabledViaTsConfig();
    }

    /**
     * @inheritdoc
     */
    public function onSubmit(array $input): void
    {
    }

    /**
     * @inheritdoc
     */
    public function showFormSubmitButton(): bool
    {
        return false;
    }

    /**
     * Translate given key
     *
     * @param string $key Key for a label in the $LOCAL_LANG array of "sysext/lang/Resources/Private/Language/locallang_tsfe.xlf
     * @param bool $convertWithHtmlspecialchars If TRUE the language-label will be sent through htmlspecialchars
     * @return string The value for the $key
     */
    protected function extGetLL($key, $convertWithHtmlspecialchars = true): string
    {
        $labelStr = $this->getLanguageService()->getLL($key);
        if ($convertWithHtmlspecialchars) {
            $labelStr = htmlspecialchars($labelStr);
        }
        return $labelStr;
    }

    /**
     * Returns the current BE user.
     *
     * @return \TYPO3\CMS\Backend\FrontendBackendUserAuthentication
     */
    protected function getBackendUser(): FrontendBackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * Helper method to return configuration options
     * Checks User TSConfig overrides and current backend user session
     *
     * @param string $option
     * @return string
     */
    protected function getConfigurationOption(string $option): string
    {
        $beUser = $this->getBackendUser();
        $identifier = $this->getIdentifier();

        if ($option && isset($beUser->extAdminConfig['override.'][$identifier . '.'][$option])) {
            $returnValue = $beUser->extAdminConfig['override.'][$identifier . '.'][$option];
        } else {
            $returnValue = $beUser->uc['TSFE_adminConfig'][$identifier . '_' . $option] ?? '';
        }

        return (string)$returnValue;
    }

    /**
     * Returns LanguageService
     *
     * @return \TYPO3\CMS\Core\Localization\LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Returns true if TSConfig admPanel.enable is set for this module (or all modules)
     *
     * @return bool
     */
    protected function isEnabledViaTsConfig(): bool
    {
        $result = false;
        $identifier = $this->getIdentifier();
        if (!empty($this->getBackendUser()->extAdminConfig['enable.']['all'])) {
            $result = true;
        } elseif (!empty($this->getBackendUser()->extAdminConfig['enable.'][$identifier])) {
            $result = true;
        }
        return $result;
    }
}
