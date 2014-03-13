<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Profile;

use Symfony\Component\Validator\Constraints as Assert;

class ConfirmCommand
{
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
     */
    public $password;
    /**
     */
    public $password_confirm;
    /**
     * @Assert\GreaterThan(
     *     value = 0 
     * )
     */
    public $terms_of_use;
   
}
