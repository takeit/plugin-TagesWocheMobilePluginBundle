<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Tageswoche\Entity;

use DateTime;
use Newscoop\Entity\User;
use Doctrine\ORM\Mapping AS ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="tw_subcodes_status")
 */
class SubcodeStatus
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=10)
     * @var string
     */
    private $code;

    /**
     * @ORM\OneToOne(targetEntity="Newscoop\Entity\User")
     * @ORM\JoinColumn(referencedColumnName="Id")
     * @var Newscoop\Entity\User
     */
    private $user;

    /**
     * @ORM\Column(name="receiver_email", type="string", length=255)
     * @var string
     */
    private $receiverEmail;

    /**
     * @ORM\Column(name="receiver_name", type="string", length=255)
     * @var string
     */
    private $receiverName;

    /**
     * @ORM\Column(type="boolean")
     * @var boolean
     */
    private $status;

    /**
     * Set code
     *
     * @param string $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * Set user
     *
     * @param string $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * Get email of the receiver
     *
     * @return string
     */
    public function getReceiverEmail()
    {
        return $this->receiverEmail;
    }

    /**
     * Set email of the receiver
     *
     * @param string $email
     */
    public function setReceiverEmail($email)
    {
        $this->receiverEmail = $email;
    }

    /**
     * Get name of the receiver
     *
     * @return string
     */
    public function getReceiverName()
    {
        return $this->receiverName;
    }

    /**
     * Set name of the receiver
     *
     * @param string $name
     */
    public function setReceiverName($name)
    {
        $this->receiverName = (string) $name;
    }

    /**
     * Get status
     *
     * @return bool
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set status
     *
     * @param boolean $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * Reset subcode status
     *
     * @return void
     */
    public function reset()
    {
        $this->user = null;
        $this->status = false;
    }
}
