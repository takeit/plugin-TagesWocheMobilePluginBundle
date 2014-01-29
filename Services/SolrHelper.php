<?php

namespace Newscoop\TagesWocheMobilePluginBundle\Services;

use Datetime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Configuration service for article type
 */
class SolrHelper
{
    const LIMIT = 12;

    /**
     * @var array
     */
    protected $dates = array(
        '24h' => '[NOW-1DAY/HOUR TO *]',
        '1d' => '[NOW/DAY TO *]',
        '2d' => '[NOW-1DAY/DAY TO NOW/DAY]',
        '7d' => '[NOW-7DAY/DAY TO *]',
        '1y' => '[NOW-1YEAR/DAY TO *]',
    );

    /**
     * @var array
     */
    protected $types = array(
        'article' => array('news', 'newswire'),
        'dossier' => 'dossier',
        'blog' => 'blog',
        'comment' => 'comment',
        'link' => 'link',
        'event' => 'event',
        'user' => 'user',
    );

    /**
     * Build solr params array
     *
     * @return array
     */
    protected function buildSolrParams()
    {
        $request = $this->getRequest();
        return array(
            'wt' => 'json',
            'rows' => self::LIMIT,
            'start' => max(0, (int) $request->request->get('start')),
        );
    }

    /**
     * Build solr date param
     *
     * @return string
     */
    protected function buildSolrDateParam()
    {
        $request = $this->getRequest();
        $date = $request->request->get('date', false);
        if (!$date) {
            return;
        }

        if (array_key_exists($date, $this->dates)) {
            return sprintf('published:%s', $this->dates[$date]);
        }

        try {
            list($from, $to) = explode(',', $date, 2);
            $fromDate = empty($from) ? null : new \DateTime($from);
            $toDate = empty($to) ? null : new \DateTime($to);
        } catch (\Exception $e) {
            return;
        }

        return sprintf('published:[%s TO %s]',
            $fromDate === null ? '*' : $fromDate->format('Y-m-d\TH:i:s\Z') . '/DAY',
            $toDate === null ? '*' : $toDate->format('Y-m-d\TH:i:s\Z') . '/DAY');
    }

    /**
     * Decode solr response
     *
     * @return array
     */
    protected function decodeSolrResponse(Response $response)
    {
        $request = $this->getRequest();

        $decoded = json_decode($response->getBody(), true);
        $decoded['responseHeader']['params']['q'] = $request->request->get('q'); // this might be modified, keep users query
        $decoded['responseHeader']['params']['date'] = $request->request->get('date');
        $decoded['responseHeader']['params']['type'] = $request->request->get('type');
        $decoded['responseHeader']['params']['source'] = $request->request->get('source');
        $decoded['responseHeader']['params']['section'] = $request->request->get('section');
        $decoded['responseHeader']['params']['sort'] = $request->request->get('sort');
        $decoded['responseHeader']['params']['topic'] = array_filter(explode(',', $request->request->get('topic', '')));

        $decoded['responseHeader']['params']['q_topic'] = null;
        if ($request->request->get('q') !== '') {
            $topic = new Topic($request->request->get('q') . ':de');
            if ($topic->exists()) {
                $decoded['responseHeader']['params']['q_topic'] = $topic->getName(5);
            }
        }

        return $decoded;
    }
}
