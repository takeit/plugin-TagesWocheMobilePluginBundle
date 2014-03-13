<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Profile;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateProfileCommand
{
    public $user;
    /**
     * @Assert\NotBlank(message="username is required")
     */
    public $username;
    /**
     * @Assert\NotBlank(message="password is required")
     */
    public $password;
    /**
     */
    public $first_name;
    /**
     */
    public $last_name;
    /**
     * @Assert\Image(
     *     maxSize = "1024k"
     * )
     */
    public $image;
    public $attributes;
}
