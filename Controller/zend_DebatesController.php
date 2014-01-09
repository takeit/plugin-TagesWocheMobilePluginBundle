<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

use Newscoop\Entity\Article;
use Tageswoche\Debate\VoteDebateCommand;

require_once __DIR__ . '/AbstractController.php';
require_once __DIR__ . '/../forms/Vote.php';

/**
 * Api Debates service controller.
 */
class Api_DebatesController extends AbstractController
{
    const IMAGE_RENDITION = 'dossierteaser';

    /**
     * @var array
     */
    private $answers = array(
        'yes' => 1,
        'no' => 2,
    );

    /**
     * @var int
     */
    private $rank = 1;

    /**
     * @var array
     */
    private $stages = array(
        'date_opening',
        'date_rebuttal',
        'date_final',
        'date_closing',
    );

    /**
     * Returns list of debates.
     *
     * @return json
     */
    public function listAction()
    {
        $response = array();
        foreach ($this->_helper->service('debate')->findAllPublished() as $debate) {
            list($pro, $con) = $this->_helper->service('debate')->getVotes($debate);
            $response[] = array(
                'debate_id' => $debate->getNumber(),
                'url' => $this->view->serverUrl($this->view->url(array(
                    'module' => 'api',
                    'controller' => 'debates',
                    'action' => 'index',
                )) . $this->getApiQueryString(array(
                    'debate_id' => $debate->getNumber(),
                ))),
                'title' => $debate->getTitle(),
                'image_url' => $this->getRenditionUrl($debate, 'rubrikenseite', array(105, 70), array(210, 140)),
                'total_pro_votes_percent' => $pro,
                'total_con_votes_percent' => $con,
                'pro_vote_display_name' => $this->_helper->service('debate')->findStatement($debate, true)->getName(),
                'con_vote_display_name' => $this->_helper->service('debate')->findStatement($debate, false)->getName(),
                'comment_count' => $this->getCommentsCount($debate),
                'published' => $this->formatDate($this->getPublishDate($debate)),
                'year' => (int) $this->getPublishDate($debate)->format('Y'),
                'month' => (int) $this->getPublishDate($debate)->format('n'),
                'week' => (int) $this->getPublishDate($debate)->format('W'),
                'rank' => $this->rank++,
            );
        }

        $this->_helper->json($response);
    }

    public function indexAction()
    {
        if ($this->_getParam('debate_id') === null) {
            $this->sendError('Missing debate_id param.', 400);
        }

        $debate = $this->_helper->service('debate')->findPublished($this->_getParam('debate_id'));
        if (empty($debate)) {
            //$this->sendError('Debate not found.', 404);
        }

        $user = null;
        if ($this->hasAuthInfo()) {
            $user = $this->getUser();
        }

        list($pro, $con) = $this->_helper->service('debate')->getVotes($debate);
        $this->_helper->json(array_merge(array(
            'debate_id' => $debate->getNumber(),
            'title' => $debate->getTitle(),
            'image_url' => $this->getRenditionUrl($debate, 'topfront', array(320, 140), array(640, 280)),
            'my_vote' => $this->_helper->service('debate')->getVote($debate->getNumber(), $this->_getParam('device_id'), $user),
            'pro_vote_display_name' => $this->_helper->service('debate')->findStatement($debate, true)->getName(),
            'con_vote_display_name' => $this->_helper->service('debate')->findStatement($debate, false)->getName(),
            'vote_url' => $this->view->serverUrl($this->view->url(array('action' => 'vote'))),
            'start_date' => $this->getArticleField($debate, 'date_opening'),
            'intro_url' => $this->getArticleUrl($debate, self::FRONT_SIDE, array('stage' => '0')),
            'intro_website_url' => $this->getWebsiteUrl($debate) . '?stage=0',
            'conclusion_url' => $this->getArticleUrl($debate, self::FRONT_SIDE, array('stage' => '4')),
            'conclusion_website_url' => $this->getWebsiteUrl($debate) . '?stage=4',
            'conclusion_date' => $this->getArticleField($debate, 'date_closing'),
            'conclusion_image_url' => $this->getLocalImageUrl($this->getAuthorImage($debate), array(70, 70), array(140, 140)),
            'comment_count' => $this->getCommentsCount($debate),
            'recommended_comment_count' => $this->getCommentsCount($debate, true),
            'comment_url' => $this->getCommentsUrl($debate),
            'comments_enabled' => $debate->commentsEnabled() && !$debate->commentsLocked(),
            'total_pro_votes_percent' => $pro,
            'total_con_votes_percent' => $con,
            'current_stage' => $this->getCurrentStage($debate),
            'stages' => $this->getStages($debate),
        ), $this->getPro($debate), $this->getCon($debate)));
    }

    public function currentAction()
    {
        $this->getRequest()->setParam('debate_id', (string) $this->_helper->service('debate')->findCurrentDebateId());
        $this->_forward('index');
    }

    public function voteAction()
    {
        if ($this->hasAuthInfo()) {
            $user = $this->getUser();
        }

        if (!$this->getRequest()->isPost()) {
            $this->sendError('POST required');
        }

        $voteDebateCommand = new VoteDebateCommand();
        $voteDebateCommand->debateId = $this->_getParam('debate_id');
        $voteDebateCommand->vote = $this->_getParam('vote');
        $voteDebateCommand->deviceId = $this->_getParam('device_id');
        $voteDebateCommand->userId = isset($user) ? $user->getId() : null;

        $form = new Api_Form_Vote();
        if ($form->isValid((array) $voteDebateCommand)) {
            $this->_helper->service('debate')->vote($voteDebateCommand);
            $this->getResponse()->setHttpResponseCode(200);
            $this->getResponse()->sendResponse();
            exit;
        } else {
            $this->sendError($form->getErrors());
        }
    }

    /**
     * Gets the debate publish date as DateTime object.
     *
     * @param Newscoop\Entity\Article $article
     * @return DateTime
     */
    private function getPublishDate(Article $article)
    {
        $date = $article->getData('date_opening');
        return new DateTime($date);
    }

    /**
     * Get current stage of a debate
     *
     * @param Newscoop\Entity\Article $debate
     * @return int
     */
    protected function getCurrentStage(Article $debate)
    {
        $nowDateTime = new DateTime('now');
        $now = $nowDateTime->format('Y-m-d');

        for ($i = 1; $i < count($this->stages); $i++) {
            if ($now >= $this->getArticleField($debate, $this->stages[$i - 1])
                && $now < $this->getArticleField($debate, $this->stages[$i])) {
                return $i - 1;
            }

            if ($i == 3 && $now == $this->getArticleField($debate, $this->stages[$i])) {
                $nowDateTime->setTime(12, 0); // on conclusion date it's open till 12h
                if ($nowDateTime->getTimestamp() <= time()) {
                    return $i - 1;
                }
            }
        }
    }

    /**
     * Get pro object
     *
     * @param Newscoop\Entity\Article $debate
     * @return array
     */
    protected function getPro(Article $debate)
    {
        $statement = $this->_helper->service('debate')->findStatement($debate, true);
        return $this->getAuthorInfo($statement, 'pro_');
    }

    /**
     * Get con object
     *
     * @param Newscoop\Entity\Article $debate
     * @return array
     */
    protected function getCon(Article $debate)
    {
        $statement = $this->_helper->service('debate')->findStatement($debate, false);
        return $this->getAuthorInfo($statement, 'con_');
    }

    /**
     * Get author info
     *
     * @param Newscoop\Entity\Article $article
     * @param string $prefix
     * @return array
     */
    protected function getAuthorInfo($article, $prefix)
    {
        foreach ($article->getAuthors() as $author) {
            return array(
                $prefix . 'author_name' => $author->getFullName(),
                $prefix . 'author_profile_url' => null,
                $prefix . 'short_bio' => $author->getBiography() ?: null,
                $prefix . 'author_image_url' => $this->getLocalImageUrl($author->getImage(), array(70, 70), array(140, 140)),
            );
        }
    }

    /**
     * Get debate stages
     *
     * @param Newscoop\Entity\Article $debate
     * @return array
     */
    protected function getStages(Article $debate)
    {
        $stages = array();
        for ($i = 1; $i <= 3; $i++) {
            list($pro, $con) = $this->_helper->service('debate')->getVotes($debate, $i);
            $stages[] = array(
                'date' => $this->getArticleField($debate, $this->stages[$i-1]),
                'pro_percent' => $pro,
                'con_percent' => $con,
                'article_url' => $this->getArticleUrl($debate, self::FRONT_SIDE, array('stage' => $i)),
                'index' => $i - 1,
                'website_url' => $this->getWebsiteUrl($debate) . '?stage=' . $i,
            );
        }

        return $stages;
    }

    /**
     * Get debate website url
     *
     * @param Newscoop\Entity\Article $debate
     * @return string
     */
    protected function getWebsiteUrl(Article $debate)
    {
        $url = parent::getWebsiteUrl($debate);
        $pieces = explode($debate->getNumber(), $url);
        return $pieces[0];
    }

    /**
     * Get author image
     *
     * @param Newscoop\Entity\Article $article
     * @return Newscoop\Image\LocalImage
     */
    protected function getAuthorImage(Article $article)
    {
        foreach ($article->getAuthors() as $author) {
            return $author->getImage();
        }
    }
}
