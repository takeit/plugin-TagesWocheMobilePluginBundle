<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Newscoop\Entity\Article;

/**
 *Route('/sections')
 *
 */
class SectionsController extends AbstractController
{
    const API_VERSION = 1;

    const IMAGE_STANDARD_WIDTH = 105;
    const IMAGE_STANDARD_HEIGHT = 70;
    const IMAGE_RETINA_FACTOR = 2;

    /** @var Zend_Controller_Request_Http */
    private $request;

    /**
     * @Route("/index")
     * @Route("/list")
     *
     * Serve list of sections.
     */
    public function listAction()
    {
        $params = $request->query->all();
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $apiHelperService->client = array_merge($apiHelperService->client, array(
            'image_width' => self::IMAGE_STANDARD_WIDTH,
            'image_height' => self::IMAGE_STANDARD_HEIGHT,
        ));

        if ($apiHelperService->isRetinaClient()) {
            $apiHelperService->client['image_width'] = $apiHelperService->client['image_width'] * self::IMAGE_RETINA_FACTOR;
            $apiHelperService->client['image_height'] = $apiHelperService->client['image_height'] * self::IMAGE_RETINA_FACTOR;
        }

        $sections = array(
            0 => array('name' => 'Front page',
                'url' => $this->url . '/api/articles/list?section_id=6&client=' . $apiHelperService->client['name'] . '&version=' . self::API_VERSION),
            1 => array('name' => 'Basel',
                'url' => $this->url . '/api/articles/list?section_id=7&client=' . $apiHelperService->client['name'] . '&version=' . self::API_VERSION),
            2 => array('name' => 'Schweiz',
                'url' => $this->url . '/api/articles/list?section_id=8&client=' . $apiHelperService->client['name'] . '&version=' . self::API_VERSION),
            3 => array('name' => 'International',
                'url' => $this->url . '/api/articles/list?section_id=9&client=' . $apiHelperService->client['name'] . '&version=' . self::API_VERSION),
            4 => array('name' => 'Sport',
                'url' => $this->url . '/api/articles/list?section_id=10&client=' . $apiHelperService->client['name'] . '&version=' . self::API_VERSION),
            5 => array('name' => 'Kultur',
                'url' => $this->url . '/api/articles/list?section_id=11&client=' . $apiHelperService->client['name'] . '&version=' . self::API_VERSION),
            6 => array('name' => 'Leben',
                'url' => $this->url . '/api/articles/list?section_id=25&client=' . $apiHelperService->client['name'] . '&version=' . self::API_VERSION),
        );

        return new JsonResponse($sections);
    }

}

