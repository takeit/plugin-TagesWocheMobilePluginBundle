<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Tageswoche\Subscription;

/**
 * Promocode View
 */
class PromocodeView extends View
{
    /**
     * @var string
     */
    public $customer_id;

    /**
     * @var string
     */
    public $digital_upgrade = false;

    /**
     * @var DateTime
     */
    public $digital_upgrade_valid_until;
}
