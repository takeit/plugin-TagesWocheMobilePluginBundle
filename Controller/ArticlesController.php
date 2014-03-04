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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Newscoop\Entity\Article;

/**
 * Route('/articles')
 */
class ArticlesController extends Controller
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
    // public function init()
    // {
    //     global $Campsite;

    //     $this->_helper->layout->disableLayout();
    //     $this->request = $this->getRequest();
    //     $this->language = $this->_helper->entity->getRepository('Newscoop\Entity\Language')->findOneBy(array('id' => self::LANGUAGE));
    //     $this->articleService = $this->_helper->service('article');
    //     $this->url = $Campsite['WEBSITE_URL'];
    //     $this->params = $this->request->getParams();

    //     if (empty($this->params['client'])) {
    //         $this->params['client'] = self::CLIENT_DEFAULT;
    //     }

    //     $this->initClient($this->params['client']);
    //     if (is_null($this->client['type'])) {
    //         $this->sendError('Invalid client', 500);
    //     }
    // }

    /**
     * @Route("/index")
     * @Route("/list")
     */
    public function listAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $em = $this->container->get('em');

        $params = $request->query->all();

        if (!empty($params['section_id'])) {

            $playlist = $em->getRepository('Newscoop\Entity\Playlist')
                ->find($params['section_id']);

            if (empty($playlist) || $playlist === null) {
                return $apiHelperService->sendError('Section not found', 404);
            }

            $articles = $em->getRepository('Newscoop\Entity\Playlist')
                ->articles($playlist, null, false, self::LIST_LIMIT_DEFAULT);
        } else {

            $qb = $em
                ->getRepository('Newscoop\Entity\Article')
                ->createQueryBuilder('a')
                ->where('a.workflowStatus = :published')
                ->setParameter('published', Article::STATUS_PUBLISHED);

            if (!empty($params['type'])) {
                $qb = $qb->andWhere($qb->expr()->eq('a.type', ':type'))
                    ->setParameter('type', $params['type']);
            }
            if (!empty($params['topic_id'])) {
                $qb->leftJoin('a.topics', 't');
                $qb->andWhere($qb->expr()->eq('t.id', ':topic_id'))
                    ->setParameter('topic_id', $params['topic_id']);
            }

            $articles = $qb
                ->setMaxResults(self::LIST_LIMIT_BYTOPIC)
                ->getQuery()
                ->getResult();
        }

        $listAds = $apiHelperService->getArticleListAds('sectionlists');

        $rank = 1;
        $ad = 0;
        $response = array();
        foreach ($articles as $item) {
            // inject sectionlists ads
            if (in_array($rank, $this->adRanks)) {
                while ($ad < count($listAds)) {
                    if ((!empty($listAds[$ad])) && ($apiHelperService->getAdImageUrl($listAds[$ad]))) {
                        $this->response[] = array_merge($apiHelperService->formatArticle($listAds[$ad]), array('rank' => (int) $rank++));
                        $ad++;
                        break;
                    }
                    $ad++;
                }
            }

            $articleNumber = isset($playlist) ? $item['articleId'] : $item->getNumber();
            $article = $em->getRepository('Newscoop\Entity\Article')
                ->findOneByNumber($articleNumber);

            if (!empty($article) && $article->isPublished()) {
                $articleResponse = $apiHelperService->formatArticle($article);

                // Hack for WOBS-2783
                if (array_key_exists('dateline', $articleResponse) && $article->getType() == 'link') {
                    //if ($responsarticleResponsee['dateline'] == null) {
                        $articleResponse['dateline'] = 'Linkempfehlung';
                    //}
                }

                $response[] = $articleResponse;
                $rank++;
            }
            //break;
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/item")
     */
    public function itemAction(Request $request)
    {
        $id = $request->get('article_id');
        $allowUnpublished = false;

        if (array_key_exists('pim_allow_unpublished', $_SESSION)) {
            if ($_SESSION['pim_allow_unpublished'] == true && $request->get('allow_unpublished') == '1') {
                $allowUnpublished = true;
            }
        }

        if (is_null($id)) {
            return new JsonResponse(array());
        }

        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $article = $this->container->get('em')
            ->getRepository('Newscoop\Entity\Article')
            ->findOneByNumber($id);
        if (!$article || (!$article->isPublished() && !$allowUnpublished)) {
            return $apiHelperService->sendError("Article not found", 404);
        }

        $cacheHelper = $this->container
            ->get('newscoop_tageswochemobile_plugin.cache_helper');

        $cacheHelper->validateBrowserCache($article->getDate(), $request);

        $templatesService = $this->container->get('newscoop.templates.service');
        $smarty = $templatesService->getSmarty();
        $context = $smarty->context();
        $context->article = new \MetaArticle($article->getLanguageId(), $article->getNumber());

        if ($request->get('side') == 'back') {

            $templateName = 'articles_backside.tpl';
            $smarty->assign('webcode', ($article->hasWebcode()) ? $apiHelperService->fixWebcode($article->getWebcode()) : null);
        } else {

            $templateName = 'articles_frontsize.tpl';
        }

        $response = new Response();
        $response->setContent($templatesService->fetchTemplate("_mobile/".$templateName));

        return $response;
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
