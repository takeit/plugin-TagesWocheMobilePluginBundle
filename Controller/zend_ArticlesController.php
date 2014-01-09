<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

use Newscoop\Entity\Article;
use Newscoop\Webcode\Manager;

require_once __DIR__ . '/AbstractController.php';
require_once($GLOBALS['g_campsiteDir'].'/classes/ArticleTopic.php');
require_once($GLOBALS['g_campsiteDir'].'/classes/ArticleAttachment.php');

/**
 */
class Api_ArticlesController extends AbstractController
{
    const LANGUAGE = 5;

    const LIST_URI_PATH = 'api/articles/list';
    const ITEM_URI_PATH = 'api/articles/item';

    const LIST_LIMIT_DEFAULT = 15;
    const LIST_LIMIT_BYTOPIC = 30;

    /** @var Zend_Controller_Request_Http */
    private $request;

    /** @var array */
    private $response = array();

    /** @var array */
    private $adRanks = array(4, 10, 16);

    /**
     * Init controller
     */
    public function init()
    {
        global $Campsite;

        $this->_helper->layout->disableLayout();
        $this->request = $this->getRequest();
        $this->language = $this->_helper->entity->getRepository('Newscoop\Entity\Language')->findOneBy(array('id' => self::LANGUAGE));
        $this->articleService = $this->_helper->service('article');
        $this->url = $Campsite['WEBSITE_URL'];
        $this->params = $this->request->getParams();

        if (empty($this->params['client'])) {
            $this->params['client'] = self::CLIENT_DEFAULT;
        }

        $this->initClient($this->params['client']);
        if (is_null($this->client['type'])) {
            $this->sendError('Invalid client', 500);
        }
    }

    /**
     * Default action controller
     */
    public function indexAction()
    {
        $this->_forward('list');
    }

    /**
     * Return list of articles
     *
     * @return Newscoop\API\Response
     */
    public function listAction()
    {
        $this->getHelper('contextSwitch')->addActionContext('list', 'json')->initContext();

        $criteria = array();
        $criteria[] = new ComparisonOperation('workflow_status', new Operator('is'), 'published');
        $params = $this->request->getParams();

        if (!empty($params['section_id'])) {
            $playlist = $this->_helper->entity->getRepository('Newscoop\Entity\Playlist')
                ->find($params['section_id']);
            if (empty($playlist)) {
                $this->sendError('Section not found', 404);
            }

            $articles = $this->_helper->entity->getRepository('Newscoop\Entity\Playlist')
                ->articles($playlist, null, false, self::LIST_LIMIT_DEFAULT);
        } else {
            if (!empty($params['type'])) {
                $criteria[] = new ComparisonOperation('type', new Operator('is'), (string) $params['type']);
            }
            if (!empty($params['topic_id'])) {
                /** @todo */
                $criteria[] = new ComparisonOperation('topic', new Operator('is'), $params['topic_id']);
            }

            $articles = \Article::GetList($criteria, null, 0, self::LIST_LIMIT_BYTOPIC, $count = 0, false, false);
        }

        $listAds = $this->getArticleListAds('sectionlists');
        $rank = 1;
        $ad = 0;
        foreach ($articles as $item) {
            // inject sectionlists ads 
            if (in_array($rank, $this->adRanks)) {
                while ($ad < count($listAds)) {
                    if ((!empty($listAds[$ad])) && ($this->getAdImageUrl($listAds[$ad]))) {
                        $this->response[] = array_merge($this->formatArticle($listAds[$ad]), array('rank' => (int) $rank++));
                        $ad++;
                        break;        
                    }
                    $ad++;
                }
            }

            $articleNumber = isset($playlist) ? $item['articleId'] : $item['number'];
            $article = $this->articleService->findOneByNumber($articleNumber);
            if (!empty($article) && $article->isPublished()) {
                $articleResponse = $this->formatArticle($article);
                // Hack for WOBS-2783
                if (array_key_exists('dateline', $articleResponse) && $article->getType() == 'link') {
                    //if ($responsarticleResponsee['dateline'] == null) {
                        $articleResponse['dateline'] = 'Linkempfehlung';
                    //}
                }
                $this->response[] = $articleResponse;
                $rank++;
            }
        }

        $this->_helper->json($this->response);
    }

    /**
     * Send article info
     */
    public function itemAction()
    {
        $id = $this->request->getParam('article_id');
        $allowUnpublished = false;

        if (array_key_exists('pim_allow_unpublished', $_SESSION)) {
            if ($_SESSION['pim_allow_unpublished'] == true && $this->request->getParam('allow_unpublished') == '1') {
                $allowUnpublished = true;
            }
        }

        if (is_null($id)) {
            print Zend_Json::encode($this->response);
            return;
        }

        $article = $this->articleService->findOneByNumber($this->request->getParam('article_id'));
        if (!$article || (!$article->isPublished() && !$allowUnpublished)) {
            $this->sendError("Article not found", 404);
        }

        $this->_helper->cache->validateBrowserCache($article->getUpdated());

        $this->_helper->smarty->setSmartyView();
        $this->view->getGimme()->article = new MetaArticle($article->getLanguageId(), $article->getNumber());
        $this->render($this->request->getParam('side') == 'back' ? 'backside' : 'frontsize');
    }

    /**
     * Get article data field value
     *
     * @param ArticleData $data
     * @param string $field
     * @return mixed
     */
    private function getFieldValue(ArticleData $data, $field)
    {
        try {
            return $data->getFieldValue($field);
        } catch (Exception $e) {
            return null;
        }
    }
}
