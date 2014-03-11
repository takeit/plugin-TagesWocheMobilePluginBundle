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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Newscoop\Entity\Article;
use Newscoop\Entity\User;
use Newscoop\TagesWocheMobilePluginBundle\Debate\VoteDebateCommand;
use Newscoop\TagesWocheMobilePluginBundle\Form\Type\VoteType;
use Newscoop\TagesWocheMobilePluginBundle\Services\ApiHelper;
use DateTime;

/**
 * Route('/debates')
 *
 * Api Debates service controller.
 */
class DebatesController extends Controller
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
     * @Route("/list")
     *
     * Returns list of debates
     */
    public function listAction()
    {
        $debateService = $this->container->get('newscoop_tageswochemobile_plugin.debate');
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $response = array();

        foreach ($debateService->findAllPublished() as $debate) {

            list($pro, $con) = $debateService->getVotes($debate);
            $response[] = array(
                'debate_id' => $debate->getNumber(),
                'url' => $apiHelperService->serverurl(
                    $this->container->get('zend_router')->assemble(array(
                        'module' => 'api',
                        'controller' => 'debates',
                        'action' => 'index',
                    )) . $apiHelperService->getApiQueryString(array(
                        'debate_id' => $debate->getNumber(),
                    ))
                ),
                'title' => $debate->getTitle(),
                'image_url' => $apiHelperService
                    ->getRenditionUrl($debate, 'rubrikenseite', array(105, 70), array(210, 140)),
                'total_pro_votes_percent' => $pro,
                'total_con_votes_percent' => $con,
                'pro_vote_display_name' => $debateService->findStatement($debate, true)->getName(),
                'con_vote_display_name' => $debateService->findStatement($debate, false)->getName(),
                'comment_count' => $apiHelperService->getCommentsCount($debate),
                'published' => $apiHelperService->formatDate($this->getPublishDate($debate)),
                'year' => (int) $this->getPublishDate($debate)->format('Y'),
                'month' => (int) $this->getPublishDate($debate)->format('n'),
                'week' => (int) $this->getPublishDate($debate)->format('W'),
                'rank' => $this->rank++,
            );
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/current")
     */
    public function currentAction(Request $request)
    {
        $request->query->set(
            'debate_id',
            (string) $this->container
                ->get('newscoop_tageswochemobile_plugin.debate')
                ->findCurrentDebateId()
        );

        return $this->forward('NewscoopTagesWocheMobilePluginBundle:Debates:index', array(
            'request' => $request
        ));
    }

    /**
     * @Route("")
     */
    public function indexAction(Request $request)
    {
        $debate_id = $request->query->get('debate_id');
        $device_id = $request->query->get('device_id');

        $debateService = $this->container->get('newscoop_tageswochemobile_plugin.debate');
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        if ($debate_id === null) {
            return $apiHelperService->sendError('Missing debate_id param.', 400);
        }

        $debate = $debateService->findPublished($debate_id);
        if ($debate === null) {
            return $apiHelperService->sendError('Debate not found.', 404);
        }

        $user = null;
        if ($apiHelperService->hasAuthInfo()) {
            $user = $apiHelperService->getUser();
        }

        list($pro, $con) = $debateService->getVotes($debate);

        $response = array_merge(array(
            'debate_id' => $debate->getNumber(),
            'title' => $debate->getTitle(),
            'image_url' => $apiHelperService->getRenditionUrl($debate, 'topfront', array(320, 140), array(640, 280)),
            'my_vote' => $debateService->getVote($debate->getNumber(), $device_id, $user),
            'pro_vote_display_name' => $debateService->findStatement($debate, true)->getName(),
            'con_vote_display_name' => $debateService->findStatement($debate, false)->getName(),
            'vote_url' => $apiHelperService->serverUrl($this->generateUrl('newscoop_tageswochemobileplugin_vote_url')),
            'start_date' => $apiHelperService->getArticleField($debate, 'date_opening'),
            'intro_url' => $apiHelperService->getArticleUrl($debate, ApiHelper::FRONT_SIDE, array('stage' => '0')),
            'intro_website_url' => $apiHelperService->getWebsiteUrl($debate) . '?stage=0',
            'conclusion_url' => $apiHelperService->getArticleUrl($debate, ApiHelper::FRONT_SIDE, array('stage' => '4')),
            'conclusion_website_url' => $apiHelperService->getWebsiteUrl($debate) . '?stage=4',
            'conclusion_date' => $apiHelperService->getArticleField($debate, 'date_closing'),
            'conclusion_image_url' => $apiHelperService->serverUrl(
                $apiHelperService->getLocalImageUrl($this->getAuthorImage($debate), array(70, 70), array(140, 140))
            ),
            'comment_count' => $apiHelperService->getCommentsCount($debate),
            'recommended_comment_count' => $apiHelperService->getCommentsCount($debate, true),
            'comment_url' => $apiHelperService->getCommentsUrl($debate),
            'comments_enabled' => $debate->commentsEnabled() && !$debate->commentsLocked(),
            'total_pro_votes_percent' => $pro,
            'total_con_votes_percent' => $con,
            'current_stage' => $this->getCurrentStage($debate),
            'stages' => $this->getStages($debate),
        ), $this->getPro($debate), $this->getCon($debate));

        return new JsonResponse($response);
    }

    /**
     * @Route("/vote", name="newscoop_tageswochemobileplugin_vote_url")
     * @Method("POST")
     */
    public function voteAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $debateService = $this->container->get('newscoop_tageswochemobile_plugin.debate');

        if ($apiHelperService->hasAuthInfo()) {
            $user = $apiHelperService->getUser();

            if (!($user instanceof User)) {
                return $user !== null ? $user : $apiHelperService->sendError('Invalid credentials.', 401);
            }
        }

        $voteDebateCommand = new VoteDebateCommand();
        $voteDebateCommand->debateId = $request->request->get('debate_id');
        $voteDebateCommand->vote = $request->request->get('vote');
        $voteDebateCommand->deviceId = $request->request->get('device_id');
        $voteDebateCommand->userId = $user->getId();

        $errors = $this->container->get('validator')->validate($voteDebateCommand);

        if (count($errors) === 0) {
            $debateService->vote($voteDebateCommand);
            $response = new JsonResponse(array(), 200);
        } else {
            $response = $apiHelperService->sendError($errors[0]->getMessage(), 500);
        }

        return $response;
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
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $nowDateTime = new DateTime('now');
        $now = $nowDateTime->format('Y-m-d');

        for ($i = 1; $i < count($this->stages); $i++) {
            if ($now >= $apiHelperService->getArticleField($debate, $this->stages[$i - 1])
                && $now < $apiHelperService->getArticleField($debate, $this->stages[$i])) {
                return $i - 1;
            }

            if ($i == 3 && $now == $apiHelperService->getArticleField($debate, $this->stages[$i])) {
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
        $statement = $this->container
            ->get('newscoop_tageswochemobile_plugin.debate')
            ->findStatement($debate, true);
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
        $statement = $this->container
            ->get('newscoop_tageswochemobile_plugin.debate')
            ->findStatement($debate, false);
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
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        foreach ($article->getArticleAuthors() as $author) {
            return array(
                $prefix . 'author_name' => $author->getFullName(),
                $prefix . 'author_profile_url' => null,
                $prefix . 'short_bio' => $author->getBiography() ?: null,
                $prefix . 'author_image_url' => $apiHelperService->serverUrl(
                    $apiHelperService->getLocalImageUrl($author->getImage(), array(70, 70), array(140, 140))
                ),
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
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $stages = array();

        for ($i = 1; $i <= 3; $i++) {

            list($pro, $con) = $this->container
                ->get('newscoop_tageswochemobile_plugin.debate')
                ->getVotes($debate, $i);

            $stages[] = array(
                'date' => $apiHelperService->getArticleField($debate, $this->stages[$i-1]),
                'pro_percent' => $pro,
                'con_percent' => $con,
                'article_url' => $apiHelperService->getArticleUrl($debate, ApiHelper::FRONT_SIDE, array('stage' => $i)),
                'index' => $i - 1,
                'website_url' => $apiHelperService->getWebsiteUrl($debate) . '?stage=' . $i,
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
        $url = $this->container
            ->get('newscoop_tageswochemobile_plugin.api_helper')
            ->getWebsiteUrl($debate);
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
        foreach ($article->getArticleAuthors() as $author) {
            return $author->getImage();
        }
    }
}
