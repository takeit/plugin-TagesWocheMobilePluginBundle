<?php

namespace Newscoop\TagesWocheMobilePluginBundle\EventListener;

use Newscoop\NewscoopBundle\Event\ConfigureMenuEvent;
use Symfony\Component\Translation\Translator;

class ConfigureMenuListener
{
    private $translator;

    /**
     * @param Translator $translator
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @param ConfigureMenuEvent $event
     */
    public function onMenuConfigure(ConfigureMenuEvent $event)
    {
        $menu = $event->getMenu();

        $menu[$this->translator->trans('Plugins')]->addChild(
        	$this->translator->trans('plugin.twmobile.admin.titlemenu'),
        	array('uri' => $event->getRouter()->generate('newscoop_tageswochemobileplugin_default_admin'))
        );
    }
}
