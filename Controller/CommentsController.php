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
        $userRepository = $em->getRepository('Newscoop\Entity\User');

        $article_id = $request->query->get('article_id');
        $comments = array();

        if (is_null($article_id)) {
            $url = '/content-api/comments.json?items_per_page=20&sort[created]=asc';
        } else {
            $url = str_replace('{number}', $article_id, '/content-api/comments/article/{number}/de/asc.json?items_per_page=10000');
        }

        try {
            $ch = curl_init('http://' . $_SERVER['HTTP_HOST'] . $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER , CURLINFO_HTTP_CODE);
            $response = curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            if ($info['http_code'] == 200) {
                $comments = json_decode($response, true);
                if (count($comments)) {
                    $comments = $comments['items'];
                } else {
                    $comments = array();
                }
            }
        } catch(Exception $e) {
            throw new Exception('Could not connect with api.');
        }

        $response = array();
        $rank = 0;
        foreach ($comments as $comment) {

            $created = new DateTime($comment['created']);
            $modified = new DateTime($comment['updated']);

            try {
                $user = $userRepository->findOneById($comment['commenter']['id']);
            } catch (Exception $e) {
                $user = null;
            }

            $response[] = array(
                'author_name' => strip_tags($comment['author']),
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
                'subject' => $comment['subject'],
                'message'=> $comment['message'],
                'recommended' => ($comment['recommended'] == '1')  ? true : false,
                'created_time' => $created->format('Y-m-d H:i:s'),
                'last_modified' => $modified->format('Y-m-d H:i:s'),
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
        $userService = $this->container->get('user');

        $parameters = $request->request->all();

        $user = $apiHelperService->getUser();

        if (!($user instanceof User)) {
            return $user !== null ? $user : $apiHelperService->sendError('Invalid credentials.', 401);
        }

        if (isset($parameters['article_id']) && isset($parameters['message'])) {

            $article = new Article(self::LANGUAGE, $parameters['article_id']);
            $acceptanceRepository = $em->getRepository('Newscoop\Entity\Comment\Acceptance');

            if (!$acceptanceRepository->checkParamsBanned($user->getName(), $user->getEmail(), $userService->getUserIp(), $article->getPublicationId())) {

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
