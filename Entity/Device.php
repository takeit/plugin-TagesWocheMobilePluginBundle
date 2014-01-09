<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Tageswoche\Entity;

use DateTime;
use Newscoop\Entity\User;
use Doctrine\ORM\Mapping AS ORM;

/**
 * @ORM\Entity(repositoryClass="Tageswoche\Repository\DeviceRepository")
 * @ORM\Table(name="tw_upgrade_device",
 *      uniqueConstraints={@ORM\UniqueConstraint(columns={"device", "user_id", "valid_until"})}
 *  )
 */
class Device
{
    /**
     * @ORM\Id @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(length=80)
     * @var string
     */
    private $device;

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
     * @param string $device
     * @param Newscoop\Entity\User $user
     * @param DateTime $upgrade
     */
    public function __construct($device, User $user, DateTime $validUntil)
    {
        $this->device = $device;
        $this->user = $user;
        $this->validUntil = $validUntil;
    }
}
