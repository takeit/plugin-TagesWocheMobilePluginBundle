<?php
/**
 * @package Newscoop
 * @copyright 2014 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */
namespace Newscoop\TagesWocheMobilePluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Newscoop\Entity\Article;

/**
 * Route('/legal')
 */
class LegalController extends Controller
{
    const ARTICLE_NUMBER = 3915;

    /**
     * @Route("/index")
     * @Route("/privacy")
     */
    public function privacyAction(Request $request)
    {
        $apiHelper = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $em = $this->container->get('em');

        $article = $em->getRepository('Newscoop\Entity\Article')
            ->findOneByNumber(self::ARTICLE_NUMBER);

        if (!$article || !$article->isPublished()) {
            return $apiHelper->sendError('Article not found', 404);
        }

        $template = 'privacy';
        $data = array('data' => array(
            'published'  => $article->getPublished(),
            'title' => $article->getTitle(),
            'body' => $apiHelper->getBody($article),
        ));

        return $this->render(
            'NewscoopTagesWocheMobilePluginBundle:legal:'.$template.'.txt.twig',
            $data
        );
    }
}
