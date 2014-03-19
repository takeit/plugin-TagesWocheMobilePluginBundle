<?php
/**
 * @package Newscoop
 * @copyright 2013 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Newscoop\Entity\Comment;
use Newscoop\Entity\User;
use Article;
use DateTime;

/**
 * Route('/comments')
 */
class CommentsController extends Controller
{
    const LANGUAGE = 5;
    const LIST_LIMIT = 20;

    const IMAGE_STANDARD_WIDTH = 35;
    const IMAGE_STANDARD_HEIGHT = 35;

    const IMAGE_RETINA_WIDTH = 70;
    const IMAGE_RETINA_HEIGHT = 70;

    /**
     * @Route("/comments/list")
     * @Method("GET")
     *
     * Lists comments.
     *
     * If article identifier given, returns all comments posted for the article,
     * otherwise returns the latest 20 posted comments.
     */
    public function listAction(Request $request)
    {
        $em = $this->container->get('em');
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $repository = $em->getRepository('Newscoop\Entity\Comment');

        $article_id = $request->query->get('article_id');
        if (is_null($article_id)) {
            $comments = $repository->findBy(array('status' => 0), array('time_created' => 'desc'), self::LIST_LIMIT);
        } else {
            $comments = $repository->findBy(array('article_num' => $article_id, 'status' => 0), array('time_created' => 'desc'));
        }

        $response = array();
        $rank = 0;
        foreach($comments as $comment) {
            $created = $comment->getTimeCreated()->format('Y-m-d H:i:s');
            $modified = $created;

            if (
                $comment->getTimeUpdated() !== null &&
                $comment->getTimeUpdated()->getTimestamp() !== false &&
                $comment->getTimeCreated()->getTimestamp() < $comment->getTimeUpdated()->getTimestamp()
            ) {
                $modified = $comment->getTimeUpdated()->format('Y-m-d H:i:s');
            }

            $user = $comment->getCommenter()->getUser();

            $response[] = array(
                'author_name' => ($user !== null) ? $user->getUsername() : 'Unbekannt',
                'author_image_url' => $apiHelperService->getUserImageUrl(
                        $user,
                        $this->getImageSizesNormal(),
                        $this->getImageSizesRetina()
                    ),
                'public_profile_url' => $apiHelperService->serverUrl(
                    $this->container->get('zend_router')->assemble(array(
                        'module' => 'api',
                        'controller' => 'profile',
                        'action' => 'public',
                        ), 'default') . $apiHelperService->getApiQueryString(array(
                            'user' => ($user !== null) ? $user->getId() : '',
                        ))
                ),
                'subject' => $comment->getSubject(),
                'message'=> $comment->getMessage(),
                'recommended' => $comment->getRecommended() ? true : false,
                'created_time' => $created,
                'last_modified' => $modified,
                'rank' => $rank++,
            );
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/comments")
     * @Method("POST")
     */
    public function composeAction(Request $request)
    {
        $em = $this->container->get('em');
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $parameters = $request->request->all();

        $user = $apiHelperService->getUser();

        if (!($user instanceof User)) {
            return $user !== null ? $user : $apiHelperService->sendError('Invalid credentials.', 401);
        }

        if (isset($parameters['article_id']) && isset($parameters['message'])) {

            $article = new Article(self::LANGUAGE, $parameters['article_id']);
            $acceptanceRepository = $em->getRepository('Newscoop\Entity\Comment\Acceptance');

            if (!$acceptanceRepository->checkParamsBanned($user->getName(), $user->getEmail(), $apiHelperService->getIp(), $article->getPublicationId())) {

                $comment = new Comment();
                $commentRepository = $em->getRepository('Newscoop\Entity\Comment');

                $subject = '';
                if (isset($parameters['subject'])) {
                    $subject = $parameters['subject'];
                }

                $values = array(
                    'user' => $user->getId(),
                    'name' => '',
                    'subject' => $subject,
                    'message' => $parameters['message'],
                    'language' => self::LANGUAGE,
                    'thread' => $parameters['article_id'],
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'status' => 'approved',
                    'time_created' => new DateTime(),
                    'recommended' => '0'
                );

                $commentRepository->save($comment, $values);
                $commentRepository->flush();
                $statusCode = 201;

            } else {

                $statusCode = 500;
            }

        } else {

            $statusCode = 500;
        }

        return new JsonResponse(array(), $statusCode);
    }

    /**
     * Get image sizes
     *
     * @return int
     */
    private function getImageSizesNormal()
    {
        return array(self::IMAGE_STANDARD_WIDTH, self::IMAGE_STANDARD_HEIGHT);
    }

    /**
     * Get image sizes for retina
     *
     * @return int
     */
    private function getImageSizesRetina()
    {
        return array(self::IMAGE_RETINA_WIDTH, self::IMAGE_RETINA_HEIGHT);
    }
}
