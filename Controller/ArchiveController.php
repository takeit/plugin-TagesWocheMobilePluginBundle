<?php
/**
 * @package Newscoop
 * @copyright 2013 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */
namespace Newscoop\TagesWocheMobilePluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Newscoop\Entity\Article;
use Newscoop\Webcode\Manager;
use DateTime;

/**
 * Route("/archive")
 *
 * Archive calendar API service controller.
 */
class ArchiveController extends Controller
{
    const LANGUAGE = 5;

    /**
     * Init controller
     */
    // public function init()
    // {
    //     $this->_helper->layout->disableLayout();
    //     $this->language = $this->_helper->entity->getRepository('Newscoop\Entity\Language')
    //         ->findOneBy(array('id' => self::LANGUAGE));
    //     $this->articleService = $this->_helper->service('article');
    // }

    /**
     * @Route("/index")
     * @Route("/calendar")
     *
     * Return list of articles of the day for the given month.
     *
     * @return Newscoop\API\Response
     */
    public function calendarAction(Request $request)
    {
        $apiHelper = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $em = $this->container->get('em');

        $defaultLanguage = $em->getRepository('Newscoop\Entity\Language')
            ->findOneBy(array('id' => self::LANGUAGE));

        $year = $request->query->get('year');
        $month = $request->query->get('month');

        $now = new DateTime();
        $year = empty($year) ? $now->format('Y') : $year;
        $month = empty($month) ? $now->format('m') : $month;
        try {
            $startDate = new DateTime($year . '-' . $month . '-01');
            $endDate = new DateTime($year . '-' . $month . '-'. date('t', mktime(0, 0, 0, $month, 1, $year)) . ' 23:59:59');
        } catch (\Exception $e) {
            return new JsonResponse($this->data);
        }

        // TODO: fix this
        // $articles = \Article::GetArticlesOfTheDay($startDate->format(self::DATE_FORMAT), $endDate->format(self::DATE_FORMAT));

        // foreach ($articles as $item) {
        //     $article = $em->getRepository('Newscoop\Entity\Article')
        //         ->find($defaultLanguage, $item->getArticleNumber());
        //     if ($article !== null) {
        //         $this->data[] = $apiHelper->formatArticle($article);
        //     }
        // }

        return new JsonResponse($this->data);
    }
}
