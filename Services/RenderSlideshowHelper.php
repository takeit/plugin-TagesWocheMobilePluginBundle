<?php

namespace Newscoop\TagesWocheMobilePluginBundle\Services;

use Datetime;
use DateTimeZone;
use Doctrine\ORM\EntityManager;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Newscoop\Webcode\Manager;

class RenderSlideshowHelper
{
    const CLIENT_DEFAULT = 'iphone';

    /**
     * @var string
     */
    private $client;

    /**
     * @var Zend_View_Abstract
     */
    private $view;

    /**
     * @var array
     */
    private $maxSize = array(
        'ipad' => 1024,
        'ipad_retina' => 2048,
        'iphone' => 480,
        'iphone_retina' => 960,
    );

    /**
     * Initialize service
     */
    public function __construct(EntityManager $em, Container $container)
    {
        $this->em = $em;
        $this->container = $container;
        $this->api_helper = $this->container
            ->get('newscoop_tageswochemobile_plugin.api_helper');
        $this->request = $this->container->get('request');

        $this->client = strtolower($this->request->get('client', self::CLIENT_DEFAULT));
        if (!isset($this->maxSize[$this->client])) {
            $this->client = self::CLIENT_DEFAULT;
        }
    }

    /**
     * Render slideshow for given article number
     *
     * @param int $articleNo
     * @return array
     */
    public function direct($articleNo)
    {
        if (empty($articleNo)) {
            return array();
        }

        $items = array();

        foreach ($this->findSlideshows($articleNo) as $slideshow) {
            foreach ($slideshow->getItems() as $item) {
                if ($item->isImage()) {
                    $items[] = array(
                        'type' => 'image',
                        'url' => $this->api_helper->getImageUrl($item, $this->maxSize[$this->client]),
                        'caption' => $item->getCaption() ?: ($item->getImage()->getCaption() ?: null),
                        'image_credits' => $item->getImage()->getPhotographer() ?: null,
                    );
                } else {
                    $items[] = array(
                        'type' => 'video',
                        'url' => $this->view->serverUrl() . $this->view->url(array(
                                'controller' => 'video',
                                'action' => 'player',
                                'module' => 'default',
                            ), 'default') .'?'. http_build_query(array('video' => $this->view->getVideoUrl($item) )),
                        'caption' => $item->getCaption(),
                        'image_credits' => null,
                    );
                }
            }

            break; // limit to 1 slideshow
        }

        return $items;
    }

    /**
     * Find article slideshows
     *
     * @param int $articleNo
     * @return array
     */
    private function findSlideshows($articleNo)
    {
        return $this->container->get('package')
            ->findByArticle($articleNo);
    }
}
