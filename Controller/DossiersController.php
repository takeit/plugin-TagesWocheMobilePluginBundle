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

use DateTime;
use Newscoop\Entity\Article;
use Newscoop\Entity\Topic;

/**
 * Route('/dossiers')
 */
class DossiersController extends Controller
{
    const ARTICLE_TYPE = 'dossier';
    const IMAGE_STANDARD_WIDTH = 320;
    const IMAGE_STANDARD_HEIGHT = 140;
    const IMAGE_RETINA_WIDTH = 640;
    const IMAGE_RETINA_HEIGHT = 280;

    /** @var array */
    private $adRanks = array(4, 9, 15);

    /**
     * @Route("/index")
     * @Route("/list")
     *
     * @return json
     */
    public function listAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $em = $this->container->get('em');

        $this->params = $request->query->all();
        $response = array();

        $active = $apiHelperService->_getParam('active');
        $showAll = (is_null($active) || $active == 'true')
            ? false
            : true;

        if (!$showAll) {
            $response = $this->processItems($this->getFeaturedDossiers(), 'article');
        } else {
            $topics = array();
            $dossierIds = $this->container->get('article.topic')->getDossiers();
            foreach($dossierIds as $dossierId) {
                $topics[] = $em->getRepository('Newscoop\Entity\Topic')
                    ->findOneBy(array(
                        'topic' => $dossierId,
                    ));
            }

            $response = $this->processItems($topics, 'topic');
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/articles")
     *
     * @return json
     */
    public function articlesAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $em = $this->container->get('em');

        $this->params = $request->query->all();

        // get the topic id
        $dossierId = isset($this->params['dossier_id'])
            ? (int) $this->params['dossier_id']
            : $this->getCurrentDossierId();

        $response = array();
        $featured = $this->getFeaturedDossiers();
        $dossierArticle = $featured[$dossierId];
        $articleIds = array();
        $listAds = $this->getArticleListAds('blogs_dossiers');
        $ad = 0;
        $rank = 1;

        // look for an article of type dossier, if exists fetch related articles
        if (array_key_exists($dossierId, $featured)) {
            $contextBox = new ContextBox(null, $dossierArticle->getNumber());
            $articleIds = $contextBox->getArticlesList() ?: array();

            foreach ($articleIds as $articleId) {
                // inject ad
                if (in_array($rank, $this->adRanks)) {
                    if (!empty($listAds[$ad])) {
                        $response[] = $apiHelperService->formatArticle($listAds[$ad]);
                        $ad++;
                    }
                }

                $article = $em->getRepository('Newscoop\Entity\Article')->findOneByNumber($articleId);
                if (!empty($article) && $article->isPublished()) {
                    $response[] = $apiHelperService->formatArticle($article);
                    $rank++;
                }
            }
        }

        // get all articles with the current topic
        $topic = $em->getRepository('Newscoop\Entity\Topic')
            ->findOneBy(array(
                'topic' => $dossierId,
            ));
        $topicArticles = $em->getRepository('Newscoop\Entity\Article')->findByTopic($topic, $limit = null);
        foreach ($topicArticles as $article) {
            // inject ad
            if (in_array($rank, $this->adRanks)) {
                if (!empty($listAds[$ad])) {
                    $response[] = $apiHelperService->formatArticle($listAds[$ad]);
                    $ad++;
                }
            }

            if ($article->isPublished() && $article->getType() !== self::ARTICLE_TYPE && !in_array($article->getNumber(), $articleIds)) {
                $response[] = $apiHelperService->formatArticle($article);
                $rank++;
            }
        }

        return new JsonResponse($response);
    }

    /**
     * Process the dossier items depending on type.
     *
     * @param array $items
     * @param string $type
     * @return array
     */
    private function processItems(array $items, $type = 'article')
    {
        $rank = 1;
        $response = array();
        foreach ($items as $item) {
            $response[] = $type == 'topic'
                ? array_merge($this->formatTopicDossier($item), array('rank' => (int) $rank++))
                : array_merge($this->formatArticleDossier($item), array('rank' => (int) $rank++));
        }

        return $response;
    }

    /**
     * Format an article as dossier object.
     *
     * @param Newscoop\Entity\Article $article
     * @return array
     */
    private function formatArticleDossier(Article $article)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        //$date = new DateTime($article->getPublishDate());
        $topics = $article->getTopics();
        return array(
            'dossier_id' => $topics[0]->getTopicId(),
            'url' => $apiHelperService->serverUrl($this->container->get('zend_router')->assemble(array(
                'module' => 'api',
                'controller' => 'dossiers',
                'action' => 'articles',
            ), 'default') . $apiHelperService->getApiQueryString(array('dossier_id' => $topics[0]->getTopicId()))),
            'title' => trim($article->getTitle()),
            'description' => trim(strip_tags($article->getData('lede'))),
            'image_url' => $apiHelperService->getImageUrl($article),
            'website_url' => $apiHelperService->getWebsiteUrl($article),
            'published' => $apiHelperService->formatDate($article->getPublishDate()),
        );
    }

    /**
     * Format a topic as dossier object.
     *
     * @param Newscoop\Entity\Topic $topic
     * @return array
     */
    private function formatTopicDossier(Topic $topic)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        return array(
            'dossier_id' => $topic->getTopicId(),
            'url' => $apiHelperService->serverUrl($this->container->get('zend_router')->assemble(array(
                'module' => 'api',
                'controller' => 'dossiers',
                'action' => 'articles',
            ), 'default') . $apiHelperService->getApiQueryString(array('dossier_id' => $topic->getTopicId()))),
            'title' => trim($topic->getName()),
            'description' => null,
            'image_url' => null,
            'published' => null,
        );
    }

    /**
     * Return identifier for the current dossier (first item in the playlist Dossiers).
     *
     * @return int
     */
    private function getCurrentDossierId()
    {
        $em = $this->container->get('em');

        $dossiersPlaylist = $em->getRepository('Newscoop\Entity\Playlist')
            ->findOneBy(array('name' => 'Dossiers'));
        $dossierArticles = $dossiersPlaylist->getArticles();
        $dossierTopics = $dossierArticles[0]->getArticle()->getTopics();
        return (int) $dossierTopics[0]->getTopicId();
    }

    /**
     * Get the list of articles of type dossier included in the Dossiers playlist.
     *
     * @return array Array of Newscoop\Entity\Article objects
     */
    private function getFeaturedDossiers()
    {
        $em = $this->container->get('em');

        $playlist = $em->getRepository('Newscoop\Entity\Playlist')
            ->findOneBy(array('name' => 'Dossiers'));
        $playlistArticles = $em->getRepository('Newscoop\Entity\Playlist')
            ->articles($playlist, null, true);

        $dossiers = array();
        foreach($playlistArticles as $playlistArticle) {
            if ($playlistArticle->getArticle()->isPublished()) {
                $article = $playlistArticle->getArticle();
                $topics = $article->getTopics();
                $dossiers[$topics[0]->getTopicId()] = $article;
            }
        }

        return $dossiers;
     }

    /**
     * Get image width
     *
     * @return int
     */
    private function getImageWidth()
    {
        return $this->isRetinaClient() ? self::IMAGE_RETINA_WIDTH : self::IMAGE_STANDARD_WIDTH;
    }

    /**
     * Get image height
     *
     * @return int
     */
    private function getImageHeight()
    {
        return $this->isRetinaClient() ? self::IMAGE_RETINA_HEIGHT : self::IMAGE_STANDARD_HEIGHT;
    }
}
