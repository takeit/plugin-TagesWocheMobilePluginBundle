<?php
/**
 * @package Newscoop
 * @copyright 2011 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Subscription;

use DateTime;
use Newscoop\Entity\User;
use Newscoop\TagesWocheMobilePluginBundle\Profile\UpdateProfileCommand;
use Newscoop\TagesWocheMobilePluginBundle\Subscription\PromocodeService;
use Newscoop\TagesWocheMobilePluginBundle\Subscription\DigitalUpgradeService;
use Newscoop\TagesWocheMobilePluginBundle\Subscription\UserService;

/**
 * Subscription Facade
 */
class SubscriptionFacade
{
    const CID = 'customer_id';

    /**
     * @var Newscoop\TagesWocheMobilePluginBundle\Subscription\PromocodeService
     */
    private $promocodeService;

    /**
     * @var Newscoop\TagesWocheMobilePluginBundle\Subscription\VerlagsManagerService
     */
    private $vmService;

    /**
     * @var Newscoop\TagesWocheMobilePluginBundle\Subscription\DigitalUpgradeService
     */
    private $digitalUpgradeService;

    /**
     * @var Newscoop\TagesWocheMobilePluginBundle\Subscription\UserService
     */
    private $userService;

    /**
     * @param Newscoop\TagesWocheMobilePluginBundle\Subscription\PromocodeService $promocodeService
     * @param Newscoop\TagesWocheMobilePluginBundle\Subscription\VerlagsManagerService $vmService
     * @param Newscoop\TagesWocheMobilePluginBundle\Subscription\DigitalUpgradeService $digitalUpgradeService
     * @param Newscoop\TagesWocheMobilePluginBundle\Subscription\UserService $userService
     */
    public function __construct(
        PromocodeService $promocodeService,
        VerlagsManagerService $vmService,
        DigitalUpgradeService $digitalUpgradeService,
        UserService $userService
    ) {
        $this->promocodeService = $promocodeService;
        $this->vmService = $vmService;
        $this->digitalUpgradeService = $digitalUpgradeService;
        $this->userService = $userService;
    }

    /**
     * Add free upgrade to existing user
     *
     * @param Newscoop\Entity\User $user
     *
     * @return void
     */
    public function freeUpgrade(User $user)
    {
        $upgradeView = $this->digitalUpgradeService->getView($user);

        if ($upgradeView->digital_upgrade) {
            return; // active upgrade
        }

        if ($upgradeView->free_digital_upgrade_consumed) {
            throw new FreeUpgradeException();
        }

        $this->upgrade($user);
    }

    /**
     * Upgrade given user
     *
     * @param Newscoop\Entity\User $user
     *
     * @return void
     */
    public function upgrade(User $user)
    {
        $customerView = $this->vmService->getView($user);
        if (!$customerView->print_subscription) {
            throw new SubscriptionNotFoundException();
        }

        $this->digitalUpgradeService->upgrade($user, $customerView->print_subscription_valid_until);
    }

    /**
     * Update user profile
     *
     * @param Newscoop\TagesWocheMobilePluginBundle\Profile\UpdateProfileCommand $command
     *
     * @return void
     */
    public function updateProfile(UpdateProfileCommand $command)
    {
        if (empty($command->attributes[self::CID])) {
            return;
        }

        if ($this->promocodeService->isPromocode($command->attributes[self::CID])) {
            $view = $this->vmService->getView($command->user);
            if ($view->print_subscription) {
                throw new UserIsCustomerException();
            }

            $this->promocodeService->activatePromocode(new ActivatePromocodeCommand(array(
                'user' => $command->user,
                'promocode' => $command->attributes[self::CID],
            )));

            unset($command->attributes[self::CID]);
        } else {
            if ( ! $this->vmService->isCustomer($command->attributes[self::CID])) {
                throw new CustomerNotFoundException();
            }

            if ($this->userService->isUsedCustomerId($command->attributes[self::CID], $command->user)) {
                throw new CustomerIdUsedException();
            }
        }
    }

    /**
     * Get subscription info for given user
     *
     * @param Newscoop\Entity\User $user
     *
     * @return Tageswoche\Subscription\SubscriptionInfo
     */
    public function getView(User $user)
    {
        $view = new SubscriptionView();
        $customerView = $this->vmService->getView($user);
        $view->mergeView($customerView);

        if ($customerView->customer_id_subcode) {
            $masterUser = $this->userService->findByCustomerId($customerView->master_id);
            if (null !== $masterUser) {
                $view->mergeView($this->digitalUpgradeService->getView($masterUser));
            }
        }

        if (!$view->digital_upgrade) {
            $view->mergeView($this->digitalUpgradeService->getView($user));
        }

        if (!$view->digital_upgrade && !$view->print_subscription) {
            $view->mergeView($this->promocodeService->getView($user));
        }

        if (!empty($view->subcodes)) {
            $view->subcodes = $this->userService->getSubcodesView($view->subcodes);
        }

        return $view;
    }
}
