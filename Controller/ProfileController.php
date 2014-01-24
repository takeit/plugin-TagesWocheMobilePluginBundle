<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Newscoop\Entity\User;
use Newscoop\User\RegisterUserCommand;
use Newscoop\User\UpdateProfileCommand;
use Newscoop\User\ConfirmCommand;

use Tageswoche\Mobile\UserDeviceService;
use Tageswoche\Subscription\SubscriptionFacade;
use Tageswoche\Promocode\PromocodeUsedException;
use Tageswoche\Subscription\CustomerIdUsedException;
use Tageswoche\Subscription\UserIsCustomerException;
use Tageswoche\Subscription\DmproException;

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

    /** @var Newscoop\Services\UserService */
    private $userService;

    /**
     * @Route("/index")
     */
    public function indexAction()
    {
        $this->userService = $this->container->get('user');

        $user = $this->getUser();
        if ($user === null) {
            return;
        }

        $form = new Api_Form_Profile();

        if (empty($_FILES['profile_image_data'])) {
            $form->removeElement('profile_image_data'); // disable maxsize error
        }

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            try {
                $command = new UpdateProfileCommand($form->getValues());
                $command->user = $user;
                $command->image = !empty($_FILES['profile_image_data'])
                    ? $form->profile_image_data->getFileInfo()
                    : null;
                $this->container->get('user.profile')->updateProfile($command);
            } catch (UserIsCustomerException $e) {
                $this->sendError(get_class($e), 403);
            } catch (PromocodeUsedException $e) {
                $this->sendError(get_class($e), 409);
            } catch (CustomerIdUsedException $e) {
                $this->sendError(get_class($e), 409);
            } catch (DmproException $e) {
                $this->sendError(get_class($e), 500);
            } catch (\Exception $e) {
                $this->sendError(get_class($e) . ': ' . $e->getMessage());
            }
        } elseif ($this->getRequest()->isPost()) {
            $this->_helper->json($form->getErrors());
        }

        $this->_helper->json(
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
                    'account_type' => $this->getUserType($user) ?: null,
                    'profile_image_url' => $this->getUserImageUrl($user, array(125, 125), array(250, 250)),
                    'public_profile_url' => $this->view->serverUrl(
                        $this->view->url(
                            array(
                                'module' => 'api',
                                'controller' => 'profile',
                                'action' => 'public',
                            ),
                            'default'
                        ) . $this->getApiQueryString(
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
                $this->getUserSubscriptionInfo($user)
            )
        );
    }

    /**
     * Route("/subscriptioninfo")
     */
    public function subscriptioninfoAction()
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $apiHelperService->assertIsSecure();
        return new JsonResponse(array_merge(
            $this->hasAuthInfo() ? $this->getUserSubscriptionInfo($this->getUser()) : array(),
            $this->getAvailableSubscriptions()
        ));
    }

    /**
     * Route("/create")
     */
    public function createAction()
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $apiHelperService->assertIsSecure();
        $apiHelperService->assertIsPost();

        try {
            $command = new RegisterUserCommand();
            $command->email = $this->_getParam('email');

            if (empty($command->email)) {
                $this->sendError("Parameter 'email' not set");
            }

            $validator = new Zend_Validate_EmailAddress();
            if (!$validator->isValid($command->email)) {
                $this->sendError("Email '{$command->email}' is not valid");
            }

            $this->container->get('user.register')->register($command);
            $this->getResponse()->setHttpResponseCode(200);
            $this->getResponse()->sendResponse();
            exit;
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 409);
        }
    }

    /**
     * Route("/public")
     */
    public function publicAction()
    {
        $user = $this->userService->findOneBy(
            array(
                'id' => $this->_getParam('user'),
            )
        );

        if (empty($user)) {
            return new JsonResponse();
        }

        $this->_helper->smarty->setSmartyView();
        $this->view->user = new MetaUser($user);
        $this->view->profile = $user->getAttributes();

        $this->render('user_profile');
    }

    /**
     * Route("/reset")
     */
    public function resetAction()
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $apiHelperService->assertIsSecure();
        $apiHelperService->assertIsPost();

        $user = $this->getUser();
        $this->container->get('promocode')->removeUserPromocode($user);
        $this->container->get('user_attributes')->removeAttributes(
            $user,
            array(
                SubscriptionFacade::CID,
                self::DIGITAL_UPGRADE,
            )
        );

        $this->container->get('mobile.free_upgrade')->reset($user, $this->_getParam('free_upgrade', false));
         return newJsonResponse(array('code' => 200));
    }

    /**
     * Route("/facebookcreate")
     */
    public function facebookcreateAction()
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $apiHelperService->assertIsSecure();
        $apiHelperService->assertIsPost();

        try {
            $token = $this->_getParam(self::FACEBOOK_AUTH_TOKEN);
            $command = ConfirmCommand::createByFacebook($this->container->get('facebook')->me($token));
            $this->container->get('user.confirm')->confirm($command);
            $this->getResponse()->setHttpResponseCode(201);
            $this->_forward('index');
        } catch (Exception $e) {
            $this->sendError('Duplicate', 500);
        }
    }

    /**
     * Route("/resetpassword")
     */
    public function resetpasswordAction()
    {
        $email = $this->_getParam('email');
        if (empty($email)) {
            $this->sendError('No email', 400);
        }

        $users = $this->container->get('user')->findBy(array('email' => $email));
        if (empty($users) || !$users[0]->isActive()) {
            $this->sendError('Not found', 412);
        }

        $this->container->get('email')->sendPasswordRestoreToken($users[0]);

        $this->getResponse()
            ->setHttpResponseCode(200)
            ->sendResponse();

        exit;
    }

    /**
     * Get user type
     *
     * @param Newscoop\Entity\User $user
     * @return string
     */
    private function getUserType(User $user)
    {
        if ($this->container->get('user')->isEditor($user)) {
            return self::TYPE_EDITOR;
        }

        if ($this->container->get('blog')->isBlogger($user)) {
            return self::TYPE_BLOGGER;
        }

        return self::TYPE_MEMBER;
    }

    /**
     * Get user image
     *
     * @param Newscoop\Entity\User $user
     * @return string
     */
    private function getUserImage(User $user)
    {
        $image = $this->container->get('image')->getUserImage($user);
        return $image ? $this->view->serverUrl($this->view->url(array('src' => $image), 'image', false, false)) : null;
    }

    /**
     * Get available subscriptions
     *
     * @return array
     */
    private function getAvailableSubscriptions()
    {
        $config = new Zend_Config_Xml(APPLICATION_PATH . '/configs/subscriptions.xml');
        $array = $config->toArray();
        $array['single_issue_product']['product_id'] = $this->getCurrentIssueProductId();
        $array['digital_subscriptions'] = array_values($array['digital_subscriptions']['subscription']);
        return $array;
    }

    /**
     * Get user subscription info
     *
     * @param Newscoop\Entity\User $user
     * @return array
     */
    private function getUserSubscriptionInfo($user)
    {
        $view = $this->container->get('user_subscription')->getView($user);

        foreach ($view as $key => $val) {
            if ($val instanceof DateTime) {
                $view->$key = $val->format('Y-m-d');
            }
        }

        return (array) $view;
    }

    /**
     * Get current issue product id
     *
     * @return string
     */
    private function getCurrentIssueProductId()
    {
        $issue = $this->container->get('mobile.issue')->findCurrent();
        $date = $this->getArticleField($issue, 'issuedate')
            ? new DateTime($this->getArticleField($issue, 'issuedate'))
            : $issue->getPublished();
        return sprintf(
            'ch.tageswoche.issue.%d.%02d',
            $date->format('Y'),
            $this->getArticleField($issue, 'issue_number')
        );
    }
}

