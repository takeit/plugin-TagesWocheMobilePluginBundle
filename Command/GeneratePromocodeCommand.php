<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 */
class GeneratePromocodeCommand extends Command
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getHelper('container')->getService('promocode.generator')->generate($input->getArgument('count'));
    }
}
