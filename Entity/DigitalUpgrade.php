<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Tageswoche\Entity;

use DateTime;
use Doctrine\ORM\Mapping AS ORM;
use Newscoop\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Tageswoche\Subscription\UpgradeView;
use Tageswoche\Subscription\DeviceLimitException;

/**
 * @ORM\Entity
 * @ORM\Table(name="tw_upgrade",
 *      uniqueConstraints={@ORM\UniqueConstraint(columns={"user_id", "valid_until"})}
 *  )
 */
class DigitalUpgrade
{
    /**
     * @ORM\Id @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @var int
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Newscoop\Entity\User")
     * @ORM\JoinColumn(referencedColumnName="Id")
     * @var Newscoop\Entity\User
     */
    private $user;

    /**
     * @ORM\Column(type="datetime", name="valid_until")
     * @var DateTime
     */
    private $validUntil;

    /**
     * @param Newscoop\Entity\User $user
     * @param DateTime $validUntil
     */
    public function __construct(User $user, DateTime $validUntil)
    {
        $this->user = $user;
        $this->validUntil = $validUntil;
    }

    /**
     * Get view
     *
     * @return object
     */
    public function getView()
    {
        $view = new UpgradeView();
        $view->free_digital_upgrade_consumed = true;

        if ($this->isActive()) {
            $view->digital_upgrade = true;
            $view->digital_upgrade_valid_until = $this->validUntil;
        }

        return $view;
    }

    /**
     * Test is upgrade is active
     *
     * @return bool
     */
    private function isActive()
    {
        $now = new DateTime();
        return  $this->validUntil->getTimestamp() >= $now->getTimestamp();
    }
}
