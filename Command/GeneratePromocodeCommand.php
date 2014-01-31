<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Tageswoche\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console;

/**
 */
class GeneratePromocodeCommand extends Console\Command\Command
{
    /**
     */
    protected function configure()
    {
        $this->setName('promocode:generate')
            ->setDescription('Generate promocodes.')
            ->addArgument('count', InputArgument::OPTIONAL, 'Promocode count', 10000);
    }

    /**
     */
    protected function execute($input, $output)
    {
        $this->getHelper('container')->getService('promocode.generator')->generate($input->getArgument('count'));
    }
}
