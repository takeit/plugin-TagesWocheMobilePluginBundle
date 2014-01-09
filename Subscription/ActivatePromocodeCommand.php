<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Tageswoche\Subscription;

use Newscoop\ValueObject;

/**
 */
class ActivatePromocodeCommand extends ValueObject
{
    /**
     * @var Newscoop\Entity\User
     */
    public $user;

    /**
     * @var string
     */
    public $promocode;
}
