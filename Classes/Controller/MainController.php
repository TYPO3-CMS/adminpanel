<?php

declare(strict_types=1);

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

namespace TYPO3\CMS\Adminpanel\Controller;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Adminpanel\ModuleApi\ConfigurableInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\DataProviderInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\ModuleDataStorageCollection;
use TYPO3\CMS\Adminpanel\ModuleApi\ModuleInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\PageSettingsProviderInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\RequestEnricherInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\ShortInfoProviderInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\SubmoduleProviderInterface;
use TYPO3\CMS\Adminpanel\Service\ModuleLoader;
use TYPO3\CMS\Adminpanel\Utility\ResourceUtility;
use TYPO3\CMS\Adminpanel\Utility\StateUtility;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Core\RequestId;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Main controller for the admin panel.
 *
 * Note this is a "shared" / singleton object: Middleware AdminPanelInitiator
 * instantiates the object and eventually calls initialize(). Later,
 * AdminPanelRenderer calls render() on the same object.
 *
 * @internal
 */
#[Autoconfigure(public: true)]
class MainController
{
    /** @var array<string, ModuleInterface> */
    protected array $modules = [];
    protected array $adminPanelModuleConfiguration;

    public function __construct(
        private readonly ModuleLoader $moduleLoader,
        private readonly UriBuilder $uriBuilder,
        private readonly RequestId $requestId,
    ) {
        $this->adminPanelModuleConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['adminpanel']['modules'] ?? [];
    }

    /**
     * Initializes settings for the admin panel.
     */
    public function initialize(ServerRequestInterface $request): ServerRequestInterface
    {
        $this->modules = $this->moduleLoader->validateSortAndInitializeModules(
            $this->adminPanelModuleConfiguration
        );
        if (StateUtility::isActivatedForUser()) {
            $request = $this->initializeModules($request, $this->modules);
        }
        return $request;
    }

    /**
     * Renders the admin panel - Called in PSR-15 Middleware
     *
     * @see \TYPO3\CMS\Adminpanel\Middleware\AdminPanelRenderer
     */
    public function render(ServerRequestInterface $request): string
    {
        $resources = ResourceUtility::getResources(['nonce' => $this->requestId->nonce]);

        $backupRequest = null;
        $frontendTypoScript = $request->getAttribute('frontend.typoscript');
        if (!$frontendTypoScript->hasSetup()) {
            // @todo: This is a hack: The admin panel is the only extension that starts
            //        a Fluid view in 'fully cached' scenarios. f:translate() now triggers
            //        the extbase configuration manager in FE, which fetches TS setup from
            //        the Request attribute, which is *usally* always available, *except*
            //        in fully cached scenarios.
            //        See https://review.typo3.org/c/Packages/TYPO3.CMS/+/80732
            //        We still want extbase to crash if it tries to fetch TS setup when it
            //        is not set, so we work around this in the admin panel here.
            //        This should later be resolved by avoiding the dependency to extbase
            //        LocalizationUtility again, when core localization services can do
            //        similar things in FE as well.
            $frontendTypoScript = clone $frontendTypoScript;
            $frontendTypoScript->setSetupArray([]);
            $request = $request->withAttribute('frontend.typoscript', $frontendTypoScript);
            $backupRequest = $GLOBALS['TYPO3_REQUEST'];
            $GLOBALS['TYPO3_REQUEST'] = $request;
        }

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $templateNameAndPath = 'EXT:adminpanel/Resources/Private/Templates/Main.html';
        $view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName($templateNameAndPath));
        $view->setPartialRootPaths(['EXT:adminpanel/Resources/Private/Partials']);
        $view->setLayoutRootPaths(['EXT:adminpanel/Resources/Private/Layouts']);

        $view->assignMultiple([
            'toggleActiveUrl' => $this->generateBackendUrl('ajax_adminPanel_toggle'),
            'resources' => $resources,
            'adminPanelActive' => StateUtility::isOpen(),
            'languageKey' => $this->getBackendUser()->user['lang'] ?? null,
        ]);
        if (StateUtility::isOpen()) {
            $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('adminpanel_requestcache');
            $requestId = $request->getAttribute('adminPanelRequestId');
            $data = $cache->get($requestId);
            $moduleResources = ResourceUtility::getAdditionalResourcesForModules($this->modules, ['nonce' => $this->requestId->nonce]);
            $settingsModules = array_filter($this->modules, static function (ModuleInterface $module): bool {
                return $module instanceof PageSettingsProviderInterface;
            });
            $parentModules = array_filter(
                $this->modules,
                static function (ModuleInterface $module): bool {
                    return $module instanceof SubmoduleProviderInterface && $module instanceof ShortInfoProviderInterface;
                }
            );
            foreach ($parentModules as $parentModule) {
                $parentModule->setModuleData($data);
            }

            $routeIdentifier = 'web_layout';
            $arguments = [
                'id' => $request->getAttribute('frontend.page.information')->getId(),
            ];
            $backendUrl = (string)$this->uriBuilder->buildUriFromRoute(
                $routeIdentifier,
                $arguments,
                UriBuilder::SHAREABLE_URL
            );

            $view->assignMultiple([
                'modules' => $this->modules,
                'settingsModules' => $settingsModules,
                'parentModules' => $parentModules,
                'saveUrl' => $this->generateBackendUrl('ajax_adminPanel_saveForm'),
                'moduleResources' => $moduleResources,
                'requestId' => $requestId,
                'data' => $data ?? [],
                'backendUrl' => $backendUrl,
            ]);
        }
        $result = $view->render();
        if ($backupRequest) {
            $GLOBALS['TYPO3_REQUEST'] = $backupRequest;
        }
        return $result;
    }

    /**
     * Stores data for admin panel in cache - Called in PSR-15 Middleware
     *
     * @see \TYPO3\CMS\Adminpanel\Middleware\AdminPanelDataPersister
     */
    public function storeData(ServerRequestInterface $request): void
    {
        if (StateUtility::isOpen()) {
            $data = $this->storeDataPerModule(
                $request,
                $this->modules,
                GeneralUtility::makeInstance(ModuleDataStorageCollection::class)
            );
            $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('adminpanel_requestcache');
            $cache->set($request->getAttribute('adminPanelRequestId'), $data);
            $cache->collectGarbage();
        }
    }

    /**
     * Generate a url to a backend route
     */
    protected function generateBackendUrl(string $route): string
    {
        return (string)$this->uriBuilder->buildUriFromRoute($route);
    }

    /**
     * @param array<string, ModuleInterface> $modules
     */
    protected function initializeModules(ServerRequestInterface $request, array $modules): ServerRequestInterface
    {
        foreach ($modules as $module) {
            if (
                ($module instanceof RequestEnricherInterface)
                && (
                    (($module instanceof ConfigurableInterface) && $module->isEnabled())
                    || (!($module instanceof ConfigurableInterface))
                )
            ) {
                $request = $module->enrich($request);
            }
            if ($module instanceof SubmoduleProviderInterface) {
                $request = $this->initializeModules($request, $module->getSubModules());
            }
        }
        return $request;
    }

    /**
     * @param array<string, ModuleInterface> $modules
     */
    protected function storeDataPerModule(ServerRequestInterface $request, array $modules, ModuleDataStorageCollection $data): ModuleDataStorageCollection
    {
        foreach ($modules as $module) {
            if (
                ($module instanceof DataProviderInterface)
                && (
                    (($module instanceof ConfigurableInterface) && $module->isEnabled())
                    || (!($module instanceof ConfigurableInterface))
                )
            ) {
                $data->addModuleData($module, $module->getDataToStore($request));
            }

            if ($module instanceof SubmoduleProviderInterface) {
                $this->storeDataPerModule($request, $module->getSubModules(), $data);
            }
        }
        return $data;
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
