<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Tageswoche\Subscription;

use Doctrine\ORM\EntityManager;
use Newscoop\Entity\User;
use Tageswoche\Entity\Promocode;

/**
 */
class PromocodeService
{
    const PREFIX = 'tw';

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
     * Get promocode view for given user
     *
     * @param Newscoop\Entity\User $user
     * @return Tageswoche\Subscription\PromocodeView
     */
    public function getView(User $user)
    {
        $promocode = $this->getRepository()->findOneByUser($user);
        return $promocode ? $promocode->getView() : new PromocodeView();
    }

    /**
     * Activate promocode
     *
     * @param Tageswoche\Promocode\ActivatePromocodeCommand $command
     * @return void
     */
    public function activatePromocode(ActivatePromocodeCommand $command)
    {
        $user = $command->user;
        $promocode = $this->getRepository()->find($this->normalizeCode($command->promocode));

        if ($promocode === null) {
            throw new PromocodeNotFoundException($command->promocode);
        }

        $userCode = $this->findByUser($user);
        if ($userCode !== null && $userCode !== $promocode) {
            throw new PromocodeNotAllowedException();
        }

        $promocode->activate($user);
        $this->em->flush($promocode);
    }

    /**
     * Test if given string is promocode
     *
     * @param string $string
     * @return bool
     */
    public function isPromocode($string)
    {
        $string = $this->normalizeCode($string);
        return preg_match('/^[0-9]{' . PromocodeGeneratorService::LENGTH . '}$/', $string) === 1;
    }

    /**
     * Find promocode for given user
     *
     * @param Newscoop\Entity\User $user
     * @return Tageswoche\Entity\Promocode
     */
    private function findByUser(User $user)
    {
        return $this->em->getRepository('Tageswoche\Entity\Promocode')->findOneByUser($user->getId());
    }

    /**
     * Remove user promocode
     *
     * @param Newscoop\Entity\User $user
     * @return void
     */
    public function removeUserPromocode(User $user)
    {
        $promocode = $this->findByUser($user);
        if ($promocode) {
            $promocode->reset();
            $this->em->flush($promocode);
        }
    }

    /**
     * Normalize promocode
     *
     * @param string $code
     * @return string
     */
    private function normalizeCode($code)
    {
        $code = strtolower($code);

        if (substr($code, 0, strlen(self::PREFIX)) === self::PREFIX) {
            $code = substr($code, strlen(self::PREFIX));
        }

        $code = str_replace('-', '', $code);
        return $code;
    }

    /**
     * Get promocode repository
     *
     * @return Doctrine\ORM\EntityRepository
     */
    private function getRepository()
    {
        return $this->em->getRepository('Tageswoche\Entity\Promocode');
    }
}
