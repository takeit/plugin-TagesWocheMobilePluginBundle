<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Tageswoche\Entity;

use DateTime;
use DateInterval;
use Doctrine\ORM\Mapping AS ORM;
use Newscoop\Entity\User;
use Tageswoche\Subscription\PromocodeView;
use Tageswoche\Subscription\PromocodeUsedException;

/**
 * @ORM\Entity(repositoryClass="Tageswoche\Repository\PromocodeRepository")
 * @ORM\Table(name="tw_promocode")
 */
class Promocode
{
    const TTL = 'P90D';

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
     * @ORM\Column(type="datetime", nullable=true)
     * @var DateTime
     */
    private $activated;

    /**
     * @param string $code
     */
    public function __construct($code)
    {
        $this->code = (string) $code;
    }

    /**
     * Activate promocode
     *
     * @param Newscoop\Entity\User $user
     * @return void
     */
    public function activate(User $user)
    {
        if ($this->user !== null && $this->user !== $user) {
            throw new PromocodeUsedException($this->code);
        }

        if ($this->user === null) {
            $this->user = $user;
            $this->activated = new \DateTime('now');
        }
    }

    /**
     * Get promocode view
     *
     * @return Tageswoche\Subscription\PromocodeView
     */
    public function getView()
    {
        $now = new DateTime();
        $view = new PromocodeView();
        $expires = $this->getExpirationDate();

        if ($expires->format('Y-m-d') >= $now->format('Y-m-d')) {
            $view->customer_id = $this->code;
            $view->digital_upgrade = true;
            $view->digital_upgrade_valid_until = $expires;
        }

        return $view;
    }

    /**
     * Get expiration date
     *
     * @return DateTime
     */
    private function getExpirationDate()
    {
        $activated = clone $this->activated;
        $activated->add(new \DateInterval(self::TTL));
        return $activated;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->code;
    }

    /**
     * Reset promocode
     *
     * @return void
     */
    public function reset()
    {
        $this->user = null;
        $this->activated = null;
    }
}
