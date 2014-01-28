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
use Newscoop\User\ConfirmCommand;

use Newscoop\TagesWocheMobilePluginBundle\Profile\RegisterUserCommand;
use Newscoop\TagesWocheMobilePluginBundle\Profile\UpdateProfileCommand;
use Newscoop\TagesWocheMobilePluginBundle\Subscription\SubscriptionFacade;
use Newscoop\TagesWocheMobilePluginBundle\Promocode\PromocodeUsedException;
use Newscoop\TagesWocheMobilePluginBundle\Subscription\CustomerIdUsedException;
use Newscoop\TagesWocheMobilePluginBundle\Subscription\UserIsCustomerException;
use Newscoop\TagesWocheMobilePluginBundle\Subscription\DmproException;

/**
 * Route('/profile')
 *
 * Profile API
 */
class ProfileController extends Controller
{
    const TYPE_EDITOR = 'editor';
    const TYPE_BLOGGER = 'blogger';
    const TYPE_MEMBER = 'community_member';

    /**
     * @Route("/index")
     */
    public function indexAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $user = $apiHelperService->getUser();
        if (!($user instanceof User)) {
                return $user !== null ? $user : $apiHelperService->sendError('Invalid credentials.', 401);
        }

        if ($request->getMethod() == 'POST') {
            try {
                $command = new UpdateProfileCommand($form->getValues());
                $command->user = $user;
                $command->first_name = $user->getFirstName();
                $command->last_name = $user->getLastName();
                $command->username = $user->getUsername();
                $command->attributes = $user->getAttributes();
                $command->image = !empty($_FILES['profile_image_data'])
                    ? $form->profile_image_data->getFileInfo()
                    : null;
                
                $errors = $this->container->get('validator')->validate($command);
                if (count($errors) === 0) {
                    $this->container->get('user.profile')->updateProfile($command);
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
                             array(
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
     * @Route("/subscriptioninfo")
     */
    public function subscriptioninfoAction()
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $apiHelperService->assertIsSecure();
        return new JsonResponse(array_merge(
            $apiHelperService->hasAuthInfo() ? $apiHelperService->getUserSubscriptionInfo($apiHelperService->getUser()) : array(),
            $this->getAvailableSubscriptions()
        ));
    }

    /**
     * @Route("/create")
     * @Method("POST")
     */
    public function createAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $apiHelperService->assertIsSecure();

        try {
            $command = new RegisterUserCommand();
            $command->email = $request->request('email');

            $errors = $this->container->get('validator')->validate($command);
            if (count($errors) === 0) {
                $this->container->get('user.register')->register($command);
                return new JsonResponse(array(), 200);
            } else {
                return $apiHelperService->sendError($errors[0]->getMessage(), 500);
            }
        } catch (Exception $e) {
            return $apiHelperService->sendError($e->getMessage(), 409);
        }
    }

    /**
     * @Route("/public")
     */
    public function publicAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $userService = $this->container->get('user');
        $user = $userService->findOneBy(
            array(
                'id' => $request->query->get('user')
            )
        );

        if (!($user instanceof User)) {
            return $user !== null ? $user : $apiHelperService->sendError('Invalid user.', 401);
        }

        // TODO: convert to twig, or plugin user_profile smarty
        //$this->_helper->smarty->setSmartyView();
        //$this->view->user = new MetaUser($user);
        //$this->view->profile = $user->getAttributes();
        //$this->render('user_profile');

        return $this->render('TagesWocheMobilePluginBundle:profile:user_profile.html.smarty', array(
            'user' => new MetaUser($user),
            'profile' => $user->getAttributes()
        ));
    }

    /**
     * @Route("/reset")
     * @Method("POST")
     */
    public function resetAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $apiHelperService->assertIsSecure();

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
     * @Route("/facebookcreate")
     * @Method("POST")
     */
    public function facebookcreateAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $apiHelperService->assertIsSecure();

        try {
            $token = $request->request->get(self::FACEBOOK_AUTH_TOKEN);
            $command = ConfirmCommand::createByFacebook($this->container->get('facebook')->me($token));
            $this->container->get('user.confirm')->confirm($command);
            $this->getResponse()->setHttpResponseCode(201);
            return $this->forward('NewscoopTagesWocheMobilePluginBundle:Profile:index', array('request' => $request));
        } catch (Exception $e) {
            return $apiHelperService->sendError('Duplicate', 500);
        }
    }

    /**
     * @Route("/resetpassword")
     */
    public function resetpasswordAction(Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $email = $request->request('email');
        if (empty($email)) {
            return $apiHelperService->sendError('No email', 400);
        }

        $users = $this->container->get('user')->findBy(array('email' => $email));
        if (empty($users) || !$users[0]->isActive()) {
            return $apiHelperService->sendError('Not found', 412);
        }

        $this->container->get('email')->sendPasswordRestoreToken($users[0]);

        return newJsonResponse(array('code' => 200));
    }

    /**
     * Get available subscriptions
     *
     * @return array
     */
    private function getAvailableSubscriptions()
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $config = new Zend_Config_Xml(APPLICATION_PATH . '/configs/subscriptions.xml');
        $array = $config->toArray();
        $array['single_issue_product']['product_id'] = $apiHelperService->getCurrentIssueProductId();
        $array['digital_subscriptions'] = array_values($array['digital_subscriptions']['subscription']);
        return $array;
    }

}

