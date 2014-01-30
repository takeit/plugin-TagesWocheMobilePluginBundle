<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace  Newscoop\TagesWocheMobilePluginBundle\Mobile;

use Newscoop\Entity\Article;
use Newscoop\Http\ClientFactory;

/**
 */
class OfflineIssueService
{
    const OFFLINE_HEADER = 'OfflineToken';

    /**
     * @var Newscoop\Http\ClientFactory
     */
    private $clientFactory;

    /**
     * @var array
     */
    private $config = array(
        'path' => '/tmp/issues/',
        'site_url' => 'http://localhost/newscoop/newscoop/',
        'secret' => '',
        'clients' => array(
            'ipad',
            'ipad_retina',
            'iphone',
            'iphone_retina',
        ),
    );

    /**
     * @param Newscoop\Http\ClientFactory $clientFactory
     */
    public function __construct(ClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    /**
     * Get issue path
     *
     * @param Newscoop\Entity\Article $issue
     * @param string $client
     * @return string
     */
    public function getIssueZipPath(Article $issue, $client)
    {
        return $this->getPath(sprintf('issue_%d.zip', $issue->getNumber()), $client);
    }

    /**
     * Get article zip path
     *
     * @param Newscoop\Entity\Article $article
     * @param string $client
     * @return string
     */
    public function getArticleZipPath($article, $client)
    {
        return $this->getPath(sprintf('%d.zip', is_numeric($article) ? $article : $article->article_id), $client);
    }

    /**
     * Get full path for given filename
     *
     * @param string $filename
     * @param string $client
     * @return string
     */
    private function getPath($filename, $client)
    {
        return realpath($this->config['path']) . '/' . $client . '/' . $filename;
    }

    /**
     * Generate offline issue
     *
     * @param Newscoop\Entity\Article $issue
     * @return void
     */
    public function generateIssue(Article $issue)
    {
        foreach ($this->config['clients'] as $client) {
            $toc = $this->fetchJson($this->getApiUrl(array(
                'controller' => 'online',
                'action' => 'toc',
                'id' => $issue->getNumber(),
            ), array(
                'client' => $client,
            )));

            $dir = $this->getIssueDir($toc, $client);
            $this->fetchTocImage($toc, 'cover_url', $dir);

            foreach ($toc->articles as $article) {
                $articleDir = $this->getArticleDir($article, $dir, $client);
                $this->generateArticle($article, $articleDir);
                $this->zip($articleDir, $this->getArticleZipPath($article, $client));
            }

            file_put_contents("$dir/toc.json", json_encode($toc));
            $this->zip($dir, $this->getIssueZipPath($issue, $client));
        }
    }

    /**
     * Zip given directory
     *
     * @param string $dir
     * @param string $desc
     * @return void
     */
    private function zip($dir, $dest)
    {
        chdir($dir);
        $command = sprintf('zip -r %s *', $dest);
        `$command`; // runs without outputing
    }

    /**
     * Generate article in given folder
     *
     * @param object $article
     * @param string $dir
     * @return void
     */
    public function generateArticle($article, $dir)
    {
        $this->fetchHtml($article, 'url', $dir);
        $this->fetchHtml($article, 'backside_url', $dir);
        $this->fetchImage($article, 'image_url', $dir);
        $this->fetchImage($article, 'story_image_url', $dir);
        $this->fetchSlideshow($article, $dir);
        $this->fetchComments($article, $dir);
    }

    /**
     * Fetch image into dir and update toc reference
     *
     * @param object $article
     * @param string $imageProperty
     * @param string $dir
     * @param string $destName
     * @return void
     */
    private function fetchImage($article, $imageProperty, $dir, $destName = null)
    {
        if ($destName === null) {
            $destName = $imageProperty;
        }

        if ($article->$imageProperty) {
            $ext = pathinfo($article->$imageProperty, PATHINFO_EXTENSION);
            file_put_contents("{$dir}/{$destName}.{$ext}", $this->fetch($article->$imageProperty));
            $article->$imageProperty = basename($dir) . '/' . "{$destName}.{$ext}";
        }
    }

    /**
     * Fetch issue image
     *
     * @param object $toc
     * @param string $imageProperty
     * @param string $dir
     * @return void
     */
    public function fetchTocImage($toc, $imageProperty, $dir)
    {
        if ($toc->$imageProperty) {
            $this->fetchImage($toc, $imageProperty, $dir);
            $toc->$imageProperty = basename($toc->$imageProperty);
        }
    }

    /**
     * Fetch article content into given dir
     *
     * @param object $article
     * @param string $urlProperty
     * @param string $dir
     * @return void
     */
    private function fetchHtml($article, $urlProperty, $dir)
    {
        chdir($dir);
        system(sprintf("wget --quiet --header='%s: %s' -E -H -k -p -N --exclude-domains 'youtube.com,vimeo.com' '%s'", self::OFFLINE_HEADER, $this->config['secret'], $article->$urlProperty));
        $parsedUrl = parse_url($article->$urlProperty);
        $article->$urlProperty = basename($dir) . '/' . $parsedUrl['host'] . $parsedUrl['path'] . '?' . $parsedUrl['query'] . '.html';
    }

    /**
     * Fetch article comments
     *
     * @param object $article
     * @param string $dir
     * @return void
     */
    private function fetchComments($article, $dir)
    {
        $comments = $this->fetchJson($article->comment_url);
        foreach ($comments as $i => $comment) {
            if (!$comment->author_image_url) {
                continue;
            }

            try {
                $ext = pathinfo($comment->author_image_url, PATHINFO_EXTENSION);
                $dest = "{$dir}/_comment_{$i}_author.{$ext}";
                file_put_contents($dest, $this->fetch($comment->author_image_url));
                $comment->author_image_url = basename($dest);
            } catch (\Exception $e) {
                $comment->author_image_url = null;
            }
        }

        file_put_contents("$dir/comments.json", json_encode($comments));
        $article->comment_url = basename($dir) . '/comments.json';
    }

    /**
     * Fetch article slideshow
     *
     * @param object $article
     * @param string $dir
     * @return void
     */
    private function fetchSlideshow($article, $dir)
    {
        foreach ($article->slideshow_images as $i => $item) {
            if ($item->type === 'image') {
                $this->fetchImage($item, 'url', $dir, '_slideshow_image_' . $i);
            }
        }
    }

    /**
     * Fetch content from given url
     *
     * @param string $url
     * @return string
     */
    private function fetch($url)
    {
        $client = $this->getClient();
        $response = $client->get($url, array(
            self::OFFLINE_HEADER => $this->config['secret'],
        ))->send();
        return $response->isSuccessful() ? $response->getBody(true) : null;
    }

    /**
     * Fetch json from given url
     *
     * @param string $url
     * @return object
     */
    private function fetchJson($url)
    {
        $data = $this->fetch($url);
        return !empty($data) ? json_decode($data) : null;
    }

    /**
     * Get http client
     *
     * @return object
     */
    private function getClient()
    {
        return isset($this->config['auth_username'])
            ? $this->clientFactory->getAuthClient($this->config['auth_username'], $this->config['auth_password'])
            : $this->clientFactory->getClient();
    }

    /**
     * Generate dir if not exists
     *
     * @param string $path
     * @return string
     */
    private function createDir($path)
    {
        if (!is_dir($path) && is_file($path)) {
            throw new \InvalidArgumentException("'$path' is a file.");
        }

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        return $path;
    }

    /**
     * Get issue dir
     *
     * @param object $toc
     * @param string $client
     * @return string
     */
    private function getIssueDir($toc, $client)
    {
        return $this->createDir($this->getPath(sprintf(
            'issue_%d',
            $toc->issue_id
        ), $client));
    }

    /**
     * Get article dir
     *
     * @param object $article
     * @param object $issueDir
     * @param string $client
     * @return string
     */
    private function getArticleDir($article, $issueDir, $client)
    {
        return $this->createDir($this->getPath(sprintf(
            '%s/%d',
            basename($issueDir),
            $article->article_id
        ), $client));
    }

}
