<?php
/**
 * @package   Newscoop\TagesWocheMobilePluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Newscoop\SolrSearchPluginBundle\Controller\OmnitickerController AS SolrOmnitickerController;

class SearchController extends SolrOmnitickerController
{
    const Q_PARAM = 'query_string';

    /**
     * Just keeping calls to parent function unchanged
     */
    public function omnitickerAction(Request $request)
    {
        $this->request = $request;
        return parent::omnitickerAction($request, null);
    }

    /**
     * @Route("/api/search")
     */
    public function searchAction(Request $request)
    {
        if (!$request->query->get(self::Q_PARAM)) {
            $apiHelper = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
            return $apiHelper->sendError("No 'query_string' provided", 500);
        }

        // Convert the query_string to q, parent controller is using that var
        $request->query->set('q', $request->query->get(self::Q_PARAM));
        $request->query->set('format', 'json');

        $this->request = $request;
        return parent::omnitickerAction($request, null);
    }

    /**
     * Build solr params
     *
     * @return array
     */
    protected function encodeParameters($parameters)
    {
        $queryService = $this->container->get('newscoop_solrsearch_plugin.query_service');
        $queryParam = trim($parameters[self::Q_PARAM]);

        if (substr($queryParam, 0, 1) === '+' && $this->container->get('webcode')->findArticleByWebcode(substr($queryParam, 1)) !== null) {
            $queryParam = "webcode:$queryParam";
        }

        $dateParam = $queryService->buildSolrDateParam($parameters);
        return array_merge(parent::encodeParameters($parameters), array(
            'q' => $queryParam,
            'fq' => '-(section:swissinfo OR type:tweet OR type:event OR type:comment)' . ($dateParam ? " AND $dateParam" : ''),
            'sort' => null,
        ));
    }
}
