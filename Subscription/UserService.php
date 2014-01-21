<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Subscription;

use Doctrine\ORM\EntityManager;
use Newscoop\Entity\User;

/**
 * User Service
 */
class UserService
{
    const CID = SubscriptionFacade::CID;

    /**
     * @var Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @param Doctrine\ORM\EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Find user by given customer id
     *
     * @param string $cid
     * @return Newscoop\Entity\User
     */
    public function findByCustomerId($cid)
    {
        $query = $this->createQueryBuilder($cid)
            ->getQuery();

        return $query->getOneOrNullResult();
    }

    /**
     * Get subcodes view
     *
     * @param array $subcodes
     * @return array
     */
    public function getSubcodesView(array $subcodes)
    {
        if (empty($subcodes)) {
            return array();
        }

        $query = $this->createQueryBuilder($subcodes)
            ->select('u.username, a.value')
            ->getQuery();

        $subcodesView = array_combine($subcodes, array_map(function () { return null; }, $subcodes));
        foreach ($query->getResult() as $row) {
            $subcodesView[$row['value']] = $row['username'];
        }

        return $subcodesView;
    }

    /**
     * Test if given customer id is used by any other user
     *
     * @param string $cid
     * @param Newscoop\Entity\User $user
     * @return bool
     */
    public function isUsedCustomerId($cid, User $user)
    {
        $query = $this->createQueryBuilder($cid)
            ->select('COUNT(u)')
            ->andWhere('u.id != :user')
            ->setParameter('user', $user->getId())
            ->getQuery();

        return (bool) $query->getSingleScalarResult();
    }

    /**
     * Create query builder for fetching users with given customer id
     *
     * @param mixed $cids
     * @return Doctrine\ORM\QueryBuilder
     */
    private function createQueryBuilder($cids)
    {
        return $this->em->getRepository('Newscoop\Entity\User')
            ->createQueryBuilder('u')
            ->join('u.attributes', 'a')
            ->andWhere('a.attribute = :attribute')
            ->andWhere('a.value in (:codes)')
            ->setParameter('attribute', self::CID)
            ->setParameter('codes', (array) $cids);
    }
}
