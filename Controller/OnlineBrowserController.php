<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
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
 * Issues Service
 */
class OnlineBrowserController extends OnlineController
{
    private $rank = 0;

    private $currentIssueId = null;

    /**
     * @Route("/")
     */
    public function issuesAction()
    {
        $mobileService = $this->container->get('newscoop_tageswochemobile_plugin.mobile.issue');

        $issues = $mobileService->findAll();
        $issues = array_map(
            array($this, 'formatIndexIssue'),
            $issues
        );

        return new JsonResponse(array(
            'additional_data' => $this->getAdditionalData(),
            'mapi' => $issues
        ));
    }

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
     * Format issue for list
     *
     * @param Newscoop\Entity\Article $issue
     * @return array
     */
    protected function formatIndexIssue(Article $issue)
    {
        $formattedIssue = parent::formatIndexIssue($issue);
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $formattedIssue['url'] = $apiHelperService->serverUrl('api/online_browser/' . $issue->getNumber());

        if ($formattedIssue['issue_id'] == $this->currentIssueId)  {
            $formattedIssue['current'] = true;
        } else {
            $formattedIssue['current'] = false;
        }

        return $formattedIssue;
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
        $formattedIssue = parent::formatTocIssue($issue);

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
        $formattedArticle['backside_url'] = $apiHelperService->serverUrl('api/online_browser/articles/' . $formattedArticle['article_id'] .'?side=back');

        return $formattedArticle;
    }

    /**
     * Gets additional information send with each request
     *
     * @return array Array container additional data
     */
    private function getAdditionalData()
    {
        $user = $this->getCurrentUser();
        $user_id = null;
        $subscription = null;

        if ($user instanceof User) {
            $user_id = $user->getId();
            $subscription = $this->getSubscription($user);
        }

        return array(
            'user_id' => $user_id,
            'subscription' => $subscription,
        );
    }

    /**
     * Get current logged in user via userservice
     *
     * @return mixed Returns user object or null
     */
    private function getCurrentUser()
    {
        return $this->container->get('user')->getCurrentUser();
    }

    /**
     * Get subscription data
     */
    private function getSubscription($user)
    {
        return $this->container->get('newscoop_tageswochemobile_plugin.verlags_manager_service')->findSubscriber($user);
    }
}
