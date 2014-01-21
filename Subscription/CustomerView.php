<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Subscription;

/**
 * Customer View
 */
class CustomerView extends View
{
    /**
     * @var string
     */
    public $customer_id;

    /**
     * @var bool
     */
    public $customer_id_subcode = false;

    /**
     * @var bool
     */
    public $print_subscription = false;

    /**
     * @var DateTime
     */
    public $print_subscription_valid_until;

    /**
     * @var array
     */
    public $subcodes = array();

    /**
     * @var string
     */
    public $master_id;
}
