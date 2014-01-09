<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Tageswoche\Subscription;

use Doctrine\ORM\EntityManager;
use Tageswoche\Entity\Promocode;
use Newscoop\RandomService;

/**
 */
class PromocodeGeneratorService
{
    const LENGTH = 8;

    /**
     * @var Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @var Newscoop\RandomService
     */
    private $random;

    /**
     * @param Doctrine\ORM\EntityManager $em
     * @param Newscoop\RandomGenerator $randomService
     */
    public function __construct(EntityManager $em, RandomService $randomService)
    {
        $this->em = $em;
        $this->random = $randomService;
    }

    /**
     * Generate codes up to given count
     *
     * @param int $count
     * @return void
     */
    public function generate($count)
    {
        $codes = $this->em->getRepository('Tageswoche\Entity\Promocode')->getCodes();
        $codes = array_flip($codes);

        for ($i = count($codes); $i < $count; $i++) {
            do {
                $random = $this->random->getRandomNumberString(self::LENGTH);
            } while (isset($codes[$random]));
            $codes[$random] = $random;
            $code = new Promocode($random);
            $this->em->persist($code);
        }

        $this->em->flush();
    }
}
