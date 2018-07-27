<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Adminpanel\Modules\Info;

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

use TYPO3\CMS\Adminpanel\Modules\AbstractSubModule;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * General information module displaying info about the current
 * request
 */
class GeneralInformation extends AbstractSubModule
{
    /**
     * Creates the content for the "info" section ("module") of the Admin Panel
     *
     * @return string HTML content for the section. Consists of a string with table-rows with four columns.
     * @see display()
     */
    public function getContent(): string
    {
        /** @var UserAspect $frontendUserAspect */
        $frontendUserAspect = GeneralUtility::makeInstance(Context::class)->getAspect('frontend.user');
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $templateNameAndPath = 'EXT:adminpanel/Resources/Private/Templates/Modules/Info/General.html';
        $view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName($templateNameAndPath));
        $view->setPartialRootPaths(['EXT:adminpanel/Resources/Private/Partials']);
        $tsfe = $this->getTypoScriptFrontendController();

        $view->assignMultiple(
            [
                'post' => $_POST,
                'get' => $_GET,
                'cookie' => $_COOKIE,
                'server' => $_SERVER,
                'info' => [
                    'pageUid' => $tsfe->id,
                    'pageType' => $tsfe->type,
                    'groupList' => implode(',', $frontendUserAspect->getGroupIds()),
                    'noCache' => $this->isNoCacheEnabled(),
                    'countUserInt' => \count($tsfe->config['INTincScript'] ?? []),
                    'totalParsetime' => $this->getTimeTracker()->getParseTime(),
                    'feUser' => [
                        'uid' => $tsfe->fe_user->user['uid'] ?? 0,
                        'username' => $tsfe->fe_user->user['username'] ?? ''
                    ],
                    'imagesOnPage' => $this->collectImagesOnPage(),
                    'documentSize' => $this->collectDocumentSize()
                ]
            ]
        );

        return $view->render();
    }

    /**
     * Identifier for this Sub-module,
     * for example "preview" or "cache"
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return 'info_general';
    }

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return $this->getLanguageService()->sL(
            'LLL:EXT:adminpanel/Resources/Private/Language/locallang_info.xlf:sub.general.label'
        );
    }

    /**
     * Collects images from TypoScriptFrontendController and calculates the total size.
     * Returns human readable image sizes for fluid template output
     *
     * @return array
     */
    protected function collectImagesOnPage(): array
    {
        $imagesOnPage = [
            'files' => [],
            'total' => 0,
            'totalSize' => 0,
            'totalSizeHuman' => GeneralUtility::formatSize(0)
        ];

        if ($this->isNoCacheEnabled() === false) {
            return $imagesOnPage;
        }

        $count = 0;
        $totalImageSize = 0;
        if (!empty($this->getTypoScriptFrontendController()->imagesOnPage)) {
            foreach ($this->getTypoScriptFrontendController()->imagesOnPage as $file) {
                $fileSize = @filesize($file);
                $imagesOnPage['files'][] = [
                    'name' => $file,
                    'size' => $fileSize,
                    'sizeHuman' => GeneralUtility::formatSize($fileSize)
                ];
                $totalImageSize += $fileSize;
                $count++;
            }
        }
        $imagesOnPage['totalSize'] = GeneralUtility::formatSize($totalImageSize);
        $imagesOnPage['total'] = $count;
        return $imagesOnPage;
    }

    /**
     * Gets the document size from the current page in a human readable format
     *
     * @return string
     */
    protected function collectDocumentSize(): string
    {
        $documentSize = 0;
        if ($this->isNoCacheEnabled() === true) {
            $documentSize = mb_strlen($this->getTypoScriptFrontendController()->content, 'UTF-8');
        }

        return GeneralUtility::formatSize($documentSize);
    }

    /**
     * @return bool
     */
    protected function isNoCacheEnabled(): bool
    {
        return (bool)$this->getTypoScriptFrontendController()->no_cache;
    }

    /**
     * @return TypoScriptFrontendController
     */
    protected function getTypoScriptFrontendController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    /**
     * @return TimeTracker
     */
    protected function getTimeTracker(): TimeTracker
    {
        return GeneralUtility::makeInstance(TimeTracker::class);
    }
}
