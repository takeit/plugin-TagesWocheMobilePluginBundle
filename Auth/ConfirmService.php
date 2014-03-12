<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Auth;

use Doctrine\ORM\EntityManager;
use Newscoop\Entity\User;
use Newscoop\Services\Auth\SocialAuthService;

use Newscoop\TagesWocheMobilePluginBundle\Profile\FacebookConfirmCommand;

/**
 */
class ConfirmService
{
    const PLACEHOLDER_COUNT = 6;

    /**
     * @var Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @var Newscoop\Services\Auth\SocialAuthService
     */
    private $auth;

    /**
     * @var Newscoop\Entity\User
     */
    private $repository;

    /**
     * @param Doctrine\ORM\EntityManager $em
     * @param Newscoop\Services\Auth\SocialAuthService $auth
     * @param Newscoop\User\UsernameService $usernameService
     * @param Newscoop\User\UserimageService $userimageService
     */
    public function __construct(
        EntityManager $em,
        SocialAuthService $auth
    ) {
        $this->em = $em;
        $this->auth = $auth;
	$this->repository = $em->getRepository('Newscoop\Entity\User');
    }

    /**
     * Confirm user
     *
     * @param Newscoop\TagesWocheMobilePluginBundle\Profile\FacebookConfirmCommand $command
     * @return Newscoop\Entity\User
     */
    public function confirm(FacebookConfirmCommand $command)
    {
        $user = $this->em->getRepository('Newscoop\Entity\User')
            ->findOneByEmail($command->email);

        if ($user === null) {
            $command->username = $this->getUnique($command->username);
            $command->image = $this->getPlaceholder($command->id ?: $command->provider_user_id);
            $user = new User();
            $this->confirmUser($user, $command);
            $this->em->persist($user);
            $this->em->flush();
        }

        if (isset($command->provider)) {
            $this->auth->addIdentity($user, $command->provider, $command->provider_user_id);
        }

        return $user;
    }

    /**
     * Confirm user
     *
     * @param Newscoop\User\ConfirmCommand $command
     * @return void
     */
    public function confirmUser(User $user, FacebookConfirmCommand $command)
    {
        $user->setEmail($command->email ?: $user->getEmail());
        $user->setUsername($command->username);
        $user->setFirstName($command->first_name);
        $user->setLastName($command->last_name);
        $user->setImage($command->image);
        $user->setPublic(true);
        $user->setActive();
    }

    /**
     * Get unique username
     *
     * @param string $username
     * @return string
     */
    public function getUnique($username)
    {
        $user = new User();
        $user->setUsername($username);
        $username = $user->getUsername();
        for ($i = ''; $i < 1000; $i++) {
            $conflict = $this->repository->findOneBy(array(
                'username' => "$username{$i}",
            ));

            if (empty($conflict)) {
                return "$username{$i}";
            }
        }
    }

    /**
     * Get placeholder filename
     *
     * @param int $id
     * @return string
     */
    public function getPlaceholder($id)
    {
        return sprintf('user_placeholder_%d.png', $id % self::PLACEHOLDER_COUNT);
    }
}
