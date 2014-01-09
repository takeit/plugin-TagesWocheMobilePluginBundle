<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Tageswoche\Subscription;

use DateTime;
use Doctrine\ORM\EntityManager;
use Newscoop\Entity\User;
use Tageswoche\Entity\Device;

/**
 */
class DeviceService
{
    /**
     * @var Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @var Tageswoche\Subscription\SubscriptionFacade
     */
    private $subscriptionFacade;

    /**
     * @param Doctrine\ORM\EntityManager $em
     * @param Tageswoche\Subscription\SubscriptionFacade $subscriptionFacade
     */
    public function __construct(EntityManager $em, SubscriptionFacade $subscriptionFacade)
    {
        $this->em = $em;
        $this->subscriptionFacade = $subscriptionFacade;
    }

    /**
     * Test if user has upgrade for given device
     *
     * @param Newscoop\Entity\User $user
     * @param string $device
     * @return bool
     */
    public function hasDeviceUpgrade(User $user, $device = null)
    {
        $view = $this->subscriptionFacade->getView($user);
        return $view->digital_upgrade && $this->addDevice($user, $view->digital_upgrade_valid_until, $device);
    }

    /**
     * Associate device to user for given date
     *
     * @param Newscoop\Entity\User $user
     * @param DateTime $validUntil
     * @param string $device
     * @return bool
     */
    private function addDevice(User $user, DateTime $validUntil, $device = null)
    {
        if ($device === null) {
            return true;
        }

        $devices = $this->getDevices($user, $validUntil);
        if (in_array($device, $devices)) {
            return true;
        }

        $device = new Device($device, $user, $validUntil);
        $this->em->persist($device);
        $this->em->flush($device);
        return true;
    }

    /**
     * Get associted devices for given user/date
     *
     * @param Newscoop\Entity\User $user
     * @param DateTime $date
     * @return array
     */
    private function getDevices(User $user, DateTime $date)
    {
        return $this->em->getRepository('Tageswoche\Entity\Device')
            ->getDeviceIds($user, $date);
    }
}
