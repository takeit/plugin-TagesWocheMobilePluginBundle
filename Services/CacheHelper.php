<?php

namespace Newscoop\TagesWocheMobilePluginBundle\Services;

use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 */
class CacehHelper
{
    const DATE_FORMAT = 'D, d M Y H:i:s \G\M\T';

    /**
     * Validates browser cache and responds with 304 if not modified
     *
     * @param DateTime $lastModified
     * @return void
     */
    public function validateBrowserCache(DateTime $lastModified, Request $request)
    {
        $ttl = new DateTime('+ 60 seconds');
        $response = new Response();
        // $response->setHeader('Cache control', 'public', true);
        $response->setPublic();
        $response->setLastModified($lastModified);
        $response->setExpires($ttl);

        //TODO: check if symfony2 code is valid compared to old code
        // $ifModifiedSince = $this->getRequest()->getHeader('If-Modified-Since')
        //     ? new DateTime($this->getRequest()->getHeader('If-Modified-Since'))
        //     : DateTime::createFromFormat('U', $lastModified->getTimestamp() - 300); // force loading

        //if ($ifModifiedSince->getTimestamp() >= $lastModified->getTimestamp()) {
        if ($response->isNotModified($request)) {
            $response->setNotModified();
            $response->setStatusCode(304);
            $response->send();
            exit;
        }
    }
}

