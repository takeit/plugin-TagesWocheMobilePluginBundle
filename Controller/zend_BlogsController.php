<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

use Newscoop\Entity\Article;

require_once __DIR__ . '/AbstractController.php';

/**
 * Blogs API service controller.
 */
class Api_BlogsController extends AbstractController
{
    const PUBLICATION = 5;
    const ISSUE = 3;
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
     * Init controller.
     */
    public function init()
    {
        $this->_helper->layout->disableLayout();
        $this->request = $this->getRequest();
    }

    /**
     * Default action controller.
     */
    public function indexAction()
    {
        $this->_forward('list');
    }

    /**
     * Returns list of articles.
     *
     * @return json
     */
    public function listAction()
    {
        $repository = $this->_helper->entity->getRepository('Newscoop\Entity\Playlist');
        $playlist = $repository->findOneBy(array('id' => self::PLAYLIST_ID));
        $articles = array();
        if ($playlist) {
            $articles = array_map(function ($playlistArticle) {
                return $playlistArticle->getArticle();
            }, $repository->articles($playlist, null, true));
        }

        $rank = 1;
        $response = array();
        foreach ($articles as $info) {
            if ($info->getData('active')) {
                $response[] = array(
                    'blog_id' => (int) $info->getSectionNumber(),
                    'url' => $this->view->serverUrl($this->view->url(array(
                        'module' => 'api',
                        'controller' => 'blogs',
                        'action' => 'posts',
                    ), 'default') . $this->getApiQueryString(array('blog_id' => (int) $info->getSectionNumber()))),
                    'title' => $info->getTitle(),
                    'description' => trim(strip_tags($info->getData('infolong'))),
                    'rank' => $rank++,
                    'image_url' => $this->getBlogImageUrl($info),
                    'website_url' => $this->getWebsiteUrl($info),
                    'published' => $this->formatDate(new DateTime($info->getPublishDate())),
                );
            }
        }

        $this->_helper->json($response);
    }

    /**
     * Routes to PostsList or PostsItem.
     */
    public function postsAction()
    {
        $params = $this->request->getParams();
        if (in_array('post_id', array_keys($params))) {
            $this->_forward('posts-item');
        } else {
            $this->_forward('posts-list');
        }
    }

    /**
     * Returns list of posts.
     *
     * @return json
     */
    public function postsListAction()
    {
        $this->getHelper('contextSwitch')->addActionContext('posts-list', 'json')->initContext();

        $response = array();
        $blogId = $this->request->getParam('blog_id');
        if ($blogId) {
            $sections = $this->_helper->service('section')->findBy(array('publication' => self::PUBLICATION, 'number' => $blogId));
            if (empty($sections)) {
                $this->sendError('Blog not found', 404);
            }

            $section = $sections[0]->getNumber();
            $blogInfo = $this->getBlogInfo($section);
            if (!$blogInfo['active']) {
                $this->sendError('Blog not found', 404);
            }

            $posts = $this->_helper->service('article')->findBy(
                array('sectionId' => $section, 'type' => 'blog', 'workflowStatus' => Article::STATUS_PUBLISHED),
                array('published' => 'desc'), self::LIST_LIMIT);
        } else {
            $posts = $this->_helper->service('article')->findBy(
                array('type' => 'blog', 'workflowStatus' => Article::STATUS_PUBLISHED),
                array('published' => 'desc'), self::LIST_LIMIT);
        }

        $this->commentStats = $this->_helper->service('comment')->getArticleStats(array_map(function($post) {
            return $post->getNumber();
        }, $posts));

        $rank = 1;
        $listAds = $this->getArticleListAds('blogs_dossiers');
        $ad = 0;
        foreach ($posts as $post) {
            if (!isset($section)) {
                $blogInfo = $this->getBlogInfo($post->getSectionNumber());
                if (!$blogInfo['active']) {
                    continue;
                }
            }

            if ($blogId && in_array($rank, $this->adRanks)) {
                if (!empty($listAds[$ad])) {
                    $response[] = array_merge($this->formatArticle($listAds[$ad]), array(
                        'blog_description' => trim(strip_tags($blogInfo['infolong'])), 
                        'author_name' => '',
                        'blog_id' => $section,
                        'blog_name' => $blogInfo['name'],
                        'blog_image_url' => $blogInfo['image_url'],
                        'blogpost_id' => $post->getNumber(),
                        'rank' => (int) $rank++));
                    $ad++;
                }
            }

            $response[] = array_merge(parent::formatArticle($post), array( // add url
                'blogpost_id' => $post->getNumber(),
                'url' => $this->view->serverUrl($this->view->url(array(
                    'module' => 'api',
                    'controller' => 'blogs',
                    'action' => 'posts',
                ), 'default') . sprintf('?post_id=%d', $post->getNumber()) . $this->getClientVersionParams(false)),
                'blog_description' => trim(strip_tags($blogInfo['infolong'])), 
                'author_name' => implode(',', $this->getAuthors($post)),
                'blog_id' => $section,
                'blog_name' => $blogInfo['name'],
                'blog_image_url' => $blogInfo['image_url'],
            ));
            $rank++;
        }

        $this->_helper->json($response);
    }

    /**
     * Returns the requested blog post.
     *
     * @return html document 
     */
    public function postsItemAction()
    {
        $article = $this->_helper->service('article')->findOneByNumber($this->_getParam('post_id'));
        if (empty($article)) {
            $this->sendError('Blog post not found.', 404);
        }

        $this->_helper->cache->validateBrowserCache($article->getUpdated());

        $this->_helper->smarty->setSmartyView();
        $this->view->getGimme()->article = new MetaArticle($article->getLanguageId(), $article->getNumber());
        $this->render('post');
    }

    /**
     * Returns the response data for the requested bloginfo.
     *
     * @param int $blogId
     * @return array
     */
    private function getBlogInfo($blogId)
    {
        $blogInfos = $this->_helper->service('article')->findBy(array('sectionId' => $blogId, 'type' => 'bloginfo'));
        if (empty($blogInfos)) {
            //$this->sendError('Blog not found, id: ' . $blogId, 404);
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
        $image = $post->getImage();
        if ($image) {
            return $this->view->serverUrl($this->view->url(array(
                'src' => $this->getHelper('service')->getService('image')->getSrc($image->getPath(), $this->getImageWidth(), $this->getImageHeight(), 'crop'),
            ), 'image', false, false));
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
        return $this->isRetinaClient() ? self::IMAGE_RETINA_WIDTH : self::IMAGE_STANDARD_WIDTH;
    }

    /**
     * Get image height
     *
     * @return int
     */
    private function getImageHeight()
    {
        return $this->isRetinaClient() ? self::IMAGE_RETINA_HEIGHT : self::IMAGE_STANDARD_HEIGHT;
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
        foreach($post->getAuthors() as $author) {
            $authors[] = $author->getFullName();
        }

        return $authors;
    }
}
