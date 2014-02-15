<?php
/**
 * @package   Newscoop\TagesWocheMobilePluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl.txt
 */
namespace Newscoop\TagesWocheMobilePluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
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
     * @Route("/index")
     * @Route("/calendar")
     *
     * Return list of articles of the day for the given month.
     */
    public function calendarAction(Request $request)
    {
        $data = array();

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
            return new JsonResponse($data);
        }

        $articleCalendarService = $this->container->get('newscoop_articles_calendar.articles_calendar_service');
        $articlesOfTheDay = $articleCalendarService->getArticleOfTheDay($startDate, $endDate);

        foreach ($articlesOfTheDay as $item) {
            $article = $item->getArticle();
            if ($article !== null) {
                $data[] = $apiHelper->formatArticle($article);
            }
        }

        return new JsonResponse($data);
    }
}
