<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * API Legal service controller.
 */
class Api_LegalController extends Zend_Controller_Action
{
    const ARTICLE_NUMBER = 3915;
    const ARTICLE_LANGUAGE = 5;

    /**
     */
    public function init()
    {
        $this->_helper->layout->disableLayout();
    }

    /**
     */
    public function privacyAction()
    {
        $this->_helper->smarty->setSmartyView();
        $this->view->getGimme()->article = new MetaArticle(self::ARTICLE_LANGUAGE, self::ARTICLE_NUMBER);
        $this->getResponse()->setHeader('Content-type', 'text/plain; charset=utf-8');
        $this->render('privacy');
    }
}
