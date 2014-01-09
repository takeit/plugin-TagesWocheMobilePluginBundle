<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Tageswoche\Repository;

use Doctrine\ORM\EntityRepository;

/**
 */
class PromocodeRepository extends EntityRepository
{
    /**
     * Get all generated codes
     *
     * @return array
     */
    public function getCodes()
    {
        $query = $this->createQueryBuilder('p')
            ->select('p.code')
            ->getQuery();

        return array_map(function($row) {
            return $row['code'];
        }, $query->getResult());
    }
}
