<?php

namespace Newscoop\TagesWocheMobilePluginBundle\Services;

use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 */
class OnlineBrowserHelper
{
    private $userService;

    private $subscriptionService;

    /**
     * [__construct description]
     *
     * @param [type] $userService         [description]
     * @param [type] $subscriptionService [description]
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
        return $this->userService->getCurrentUser();
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
            $hasValidSubscription = $this->hasValidSubscription($user);
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

