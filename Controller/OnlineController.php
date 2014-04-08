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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Newscoop\Entity\Article;

use Newscoop\TagesWocheMobilePluginBundle\Mobile\IssueFacade;

/**
 * Route('/online')
 *
 * Issues Service
 */
class OnlineController extends Controller
{

    /**
     * @var int
     */
    private $rank = 1;

    /**
     * @var Tageswoche\Mobile\IssueFacade
     */
    private $service;

    /**
     * @var array
     */
    private $commentStats = array();

    /**
     * @Route("/issues")
     */
    public function issuesAction()
    {
        $mobileService = $this->container->get('newscoop_tageswochemobile_plugin.mobile.issue');

        $issues = $mobileService->findAll();
        array_shift($issues); // all but current
        return new JsonResponse(array_map(array($this, 'formatIndexIssue'), $issues));
    }

    /**
     * @Route("/toc/{id}")
     */
    public function tocAction($id, Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $mobileService = $this->container->get('newscoop_tageswochemobile_plugin.mobile.issue');

        if (in_array($id, array(IssueFacade::CURRENT_ISSUE, $mobileService->getCurrentIssueId()))) {
            if (!$apiHelperService->isSecure()) {
                return $apiHelperService->sendError('Secure connection required', 400);
            }
        }

        if (!$id) {
            return $apiHelperService->sendError('Missing id', 400);
        }

        $this->issue = $mobileService->find($id);
        if (empty($this->issue)) {
            return $apiHelperService->sendError('Issue not found.', 404);
        }

        return new JsonResponse($this->formatTocIssue($this->issue));
    }

    /**
     * @Route("/articles")
     * @Route("/articles/{id}")
     */
    public function articlesAction(Request $request, $id = null)
    {
        if (!isset($id)) {
            $id = $request->query->get('id');
        }
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $article = $this->container->get('em')
            ->getRepository('Newscoop\Entity\Article')
            ->findOneByNumber($id);
        if (!$article || !$article->isPublished()) {
            return $apiHelperService->sendError('Article not found.', 404);
        }

        $cacheHelper = $this->container->get('newscoop_tageswochemobile_plugin.cache_helper');
        $cacheHelper->validateBrowserCache($article->getDate(), $this->getRequest());

        if ($this->container->get('newscoop_tageswochemobile_plugin.mobile.issue')->isInCurrentIssue($article)) {
            if (!$apiHelperService->isSecure()) {
                return $apiHelperService->sendError('Secure connection required', 400);
            }
            $isSubscriber = $apiHelperService->isSubscriber($article);
            if (!$isSubscriber || ($isSubscriber instanceof JSONResponse)) {
                if ($isSubscriber instanceof JSONResponse) {
                    return $isSubscriber;
                } else {
                    return $apiHelperService->sendError('Unauthorized', 401);
                }
            }
        }

        $templatesService = $this->container->get('newscoop.templates.service');
        $smarty = $templatesService->getSmarty();
        $gimme =  $smarty->context();
        $gimme->article = new \MetaArticle($article->getLanguageId(), $article->getNumber());
        $smarty->assign('gimme', $gimme);
        $smarty->assign('width', $apiHelperService->getClientWidth());
        $smarty->assign('height', $apiHelperService->getClientHeight());

        $response = new Response();
        $response->headers->set('Content-Type', 'text/html');
        $response->setContent($templatesService->fetchTemplate("_views/online_article.tpl"));
        return $response;
    }

    /**
     * Format issue for list
     *
     * @param Newscoop\Entity\Article $issue
     * @return array
     */
    private function formatIndexIssue(Article $issue)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        return array(
            'issue_id' => $issue->getNumber(),
            // TODO add apiHelperService function to format this url
            'url' => $apiHelperService->serverUrl('api/online/toc/' . $issue->getNumber() . '?api=' . $apiHelperService->getClientVersionParams()),
            'cover_url' => $apiHelperService->getCoverUrl($issue),
            'title' => $issue->getTitle(),
            'description' => $apiHelperService->getArticleField($issue, 'shortdescription'),
            'year' => (int) $issue->getPublishDate()->format('Y'),
            'month' => (int) $issue->getPublishDate()->format('m'),
            'rank' => $this->rank++,
        );
    }

    /**
     * Format toc issue
     *
     * @param Newscoop\Entity\Article $issue
     * @return array
     */
    private function formatTocIssue(Article $issue)
    {
        $mobileService = $this->container->get('newscoop_tageswochemobile_plugin.mobile.issue');
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $articles = $mobileService->getArticles($issue);
        $this->commentStats = $this->container->get('comment')->getArticleStats(array_map(function($article) {
            return $article->getNumber();
        }, $articles));

        $toc = array(
            'issue_id' => $issue->getNumber(),
            // TODO add apiHelperService function to format this url
            'offline_url' => $apiHelperService->serverUrl('api/offline/issues/' . $issue->getNumber() . $apiHelperService->getApiQueryString()),
            'cover_url' => $apiHelperService->getCoverUrl($issue),
            'single_issue_product_id' => sprintf('ch.tageswoche.issue.%d.%s', $issue->getPublishDate()->format('Y'), trim($apiHelperService->getArticleField($issue, 'issue_number'))),
            'title' => $issue->getTitle(),
            'description' => $apiHelperService->getArticleField($issue, 'shortdescription'),
            'publication_date' => $issue->getPublishDate()->format('Y-m-d'),
            'last_modified' => $issue->getDate()->format($apiHelperService::DATE_FORMAT),
            'articles' => array_map(array($this, 'formatArticle'), $articles),
        );

        $toc['last_modified'] = array_reduce($toc['articles'], function($prev, $article) {
            return $prev > $article['last_modified'] ? $prev : $article['last_modified'];
        });

        return $toc;
    }

    /**
     * Format article
     *
     * @param Newscoop\Entity\Article $article
     * @return array
     */
    protected function formatArticle(Article $article)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $sectionId = $apiHelperService->getSectionId($article);
        $storyId = $apiHelperService->getStoryName($article) ? $sectionId . $apiHelperService->getStoryName($article) : null;
        return array_merge($apiHelperService->formatArticle($article), array(
            // TODO add apiHelperService function to format this url
            'url' => $apiHelperService->serverUrl('api/online/articles?id=' . $article->getNumber() . '&api=' . $apiHelperService->getClientVersionParams()),
            'section_id' => $sectionId,
            'section_name' => $apiHelperService->getSectionName($article),
            'section_rank' => $apiHelperService->getSectionRank($sectionId),
            'image_url' => $apiHelperService->getArticleImageUrl($article),
            'article_quality' => $apiHelperService->isProminent($article) ? 'prominent' : 'companion',
            'last_modified' => $article->getDate()->format($apiHelperService::DATE_FORMAT),
            'published' => $article->getPublishDate()->format($apiHelperService::DATE_FORMAT),
            'story_name' => $apiHelperService->getStoryName($article) ?: null,
            'story_id' => $storyId ?: null,
            'teaser_short' => $apiHelperService->getTeaserShort($article),
            'facebook_teaser' => $apiHelperService->getTeaser($article, 'social'),
            'twitter_teaser' => $apiHelperService->getTeaser($article, 'social'),
            'advertisement' => $apiHelperService->isAd($article),
            'story_image_url' => $apiHelperService->getRenditionUrl($article, 'ios_app_story_image', array(174, 174), array(348, 348))
        ));
    }

}
