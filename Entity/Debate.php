<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping AS ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="plugin_debate")
 */
class Debate
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", name="debate_nr")
     * @var int
     */
    private $id;

    /**
     * @ORM\ManyToMany(targetEntity="Newscoop\Entity\Article")
     * @ORM\JoinTable(name="plugin_debate_article",
     *      joinColumns={
     *          @ORM\JoinColumn(name="fk_debate_nr", referencedColumnName="debate_nr")
     *      },
     *      inverseJoinColumns={
     *          @ORM\JoinColumn(name="fk_article_nr", referencedColumnName="Number"),
     *          @ORM\JoinColumn(name="fk_article_language_id", referencedColumnName="IdLanguage")
     *      })
     * @var Doctrine\Common\Collections\Collection
     */
    private $articles;

    /**
     * @ORM\OneToMany(targetEntity="Newscoop\TagesWocheMobilePluginBundle\Entity\Vote", mappedBy="debate")
     * @var Doctrine\Common\Collections\Collection
     */
    private $votes;

    /**
     */
    public function __construct()
    {
        $this->articles = new ArrayCollection();
        $this->votes = new ArrayCollection();
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get votes
     *
     * @return array
     */
    public function getVotes()
    {
        return $this->votes;
    }
}
