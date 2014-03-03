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
use Newscoop\Entity\User;
use Newscoop\Entity\Article;
use Newscoop\Entity\ArticleTopic;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Route('/topics')
 */
class TopicsController extends Controller
{
    const PUBLICATION = 5;
    const LANGUAGE = 5;
    const ARTICLE_TOPICS = 412233;

    /** @var Zend_Controller_Request_Http */
    private $request;

    /**
     * @Route("/index")
     * @Route("/list")
     */
    public function listAction()
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $response = array();

        if ($apiHelperService->hasAuthInfo()) {
            $user = $apiHelperService->getUser();
            if (!($user instanceof User)) {
                return $user !== null ? $user : $apiHelperService->sendError('Invalid credentials.', 401);
            }
            $topicsTemp = $this->container->get('user.topic')->getTopics($user);
            $topics = array();
            foreach ($topicsTemp as $item) {
                $topics[] = new Topic($item->getTopicId());
            }
        } else {
            $topics = \ArticleTopic::GetArticleTopics(self::ARTICLE_TOPICS);
        }

        foreach ($topics as $topic) {
            $response[] = array(
                'id' => (int) $topic->getTopicId(),
                'topic_name' => $topic->getName(self::LANGUAGE),
            );
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/subscribe")
     * @Method("POST")
     */
    public function subscribeAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        if (!$apiHelperService->isSecure()) {
            return $apiHelperService->sendError('Secure connection required', 400);
        }

        $this->container->get('user.topic')->followTopic($apiHelperService->getUser(), $this->getTopic($request->request->get('topic_id')));
        return new JsonResponse(array(
            'status' => 200,
        ));
    }

    /**
     * @Route("/unsubscribe")
     * @Method("POST")
     */
    public function unsubscribeAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        if (!$apiHelperService->isSecure()) {
            return $apiHelperService->sendError('Secure connection required', 400);
        }

        $this->container->get('user.topic')->unfollowTopic($apiHelperService->getUser(), $apiHelperService->getTopic($request->request->get('topic_id')));
        return new JsonResponse(array(
            'status' => 200,
        ));
    }

    /**
     * @Route("/my_topics")
     */
    public function mytopicsAction()
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $user = $apiHelperService->getUser();
        if (!($user instanceof User)) {
                return $user !== null ? $user : $apiHelperService->sendError('Invalid credentials.', 401);
        }

        $articles = new ArrayCollection();
        foreach ($this->container->get('user.topic')->getTopics($user) as $topic) {
            foreach ($em->getRepository('Newscoop\Entity\Article')->getArticlesForTopic(self::PUBLICATION, $topic->getTopicId()) as $article) {
                $articles->add(array_merge($apiHelperService->formatArticle($article), array(
                    'topic_id' => (int) $topic->getTopicId(),
                    'topic_name' => $topic->getName(),
                    'topic_url' => $this->getTopicUrl($topic),
                )));
            }
        }

        return new JsonResponse($articles->toArray());
    }

    /**
     * Get topic
     *
     * @return Newscoop\Entity\UserTopic
     */
    private function getTopic($topicId)
    {
        $topic = $this->container->get('user.topic')->findTopic($topicId);
        if (!$topic) {
            return $apiHelperService->sendError('Topic not found.', 404);
        }

        return $topic;
    }
}
