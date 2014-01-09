<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Tageswoche\Repository;

use DateTime;
use Doctrine\ORM\EntityRepository;
use Newscoop\Entity\User;

/**
 */
class DeviceRepository extends EntityRepository
{
    /**
     * Get associated device ids for given user/date
     *
     * @param Newscoop\Entity\User $user
     * @param DateTime $date
     * @return array
     */
    public function getDeviceIds(User $user, DateTime $date)
    {
        $query = $this->createQueryBuilder('d')
            ->select('d.device')
            ->andWhere('d.user = :user')
            ->andWhere('d.validUntil = :validUntil')
            ->getQuery();

        $query->setParameters(array(
            'user' => $user,
            'validUntil' => $date,
        ));

        return array_map(function ($row) {
            return $row['device'];
        }, $query->getResult());
    }
}
