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
     * @Route("/articles")
     */
    public function articlesAction(Request $request)
    {
        $offlineService = $this->container->get('newscoop_tageswochemobile_plugin.mobile.issue.offline');
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        if (!$apiHelperService->isSubscriber()) {
            $apiHelperService->sendError('Unathorized', 401); 
        }

        if (!$request->query->get('id') || !is_numeric($request->query->get('id'))) {
            return $apiHelperService->sendError(self::NOT_FOUND, self::NOT_FOUND_CODE);
        }

        $this->sendZip($offlineService->getArticleZipPath($request->query->get('id'), $apiHelperService->getClient()));
    }

    /**
     * @Route("/issues")
     */
    public function issuesAction(Request $request)
    {
        $offlineService = $this->container->get('newscoop_tageswochemobile_plugin.mobile.issue.offline');
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        if (!$apiHelperService->isSubscriber()) {
            $apiHelperService->sendError('Unathorized', 401); 
        }

        $issue = $this->container->get('newscoop_tageswochemobile_plugin.mobile.issue')->find($request->query->get('id'));
        if (!$issue) {
            return $apiHelperService->sendError(self::NOT_FOUND, self::NOT_FOUND_CODE);
        }

        $this->sendZip($offlineService->getIssueZipPath($issue, $apiHelperService->getClient()));
    }

    /**
     * Send zip file to browser
     *
     * @param string $zip
     * @return void
     */
    private function sendZip($zip)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        if (!file_exists($zip)) {
            return $apiHelperService->sendError(self::NOT_FOUND, self::NOT_FOUND_CODE);
        }

        $response = new Response(file_get_contents($zip));

        $response->headers->set('Content-Type', 'application/zip', true);
        $response->headers->set('Content-Disposition', sprintf('attachment; filename=%s', basename($zip)));
        $response->headers->set('Content-Length', filesize($zip));

         return $response;
    }
}
