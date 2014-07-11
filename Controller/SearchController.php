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
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Newscoop\TagesWocheMobilePluginBundle\Controller\OmnitickerController AS MobileOmnitickerController;

class SearchController extends MobileOmnitickerController
{
    const Q_PARAM = 'query_string';

    /**
     * @Route("/api/search/")
     */
    public function searchMobileAction(Request $request)
    {
        if (!$request->query->get(self::Q_PARAM)) {
            $apiHelper = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
            return $apiHelper->sendError("No 'query_string' provided", 500);
        }

        // Convert the query_string to q, parent controller is using that var
        $request->query->set('q', $request->query->get(self::Q_PARAM));
        $request->query->set('format', 'json');

        // Convert app data parameters to support date parameters by QueryService
        if ($request->query->get('start_date') && $request->query->get('end_date')) {
            $request->query->set('date', $request->query->get('start_date').','.$request->query->get('end_date'));
        } elseif ($request->query->get('start_date')) {
            $request->query->set('date', $request->query->get('start_date').',');
        } elseif ($request->query->get('end_date')) {
            $request->query->set('date', ','.$request->query->get('end_date'));
        }

        $this->request = $request;
        return parent::omnitickerMobileAction($request);
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
