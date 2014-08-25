<?php
/**
 * @package Newscoop\TagesWocheMobilePluginBundle
 * @author Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric z.ú.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Services;

use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OnlineBrowserHelper
{
    /**
     * @var \Newscoop\Services\UserService
     */
    private $userService;

    /**
     * @var \Newscoop\TagesWocheMobilePluginBundle\Subscription\VerlagsManagerService
     */
    private $subscriptionService;

    /**
     * @var \Newscoop\Entity\User
     */
    private $currentUser;

    /**
     * Contructor for class
     *
     * @param \Newscoop\Services\UserService                                            $userService          UserService
     * @param \Newscoop\TagesWocheMobilePluginBundle\Subscription\VerlagsManagerService $subscriptionService  VerlagsManagerService
     */
    public function __construct($userService, $subscriptionService)
    {
        $this->userService = $userService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Check if a user is logged in
     *
     * @return boolean
     */
    public function userLoggedIn()
    {
        $user = $this->getCurrentUser();
        return ($user instanceof \Newscoop\Entity\User);
    }

    /**
     * Get current logged in user via userservice
     *
     * @return mixed Returns user object or null
     */
    public function getCurrentUser()
    {
        if (!isset($this->currentUser)) {
            $this->currentUser = $this->userService->getCurrentUser();
        }
        return $this->currentUser;
    }

    /**
     * Check if user has a valid subscription
     *
     * @return boolean
     */
    public function hasValidSubscription()
    {
        $hasValidSubscription = false;
        $user = $this->getCurrentUser();
        if ($user) {
            $hasValidSubscription = $this->subscriptionService->hasValidSubscription($user);
        }
        return $hasValidSubscription;
    }

    /**
     * Get subscription data
     */
    public function getSubscription($user)
    {
        return $this->subscriptionService->findSubscriber($user);
    }
}

