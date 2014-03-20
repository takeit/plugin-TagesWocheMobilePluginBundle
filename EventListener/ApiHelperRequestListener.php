<?php
/**
 * @package Newscoop\TagesWocheMobilePluginBundle
 * @author Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\EventListener;

use Newscoop\TagesWocheMobilePluginBundle\Services\ApiHelper;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;

class ApiHelperRequestListener
{
    /**
     * Mobile API helper service
     *
     * @var Newscoop\TagesWocheMobilePluginBundle\Services\ApiHelper
     */
    protected $apiHelperService;

    /**
     * Contruct ApiHelper object
     *
     * @param Newscoop\TagesWocheMobilePluginBundle\Services\ApiHelper $apiHelperService
     */
    public function __construct(ApiHelper $apiHelperService)
    {
        $this->apiHelperService = $apiHelperService;
    }

    /**
     * Request event handler
     *
     * @param  Symfony\Component\HttpKernel\Event\GetResponseEvent $event
     */
    public function onRequest(GetResponseEvent $event)
    {
        if (HttpKernel::MASTER_REQUEST != $event->getRequestType()) {
            // don't do anything if it's not the master request
            return;
        }

        $this->apiHelperService->setRequest($event->getRequest());
    }
}
