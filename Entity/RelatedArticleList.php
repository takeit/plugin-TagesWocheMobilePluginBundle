<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Entity;

use Doctrine\ORM\Mapping AS ORM;
use Doctrine\Common\Collections\ArrayCollection as Collection;

/**
 * Related article list
 *
 * @ORM\Entity
 * @ORM\Table(name="context_boxes")
 */
class RelatedArticleList
{
    /**
     * @ORM\Id @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(type="integer", name="fk_article_no")
     * @var int
     */
    private $number;

    /**
     */
    public function __construct()
    {
        $this->articles = new Collection();
    }
}
