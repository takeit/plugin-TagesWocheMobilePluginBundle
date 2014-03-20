<?php

namespace Newscoop\TagesWocheMobilePluginBundle\Services;

use Datetime;
use DateTimeZone;
use Doctrine\ORM\EntityManager;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Newscoop\Webcode\Manager;
use Newscoop\Entity\Article;
use Newscoop\Entity\User;
use Newscoop\TagesWocheMobilePluginBundle\Mobile\OfflineIssueService;
/**
 * Configuration service for article type
 */
class ApiHelper
{
    const DIGITAL_UPGRADE = '_digital_upgrade';

    const DATE_FORMAT = 'Y-m-d H:i:s';

    const CLIENT_DEFAULT = 'ipad';
    const VERSION_DEFAULT = '1.0';

    const FRONT_SIDE = 'front';
    const BACK_SIDE = 'back';

    const IMAGE_STANDARD_RENDITION = 'rubrikenseite';

    const IMAGE_STANDARD_WIDTH = 105;
    const IMAGE_STANDARD_HEIGHT = 70;
    const IMAGE_RETINA_FACTOR = 2;

    const AD_WIDTH = 320;
    const AD_HEIGHT = 70;

    const FACEBOOK_AUTH_TOKEN = 'fb_access_token';

    const AD_TYPE = 'iPad_Ad';

    const TYPE_EDITOR = 'editor';
    const TYPE_BLOGGER = 'blogger';
    const TYPE_MEMBER = 'community_member';

    const PROMINENT_SWITCH = 'iPad_prominent';
    const AD_SECTION = 'ad_name';

    /** @var int */
    private $rank = 1;

    /** @var array */
    private $fields = array(
        'teaser' => array(
            'newswire' => 'DataLead',
            'blog' => 'lede',
        ),
        'social_teaser' => array(
            'newswire' => 'DataLead',
            'blog' => 'lede',
            'news' => 'lede',
            'dossier' => 'lede',
            'eventnews' => 'lede',
            'event' => 'description'
        ),
    );

    /** @var array */
    private $clientSize = array();

    public $client;

    /** @var array */
    private $sections = array();

    /** @var array */
    private $sectionRanks = array();

    /** @var array */
    private $ids = array();

    /**
     * @var Request
     */
    private $request = null;

    /**
     * Initialize service
     */
    public function __construct(EntityManager $em, Container $container) {
        $this->em = $em;
        $this->container = $container;
        $this->router = $this->container->get('router');
    }

    /**
     * Set request for service (done in event listener)
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     */
    public function setRequest (Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get user by username and password params
     *
     * @return Newscoop\Entity\User
     */
    public function getUser()
    {
        $token = $this->_getParam(self::FACEBOOK_AUTH_TOKEN);
        if ($token) {
            $user = $this->container->get('newscoop_tageswochemobile_plugin.facebook')
                ->findByAuthToken($token);
            return $user !== null ? $user : $this->sendError('Invalid credentials', 412);
        }

        $username = $this->_getParam('username');
        $password = $this->_getParam('password');

        if (empty($username) || empty($password)) {
            return $this->sendError('Invalid credentials.', 401);
        }

        $user = $this->container->get('auth.adapter')->findByCredentials($username, $password);

        return $user !== null ? $user : $this->sendError('Invalid credentials.', 401);
    }

    /**
     * Send error and exit
     *
     * @param string $body
     * @param int $code
     * @return JsonResponse
     */
    public function sendError($body = '', $code = 400)
    {
        $json = new JsonResponse(array(
            'code' => $code,
            'message' => $body,
        ));
        $json->setStatusCode($code);

        return $json;
    }

    /**
     * Assert request is secure
     *
     * @return void
     */
    public function isSecure()
    {
        if (
            APPLICATION_ENV === 'development' ||
            $this->isAuthorized() ||
            $this->request === null
        ) {
            return true;
        }

        if (!$this->request->isSecure()) {
            return false;
        }
    }

    /**
     * Assert request is post
     *
     * @return void
     */
    public function isPost()
    {
        if ($this->request !== null && $this->request->getMethod() != 'POST') {
            return false;
        }
        return true;
    }

    /**
     * Get client and version params
     *
     * @param bool $onlyParams
     * @return string
     */
    public function getClientVersionParams($onlyParams = true)
    {
        if ($this->request !== null) {
            return sprintf('%sclient=%s&version=%s', $onlyParams ? '?' : '&', $this->request->query->get('client', 'ipad'), $this->request->query->get('version', '1.0'));
        }
        return null;
    }

    /**
     * Assert that user is subscriber and can consume premium content
     *
     * @param Newscoop\Entity\Article $article
     * @return void
     */
    public function isSubscriber($article = null)
    {

        // user is accessing from a authorized server
        // no auth required
        if ($this->isAuthorized()) {
            return true;
        }

        // user has included DMPro device data
        if ($this->_getParam('receipt_data') && $this->_getParam('device_id')) {
            if ($this->container->get('newscoop_tageswochemobile_plugin.mobile.purchase')->isValid($this->_getParam('receipt_data'))) {
                return true;
            }
        }

        // user provided login details and has device upgrade
        if ($this->hasAuthInfo() && ($user = $this->getUser())) {
            if ($this->container->get('newscoop_tageswochemobile_plugin.subscription.device')->hasDeviceUpgrade($user, $this->_getParam('device_id'))) {
                return true;
            }
        }

        // reqeusted article is in the current issue or is requesting an ad
        // no auth required
        if ($article !== null && ! $this->container->get('newscoop_tageswochemobile_plugin.mobile.issue')->isInCurrentIssue($article)) {
            return true;
        } elseif ($article !== null && $this->isAd($article)) {
            return true;
        }

        return false;
    }

    /**
     * Test if request is authorized
     *
     * @return bool
     */
    public function isAuthorized()
    {
        $options = $this->container->getParameter('offline');
        if (
            !empty($options['secret']) &&
            $this->request !== null &&
            $this->request->headers->get(OfflineIssueService::OFFLINE_HEADER) === $options['secret']
        ) {
            return;
        }

        return $this->sendError('Unauthorized.', 401);
    }

    /**
     * Test if request has auth info
     *
     * @return bool
     */
    public function hasAuthInfo()
    {
        return $this->request !== null && ($this->request->request->get('username') || $this->request->request->get(self::FACEBOOK_AUTH_TOKEN));
    }

    /**
     * Get topic api url
     *
     * @param Newscoop\Entity\Topic $topic
     * @return string
     */
    public function getTopicUrl($topic)
    {
        return $this->serverUrl($this->container->get('zend_router')->assemble(array(
            'module' => 'api',
            'controller' => 'articles',
            'action' => 'list',
        ), 'default') . $this->getApiQueryString(array(
            'topic_id' => $topic->getTopicId(),
        )));
    }

    /**
     * Get article api url
     *
     * @param Newscoop\Entity\Article $article
     * @param string $side
     * @param array $params
     * @return string
     */
    public function getArticleUrl($article, $side = self::FRONT_SIDE, array $params = array())
    {
        $params['article_id'] = $article->getNumber();
        $params['side'] = $side;

        // return $this->router
        //     ->generate('NewscoopTagesWocheMobilePluginBundle:Articles:list', $params);

        return $this->serverUrl(
            $this->container->get('zend_router')->assemble(array(
                'module' => 'api',
                'controller' => 'articles',
                'action' => 'item',
            ), 'default') . $this->getApiQueryString($params)
        );
    }

    /**
     * Get article comments api url
     *
     * @param mixed $article
     * @return string
     */
    public function getCommentsUrl($article)
    {
        return $this->serverUrl($this->container->get('zend_router')->assemble(array(
            'module' => 'api',
            'controller' => 'comments',
            'action' => 'list',
        ), 'default') . $this->getApiQueryString(array(
            'article_id' => $article->getNumber(),
        )));
    }

    /**
     * Get api query string
     *
     * @param array $params
     * @return string
     */
    public function getApiQueryString(array $params = array())
    {
        if ($this->request !== null) {
            $params = array_filter(array_merge($params, array(
                'client' => $this->request->query->get('client'),
                'version' => $this->request->query->get('version'),
            )));
        }

        return empty($params) ? '' : '?' . implode('&', array_map(function ($key) use ($params) {
            return sprintf('%s=%s', $key, $params[$key]);
        }, array_keys($params)));
    }

    /**
     * Get article dateline
     *
     * @param Newscoop\Entity\Article $article
     * @return string
     */
    public function getDateline($article)
    {
        try {
            $dateline = ($article->getType() === 'blog')
                ? $article->getSection()->getName()
                : $article->getData('dateline');
            return !empty($dateline) ? $dateline : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get article website url
     *
     * @param Newscoop\Entity\Article $article
     * @return string
     */
    public function getWebsiteUrl($article)
    {
        if ($article->hasWebcode()) {
            return $this->serverUrl($this->fixWebcode($article->getWebcode()));
        }
        return null;
    }

    /**
     * Adds + to webcode is not present
     *
     * @param  string $webcode Webcode of an article
     *
     * @return string          Fixed webcode
     */
    public function fixWebcode($webcode) {

        if (substr($webcode, 0, 1) != '+') {
            $webcode = '+' . $webcode;
        }

        return $webcode;
    }

    /**
     * Get topic
     *
     * @return Newscoop\Entity\UserTopic
     */
    private function getTopic($topicId)
    {
        if (!$topicId && $this->request !== null) {
            $topicId = $this->request->query->get('topic_id');
        }
        $topic = $this->_helper->service('user.topic')->findTopic($topicId);
        if (!$topic) {
            $this->sendError('Topic not found.', 404);
        }

        return $topic;
    }

    /**
     * Get topics
     *
     * @param Newscoop\Entity\Article $article
     * @return array
     */
    public function getTopics($article)
    {
        $topics = array();
        $articleTopics = $article->getTopics();
        if ($articleTopics !== null && $articleTopics->count() > 0) {
            foreach ($articleTopics as $topic) {
                $topics[] = array(
                    'topic_id' => $topic->getTopicId(),
                    'topic_name' => $topic->getName(),
                );
            }
        }
        return $topics;
    }

    /**
     * Get article image
     *
     * @param Article $article
     * @return string $thumbnail
     */
    public function getImage($article, $rendition)
    {
        $renditions = $this->container->get('image.rendition')->getRenditions();
        if (!array_key_exists($rendition, $renditions)) {
            return null;
        }

        $articleRenditions = $this->container->get('image.rendition')
            ->getArticleRenditions($article->getId());
        $articleRendition = $articleRenditions[$renditions[$rendition]];

        if ($articleRendition === null) {
            return null;
        }

        $thumbnail = $articleRendition->getRendition()->
            getThumbnail($articleRendition->getImage(), $this->container->get('image'));

        return $thumbnail;
    }

    /**
     * Get article image url
     *
     * @param mixed $article
     * @param string $rendition
     * @param int $width
     * @param int $height
     * @return string
     */
    public function getImageUrl($article, $rendition = self::IMAGE_STANDARD_RENDITION)
    {
        $image = $this->getImage($article, $rendition);
        if (empty($image)) {
            return null;
        }

        $imageUrl = $this->container->get('zend_router')->assemble(array(
            'src' => $this->container->get('image')->getSrc(basename($image->src), $this->getClientWidth(), $this->getClientHeight(), 'crop'),
        ), 'image', false, false);

        return $this->serverUrl($imageUrl);
    }

    /**
     * Get image url
     *
     * @param object $item Newscoop\Entity\Image
     * @param int $max
     *
     * @return string
     */
    public function getImageUrlHelper($item, $max = 2048)
    {
        $orig = $item->getImage()->isLocal()
            ? $this->serverUrl($item->getImage()->getPath())
            : $item->getImage()->getPath();

        $src = $this->container->get('image')->getSrc($orig, $max, $max, 'fit');

        return $this->serverUrl(
            $this->container->get('zend_router')->assemble(
                array('src' => $src), 'image', true, false
            )
        );
    }

    /**
     * Get video url
     *
     * @param object $item Newscoop\Entity\Image
     *
     * @return string
     */
    public function getVideoUrlHelper($item)
    {
        if (strpos($item->getVideoUrl(), 'http') !== false) {
            return $item->getVideoUrl();
        }

        if (is_numeric($item->getVideoUrl())) {
            return sprintf('http://vimeo.com/%d', $item->getVideoUrl());
        } else {
            return sprintf('http://youtu.be/%s', $item->getVideoUrl());
        }
    }

    /**
     * Get ad image url
     *
     * @param mixed $ad
     * @param string $rendition
     * @return string
     */
    public function getAdImageUrl(Article $ad)
    {
        $images = $this->container->get('image')->findByArticle($ad->getNumber());
        foreach ($images as $image) {
            if ($image->getWidth() <= self::AD_WIDTH * 2) {
                return $this->serverUrl('/' .$image->getPath());
            }
        }

        return null;
    }

    /**
     * Get local image url
     *
     * @param object $image
     * @param array $normalSizes
     * @param array $retinaSizes
     * @return string
     */
    public function getLocalImageUrl($image, array $normalSizes, array $retinaSizes)
    {
        if ($image === null) {
            return null;
        } elseif (is_string($image)) {
            if (strpos($image, '.jpg') !== false ){
                $src = $image;
            } else {
                $image = $this->em->getRepository('Newscoop\Image\LocalImage')
                    ->findOneById($image);
                if ($image === null) {
                    return null;
                }
                $src = $image->getPath();
            }
        } else {
            $src = $image->getPath();
        }

        list($width, $height) = $this->isRetinaClient() ? $retinaSizes : $normalSizes;
        $imageUrl = $this->container->get('zend_router')->assemble(array(
            'src' => $this->container->get('image')->getSrc($src, $width, $height, 'fit'),
        ), 'image', false, false);

        return $imageUrl;
    }

    /**
     * Get comments count
     *
     * @param mixed Article
     * @param bool $recommended
     * @return int
     */
    public function getCommentsCount($article, $recommended = false)
    {
        $constraints = array('thread' => $article->getNumber());

        if ($recommended) {
            $constraints['recommended'] = 1;
        }

        return $this->container->get('comment')->countBy($constraints);
    }

    /**
     * Format article for api
     *
     * @param mixed $articlegetImageUrl
     * @return array
     */
    public function formatArticle($article)
    {
        $renderSlideshowHelper = $this->container
            ->get('newscoop_tageswochemobile_plugin.render_slideshow_helper');

        $data = array(
            'article_id' => $article->getNumber(),
            'url' => $this->getArticleUrl($article),
            'backside_url' => $this->getArticleUrl($article, 'back'),
            'dateline' => $this->getDateline($article),
            'short_name' => $this->getShortname($article),
            'published' => $this->formatDate($article->getPublishDate()),
            'rank' => $this->rank++,
            'website_url' => $this->getWebsiteUrl($article),
            'image_url' => $this->getImageUrl($article),
            'comments_enabled' => $article->commentsEnabled(),
            'comment_count' => $this->getCommentsCount($article),
            'recommended_comment_count' => $this->getCommentsCount($article, true),
            'comment_url' => $this->getCommentsUrl($article),
            'topics' => $this->getTopics($article),
            'slideshow_images' => $renderSlideshowHelper->direct($article->getNumber()),
            'teaser' => $this->getTeaser($article),
            'facebook_teaser' => $this->getTeaser($article, 'social'),
            'twitter_teaser' => $this->getTeaser($article, 'social'),
            'link' => (bool) ($article->getType() == 'link')
        );

        if ($article->getType() == 'link') {
            $data['url'] = $article->getData('link_url');
        }

        if ($this->isAd($article)) {
            $data['advertisement'] = true;
            $data['image_url'] = $this->getAdImageUrl($article);
            $data['short_name'] = $this->getArticleField($article, 'ad_name') ? ucwords((string) $this->getArticleField($article, 'ad_name')) : 'Anzeige';
            $data['url'] = $this->getArticleField($article, 'hyperlink');
        }

        return $data;
    }

    /**
     * Get section name
     *
     * @param Newscoop\Entity\Article $article
     * @return string
     */
    public function getSectionName($article)
    {
        try {
            if ($this->isAd($article)) {
                return $article->getData(self::AD_SECTION) ? ucwords((string) $article->getData(self::AD_SECTION)) : 'Anzeige';
            } else {
                return ($this->getArticleField($article, 'printsection')) ? ucwords((string) $this->getArticleField($article, 'printsection')) : '';
            }
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Test if article is prominent
     *
     * @param Newscoop\Entity\Article $article
     * @return bool
     */
    public function isProminent($article)
    {
        try {
            return $this->isAd($article) || $article->getData(self::PROMINENT_SWITCH);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get story name
     *
     * @param Newscoop\Entity\Article $article
     * @return string
     */
    public function getStoryName($article)
    {
        try {
            return ucwords((string) $article->getData('printstory'));
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get cover image url
     *
     * @param Newscoop\Entity\Article $issue
     * @return string
     */
    public function getCoverUrl(Article $issue)
    {
        $image = $issue->getFirstImage($issue);
        if ($image) {
            return $this->serverUrl(
                $this->getLocalImageUrl($image, array(145, 201), array(290, 402))
            );
        }
    }

    /**
     * Get article image url
     *
     * @param Newscoop\Entity\Article $article
     * @return string
     */
    public function getArticleImageUrl($article)
    {
        if ($this->isProminent($article) || $this->isAd($article)) {
            return $this->getRenditionUrl($article, 'mobile_prominent', array(300, 100), array(600, 200));
        } else {
            return $this->getRenditionUrl($article, 'mobile_non_prominent', array(100, 100), array(200, 200));
        }
    }

    /**
     * Format datetime
     *
     * @param DateTime $date
     * @return string
     */
    public function formatDate(DateTime $date)
    {
        $date->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Get teaser
     *
     * @param Newscoop\Entity\Article $article
     * @return string
     */
    public function getTeaser($article, $option = false)
    {
        if ($option == 'social') {
            try {
                $field = isset($this->fields['social_teaser'][$article->getType()])
                    ? $this->fields['social_teaser'][$article->getType()]
                    : 'teaser';
                return strip_tags(trim($article->getData($field)));
            } catch (\Exception $e) {
                return strip_tags(trim($article->getTitle()));
            }
        }

        try {
            $field = isset($this->fields['teaser'][$article->getType()])
                ? $this->fields['teaser'][$article->getType()]
                : 'teaser';
            return strip_tags(trim($article->getData($field)));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get short teaser
     *
     * @param Newscoop\Entity\Article $article
     * @return string
     */
    public function getTeaserShort($article)
    {
        $field = ($article->getType() == 'blog') ? 'teaser' : 'seo_title';

        try {
            $teaserShort = $this->getArticleField($article, $field);
        } catch (Exception $e) {
            $teaserShort = $article->getTitle();
        }

        if (!empty($teaserShort)) {
            return substr($teaserShort, 0, 120);
        }

        return null;
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
     * Get id for string
     *
     * @param Newscoop\Entity\Article $article
     * @return int
     */
    public function getSectionId($article)
    {
        try {
            $key = ($this->getArticleField($article, 'printsection')) ? ucwords((string) $this->getArticleField($article, 'printsection')) : $article->getNumber();
        } catch (\Exception $e) {
            $key = $article->getNumber();
        }

        if (!isset($this->ids[$key])) {
            $this->ids[$key] = count($this->ids) + 1;
        }

        return $this->ids[$key];
    }

    /**
     * Get body
     *
     * @param Newscoop\Entity\Article $article
     * @return string
     */
    public function getBody($article)
    {
        try {
            $field = isset($this->fields['body'][$article->getType()])
                ? $this->fields['body'][$article->getType()]
                : 'body';
            return strip_tags(trim($article->getData($field)));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get sources
     *
     * @param Newscoop\Entity\Article $article
     * @return string
     */
    public function getSources($article)
    {
        try {
            $field = isset($this->fields['sources'][$article->getType()])
                ? $this->fields['sources'][$article->getType()]
                : 'sources';
            return strip_tags(trim($article->getData($field)));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Test if client is iphone
     *
     * @return bool
     */
    public function isIphoneClient()
    {
        return strpos($this->_getParam('client', 'iphone'), 'iphone') !== false;
    }

    /**
     * Get client width
     *
     * @return int
     */
    public function getClientWidth()
    {
        if (empty($this->clientSize)) {
            $this->initClientSize();
        }

        return $this->clientSize['width'];
    }

    /**
     * Get client height
     *
     * @return int
     */
    public function getClientHeight()
    {
        if (empty($this->clientSize)) {
            $this->initClientSize();
        }

        return $this->clientSize['height'];
    }

    /**
     * Init client size
     *
     * @return void
     */
    private function initClientSize()
    {
        $this->clientSize = array(
            'width' => self::IMAGE_STANDARD_WIDTH,
            'height' => self::IMAGE_STANDARD_HEIGHT,
        );

        if ($this->isRetinaClient()) {
            $this->clientSize['width'] *= self::IMAGE_RETINA_FACTOR;
            $this->clientSize['height'] *= self::IMAGE_RETINA_FACTOR;
        }
    }

    /**
     * Test if client is retina
     *
     * @return bool
     */
    public function isRetinaClient()
    {
        if ($this->request !== null) {
            return strpos($this->request->query->get('client', self::CLIENT_DEFAULT), 'retina') !== false;
        }
        return null;
    }

    /**
     * Get article shortname
     *
     * @param Newscoop\Entity\Article $article
     * @return string
     */
    public function getShortname($article)
    {
        try {
            $shortname = $article->getData('short_name');
            return !empty($shortname) ? $shortname : $article->getTitle();
        } catch (\Exception $e) {
            return $article->getTitle();
        }
    }

    /**
     * Get current issue product id
     *
     * @return string
     */
    public function getCurrentIssueProductId()
    {
        $issue = $this->container->get('newscoop_tageswochemobile_plugin.mobile.issue')->findCurrent();
        $date = $this->getArticleField($issue, 'issuedate')
            ? new DateTime($this->getArticleField($issue, 'issuedate'))
            : $issue->getPublished();
        return sprintf(
            'ch.tageswoche.issue.%d.%02d',
            $date->format('Y'),
            $this->getArticleField($issue, 'issue_number')
        );
    }

    /**
     * Get article field
     *
     * @param Article $article
     * @param string $field
     * @return mixed
     */
    public function getArticleField($article, $field)
    {
        try {
            return $article->getData($field);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get rendition image url
     *
     * @param Newscoop\Entity\Article $article
     * @param string $rendition
     * @param array $normalSizes
     * @param array $retinaSizes
     * @return string
     */
    public function getRenditionUrl($article, $rendition, array $normalSizes, array $retinaSizes)
    {
        list($width, $height) = $this->isRetinaClient() ? $retinaSizes : $normalSizes;
        $image = $this->container->get('image.rendition')->getArticleRenditionImage($article->getNumber(), $rendition, $width, $height);
        if (empty($image['src'])) {
            return null;
        }

        $src = $this->container->get('zend_router')->assemble(
            array('src' => $image['src']), 'image', true, false
        );

        //$src = Zend_Registry::get('view')->url(array('src' => $image['src']), 'image', true, false);
        return $this->serverUrl($src);
    }

    /**
     * Get user subscription info
     *
     * @param Newscoop\Entity\User $user
     * @return array
     */
    public function getUserSubscriptionInfo($user)
    {
        $view = $this->container->get('newscoop_tageswochemobile_plugin.user_subscription')->getView($user);

        foreach ($view as $key => $val) {
            if ($val instanceof DateTime) {
                $view->$key = $val->format('Y-m-d');
            }
        }

        return (array) $view;
    }

    /**
     * Get user type
     *
     * @param Newscoop\Entity\User $user
     * @return string
     */
    public function getUserType(User $user)
    {
        foreach($this->container->get('user.list')->findEditors() as $editor) {
            if ($editor->getId() == $user->getId()) {
                return self::TYPE_EDITOR;
            }
        }

        // TODO: figure out how to do this with latest version
        if ($this->container->get('blog')->isBlogger($user)) {
            return self::TYPE_BLOGGER;
        }

        return self::TYPE_MEMBER;
    }

    /**
     * Get user image url
     *
     * @param Newscoop\Entity\User $user
     * @param array $normalSizes
     * @param array $retinaSizes
     * @return string
     */
    public function getUserImageUrl($user, array $normalSizes, array $retinaSizes)
    {
        list($width, $height) = $this->isRetinaClient() ? $retinaSizes : $normalSizes;

        if ($user === null || $user->getImage() === null) {
            return $this->serverUrl('/themes/publication_1/theme_1/_css/tw2011/img/user_blank_'.$width.'x'.$height.'.png');
        }

        $imageUrl = $this->container->get('zend_router')->assemble(array(
            'src' => $this->container->get('image')->getSrc('images/'.$user->getImage(), $width, $height, 'fit'),
        ), 'image', true, false);

        return $this->serverUrl($imageUrl);
    }

    /**
     * Get client identification
     *
     * @return string
     */
    public function getClient()
    {
        return strtolower($this->_getParam('client', self::CLIENT_DEFAULT));
    }

    /**
     * Test if article is advertisement
     *
     * @param Newscoop\Entity\Article $article
     * @return bool
     */
    public function isAd($article)
    {
        return $article->getType() === self::AD_TYPE;
    }

    /**
     * Get list of ads (articleType == iPad_Ad)
     *
     * @param string switch
     * @return array
     */
    public function getArticleListAds($switch = null)
    {
        $listAds = array();
        $ads =$this->em->getRepository('Newscoop\Entity\Article')
            ->findBy(array('type' => self::AD_TYPE), array('articleOrder' => 'asc'));
        foreach ($ads as $ad) {
            try {
                if ($ad->getData('active')) {
                    if ($switch) {
                        if ($ad->getData($switch)) {
                            $listAds[] = $ad;
                        }
                    } else {
                        $listAds[] = $ad;
                    }
                }
            } catch (InvalidPropertyException $e) { // ignore
            }
        }

        return $listAds;
    }

    /**
     * Init client property
     *
     * @return void
     */
    public function initClient($client)
    {
        $type = null;
        if (strstr($client, 'ipad')) {
            $type = 'ipad';
        } elseif (strstr($client, 'iphone')) {
            $type = 'iphone';
        }

        $this->client = array(
            'name' => $client,
            'type' => $type,
        );
    }

    /**
     * Get ip address of client
     *
     * @return string ip addres
     */
    public function getIp() {
        return $_SERVER['REMOTE_ADDR'];
    }

    public function absoluteUrl($relativeUrl)
    {
        if (strpos($relativeUrl, 'http://') === 0 || strpos($relativeUrl, 'https://') === 0) {
            return $relativeUrl;
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https://' : 'http://';

        return $protocol . $_SERVER['HTTP_HOST'] . ((substr($relativeUrl, 0, 1) == '/') ? $relativeUrl : '/' . $relativeUrl);
    }

    public function serverUrl($relativeUrl)
    {
        return $this->absoluteUrl($relativeUrl);
    }

    public function apiUrl($relativeUrl, $makeAbsolute=true)
    {
        $relativeUrl = '/api/' . $relativeUrl;
        return ($makeAbsolute) ? absoluteUrl($relativeUrl) : $relativeUrl;
    }

    public function _getParam($param)
    {
        if ($this->request !== null) {
            if ($this->request->request->get($param)) {
                return $this->request->request->get($param);
            }
            if ($this->request->query->get($param)) {
                return $this->request->query->get($param);
            }
        }

        return null;
    }
}
