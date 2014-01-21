<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Entity;

use Doctrine\ORM\Mapping AS ORM;
use Newscoop\Entity\Article;

/**
 * @ORM\Entity
 * @ORM\Table(name="Xmobile_issue")
 */
class MobileIssueArticle
{
    /**
     * @ORM\OneToOne(targetEntity="Newscoop\Entity\Article")
     * @ORM\JoinColumn(name="NrArticle", referencedColumnName="Number")
     */
    private $article;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer", name="NrArticle")
     * @var int
     */
    private $articleNumber;

    /**
     * @ORM\Column(type="string", name="Fissuedate")
     * @var string
     */
    private $date;

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
