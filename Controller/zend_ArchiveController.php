<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

use Newscoop\Entity\Article;
use Newscoop\Webcode\Manager;

require_once __DIR__ . '/AbstractController.php';

/**
 * Archive calendar API service controller.
 */
class Api_ArchiveController extends AbstractController
{
    const LANGUAGE = 5;

    /** @var Zend_Controller_Request_Http */
    private $request;

    /** @var array */
    private $data = array();


    /**
     * Init controller
     */
    public function init()
    {
        $this->_helper->layout->disableLayout();
        $this->language = $this->_helper->entity->getRepository('Newscoop\Entity\Language')
            ->findOneBy(array('id' => self::LANGUAGE));
        $this->articleService = $this->_helper->service('article');
    }

    /**
     * Default action controller
     */
    public function indexAction()
    {
        $this->_forward('calendar');
    }

    /**
     * Return list of articles of the day for the given month.
     *
     * @return Newscoop\API\Response
     */
    public function calendarAction()
    {
        $params = $this->getRequest()->getParams();

        $now = new DateTime();
        $year = empty($params['year']) ? $now->format('Y') : $params['year'];
        $month = empty($params['month']) ? $now->format('m') : $params['month'];
        try {
            $startDate = new DateTime($year . '-' . $month . '-01');
            $endDate = new DateTime($year . '-' . $month . '-'. date('t', mktime(0, 0, 0, $month, 1, $year)) . ' 23:59:59');
        } catch (\Exception $e) {
            $this->_helper->json($this->data);
        }

        $articles = \Article::GetArticlesOfTheDay($startDate->format(self::DATE_FORMAT), $endDate->format(self::DATE_FORMAT));

        foreach ($articles as $item) {
            $article = $this->articleService->find($this->language, $item->getArticleNumber());
            if (!empty($article)) {
                $this->data[] = $this->formatArticle($article);
            }
        }

        $this->_helper->json($this->data);
    }
}
