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

use Newscoop\TagesWocheMobilePluginBundle\Mobile\IssueFacade;

/**
 * Route('/online')
 *
 * Issues Service
 */
class OnlineController extends Controller
{
    const LANGUAGE_ID = 1;
    const AD_SECTION = 'ad_name';
    const PROMINENT_SWITCH = 'iPad_prominent';

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

    /** @var array */
    private $sections = array();

    /** @var array */
    private $sectionRanks = array();

    /** @var array */
    private $ids = array();

    /**
     * @Route("/issues")
     */
    public function issuesAction()
    {
        $mobileService = $this->container->get('mobile.issue');

        $issues = $mobileService->findAll();
        array_shift($issues); // all but current
        return new JsonResponse(array_map(array($this, 'formatIndexIssue'), $issues));
    }

    /**
     * @Route("/toc")
     */
    public function tocAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $mobileService = $this->container->get('mobile.issue');

        if (in_array($request->query->get('id'), array(IssueFacade::CURRENT_ISSUE, $mobileService->getCurrentIssueId()))) {
            return $apiHelperService->assertIsSecure();
        }

        if (!$request->query->get('id')) {
            return $apiHelperService->sendError();
        }

        $this->issue = $mobileService->find($request->query->get('id'));
        if (empty($this->issue)) {
            return $apiHelperService->sendError('Issue not found.', 404);
        }

        return new JsonResponse($this->formatTocIssue($this->issue));
    }

    /**
     * @Route("/articles")
     */
    public function articlesAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $article = $this->container->get('article')->findOneByNumber($request->query->get('id'));
        if (!$article || !$article->isPublished()) {
            return $apiHelperService->sendError('Article not found.', 404);
        }

        $cacheHelper = $this->container->get('newscoop_tageswochemobile_plugin.cache_helper');
        $cacheHelper->validateBrowserCache($article->getUpdated(), $request);

        if ($this->container->get('mobile.issue')->isInCurrentIssue($article)) {
            $apiHelperService->assertIsSecure();
            $apiHelperService->assertIsSubscriber($article);
        }

        //$this->_helper->smarty->setSmartyView();
        //$this->view->getGimme()->article = new MetaArticle($article->getLanguageId(), $article->getNumber());
        //$this->view->width = $apiHelperService->getClientWidth();
        //$this->view->height = $apiHelperService->getClientHeight();
        //$this->render('article');

        return $this->render('NewscoopTagesWocheMobilePluginBundle:online:article.html.smarty', array(
            'article' => new \MetaArticle($article->getLanguageId(), $article->getNumber()),
            'width' => $apiHelperService->getClientWidth(),
            'height' => $apiHelperService->getClientHeight()
        ));
    }

    /**
     * Format issue for list
     *
     * @param Newscoop\Entity\Article $issue
     * @return array
     */
    private function formatIndexIssue(Article $issue)
    {
        return array(
            'issue_id' => $issue->getNumber(),
            'url' => $this->view->serverUrl($this->view->url(array(
                'controller' => 'online',
                'action' => 'toc',
                'id' => $issue->getNumber(),
            ), 'api')) . $this->getClientVersionParams(),
            'cover_url' => $this->getCoverUrl($issue),
            'title' => $issue->getTitle(),
            'description' => $this->getArticleField($issue, 'shortdescription'),
            'year' => (int) $issue->getPublished()->format('Y'),
            'month' => (int) $issue->getPublished()->format('m'),
            'rank' => $this->rank++,
        );
    }

    /**
     * Get cover image url
     *
     * @param Newscoop\Entity\Article $issue
     * @return string
     */
    private function getCoverUrl(Article $issue)
    {
        $image = $issue->getImage();
        if ($image) {
            return $this->view->serverUrl($this->view->url(array(
                'src' => $this->container->get('image')->getSrc($image->getPath(), $this->getCoverImageWidth(), $this->getCoverImageHeight(), 'crop'),
            ), 'image', false, false));
        }
    }

    /**
     * Get cover image width
     *
     * @return int
     */
    private function getCoverImageWidth()
    {
        return $this->isRetinaClient() ? 290 : 145;
    }

    /**
     * Get cover image height
     *
     * @return int
     */
    private function getCoverImageHeight()
    {
        return $this->isRetinaClient() ? 402 : 201;
    }

    /**
     * Format toc issue
     *
     * @param Newscoop\Entity\Article $issue
     * @return array
     */
    private function formatTocIssue(Article $issue)
    {
        $mobileService = $this->container->get('mobile.issue');

        $articles = $mobileService->getArticles($issue);
        $this->commentStats = $this->container->get('comment')->getArticleStats(array_map(function($article) {
            return $article->getNumber();
        }, $articles));

        $toc = array(
            'issue_id' => $issue->getNumber(),
            'offline_url' => $this->view->serverUrl($this->view->url(array(
                'module' => 'api',
                'controller' => 'offline',
                'action' => 'issues',
                'id' => $issue->getNumber(),
            ))) . $this->getApiQueryString(),
            'cover_url' => $this->getCoverUrl($issue),
            'single_issue_product_id' => sprintf('ch.tageswoche.issue.%d.%s', $issue->getPublished()->format('Y'), trim($this->getArticleField($issue, 'issue_number'))),
            'title' => $issue->getTitle(),
            'description' => $this->getArticleField($issue, 'shortdescription'),
            'publication_date' => $issue->getPublished()->format('Y-m-d'),
            'last_modified' => $issue->getUpdated()->format(self::DATE_FORMAT),
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
        $sectionId = $this->getSectionId($article);
        $storyId = $this->getStoryName($article) ? $sectionId . $this->getStoryName($article) : null;
        return array_merge(parent::formatArticle($article), array(
            'url' => $this->view->serverUrl($this->view->url(array(
                'controller' => 'online',
                'action' => 'articles',
                'id' => $article->getNumber(),
            ), 'api')) . $this->getClientVersionParams(),
            'section_id' => $sectionId,
            'section_name' => $this->getSectionName($article),
            'section_rank' => $this->getSectionRank($sectionId),
            'image_url' => $this->getArticleImageUrl($article),
            'article_quality' => $this->isProminent($article) ? 'prominent' : 'companion',
            'last_modified' => $article->getUpdated()->format(self::DATE_FORMAT),
            'published' => $article->getPublished()->format(self::DATE_FORMAT),
            'story_name' => $this->getStoryName($article) ?: null,
            'story_id' => $storyId ?: null,
            'teaser_short' => $this->getTeaserShort($article),
            'facebook_teaser' => $this->getTeaser($article, 'social'),
            'twitter_teaser' => $this->getTeaser($article, 'social'),
            'advertisement' => $this->isAd($article),
            'story_image_url' => $this->getRenditionUrl($article, 'ios_app_story_image', array(174, 174), array(348, 348))
        ));
    }

    /**
     * Test if article is prominent
     *
     * @param Newscoop\Entity\Article $article
     * @return bool
     */
    protected function isProminent($article)
    {
        try {
            return $this->isAd($article) || $article->getData(self::PROMINENT_SWITCH);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get section name
     *
     * @param Newscoop\Entity\Article $article
     * @return string
     */
    protected function getSectionName($article)
    {
        try {
            if ($this->isAd($article)) {
                return $article->getData(self::AD_SECTION) ? ucwords((string) $article->getData(self::AD_SECTION)) : 'Anzeige';
            } else {
                return ucwords((string) $article->getData('printsection'));
            }
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Get section rank
     *
     * @param int $sectionId
     * @return void
     */
    public function getSectionRank($sectionId)
    {
        if (!isset($this->sectionRanks[$sectionId])) {
            $this->sectionRanks[$sectionId] = count($this->sectionRanks) + 1;
        }

        return $this->sectionRanks[$sectionId];
    }

    /**
     * Get story name
     *
     * @param Newscoop\Entity\Article $article
     * @return string
     */
    protected function getStoryName($article)
    {
        try {
            return ucwords((string) $article->getData('printstory'));
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Get id for string
     *
     * @param Newscoop\Entity\Article $article
     * @return int
     */
    protected function getSectionId($article)
    {
        try {
            $key = $article->getData('printsection') ?: $article->getNumber();
        } catch (Exception $e) {
            $key = $article->getNumber();
        }

        if (!isset($this->ids[$key])) {
            $this->ids[$key] = count($this->ids) + 1;
        }

        return $this->ids[$key];
    }

    /**
     * Get short teaser 
     *
     * @param Newscoop\Entity\Article $article
     * @return string
     */
    protected function getTeaserShort($article)
    {
        $field = ($article->getType() == 'blog') ? 'teaser' : 'seo_title';

        try {
            $teaserShort = $article->getData($field);
        } catch (Exception $e) {
            $teaserShort = $article->getTitle();
        }
        
        if (!empty($teaserShort)) {
            return substr($teaserShort, 0, 120);
        }

        return null;
    }

    /**
     * Get article image url
     *
     * @param Newscoop\Entity\Article $article
     * @return string
     */
    protected function getArticleImageUrl($article)
    {
        if ($this->isProminent($article) || $this->isAd($article)) {
            return $this->getRenditionUrl($article, 'mobile_prominent', array(300, 100), array(600, 200));
        } else {
            return $this->getRenditionUrl($article, 'mobile_non_prominent', array(100, 100), array(200, 200));
        }
    }
}
