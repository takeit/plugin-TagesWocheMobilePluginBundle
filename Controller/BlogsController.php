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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Newscoop\Entity\Article;

class BlogsController extends Controller
{
    const PUBLICATION = 1;
    const ISSUE = 3;
    const ISSUE_ID = 13;
    const LANGUAGE = 5;
    const LIST_LIMIT = 30;
    const IMAGE_RENDITION = 'topfront';
    const IMAGE_STANDARD_WIDTH = 320;
    const IMAGE_STANDARD_HEIGHT = 140;
    const IMAGE_RETINA_WIDTH = 640;
    const IMAGE_RETINA_HEIGHT = 280;
    const PLAYLIST_ID = 30;

    /** @var Zend_Controller_Request_Http */
    private $request;

    /**
     * @var array
     */
    private $commentStats = array();

    /** @var array */
    private $adRanks = array(4, 9, 15);

    /**
     * @Route("/index")
     * @Route("/list")
     *
     * Returns list of articles.
     *
     * @return json
     */
    public function listAction(Request $request)
    {
        $em = $this->container->get('em');
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');

        $repository = $em->getRepository('Newscoop\Entity\Playlist');
        $playlist = $repository->findOneBy(array('id' => self::PLAYLIST_ID));
        $articles = array();
        if ($playlist) {
            $articles = array_map(function ($playlistArticle) {
                return $playlistArticle->getArticle();
            }, $repository->articles($playlist, null, true));
        }

        $rank = 1;
        $response = array();
        foreach ($articles as $article) {
            if ($article->getData('active')) {

                $response[] = array(
                    'blog_id' => (int) $article->getSection()->getNumber(),
                    'url' => $apiHelperService->serverUrl(
                        $this->container->get('router')
                        ->generate(
                            'newscoop_tageswochemobileplugin_blogs_postlist',
                            array('blog_id' => (int) $article->getSection()->getNumber())
                        )
                    ),
                    'title' => $article->getTitle(),
                    'description' => trim(strip_tags($article->getData('infolong'))),
                    'rank' => $rank++,
                    'image_url' => $apiHelperService->serverUrl(
                        $apiHelperService->getLocalImageUrl(
                            $article->getFirstImage(),
                            $this->getImageSizesNormal(),
                            $this->getImageSizesRetina()
                        )
                    ),
                    'website_url' => $apiHelperService->getWebsiteUrl($article),
                    'published' => $apiHelperService->formatDate($article->getPublished()),
                );
            }
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/posts/list")
     */
    public function postListAction(Request $request)
    {
        $em = $this->container->get('em');
        $apiHelperService = $this->container->get('newscoop_tageswochemobile_plugin.api_helper');
        $blogId = $request->query->get('blog_id');

        $response = array();
        if ($blogId) {
            $sections = $em->getRepository('Newscoop\Entity\Section')
                ->findBy(array(
                    'publication' => self::PUBLICATION,
                    'issue' => self::ISSUE_ID,
                    'number' => $blogId
                ));
            if ($sections === null || (is_array($sections) && count($sections) == 0)) {
                return $apiHelperService->sendError('Blog not found', 404);
            }

            $section = $sections[0]->getNumber();
            $blogInfo = $this->getBlogInfo($section);
            if (!$blogInfo || !array_key_exists('active', $blogInfo) || !$blogInfo['active']) {
                return $apiHelperService->sendError('Blog not found', 404);
            }

            $posts = $em->getRepository('Newscoop\Entity\Article')
                ->findBy(
                    array(
                        'section' => $section, 'type' => 'blog',
                        'workflowStatus' => Article::STATUS_PUBLISHED
                    ),
                    array('published' => 'desc'), self::LIST_LIMIT
                );
        } else {
            $section = null;
            $posts = $em->getRepository('Newscoop\Entity\Article')
                ->findBy(
                    array(
                        'type' => 'blog',
                        'workflowStatus' => Article::STATUS_PUBLISHED
                    ),
                    array('published' => 'desc'), self::LIST_LIMIT
                );
        }

        $this->commentStats = $this->container->get('comment')->getArticleStats(array_map(function($post) {
            return $post->getNumber();
        }, $posts));

        $rank = 1;
        $listAds = $apiHelperService->getArticleListAds('blogs_dossiers');
        $ad = 0;
        foreach ($posts as $post) {
            if (!isset($section)) {
                $blogInfo = $this->getBlogInfo($post->getSection()->getNumber());
                if (!$blogInfo || !array_key_exists('active', $blogInfo) || !$blogInfo['active']) {
                    continue;
                }
            }

            if ($blogId && in_array($rank, $this->adRanks)) {
                if (!empty($listAds[$ad])) {
                    $response[] = array_merge(
                        $apiHelperService->formatArticle($listAds[$ad]),
                        array(
                            'blog_description' => trim(strip_tags($blogInfo['infolong'])),
                            'author_name' => '',
                            'blog_id' => $section,
                            'blog_name' => $blogInfo['name'],
                            'blog_image_url' => $blogInfo['image_url'],
                            'blogpost_id' => $post->getNumber(),
                            'rank' => (int) $rank++
                        )
                    );
                    $ad++;
                }
            }

            $response[] = array_merge(
                $apiHelperService->formatArticle($post),
                array( // add url
                    'blogpost_id' => $post->getNumber(),
                    'url' => $apiHelperService->serverUrl(
                        $this->container->get('zend_router')->assemble(array(
                            'module' => 'api',
                            'controller' => 'blogs',
                            'action' => 'posts',
                        ), 'default') . sprintf('?post_id=%d', $post->getNumber()) . $apiHelperService->getClientVersionParams(false)
                    ),
                    'blog_description' => trim(strip_tags($blogInfo['infolong'])),
                    'author_name' => implode(',', $this->getAuthors($post)),
                    'blog_id' => $section,
                    'blog_name' => $blogInfo['name'],
                    'blog_image_url' => $blogInfo['image_url'],
                )
            );
            $rank++;
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/posts")
     */
    public function postItemAction(Request $request)
    {
        $postId = $request->query->get('post_id');
        $apiHelperService = $this->container
            ->get('newscoop_tageswochemobile_plugin.api_helper');
        $article = $this->container->get('em')
            ->getRepository('Newscoop\Entity\Article')
            ->findOneByNumber($postId);
        if ($article === null) {
            return $apiHelperService->sendError('Blog post not found.', 404);
        }

        $cacheHelper = $this->container
            ->get('newscoop_tageswochemobile_plugin.cache_helper');

        $cacheHelper->validateBrowserCache($article->getUpdated(), $request);

        $templatesService = $this->container->get('newscoop.templates.service');
        $smarty = $templatesService->getSmarty();
        $context = $smarty->context();
        $context->article = new \MetaArticle($article->getLanguageId(), $article->getNumber());

        $response = new Response();
        $response->setContent($templatesService->fetchTemplate("_mobile/articles_frontsize.tpl"));
        return $response;
    }

    /**
     * Returns the response data for the requested bloginfo.
     *
     * @param int $blogId
     * @return array
     */
    private function getBlogInfo($blogId)
    {
        $apiHelperService = $this->container
            ->get('newscoop_tageswochemobile_plugin.api_helper');
        $blogInfos = $this->container->get('em')
            ->getRepository('Newscoop\Entity\Article')
            ->findBy(array('section' => $blogId, 'type' => 'bloginfo'));

        if (empty($blogInfos)) {
            return false;
        }

        return array(
            'id' => $blogInfos[0]->getNumber(),
            'name' => $blogInfos[0]->getName(),
            'image_url' => $this->getBlogImageUrl($blogInfos[0]),
            'infolong' => $blogInfos[0]->getData('infolong'),
            'active' => $blogInfos[0]->getData('active') ? true : false,
        );
    }

    /**
     * Get cover image url
     *
     * @param Newscoop\Entity\Article $post
     * @return string
     */
    protected function getBlogImageUrl(Article $post)
    {
        $apiHelperService = $this->container
            ->get('newscoop_tageswochemobile_plugin.api_helper');
        $image = $post->getFirstImage();
        if ($image !== null) {
            return $apiHelperService->serverUrl(
                $this->container->get('zend_router')->assemble(array(
                    'src' => $this->container->get('image')->getSrc($image->getPath(), $this->getImageWidth(), $this->getImageHeight(), 'crop'),
                ), 'image', false, false)
            );
        } else {
            return null;
        }
    }

    /**
     * Get image width
     *
     * @return int
     */
    private function getImageWidth()
    {
        $apiHelperService = $this->container
            ->get('newscoop_tageswochemobile_plugin.api_helper');
        return $apiHelperService->isRetinaClient() ? self::IMAGE_RETINA_WIDTH : self::IMAGE_STANDARD_WIDTH;
    }

    /**
     * Get image height
     *
     * @return int
     */
    private function getImageHeight()
    {
        $apiHelperService = $this->container
            ->get('newscoop_tageswochemobile_plugin.api_helper');
        return $apiHelperService->isRetinaClient() ? self::IMAGE_RETINA_HEIGHT : self::IMAGE_STANDARD_HEIGHT;
    }

    /**
     * Get image sizes
     *
     * @return int
     */
    private function getImageSizesNormal()
    {
        return array(self::IMAGE_STANDARD_WIDTH, self::IMAGE_STANDARD_HEIGHT);
    }

    /**
     * Get image sizes for retina
     *
     * @return int
     */
    private function getImageSizesRetina()
    {
        return array(self::IMAGE_RETINA_WIDTH, self::IMAGE_RETINA_HEIGHT);
    }

    /**
     * Returns blog author names.
     *
     * @param Newscoop\Entity\Article $article
     * @return array
     */
    private function getAuthors(Article $post)
    {
        $authors = array();
        foreach($post->getArticleAuthors() as $author) {
            $authors[] = $author->getFullName();
        }

        return $authors;
    }
}
