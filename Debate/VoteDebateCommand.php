<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Debate;

use Symfony\Component\Validator\Constraints as Assert;

/**
 */
class VoteDebateCommand
{
    /**
     * @Assert\NotBlank(message="debate_id is required")
     */
    public $debateId;

    /**
     * @Assert\NotBlank(message="vote is required")
     */
    public $vote;

    public $deviceId;

    /**
     * @Assert\NotBlank(message="userId is required")
     */
    public $userId;
}
