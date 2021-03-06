<?php
/**
 * @package   Newscoop\TagesWocheMobilePluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Services;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityNotFoundException;
use Imagine\Image\Box;
use Imagine\Gd\Imagine;
use Newscoop\Entity\User;
use Newscoop\Image\ImageService;
use Newscoop\TagesWocheMobilePluginBundle\Subscription\SubscriptionFacade;
use Newscoop\TagesWocheMobilePluginBundle\Profile\UpdateProfileCommand;

/**
 * Update profile service
 */
class UpdateProfileService
{
    const CID = SubscriptionFacade::CID;

    /**
     * @var Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @var Newscoop\Image\ImageService
     */
    private $imageService;

    /**
     * @var Newscoop\TagesWocheMobilePluginBundle\Subscription\SubscriptionFacade
     */
    private $subscriptionService;

    /**
     * @var array
     */
    protected $supportedTypes = array(
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
    );

    /**
     * @param Doctrine\ORM\EntityManager $em
     * @param Newscoop\Image\ImageService $imageService
     * @param Newscoop\TagesWocheMobilePluginBundle\Subscription\SubscriptionFacade $subscriptionService
     */
    public function __construct(
        EntityManager $em,
        ImageService $imageService,
        SubscriptionFacade $subscriptionService
    ) {
        $this->em = $em;
        $this->imageService = $imageService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Update user profile
     *
     * @param Newscoop\TagesWocheMobilePluginBundle\Profile\UpdateProfileCommand $command
     * @return void
     */
    public function updateProfile(UpdateProfileCommand $command)
    {
        $command->user = is_int($command->user)
            ? $this->findUserById($command->user)
            : $command->user;

        $this->validateUsername($command->username, $command->user);
        $this->subscriptionService->updateProfile($command);

        if (!empty($command->image)) {
            $command->image = $this->saveProfileImage($command->image);
        }

        $command->user->updateProfile(
            $command->username,
            $command->password,
            $command->first_name,
            $command->last_name,
            $command->image,
            $command->attributes
        );

        $this->em->flush();
    }

    /**
     * Save Profile image
     *
     * @param array $info
     *
     * @return string
     */
    public function saveProfileImage(array $info) {
        if (!in_array($info['type'], $this->supportedTypes)) {
            throw new \InvalidArgumentException("Unsupported image type '$info[type]'.");
        }

        $imagine = new Imagine();

        $extension = substr($info['type'], (strpos($info['type'], "/") + 1));

        $originalName = sha1_file($info['tmp_name']) . '.' . array_pop(explode('.', $info['name']));
        $newName = sha1_file($info['tmp_name']) . '.' . $extension;

        $originalPath = APPLICATION_PATH . "/../images/" . $originalName;
        $newPath = APPLICATION_PATH . "/../images/" . $newName;

        if (!file_exists($originalPath)) {
            rename($info['tmp_name'], $originalPath);
        }

        //rename($originalPath, $newPath);

        $profileImagePath = APPLICATION_PATH . "/../images/cache/125x125/fit/images|" . $newName;
        $imagine->open($originalPath)
            ->resize(new Box(125, 125))
            ->save($profileImagePath);

        $profileImagePath = APPLICATION_PATH . "/../images/cache/130x130/crop/images|" . $newName;
        $imagine->open($originalPath)
            ->resize(new Box(130, 130))
            ->save($profileImagePath);

        return $newName;
    }

    /**
     * Find user by given id
     *
     * @param int $id
     * @return Newscoop\Entity\User
     */
    private function findUserById($id)
    {
        $user = $this->em->getRepository('Newscoop\Entity\User')->find($id);

        if ($user === null) {
            throw new EntityNotFoundException($id);
        }

        return $user;
    }

    /**
     * Validate that username is unique
     *
     * @param string $username
     * @param Newscoop\Entity\User $user
     * @return void
     */
    private function validateUsername($username, User $user)
    {
        if (!$this->em->getRepository('Newscoop\Entity\User')->isUnique('username', $username, $user->getId())) {
            throw new UsernameException($username);
        }
    }
}
