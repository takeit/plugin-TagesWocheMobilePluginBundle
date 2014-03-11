<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Profile;

use Symfony\Component\Validator\Constraints as Assert;

class FacebookConfirmCommand
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $provider;

    /**
     * @var int
     */
    public $provider_user_id;

    /**
     * @var string
     */
    public $email;

    /**
     * @Assert\NotBlank(message="first_name is required")
     */
    public $first_name;
    /**
     * @Assert\NotBlank(message="last_name is required")
     */
    public $last_name;
    /**
     * @Assert\NotBlank(message="username is required")
     */
    public $username;
    /**
     * @Assert\Image(
     *     maxSize = "1024k"
     * )
     */
    public $image;
   
    /**
     * Create command by given facebook info
     *
     * @param object $info
     * @return Newscoop\User\ConfirmCommand
     */
    public static function createByFacebook($info)
    {
        $command = new self($info);
        $command->id = null;
        $command->provider = 'Facebook';
        $command->provider_user_id = $info->id;
        $command->username = $info->name;
        return $command;
    }
 
}
