<?php
/**
 * @package Newscoop
 * @copyright 2013 Sourcefabric o.p.s.
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
 * Route('/wheel-advertisement')
 *
 * Wheel Advertisement API service controller
 */
class WheelAdvertisementController extends Controller
{
    const AD_TYPE = 'iPad_Ad';
    const AD_SECTION = 'ad_name';

    const IMAGE_STANDARD_RENDITION = 'rubrikenseite';

    const AD_STANDARD_WIDTH = 320;
    const AD_IPHONE5_WIDTH = 640;
    const AD_IPHONE5_HEIGHT = 906;

    const AD_STANDARD_HEIGHT = 365;
    const AD_PORTRAIT_HEIGHT = 912;
    const AD_LANDSCAPE_HEIGHT = 655;

    const IMAGE_RETINA_FACTOR = 2;

    protected $client;

    /** @var Zend_Controller_Request_Http */
    private $request;

    /** @var array */
    private $response = array();

    /**
     * @Route("/")
     * @Route("/index")
     * @Route("/list")
     */
    public function listAction()
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $response = array();

        $wheelAds = $this->getWheelAds();
        foreach ($wheelAds as $article) {
            $rank = $this->getRank($article);
            $response[] = array_merge($apiHelperService->formatArticle($article), array(
                'rank' => $rank,
                'iphone_image_url' => $this->getWheelAdImageUrl($article, 'iphone'),
                'iphone5_image_url' => $this->getWheelAdImageUrl($article, 'iphone5'),
                'ipad_portrait_image_url' => $this->getWheelAdImageUrl($article, 'ipad_portrait'),
                'ipad_landscape_image_url' => $this->getWheelAdImageUrl($article, 'ipad_landscape'),
            ));

        }

        return new JsonResponse($response);
    }

    /**
     * Get ad rank
     *
     * @param mixed $article
     * @return int
     */
    private function getRank($article)
    {
       if ($article->getData('ad_left')) {
           return 0;
       }
       if ($article->getData('ad_right')) {
           return 1;
       }

       return null;
    }

    /**
     * Get article image url
     *
     * @param mixed $article
     * @param string $device
     * @return string
     */
    private function getWheelAdImageUrl($article, $device)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $baseUrl = $this->getRequest()->getSchemeAndHttpHost();

        $images = $this->container->get('image')->findByArticle($article->getNumber());
        foreach ($images as $image) {
            if ($image->getWidth() == $this->getAdWidth($device) && $image->getHeight() == $this->getAdHeight($device)) {
                return $baseUrl . '/' .$image->getPath();
            }
            // if no standard image exists, check for larger retina image
            if ($image->getWidth() == ($this->getAdWidth($device) * self::IMAGE_RETINA_FACTOR) &&
                $image->getHeight() == ($this->getAdHeight($device) * self::IMAGE_RETINA_FACTOR)) {
                return $baseUrl . '/' .$image->getPath();
            }
            // if this is a retina client and no retina width images were found, take the normal instead
            if ($apiHelperService->isRetinaClient()) {
                if ($image->getWidth() == self::AD_STANDARD_WIDTH && $image->getHeight() == $this->getAdHeight($device, true)) {
                    return $baseUrl . '/' .$image->getPath();
                }
            }
        }

        return null;
    }

    /**
     * Get ad width
     *
     * @param string device
     * @return int
     */
    private function getAdWidth($device)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        if ($device == 'iphone5') {
            return self::AD_IPHONE5_WIDTH;
        } else {
            if ($apiHelperService->isRetinaClient()) {
                return self::AD_STANDARD_WIDTH * self::IMAGE_RETINA_FACTOR;
            }
            return self::AD_STANDARD_WIDTH;
        }
    }

    /**
     * Get ad height
     *
     * @param string $device
     * @param boolean $standard
     * @return int
     */
    private function getAdHeight($device, $standard = false)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $height = null;

        switch($device) {
            case 'iphone5':
                return self::AD_IPHONE5_HEIGHT;
                break;
            case 'iphone':
                $height = self::AD_STANDARD_HEIGHT;
                break;
            case 'ipad_portrait':
                $height = self::AD_PORTRAIT_HEIGHT;
                break;
            case 'ipad_landscape':
                $height =  self::AD_LANDSCAPE_HEIGHT;
                break;
        }

        if (!$standard) {
            if ($apiHelperService->isRetinaClient()) {
                return $height * self::IMAGE_RETINA_FACTOR;
            }
        }

        return $height;
    }

    /**
     * Get list of ads (articleType == iPad_Ad)
     *
     * @param string switch
     * @return array
     */
    protected function getWheelAds()
    {
        $em = $this->container->get('em');
        $wheelAds = array();

        $ads = $em->getRepository('Newscoop\Entity\Article')
            ->findBy(array('type' => self::AD_TYPE), array('articleOrder' => 'asc'));

        foreach ($ads as $ad) {
            if ($ad->getData('active')) {
                if ($ad->getData('ad_left')) {
                    $wheelAds[] = $ad;
                }
                if ($ad->getData('ad_right')) {
                    $wheelAds[] = $ad;
                }
            }
        }

        return $wheelAds;
    }
}
