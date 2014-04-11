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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Newscoop\Entity\User;

use Newscoop\TagesWocheMobilePluginBundle\Profile\FacebookConfirmCommand;
use Newscoop\TagesWocheMobilePluginBundle\Profile\RegisterUserCommand;
use Newscoop\TagesWocheMobilePluginBundle\Profile\UpdateProfileCommand;
use Newscoop\TagesWocheMobilePluginBundle\Subscription\SubscriptionFacade;
use Newscoop\TagesWocheMobilePluginBundle\Promocode\PromocodeUsedException;
use Newscoop\TagesWocheMobilePluginBundle\Subscription\CustomerIdUsedException;
use Newscoop\TagesWocheMobilePluginBundle\Subscription\UserIsCustomerException;
use Newscoop\TagesWocheMobilePluginBundle\Subscription\DmproException;
use Exception;

/**
 * Route('/profile')
 *
 * Profile API
 */
class ProfileController extends Controller
{
    const FACEBOOK_AUTH_TOKEN = 'fb_access_token';

    /**
     * @Route("/profile")
     * @Route("/profile/")
     */
    public function indexAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $user = $apiHelperService->getUser();
        if (!($user instanceof User)) {
            return $user !== null ? $user : $apiHelperService->sendError('Invalid credentials.', 401);
        }

        //$token = $request->request->get(self::FACEBOOK_AUTH_TOKEN);
        if ($request->getMethod() == 'POST') {
            try {
                $command = new UpdateProfileCommand($request->request->all());
                $command->user = $user;
                $command->attributes = array(
                    'bio' => $request->request->get('bio'),
                    'website' => $request->request->get('website'),
                    'facebook' => $request->request->get('facebook'),
                    'google' => $request->request->get('google'),
                    'email_pubic' => $request->request->get('email_pubic'),
                    'birth_date' => $request->request->get('birth_date')
                );
                $command->image = !empty($_FILES['profile_image_data'])
                    ? $request->files->get('profile_image_data')
                    : null;

                $errors = $this->container->get('validator')->validate($command);

                $command->image =  !empty($_FILES['profile_image_data'])
                    ? $_FILES['profile_image_data']
                    : null;

                if (count($errors) === 0) {
                    $this->container->get('newscoop_tageswochemobile_plugin.user.profile')->updateProfile($command);
                } else {
                    return $apiHelperService->sendError($errors[0]->getMessage(), 500);
                }

            } catch (UserIsCustomerException $e) {
                return $apiHelperService->sendError(get_class($e), 403);
            } catch (PromocodeUsedException $e) {
                return $apiHelperService->sendError(get_class($e), 409);
            } catch (CustomerIdUsedException $e) {
                return $apiHelperService->sendError(get_class($e), 409);
            } catch (DmproException $e) {
                return $apiHelperService->sendError(get_class($e), 500);
            } catch (\Exception $e) {
                return $apiHelperService->sendError(get_class($e) . ': ' . $e->getMessage());
            }
        }

        return new JsonResponse(
            array_merge(
                array(
                    'first_name' => $user->getFirstName() ?: null,
                    'last_name' => $user->getLastName() ?: null,
                    'birth_date' => $user->getAttribute('birth_date') ?: null,
                    'facebook' => $user->getAttribute('facebook') ?: null,
                    'google' => $user->getAttribute('google') ?: null,
                    'twitter' => $user->getAttribute('twitter') ?: null,
                    'website' => $user->getAttribute('website') ?: null,
                    'bio' => $user->getAttribute('bio') ?: null,
                    'email_public' => (bool) $user->getAttribute('email_public'),
                    'member_since' => $user->getCreated()->format('Y-m-d') ?: null,
                    'account_type' => $apiHelperService->getUserType($user) ?: null,
                    'profile_image_url' => $apiHelperService->getUserImageUrl($user, array(125, 125), array(250, 250)),
                    'public_profile_url' => $apiHelperService->serverUrl(
                        $this->container->get('zend_router')->assemble(
                            array(
                                'module' => 'api',
                                'controller' => 'profile',
                                'action' => 'public',
                            ),
                            'default'
                        ) . $apiHelperService->getApiQueryString(
                            array(
                                'user' => $user->getId(),
                            )
                        )
                    ),
                    'verified' => (bool) $user->getAttribute('is_verified'),

                    'subscribed_topics' => array_map(
                        function ($topic) {
                            return array(
                                'topic_id' => $topic->getTopicId(),
                                'topic_name' => $topic->getName(),
                            );
                        },
                        $this->container->get('user.topic')->getTopics($user)
                    ),
                ),
                $apiHelperService->getUserSubscriptionInfo($user)
            )
        );
    }

    /**
     * @Route("/profile/subscription_info")
     */
    public function subscriptionInfoAction()
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        if (!$apiHelperService->isSecure()) {
            return $apiHelperService->sendError('Secure connection required', 400);
        }
        return new JsonResponse(array_merge(
            $apiHelperService->hasAuthInfo() ? $apiHelperService->getUserSubscriptionInfo($apiHelperService->getUser()) : array(),
            $this->getAvailableSubscriptions()
        ));
    }

    /**
     * @Route("/profile/create")
     * @Method("POST")
     */
    public function createAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        if (!$apiHelperService->isSecure()) {
            return $apiHelperService->sendError('Secure connection required', 400);
        }

        try {
            $command = new RegisterUserCommand();
            $command->email = $request->request->get('email');

            $errors = $this->container->get('validator')->validate($command);
            if (count($errors) === 0) {
                $this->container->get('newscoop_tageswochemobile_plugin.user.register')->register($command);
                return new JsonResponse(array(), 200);
            } else {
                return $apiHelperService->sendError($errors[0]->getMessage(), 500);
            }
        } catch (Exception $e) {
            return $apiHelperService->sendError($e->getMessage(), 409);
        }
    }

    /**
     * @Route("/profile/public")
     * @Route("/profile/public/user/{id}")
     */
    public function publicAction(Request $request, $id = null)
    {
        if (!isset($id)) {
            $id = $request->query->get('user');
        }
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $userService = $this->container->get('user');
        $user = $userService->findOneBy(
            array(
                'id' => $id
            )
        );

        if (!($user instanceof User)) {
            return $user !== null ? $user : $apiHelperService->sendError('Invalid user.', 401);
        }

        $profileService = $this->get('newscoop_tages_woche_extra.profile');
        $templatesService = $this->container->get('newscoop.templates.service');
        $smarty = $templatesService->getSmarty();
        $smarty->assign('user', new \MetaUser($user));
        $smarty->assign('profile', $user->getAttributes());
        $smarty->assign('questions', $profileService->getPoliticianQuestions());
        $smarty->assign('labels', $profileService->getFormLabels());

        $response = new Response();
        $response->headers->set('Content-Type', 'text/html');
        $response->setContent($templatesService->fetchTemplate("_mobile/user_profile.tpl"));
        return $response;

    }

    /**
     * @Route("/profile/reset")
     * @Method("POST")
     */
    public function resetAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        if (!$apiHelperService->isSecure()) {
            return $apiHelperService->sendError('Secure connection required', 400);
        }

        $user = $apiHelperService->getUser();
        $this->container->get('promocode')->removeUserPromocode($user);
        $this->container->get('user_attributes')->removeAttributes(
            $user,
            array(
                SubscriptionFacade::CID,
                self::DIGITAL_UPGRADE,
            )
        );

        $this->container->get('mobile.free_upgrade')->reset($user, $request->request->get('free_upgrade'));
        return newJsonResponse(array('code' => 200));
    }

    /**
     * @Route("/profile/facebookcreate")
     * @Method("POST")
     */
    public function facebookcreateAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        if (!$apiHelperService->isSecure()) {
            return $apiHelperService->sendError('Secure connection required', 400);
        }

        try {
            $token = $request->request->get(self::FACEBOOK_AUTH_TOKEN);
            $command = FacebookConfirmCommand::createByFacebook($this->container->get('newscoop_tageswochemobile_plugin.facebook')->getFacebookUser($token));
            $this->container->get('newscoop_tageswochemobile_plugin.user.confirm')->confirm($command);
            $response = new Response();
            $response->setStatusCode(201);
            return $this->forward('NewscoopTagesWocheMobilePluginBundle:Profile:index');
        } catch (Exception $e) {
            return $apiHelperService->sendError('Duplicate', 500);
        }
    }

    /**
     * @Route("/profile/reset_password")
     */
    public function resetpasswordAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $email = $request->query->get('email');
        if (empty($email)) {
            return $apiHelperService->sendError('No email', 400);
        }

        $users = $this->container->get('user')->findBy(array('email' => $email));
        if (empty($users) || !$users[0]->isActive()) {
            return $apiHelperService->sendError('Not found', 412);
        }

        $this->container->get('email')->sendPasswordRestoreToken($users[0]);

        return new JsonResponse(array('code' => 200));
    }

    /**
     * Get available subscriptions
     *
     * @return array
     */
    private function getAvailableSubscriptions()
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $subscriptions = $this->container->getParameter('subscriptions');
        $subscriptions['single_issue_product']['product_id'] = $apiHelperService->getCurrentIssueProductId();
        $subscriptions['digital_subscriptions'] = array_values($subscriptions['digital_subscriptions']);

        return $subscriptions;
    }
}
