<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

use Newscoop\Entity\Article;
use Newscoop\Entity\Topic;

require_once __DIR__ . '/AbstractController.php';

/**
 * Dossiers API service controller.
 */
class Api_DossiersController extends AbstractController
{
    const ARTICLE_TYPE = 'dossier';
    const IMAGE_STANDARD_WIDTH = 320;
    const IMAGE_STANDARD_HEIGHT = 140;
    const IMAGE_RETINA_WIDTH = 640;
    const IMAGE_RETINA_HEIGHT = 280;

    /** @var array */
    private $adRanks = array(4, 9, 15);

    /**
     * Init controller.
     */
    public function init()
    {
        $this->_helper->layout->disableLayout();
        $this->params = $this->getRequest()->getParams();

        if (empty($this->params['client'])) {
            $this->sendError('Not client provided', 500);
        }
    }

    /**
     * Default action controller.
     */
    public function indexAction()
    {
        $this->_forward('list');
    }

    /**
     * Returns list of dossiers.
     *
     * @return json
     */
    public function listAction()
    {
        $response = array();
        $showAll = (!$this->_hasParam('active') || $this->_getParam('active') == 'true')
            ? false
            : true;

        if (!$showAll) {
            $response = $this->processItems($this->getFeaturedDossiers(), 'article');
        } else {
            $topics = array();
            $dossierIds = $this->_helper->service('article.topic')->getDossiers();
            foreach($dossierIds as $dossierId) {
                $topics[] = $this->_helper->entity->getRepository('Newscoop\Entity\Topic')
                    ->findOneBy(array(
                        'topic' => $dossierId,
                    ));
            }

            $response = $this->processItems($topics, 'topic');
        }

        $this->_helper->json($response);
    }

    /**
     * Returns list of articles in given dossier.
     *
     * @return json
     */
    public function articlesAction()
    {
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
                        $response[] = $this->formatArticle($listAds[$ad]);
                        $ad++;
                    }
                }

                $article = $this->_helper->service('article')->findOneByNumber($articleId);
                if (!empty($article) && $article->isPublished()) {
                    $response[] = $this->formatArticle($article);
                    $rank++;
                }
            }
        }

        // get all articles with the current topic
        $topic = $this->_helper->entity->getRepository('Newscoop\Entity\Topic')
            ->findOneBy(array(
                'topic' => $dossierId,
            ));
        $topicArticles = $this->_helper->service('article')->findByTopic($topic, $limit = null);
        foreach ($topicArticles as $article) {
            // inject ad
            if (in_array($rank, $this->adRanks)) {
                if (!empty($listAds[$ad])) {
                    $response[] = $this->formatArticle($listAds[$ad]);
                    $ad++;
                }
            }

            if ($article->isPublished() && $article->getType() !== self::ARTICLE_TYPE && !in_array($article->getNumber(), $articleIds)) {
                $response[] = $this->formatArticle($article);
                $rank++;
            }
        }

        $this->_helper->json($response);
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
        $date = new DateTime($article->getPublishDate());
        $topics = $article->getTopics();
        return array(
            'dossier_id' => $topics[0]->getTopicId(),
            'url' => $this->view->serverUrl($this->view->url(array(
                'module' => 'api',
                'controller' => 'dossiers',
                'action' => 'articles',
            ), 'default') . $this->getApiQueryString(array('dossier_id' => $topics[0]->getTopicId()))),
            'title' => trim($article->getTitle()),
            'description' => trim(strip_tags($article->getData('lede'))),
            'image_url' => $this->getDossierImageUrl($article),
            'website_url' => $this->getWebsiteUrl($article),
            'published' => $this->formatDate($date),
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
        return array(
            'dossier_id' => $topic->getTopicId(),
            'url' => $this->view->serverUrl($this->view->url(array(
                'module' => 'api',
                'controller' => 'dossiers',
                'action' => 'articles',
            ), 'default') . $this->getApiQueryString(array('dossier_id' => $topic->getTopicId()))),
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
        $dossiersPlaylist = $this->_helper->entity->getRepository('Newscoop\Entity\Playlist')
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
        $playlist = $this->_helper->entity->getRepository('Newscoop\Entity\Playlist')
            ->findOneBy(array('name' => 'Dossiers'));
        $playlistArticles = $this->_helper->entity->getRepository('Newscoop\Entity\Playlist')
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
     * Get image url
     *
     * @param Newscoop\Entity\Article $article
     * @return string
     */
    protected function getDossierImageUrl(Article $article)
    {
        $image = $article->getImage();
        if ($image) {
            return $this->view->serverUrl($this->view->url(array(
                'src' => $this->getHelper('service')->getService('image')->getSrc($image->getPath(), $this->getImageWidth(), $this->getImageHeight(), 'crop'),
            ), 'image', false, false));
        } else {
            return null;
        }
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
