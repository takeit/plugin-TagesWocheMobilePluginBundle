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
        $mobileService = $this->container->get('newscoop_tageswochemobile_plugin.mobile.issue');

        $issues = $mobileService->findAll();
        array_shift($issues); // all but current
        return new JsonResponse(array_map(array($this, 'formatIndexIssue'), $issues));
    }

    /**
     * @Route("/toc/{id}", requirements={"id" = "\d+"})
     */
    public function tocAction($id)
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
     * @Route("/articles/{id}", requirements={"id" = "\d+"})
     */
    public function articlesAction($id)
    {
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
            if (!$apiHelperService->isSubscriber($article)) {
                return $apiHelperService->sendError('Unathorized', 401);
            }
        }

        //$this->_helper->smarty->setSmartyView();
        //$this->view->getGimme()->article = new MetaArticle($article->getLanguageId(), $article->getNumber());
        //$this->view->width = $apiHelperService->getClientWidth();
        //$this->view->height = $apiHelperService->getClientHeight();
        //$this->render('article');

        // TODO: find out how to do the getGimme() thing as above and what that does
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
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        return array(
            'issue_id' => $issue->getNumber(),
            // TODO add apiHelperService function to format this url
            'url' => $apiHelperService->serverUrl('online/toc?id=' . $issue->getNumber() . '&api=' . $apiHelperService->getClientVersionParams()),
            'cover_url' => $this->getCoverUrl($issue),
            'title' => $issue->getTitle(),
            'description' => $apiHelperService->getArticleField($issue, 'shortdescription'),
            'year' => (int) $issue->getPublishDate()->format('Y'),
            'month' => (int) $issue->getPublishDate()->format('m'),
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
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $image = $apiHelperService->getImageUrl($issue);
        if ($image) {
            return $apiHelperService->serverUrl(
                $apiHelperService->getLocalImageUrl($image, array(145, 201), array(290, 402))
            );
        }
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
            'offline_url' => $apiHelperService->serverUrl('offline/issues/' . $issue->getNumber() . $apiHelperService->getApiQueryString()),
            'cover_url' => $this->getCoverUrl($issue),
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
        $sectionId = $this->getSectionId($article);
        $storyId = $this->getStoryName($article) ? $sectionId . $this->getStoryName($article) : null;
        return array_merge(parent::formatArticle($article), array(
            // TODO add apiHelperService function to format this url
            'url' => $apiHelperService->serverUrl('online/articles?id=' . $article->getNumber() . '&api=' . $apiHelperService->getClientVersionParams()),
            'section_id' => $sectionId,
            'section_name' => $this->getSectionName($article),
            'section_rank' => $this->getSectionRank($sectionId),
            'image_url' => $this->getArticleImageUrl($article),
            'article_quality' => $this->isProminent($article) ? 'prominent' : 'companion',
            'last_modified' => $article->getDate()->format($apiHelperService::DATE_FORMAT),
            'published' => $article->getPublishDate()->format($apiHelperService::DATE_FORMAT),
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
