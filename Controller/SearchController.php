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
use Newscoop\TagesWocheMobilePluginBundle\Controller\OmnitickerController AS MobileOmnitickerController;

class SearchController extends MobileOmnitickerController
{
    const Q_PARAM = 'query_string';

    /**
     * @Route("/")
     */
    public function omnitickerAction(Request $request)
    {
        if (!$request->query->get(self::Q_PARAM)) {
            $apiHelper = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
            $apiHelper->sendError("No 'query_string' provided", 500);
        }

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
        $queryParam = trim($parameters[self::Q_PARAM]);

        if ($this->container->get('webcode')->findArticleByWebcode($queryParam) !== null) {
            $queryParam = "webcode:$queryParam";
        }

        $dateParam = $this->buildSolrDateParam($parameters);
        return array_merge(parent::encodeParameters($parameters), array(
            'q' => $queryParam,
            'fq' => '-(section:swissinfo OR type:tweet OR type:event OR type:comment)' . ($dateParam ? " AND $dateParam" : ''),
            'sort' => null,
        ));
    }
}
