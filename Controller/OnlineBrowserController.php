<?php
/**
 * @package Newscoop\TagesWocheMobilePluginBundle
 * @author Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric z.ú.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Newscoop\Entity\Article;
use Newscoop\Entity\User;

use Newscoop\TagesWocheMobilePluginBundle\Mobile\IssueFacade;

/**
 * Route('/online_browser')
 *
 * Online issue service. Please check class agesWocheMobilePluginBundle\EventListener\ApiHelperRequestListener
 * when making changes to the namespace of this file.
 */
class OnlineBrowserController extends OnlineController
{
    /**
     * @var integer
     */
    private $rank = 0;

    /**
     * Contains the article number for the current issue
     *
     * @var mixed
     */
    private $currentIssueId = null;

    /**
     * @Route("/{issue_id}")
     */
    public function tocAction($issue_id, Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $mobileService = $this->container->get('newscoop_tageswochemobile_plugin.mobile.issue');

        if (!$issue_id) {
            return $apiHelperService->sendError('Missing id', 400);
        }

        $this->issue = $mobileService->find($issue_id);
        if (empty($this->issue)) {
            return $apiHelperService->sendError('Issue not found.', 404);
        }

        return new JsonResponse(array(
            'additional_data' => $this->getAdditionalData(),
            'mapi' => $this->formatTocIssue($this->issue)
        ));
    }

    /**
     * @Route("/articles/{article_id}")
     */
    public function articlesAction($article_id, Request $request)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $article = $this->container->get('em')
            ->getRepository('Newscoop\Entity\Article')
            ->findOneByNumber($article_id);
        if (!$article || !$article->isPublished()) {
            return $apiHelperService->sendError('Article not found.', 404);
        }

        if ($this->container->get('newscoop_tageswochemobile_plugin.mobile.issue')->isInCurrentIssue($article)) {

            $user = $this->getUser();

            if (!$user instanceof User) {
                return $apiHelperService->sendError('User not logged in.', 401);
            }

            $subscription = $this->getSubscription($user);

            // Check if user has a subscription
            if (!$subscription) {
                return $apiHelperService->sendError('Invalid or no subscription.', 401);
            }
        }

        $templatesService = $this->container->get('newscoop.templates.service');
        $smarty = $templatesService->getSmarty();
        $gimme =  $smarty->context();
        $gimme->article = new \MetaArticle($article->getLanguageId(), $article->getNumber());
        $smarty->assign('gimme', $gimme);
        $smarty->assign('width', $apiHelperService->getClientWidth());
        $smarty->assign('height', $apiHelperService->getClientHeight());
        $smarty->assign('browser_version', true);

        $response = new Response();
        $response->headers->set('Content-Type', 'text/html');
        $response->setContent($templatesService->fetchTemplate("_views/online_article.tpl"));
        return $response;
    }

    /**
     * @Route("/articles/{article_id}/back")
     */
    public function articlesBackAction($article_id, Request $request)
    {
        // This code is duplicated from ArticlesControler::itemAction
        // Please check changes here and there
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $em = $this->container->get('em');

        $article = $em
            ->getRepository('Newscoop\Entity\Article')
            ->findOneByNumber($article_id);
        if (!$article || (!$article->isPublished() && !$allowUnpublished)) {
            return $apiHelperService->sendError("Article not found", 404);
        }

        $metaArticle = new \MetaArticle($article->getLanguageId(), $article->getNumber());
        $templatesService = $this->container->get('newscoop.templates.service');
        $smarty = $templatesService->getSmarty();
        $context = $smarty->context();
        $context->article = $metaArticle;
        $smarty->assign('webcode', ($article->hasWebcode()) ? $apiHelperService->fixWebcode($article->getWebcode()) : null);
        $smarty->assign('browser_version', true);

        $response = new Response();
        $response->headers->set('Content-Type', 'text/html');
        $response->setContent($templatesService->fetchTemplate('_mobile/articles_backside.tpl'));
        return $response;
    }

    /**
     * Format toc issue
     *
     * @param Newscoop\Entity\Article $issue
     *
     * @return array
     */
    protected function formatTocIssue(Article $issue)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $formattedIssue = parent::formatTocIssue($issue);

        $image = $issue->getFirstImage($issue);
        if ($image) {
            $formattedIssue['cover_url'] = $apiHelperService->serverUrl(
                $apiHelperService->getLocalImageUrl($image, array(650, 901), array(650, 901))
            );
        }
        unset($formattedIssue['offline_url']);

        return $formattedIssue;
    }

    /**
     * Format article
     *
     * @param Newscoop\Entity\Article $article
     *
     * @return array
     */
    protected function formatArticle(Article $article)
    {
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $formattedArticle = parent::formatArticle($article);

        $formattedArticle['url'] = $apiHelperService->serverUrl('api/online_browser/articles/' . $formattedArticle['article_id']);

        if (is_array($formattedArticle['slideshow_images']) && !empty($formattedArticle['slideshow_images'])) {
            $formattedArticle['slideshow_images'] = array_map(function($data) {
                if ($data['type'] == 'video') {
                    $queryString = parse_url($data['url'], PHP_URL_QUERY);
                    parse_str($queryString, $queryBag);
                    $data['url'] = $queryBag['video'];
                }
                return $data;
            }, $formattedArticle['slideshow_images']);
        }

        return $formattedArticle;
    }

    /**
     * Gets additional information send with each request
     *
     * @return array Array container additional data
     */
    private function getAdditionalData()
    {
        $browserHelper = $this->container->get('newscoop_tageswochemobile_plugin.online.browser');
        $user = $browserHelper->getCurrentUser();
        $user_id = null;
        $subscription = null;

        if ($user instanceof User) {
            $user_id = $user->getId();
            $subscription = $browserHelper->getSubscription($user);
        }

        return array(
            'user_id' => $user_id,
            'subscription' => $subscription,
        );
    }
}
