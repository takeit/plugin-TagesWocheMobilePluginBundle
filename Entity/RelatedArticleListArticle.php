<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection as Collection;
use Doctrine\ORM\Mapping AS ORM;
use Newscoop\Entity\Article;

/**
 * Related article list article
 *
 * @ORM\Entity(repositoryClass="Newscoop\TagesWocheMobilePluginBundle\Entity\Repository\RelatedArticleListArticleRepository")
 * @ORM\Table(name="context_articles")
 */
class RelatedArticleListArticle
{
    /**
     * @ORM\Id @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @var int
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="RelatedArticleList")
     * @ORM\JoinColumn(name="fk_context_id")
     * @var Newscoop\TagesWocheMobilePluginBundle\Entity\RelatedArticleList
     */
    private $list;

    /**
     * @ORM\OneToOne(targetEntity="Newscoop\Entity\Article")
     * @ORM\JoinColumn(name="fk_article_no", referencedColumnName="Number")
     * @var Newscoop\Entity\Article
     */
    private $article;

    /**
     * Get article
     *
     * @return Newscoop\Entity\Article
     */
    public function getArticle()
    {
        return $this->article;
    }
}
