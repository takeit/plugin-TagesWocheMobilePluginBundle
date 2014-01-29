<?php
/**
 * @package Newscoop
 * @copyright 2011 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Newscoop\TagesWocheMobilePluginBundle\Entity\RelatedArticleList;
use Newscoop\Entity\Article;

/**
 */
class RelatedArticleListArticleRepository extends EntityRepository
{
    /**
     * Find articles assigned to given list
     *
     * @param Newscoop\TagesWocheMobilePluginBundle\Entity\RelatedArticleList $list
     * @return array
     */
    public function findArticlesByList(RelatedArticleList $list)
    {
        $query = $this->createQueryBuilder('r')
            ->select('r, a')
            ->join('r.article', 'a')
            ->where('r.list = :list')
            ->andWhere('a.workflowStatus = :published')
            ->orderBy('r.id')
            ->getQuery();

        $query->setParameter('list', $list);
        $query->setParameter('published', Article::STATUS_PUBLISHED);

        return array_map(function($listArticle) {
            return $listArticle->getArticle();
        }, $query->getResult());
    }
}
