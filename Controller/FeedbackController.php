<?php
/**
 * @package Newscoop
 * @copyright 2014 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Newscoop\Entity\Feedback;
use Newscoop\Entity\User;
use DateTime;

/**
 * Route('/feedback')
 */
class FeedbackController extends Controller
{
    const PUBLICATION = 5;
    const LANGUAGE = 5;
    const MESSAGE_STATUS_PENDING = 'pending';
    const MESSAGE_STATUS_APPROVED = 'approved';


    /**
     * @Route("/index")
     * @Method("POST")
     */
    public function indexAction(Request $request)
    {
        $em = $this->container->get('em');
        $apiHelper = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $params = $request->request->all();
        $user = $apiHelper->getUser();

        if (!($user instanceof User)) {
            return $user !== null ? $user : $apiHelper->sendError('Invalid credentials.', 401);
        }

        $acceptanceRepository = $em->getRepository('Newscoop\Entity\Comment\Acceptance');

        if ($acceptanceRepository->checkParamsBanned(
                $user->getName(), $user->getEmail(), $apiHelper->getIp(), self::PUBLICATION)) {
            return $apiHelper->sendError('Invalid credentials.', 401);
        }

        if (!array_key_exists('message', $params) || empty($params['message'])) {
            return $apiHelper->sendError('Empty message.', 500);
        }

        $feedback = new Feedback();
        $feedbackRepository = $em->getRepository('Newscoop\Entity\Feedback');

        $values = array(
            'user' => $user->getId(),
            'publication' => self::PUBLICATION,
            'section' => '',
            'article' => '',
            'subject' => $params['subject'],
            'message' => $params['message'],
            'url' => 'API',
            'time_created' => new DateTime(),
            'language' => self::LANGUAGE,
            'status' => self::MESSAGE_STATUS_PENDING,
            'attachment_type' => 'none',
            'attachment_id' => 0,
        );

        $feedbackRepository->save($feedback, $values);
        $feedbackRepository->flush();

        $this->sendMail($values);

        return new JsonResponse(array(), 201);
    }


    /**
     * Sends feedback e-mail message.
     *
     * @param array $values
     *
     * @return void
     */
    public function sendMail(array $values)
    {
        $userRepository = $this->container->get('em')->getRepository('Newscoop\Entity\User');
        $user = $userRepository->find($values['user']);

        $toEmail = 'dienstpult@tageswoche.ch';
        $fromEmail = $user->getEmail();

        $body = array(
            'data' => array(
                'message' => $values['message'],
                'userName' => $user->getUsername(),
                'userRealName' => $user->getRealName(),
                'url' => $values['url'],
            )
        );

        try {
            $mail = \Swift_Message::newInstance();

            $mail->setSubject('Leserfeedback: ' . $values['subject'])
                ->setFrom($fromEmail)
                ->setTo($toEmail)
                ->setBody(
                    $this->renderView(
                        'NewscoopTagesWocheMobilePluginBundle:feedback:message.html.twig',
                        $body
                    )
                );

            $this->container->get('mailer')->send($mail);
        } catch (\Exception $e) {
        }
    }
}
