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
                ->add('orderBy', 'a.publication ASC, a.issue DESC, a.section ASC, a.articleOrder ASC')
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
        // On making changes for the back section of an article also check
        // OnlineBrowserController::articlesBackAction
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
        $em = $this->container->get('em');

        $article = $em
            ->getRepository('Newscoop\Entity\Article')
            ->findOneByNumber($id);
        if (!$article || (!$article->isPublished() && !$allowUnpublished)) {
            return $apiHelperService->sendError("Article not found", 404);
        }

        $cacheHelper = $this->container
            ->get('newscoop_tageswochemobile_plugin.cache_helper');

        $cacheHelper->validateBrowserCache($article->getDate(), $request);

        $metaArticle = new \MetaArticle($article->getLanguageId(), $article->getNumber());
        $templatesService = $this->container->get('newscoop.templates.service');
        $smarty = $templatesService->getSmarty();
        $context = $smarty->context();
        $context->article = $metaArticle;

        if ($request->get('side') == 'back') {
            $templateName = 'articles_backside.tpl';
            $smarty->assign('webcode', ($article->hasWebcode()) ? $apiHelperService->fixWebcode($article->getWebcode()) : null);
        } else {
            $articleTopic = $em
                ->getRepository('Newscoop\Entity\Article')
                ->createQueryBuilder('a')
                ->select('a.number', 't.id')
                ->leftJoin('a.topics', 't')
                ->where('a.number = :number')
                ->setParameter('number', $id)
                ->getQuery()
                ->getArrayResult();

            $templateName = 'articles_frontsize.tpl';

            foreach ($articleTopic as $topicId) {
                if ($topicId['id'] === 816) {
                    $templateName = 'online_article_debate_new.tpl';
                }
            }
        }

        $response = new Response();
        $response->headers->set('Content-Type', 'text/html');
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
