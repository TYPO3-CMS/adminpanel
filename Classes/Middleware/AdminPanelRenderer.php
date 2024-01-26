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

namespace TYPO3\CMS\Adminpanel\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Adminpanel\Controller\MainController;
use TYPO3\CMS\Adminpanel\Utility\StateUtility;
use TYPO3\CMS\Core\Http\Stream;

/**
 * Render the admin panel via PSR-15 middleware
 *
 * @internal
 */
readonly class AdminPanelRenderer implements MiddlewareInterface
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    /**
     * Render the admin panel if activated
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $typoScriptConfig = $request->getAttribute('frontend.typoscript')->getConfigArray();
        if (StateUtility::isActivatedForUser()
            && ($typoScriptConfig['admPanel'] ?? false)
            && !StateUtility::isHiddenForUser()
        ) {
            $body = $response->getBody();
            $body->rewind();
            $contents = $response->getBody()->getContents();
            $content = str_ireplace(
                '</body>',
                $this->container->get(MainController::class)->render($request) . '</body>',
                $contents
            );
            $body = new Stream('php://temp', 'rw');
            $body->write($content);
            $response = $response->withBody($body);
        }
        return $response;
    }
}
