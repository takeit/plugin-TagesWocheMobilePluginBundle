<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Subscription;

use Exception;
use DateTime;
use Doctrine\ORM\EntityManager;
use Newscoop\Entity\User;
use Newscoop\TagesWocheMobilePluginBundle\Entity\DigitalUpgrade;

/**
 * Digital Upgrade Service
 */
class DigitalUpgradeService
{
    /**
     * @var Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @param Doctrine\ORM\EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Get upgrade info for given user
     *
     * @param Newscoop\Entity\User $user
     * @return object
     */
    public function getView(User $user)
    {
        try {
            $upgrade = $this->findByUser($user);
            return $upgrade->getView();
        } catch (Exception $e) {
            return new UpgradeView();
        }
    }

    /**
     * Upgrade user
     *
     * @param Newscoop\Entity\User $user
     * @param DateTime $subscriptionValidUntil
     * @return void
     */
    public function upgrade(User $user, DateTime $subscriptionValidUntil)
    {
        $upgrade = $this->getRepository()->findOneBy(array(
            'user' => $user,
            'validUntil' => $subscriptionValidUntil,
        ));

        if (null === $upgrade) {
            $upgrade = new DigitalUpgrade($user, $subscriptionValidUntil);
            $this->em->persist($upgrade);
            $this->em->flush($upgrade);
        }
    }

    /**
     * Public function reset
     *
     * @param Newscoop\Entity\User $user
     * @param bool $freeUpgrade
     * @return void
     */
    public function reset(User $user, $freeUpgrade)
    {
        try {
            while ($upgrade = $this->findByUser($user)) {
                $this->em->remove($upgrade);
                $this->em->flush();
            }
        } catch (\Exception $e) {
            // ignore if no upgrade
        }

        if ($freeUpgrade) {
            $this->upgrade($user, new DateTime('- 5 days'));
        }
    }

    /**
     * Find current upgrade for given user
     *
     * @param Newscoop\Entity\User $user
     * @return Tageswoche\Entity\DigitalUpgrade
     */
    private function findByUser(User $user)
    {
        $query = $this->getRepository()
            ->createQueryBuilder('u')
            ->where('u.user = :user')
            ->orderBy('u.validUntil', 'DESC')
            ->setMaxResults(1)
            ->getQuery();

        $query->setParameter('user', $user);
        return $query->getSingleResult();
    }

    /**
     * Get repository
     *
     * @return Doctrine\ORM\EntityRepository
     */
    private function getRepository()
    {
        return $this->em->getRepository('Newscoop\TagesWocheMobilePluginBundle\Entity\DigitalUpgrade');
    }
}
