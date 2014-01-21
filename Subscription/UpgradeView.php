<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Subscription;

/**
 * Upgrade View
 */
class UpgradeView extends View
{
    /**
     * @var string
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
}
