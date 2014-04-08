<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Newscoop\Entity\Article;

use Newscoop\TagesWocheMobilePluginBundle\Mobile\OfflineIssueService;

/**
 * Route("/offline")
 */
class OfflineController extends Controller
{
    const NOT_FOUND = 'Not found';
    const NOT_FOUND_CODE = 404;

    /**
     * @var Tageswoche\Mobile\OfflineIssueService
     */
    private $service;

    /**
     * @Route("/articles/{id}", requirements={"id" = "\d+"})
     */
    public function articlesAction($id, Request $request)
    {
        $offlineService = $this->container->get('newscoop_tageswochemobile_plugin.mobile.issue.offline');
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $isSubscriber = $apiHelperService->isSubscriber();
        if (!$isSubscriber || ($isSubscriber instanceof JSONResponse)) {
            if ($isSubscriber instanceof JSONResponse) {
                return $isSubscriber;
            } else {
                return $apiHelperService->sendError('Unauthorized', 401);
            }
        }

        if (!$id) {
            return $apiHelperService->sendError(self::NOT_FOUND, self::NOT_FOUND_CODE);
        }

        $zip = $offlineService->getArticleZipPath($id, $apiHelperService->getClient());

        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        if (!file_exists($zip)) {
            return $apiHelperService->sendError(self::NOT_FOUND, self::NOT_FOUND_CODE);
        }

        $response = new Response();

        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'application/zip', true);
        $response->headers->set('Content-Disposition', sprintf('attachment; filename=%s', basename($zip)));
        $response->headers->set('Content-Length', filesize($zip));
        $response->sendHeaders();
        $response->setContent(readfile($zip));

        return $response;
    }

    /**
     * @Route("/issues/{id}", requirements={"id" = "\d+"})
     */
    public function issuesAction($id, Request $request)
    {
        $offlineService = $this->container->get('newscoop_tageswochemobile_plugin.mobile.issue.offline');
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $isSubscriber = $apiHelperService->isSubscriber();
        if (!$isSubscriber || ($isSubscriber instanceof JSONResponse)) {
            if ($isSubscriber instanceof JSONResponse) {
                return $isSubscriber;
            } else {
                return $apiHelperService->sendError('Unauthorized', 401);
            }
        }

        $issue = $this->container->get('newscoop_tageswochemobile_plugin.mobile.issue')->find($id);
        if (!$issue) {
            return $apiHelperService->sendError(self::NOT_FOUND, self::NOT_FOUND_CODE);
        }

        $zip = $offlineService->getIssueZipPath($issue, $apiHelperService->getClient());

        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        if (!file_exists($zip)) {
            return $apiHelperService->sendError(self::NOT_FOUND, self::NOT_FOUND_CODE);
        }

        $response = new Response();

        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'application/zip', true);
        $response->headers->set('Content-Disposition', sprintf('attachment; filename=%s', basename($zip)));
        $response->headers->set('Content-Length', filesize($zip));
        $response->sendHeaders();
        $response->setContent(readfile($zip));

        return $response;
    }

}
