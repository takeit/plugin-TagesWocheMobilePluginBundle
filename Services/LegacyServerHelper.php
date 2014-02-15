<?php
/**
 * @package   Newscoop\TagesWocheMobilePluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Services;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Buzz\Browser;

class LegacyServerHelper
{
    const URL_LEGACY_SERVER = 'http://agenda.tageswoche.ch/api/cinema';

    /**
     * Passes through a request to the legacy solution
     *
     * @param  Request $request
     *
     * @return mixed            JsonResponse
     */
    public function passthroughRequest(Request $request) {

        $parameters = $request->query->all();

        try {
            $buzz = new Browser();
            $legacyResponse = $buzz->get(self::URL_LEGACY_SERVER .'?'. http_build_query($parameters));
        } catch (\Exception $e) {
            return $this->container->get('newscoop_tageswochemobile_plugin.api_helper')->sentError('Request to legacy server failed.', 500);
        }

        $response = new JsonResponse(json_decode($legacyResponse->getContent(), true), $legacyResponse->getStatusCode());
        $response->headers->set('Content-type', $legacyResponse->getHeader('Content-Type'));

        return $response;
    }
}
