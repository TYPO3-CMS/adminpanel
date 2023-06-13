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

namespace TYPO3\CMS\Adminpanel\Utility;

use TYPO3\CMS\Adminpanel\ModuleApi\ModuleInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\ResourceProviderInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\SubmoduleProviderInterface;
use TYPO3\CMS\Core\Utility\PathUtility;

class ResourceUtility
{
    /**
     * Get additional resources (css, js) from modules and merge it to
     * one array - returns an array of full html tags
     *
     * @param ModuleInterface[] $modules
     * @return array{js: string, css: string}
     */
    public static function getAdditionalResourcesForModules(array $modules): array
    {
        $result = [
            'js' => '',
            'css' => '',
        ];
        foreach ($modules as $module) {
            if ($module instanceof ResourceProviderInterface) {
                foreach ($module->getJavaScriptFiles() as $file) {
                    $result['js'] .= static::getJsTag($file);
                }
                foreach ($module->getCssFiles() as $file) {
                    $result['css'] .= static::getCssTag($file);
                }
            }
            if ($module instanceof SubmoduleProviderInterface) {
                $subResult = self::getAdditionalResourcesForModules($module->getSubModules());
                $result['js'] .= $subResult['js'];
                $result['css'] .= $subResult['css'];
            }
        }
        return $result;
    }

    /**
     * Get a css tag for file - with absolute web path resolving
     */
    protected static function getCssTag(string $cssFileLocation): string
    {
        $css = '<link rel="stylesheet" href="' .
               htmlspecialchars(
                   PathUtility::getPublicResourceWebPath($cssFileLocation),
                   ENT_QUOTES | ENT_HTML5
               ) .
               '" media="all" />';
        return $css;
    }

    /**
     * Get a script tag for JavaScript with absolute paths
     */
    protected static function getJsTag(string $jsFileLocation): string
    {
        $js = '<script src="' .
              htmlspecialchars(
                  PathUtility::getPublicResourceWebPath($jsFileLocation),
                  ENT_QUOTES | ENT_HTML5
              ) .
              '"></script>';
        return $js;
    }

    /**
     * Return a string with tags for main admin panel resources
     */
    public static function getResources(): array
    {
        $jsFileLocation = 'EXT:adminpanel/Resources/Public/JavaScript/admin-panel.js';
        $js = self::getJsTag($jsFileLocation);
        $cssFileLocation = 'EXT:adminpanel/Resources/Public/Css/adminpanel.css';
        $css = self::getCssTag($cssFileLocation);

        return [
            'css' => $css,
            'js' => $js,
        ];
    }
}
