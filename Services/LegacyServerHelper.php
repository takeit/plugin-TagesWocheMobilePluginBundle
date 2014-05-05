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
use Symfony\Component\DependencyInjection\Container;
use Buzz\Browser;

class LegacyServerHelper
{
    const URL_LEGACY_SERVER = 'http://ausgehen.tageswoche.ch/api';

    /**
     * @var array
     */
    private $config = array('authorization' => false);

    /**
     * @var Symfony\Component\DependencyInjection\Container
     */
    private $container;

    /**
     * Initialize service data
     *
     * @param array $config
     * @param Symfony\Component\DependencyInjection\Container $container
     */
    public function __construct($config = array(), $container)
    {
        $this->config = array_merge($this->config, $config);
        $this->container = $container;
    }

    /**
     * Passes through a request to the legacy solution
     *
     * @param  Request $request
     *
     * @return mixed
     */
    public function passthroughRequest(Request $request, $urlSuffix) {

        $parameters = $request->query->all();
        $headers = array();

        try {
            if ($this->config['authorization']) {
                $headers = array(
                    'Authorization' => 'Basic '.base64_encode($this->config['credentials'])
                );
            }

            $buzz = new Browser();
            $legacyUrl = self::URL_LEGACY_SERVER . $urlSuffix .'?'. http_build_query($parameters);
            $legacyResponse = $buzz->get($legacyUrl, $headers);

        } catch (\Exception $e) {
            return $this->container->get('newscoop_tageswochemobile_plugin.api_helper')->sendError('Request to legacy server failed.', 500);
        }

        $response = new JsonResponse(json_decode($legacyResponse->getContent(), true), $legacyResponse->getStatusCode());
        $response->headers->set('Content-type', $legacyResponse->getHeader('Content-Type'));

        return $response;
    }
}
