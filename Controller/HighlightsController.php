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

/**
 * Route('/highlights')
 */
class HighlightsController extends Controller
{
    const LANGUAGE = 5;
    const PUBLICATION = 1;

    const IMAGE_TOP_RENDITION = 'topfront';
    const IMAGE_STANDARD_RENDITION = 'rubrikenseite';
    const IMAGE_TOP_WIDTH = 320;
    const IMAGE_TOP_HEIGHT = 140;
    const IMAGE_STANDARD_WIDTH = 105;
    const IMAGE_STANDARD_HEIGHT = 70;
    const IMAGE_RETINA_FACTOR = 2;

    /** @var Zend_Controller_Request_Http */
    private $request;

    /** @var array */
    private $response = array();

    /** @var array */
    private $adRanks = array(3, 5, 8);

    /**
     * Return list of articles.
     * @Route("/")
     * @Route("/index")
     * @Route("/list")
     *
     * @return json
     */
    public function listAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $em = $this->container->get('em');

        $response = array();
        $params = $request->query->all();
        $this->request = $request;
        $this->initClient($params['client']);

        if (isset($params['section_id'])) {
            $sectionIds = array((int) $params['section_id']);
        } else {
            $sectionIds = array(6, 7, 8, 9, 10, 11, 25); // @todo config
        }

        $listAds = $apiHelperService->getArticleListAds('newshighlight');
        $sectionRank = 1;
        $articlesInResponse = array();
        $ad = 0;

        foreach ($sectionIds as $sectionId) {
            $limit = 3;

            if ($sectionId == 6) {
                $limit = 5;
            }

            $playlistRepository = $em->getRepository('Newscoop\Entity\Playlist');
            $playlist = $playlistRepository->findOneBy(array('id' => $sectionId));
            if ($playlist) {

                $articleArray = $playlistRepository->articles($playlist, null, false, $limit, null, true, $articlesInResponse);
                $rank = 1;
                foreach ($articleArray as $articleItem) {
                    // inject newshighlight ad
                    if (($sectionRank == 1 && $rank == 3) ||
                        ($sectionRank == 3 && $rank == 2) ||
                        ($sectionRank == 5 && $rank == 2)) {
                        if ((!empty($listAds[$ad])) && ($apiHelperService->getAdImageUrl($listAds[$ad]))) {
                            $this->response[] = array_merge($apiHelperService->formatArticle($listAds[$ad]), array(
                                'rank' => (int) $rank++,
                                'section_id' => (int) $sectionId,
                                'section_name' => $playlist->getName(),
                                'section_rank' => $sectionRank));
                            $ad++;
                        }
                    }

                    if (!in_array($articleItem['articleId'], $articlesInResponse)){
                        $articles = $em->getRepository('Newscoop\Entity\Article')->findBy(array('number' => $articleItem['articleId']));
                        $article = $articles[0];
                        if (!$article->isPublished()) {
                            continue;
                        }

                        // gets the article image in the proper size
                        if ($sectionId == 6 && $rank == 1) {
                            $normalSize = array(self::IMAGE_TOP_WIDTH, self::IMAGE_TOP_HEIGHT);
                            $retinaSize = array(self::IMAGE_TOP_WIDTH * self::IMAGE_RETINA_FACTOR, self::IMAGE_TOP_HEIGHT * self::IMAGE_RETINA_FACTOR);
                            $image = $apiHelperService->getRenditionUrl($article, self::IMAGE_TOP_RENDITION, $normalSize, $retinaSize);
                        } else {
                            $normalSize = array(self::IMAGE_STANDARD_WIDTH, self::IMAGE_STANDARD_HEIGHT);
                            $retinaSize = array(self::IMAGE_STANDARD_WIDTH * self::IMAGE_RETINA_FACTOR, self::IMAGE_STANDARD_HEIGHT * self::IMAGE_RETINA_FACTOR);
                            $image = $apiHelperService->getRenditionUrl($article, self::IMAGE_STANDARD_RENDITION, $normalSize, $retinaSize);
                        }

                        $response = array_merge($apiHelperService->formatArticle($article), array(
                            'image_url' => $image,
                            'rank' => $rank++,
                            'section_id' => (int) $sectionId,
                            'section_name' => $playlist->getName(),
                            'section_url' => $this->getSectionUrl($sectionId),
                            'section_rank' => $sectionRank,
                        ));

                        // Hack for WOBS-2783
                        if (array_key_exists('dateline', $response) && $article->getType() == 'link') {
                            $response['dateline'] = 'Linkempfehlung';
                        }

                        $articlesInResponse[] = (int) $article->getNumber();
                        $this->response[] = $response;
                    }
                }
                $sectionRank++;
            }
        }

        return new JsonResponse($this->response);
    }

    private function lookforImageUrl(Article $article, $rendition, $width, $height)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $image = $this->lookforImage($article, $rendition);
        if (empty($image)) {
            return null;
        }

        $imageUrl = $$this->container->get('zend_router')->assemble(array(
            'src' => $this->container->get('image')->getSrc(basename($image->src), $width, $height, 'crop'),
        ), 'image', false, false);

        return $apiHelperService->serverUrl($imageUrl);
    }

    /**
     * Return image url
     *
     * @param Article $article
     * @return string $thumbnail
     */
    private function lookforImage(Article $article, $rendition)
    {
        $renditions = $this->container->get('image.rendition')->getRenditions();
        if (!array_key_exists($rendition, $renditions)) {
            return null;
        }

        $articleRenditions = $this->container->get('image.rendition')->getArticleRenditions($article->getId());
        $articleRendition = $articleRenditions[$renditions[$rendition]];

        if ($articleRendition === null) {
            return null;
        }

        $thumbnail = $articleRendition->getRendition()->
            getThumbnail($articleRendition->getImage(), $this->container->get('image'));

        return $thumbnail;
    }

    /**
     * Get article comments api url
     *
     * @param int $section Section (playlist) identifier
     * @return string
     */
    protected function getSectionUrl($section)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        return  $apiHelperService->serverUrl(
            $this->container->get('zend_router')->assemble(array(
                'module' => 'api',
                'controller' => 'articles',
                'action' => 'list',
                ), 'default') . $apiHelperService->getApiQueryString(array(
                'section_id' => $section,
        )));
    }

    protected function initClient($client)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $apiHelperService->initClient($client);

        $this->client = array_merge($apiHelperService->client, array(
            'image_width' => self::IMAGE_STANDARD_WIDTH,
            'image_height' => self::IMAGE_STANDARD_HEIGHT,
        ));

        if ($apiHelperService->isRetinaClient()) {
            $this->client['image_width'] = $this->client['image_width'] * self::IMAGE_RETINA_FACTOR;
            $this->client['image_height'] = $this->client['image_height'] * self::IMAGE_RETINA_FACTOR;
        }
    }
}
