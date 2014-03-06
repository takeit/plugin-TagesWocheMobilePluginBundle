<?php
/**
 * @package   Newscoop\TagesWocheMobilePluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use DateTime;
use DateTimeZone;
use DateInterval;
use Newscoop\Webcode\Manager;
use Newscoop\SolrSearchPluginBundle\Controller\OmnitickerController AS SolrOmnitickerController;

class OmnitickerController extends SolrOmnitickerController
{
    const TYPE_TWITTER = 'tweet';
    const TYPE_NEWSWIRE = 'newswire';
    const TYPE_LINK = 'link';
    const TYPE_DOSSIER = 'dossier';
    const TYPE_COMMENT = 'comment';
    const TYPE_USER = 'user';
    const TYPE_EVENT = 'event';
    const TYPE_BLOG = 'blog';

    const EN_SECTION_ID = 90;
    const EN_SECTION_NAME = 'Swissinfo';
    const EN_SECTION_TYPE = 'english_news';

    /** @var array */
    private $literalTypes = array(
        self::TYPE_TWITTER,
        self::TYPE_LINK,
        self::TYPE_NEWSWIRE,
        self::TYPE_COMMENT,
        self::TYPE_EVENT,
    );

    /** @var int */
    private $rank = 1;

    /** @var array */
    private $commentStats = array();

    /**
     * @var Symfony\Component\HttpFoundation\Request
     */
    protected $request = null;

    /**
     * @var array
     */
    private $topicTypes = array('newswire', 'news', 'blog', 'dossier');

    /**
     * @var array
     */
    private $fields = array(
        'community' => array(
            'short_title',
            'teaser',
            'link_url',
            'published',
        ),
        'link' => array(
            'short_title',
            'teaser',
            'link_url',
            'published',
        ),
        'event' => array(
            'short_title',
            'teaser',
            'link_url',
            'published',
        ),
        'comment' => array(
            'short_title',
            'teaser',
            'website_url',
            'published',
        ),
        'tweet' => array(
            'short_title',
            'teaser',
            'link_url',
            'published',
        )
    );

    /**
     * @Route("/")
     */
    public function omnitickerAction(Request $request)
    {
        $this->request = $request;

        $request->query->set('format', 'json');
        $responseObject = parent::omnitickerAction($request, null);
        $responseData = json_decode($responseObject->getContent(), true);

        if (is_array($responseData)) {
            $this->commentStats = $this->container->get('comment')
                ->getArticleStats(array_filter(array_map(function($doc) {
                return OmnitickerController::getArticleId($doc);
            }, $responseData['response']['docs'])));
        }

        $mappedData = array_map(array($this, 'formatDoc'), (array) $responseData['response']['docs']);
        $mappedData = array_filter($mappedData);

        $response = new JsonResponse($mappedData);
        $response->headers->set('Expires', $this->getExpires($request), true);
        $response->headers->set('Cache-Control', 'public', true);
        $response->headers->set('Pragma', '', true);
        return $response;
    }

    /**
     * Format document for api
     *
     * @param array $doc
     * @return array
     */
    private function formatDoc(array $doc)
    {
        $apiHelper = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $slideshowHelper = $this->container->get('newscoop_tageswochemobile_plugin.render_slideshow_helper');

        $id = self::getArticleId($doc);

        $article = $this->container->get('em')
            ->getRepository('Newscoop\Entity\Article')->findOneByNumber($id);

        if ($article === null) {
            return array();
        }

        return $this->filterDoc(array(
            'article_id' => $id,
            'short_title' =>  html_entity_decode($this->formatTitle($doc), ENT_COMPAT, 'UTF-8'),
            'teaser' => html_entity_decode($this->formatTeaser($doc), ENT_COMPAT, 'UTF-8'),
            'url' => $this->formatUrl($id),
            'backside_url' => $this->formatUrl($id, false),
            'link_url' => $this->formatLinkUrl($doc),
            'website_url' => $apiHelper->getWebsiteUrl($article),
            'section_id' => !empty($doc['section_id']) ? (int) $doc['section_id'] : null,
            'section_name' => !empty($doc['section_name']) ? $doc['section_name'] : null,
            'comments_enabled' => isset($this->commentStats[$id]['comments_enabled']) ? $this->commentStats[$id]['comments_enabled'] : false,
            'comment_url' => $id ? $apiHelper->serverUrl($this->container->get('zend_router')->assemble(array(
                'module' => 'api',
                'controller' => 'comments',
                'action' => 'list',
            )) . sprintf('?article_id=%d%s', $id, $apiHelper->getClientVersionParams(false))) : null,
            'comment_count' => isset($this->commentStats[$id]) ? $this->commentStats[$id]['normal'] : null,
            'recommended_comment_count' => isset($this->commentStats[$id]) ? $this->commentStats[$id]['recommended'] : null,
            'source' => $this->formatType($doc),
            'rank' => $this->rank++,
            'last_modified' => $this->formatDate($doc, 'updated'),
            'published' => $this->formatDate($doc, 'published'),
            'topics' => $this->formatTopics($doc),
            'facebook_teaser' => $this->formatTeaser($doc),
            'twitter_teaser' => $this->formatTeaser($doc),
            'slideshow_images' => $slideshowHelper->direct($id),
        ));
    }

    /**
     * Filter out unused fields
     *
     * @param array $doc
     * @return array
     */
    private function filterDoc(array $doc)
    {
        if (isset($this->fields[$doc['source']])) {
            foreach (array_keys($doc) as $key) {
                if (!in_array($key, $this->fields[$doc['source']])
                    && !in_array($key, array('source', 'rank'))) {
                    unset($doc[$key]);
                }
            }
        }

        return $doc;
    }

    /**
     * Format doc url
     *
     * @param int $articleId
     * @param bool $isFront
     * @return string
     */
    private function formatUrl($articleId, $isFront = true)
    {
        if (empty($articleId)) {
            return null;
        }

        $apiHelper = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        return $apiHelper->serverUrl($this->container->get('zend_router')->assemble(array(
            'module' => 'api',
            'controller' => 'articles',
            'action' => 'item',
        )) . sprintf('?article_id=%d&side=%s%s', $articleId, $isFront ? 'front' : 'back', $apiHelper->getClientVersionParams(false)));
    }

    /**
     * Format link url
     *
     * @param array $doc
     * @return string
     */
    private function formatLinkUrl(array $doc)
    {
        if (!empty($doc['link_url'])) {
            return $doc['link_url'];
        }

        if (!empty($doc['tweet'])) {
            $matches = array();
            if (preg_match('#http://t.co/[a-z0-9]+#i', $doc['tweet'], $matches) === 1) {
                return $matches[0];
            }
        }

        $apiHelper = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        switch ($doc['type']) {
            case self::TYPE_USER:
                $ids = explode('-', $doc['id']);
                return $apiHelper->serverUrl(
                    $apiHelper->container->get('zend_router')->assemble(array(
                        'module' => 'api',
                        'controller' => 'profile',
                        'action' => 'public',
                        'user' => $ids[1],
                    ), 'default') . $apiHelper->getClientVersionParams(true)
                );
        }
    }

    /**
     * Format date
     *
     * @param array $doc
     * @param string $key
     * @return string
     */
    private function formatDate(array $doc, $key)
    {
        if (!isset($doc[$key])) {
            return null;
        }

        try {
            $date = new DateTime($doc[$key]);
        } catch (Exception $e) {
            return null;
        }

        $date->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Format document title
     *
     * @param array $doc
     * @return string
    */
    private function formatTitle(array $doc)
    {
        switch ($doc['type']) {
            case self::TYPE_TWITTER:
                return $doc['tweet_user_screen_name'];

            case self::TYPE_LINK:
                return $doc['title'];

            case self::TYPE_COMMENT:
                return $doc['subject'];

            case self::TYPE_USER:
                return $doc['user'];

            default:
                return !empty($doc['title']) ? $doc['title'] : null;
        }
    }

    /**
     * Format document teaser
     *
     * @param array $doc
     * @return string
     */
    private function formatTeaser(array $doc)
    {
        switch ($doc['type']) {
            case self::TYPE_TWITTER:
                return $doc['tweet'];

            case self::TYPE_LINK:
                return $doc['link_description'];

            case self::TYPE_COMMENT:
                return $doc['message'];

            case self::TYPE_USER:
                return isset($doc['bio']) ? $doc['bio'] : null;

            case self::TYPE_EVENT:
                $doc += array(
                    'event_organizer' => '',
                    'event_town' => '',
                    'event_date' => '',
                    'event_time' => '',
                );

                return sprintf('%s %s, %s %s %s',
                    $doc['event_organizer'],
                    $doc['event_town'],
                    $doc['event_date'],
                    $doc['event_time'],
                    empty($doc['event_time']) ? '' : 'Uhr');

            default:
                return !empty($doc['lead']) ? $doc['lead'] : null;
        }
    }

    /**
     * Format document type
     *
     * @param array $doc
     * @return string
     */
    private function formatType(array $doc)
    {
        if ($this->isEnglishNews($doc)) {
            return self::EN_SECTION_TYPE;
        } else if (in_array($doc['type'], $this->literalTypes)) {
            return $doc['type'];
        }

        if ($doc['type'] === self::TYPE_USER) {
            return 'community';
        }

        if ($doc['type'] === self::TYPE_BLOG && $this->isSearch()) {
            return 'blogpost';
        }

        if ($doc['type'] === self::TYPE_DOSSIER && $this->isSearch()) {
            return self::TYPE_DOSSIER;
        }

        return $this->isSearch() ? 'article' : 'tageswoche';
    }

    /**
     * Test if is search
     *
     * @return bool
     */
    protected function isSearch()
    {
        if ($this->request !== null && strpos($this->request->get('_controller'), 'SearchController') !== false) {
            return true;
        }
        return false;
    }

    /**
     * Test if is english news
     *
     * @param array $doc
     * @return bool
     */
    private function isEnglishNews(array $doc)
    {
        return (!empty($doc['section_id']) && $doc['section_id'] == self::EN_SECTION_ID)
            || (!empty($doc['section_name']) && $doc['section_name'] === self::EN_SECTION_NAME);
    }

    /**
     * Build date range query
     *
     * @return string
     */
    protected function buildSolrDateParam($parameters)
    {
        if (!array_key_exists('start_date', $parameters) || !$parameters['start_date']) {
            return;
        }

        $apiHelper = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        try {
            $startDate = new DateTime($parameters['start_date']);
        } catch (Exception $e) {
            return $apiHelper->sendError($e->getMessage(), 500);
        }

        $endDate = $startDate;
        if (array_key_exists('end_date', $parameters) && $parameters['end_date']) {
            try {
                $endDate = new DateTime($parameters['end_date']);
            } catch (Exception $e) {
                return $apiHelper->sendError($e->getMessage(), 500);
            }
        }

        return sprintf('published:[%s/DAY TO %s+1DAY/DAY]', // it's <,) interval thus +1 day
            $startDate->format('Y-m-d\TH:i:s\Z'),
            $endDate->format('Y-m-d\TH:i:s\Z'));
    }

    /**
     * Build solr params
     *
     * @return array
     */
    protected function encodeParameters($parameters)
    {
        $rows = 100;

        if (
            array_key_exists('start_date', $parameters) &&
            $parameters['start_date'] &&
            (
                !array_key_exists('query_string', $parameters) ||
                !$parameters['query_string']
            )
        ) {
            $rows = 200;
        }

        return array_merge(parent::encodeParameters($parameters), array(
            'rows' => $rows
        ));
    }

    /**
     * Get expires string
     *
     * @return string
     */
    private function getExpires($request)
    {
        $now = new DateTime();
        $start = new DateTime($request->query->get('start_date') ?: 'now');
        $expires = new DateInterval($start->format('Y-m-d') === $now->format('Y-m-d') || $start->getTimestamp() > $now->getTimestamp() ? 'PT5M' : 'P300D');
        return $now->add($expires)->format(DateTime::RFC1123);
    }

    /**
     * Get article id
     *
     * @param array $doc
     * @return int
     *
     * static to work within closure
     */
    public static function getArticleId(array $doc)
    {
        return strpos($doc['id'], 'article-') === 0 ? (int) preg_replace('/(^article-)|([0-9]+-$)/', '', $doc['id']) : null;
    }

    /**
     * Format topics
     *
     * @param array $doc
     * @return array
     */
    protected function formatTopics($doc)
    {
        if (!in_array($doc['type'], $this->topicTypes)) {
            return array();
        }

        $ids = explode('-', $doc['id']);
        $article = $this->container->get('em')
            ->getRepository('Newscoop\Entity\Article')->findOneByNumber($ids[1]);
        if (empty($article)) {
            return array();
        }

        $topics = array();
        foreach ($article->getTopicNames() as $id => $name) {
            $topics[] = array(
                'topic_id' => $id,
                'topic_name' => $name,
            );
        }

        return $topics;
    }
}
