<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Subscription;

/**
 * Subscription View
 */
class SubscriptionView extends View
{
    /**
     * @var bool
     */
    public $digital_upgrade = false;

    /**
     * @var DateTime
     */
    public $digital_upgrade_valid_until;

    /**
     * @var bool
     */
    public $free_digital_upgrade_consumed = false;

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
     * @var string
     */
    public $subscription_name;

    /**
     * @var bool
     */
    public $print;

    /**
     * @var array
     */
    public $subcodes = array();

    /**
     * Merge given view into this view
     *
     * @param object $view
     * @return void
     */
    public function mergeView(View $view = null)
    {
        if ($view === null) {
            return;
        }

        foreach ($view as $key => $val) {
            if (property_exists($this, $key)) {
                $this->$key = $val;
            }
        }
    }
}
