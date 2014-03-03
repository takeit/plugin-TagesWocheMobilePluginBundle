<?php
/**
 * @package   Newscoop\TagesWocheMobilePluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 */
class LunchgateController extends Controller
{
    const LEGACY_URL = '/lunchgate';

    /**
     * @Route("/list/")
     */
    public function indexAction(Request $request) {

        $legacyHelper = $this->container->get('newscoop_tageswochemobile_plugin.legacy_request');

        return $legacyHelper->passthroughRequest($request, self::LEGACY_URL);
    }
}
