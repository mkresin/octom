<?php

namespace octom;

use PicoFeed\Client\Client;

/**
 * Class to interact with the GitHub API
 *
 * @author Mathias Kresin
 * @copyright Copyright (c) 2015, Mathias Kresin
 */
class Api
{
    /**
     * API access token
     *
     * @access protected
     * @var string
     */
    protected $token = '';

    /**
     * HTTP request/response Etag header
     *
     * @access protected
     * @var string
     */
    protected $etag = '';

    /**
     * repository details
     *
     * @access protected
     * @var array
     */
    protected $repo = array();

    /**
     * github ratelimit headers
     *
     * @access public
     * @var array
     */
    public $rate_limit = array('X-Number-Queries' => 0);

    public function __construct($repo, $token = '', $etag = '')
    {
        $this->token = $token;
        $this->repo = $this->getRepoInfos($repo);
    }

    /**
     * Get the Etag HTTP header value
     *
     * @access public
     * @return string
     */
    public function getEtag()
    {
        return $this->etag;
    }

    /**
     * Query the github API
     *
     * @access private
     * @param string $url   full github api url to fetch from
     * @param string $etag  etag to send with the request
     * @return string
     */
    protected function getContent($url, $etag = '')
    {
        $client = Client::getInstance();
        $client->setUserAgent('octom (https://github.com/mkresin/octom)');
        $client->setEtag($etag);
        $client->setUsername('token');
        $client->setPassword($this->token);
        $client->setUrl($url);
        // TODO: add HTML Header: "Accept: application/vnd.github.v3.html+json"

        // returns headers if needed
        $response = $client->doRequest();

        // debug informations
        $this->rate_limit['X-Number-Queries'] += 1;
        $this->rate_limit['X-RateLimit-Limit'] = $response['headers']['X-RateLimit-Limit'];
        $this->rate_limit['X-RateLimit-Remaining'] = $response['headers']['X-RateLimit-Remaining']; //could be missing if nothing remains
        $this->rate_limit['X-RateLimit-Reset'] = $response['headers']['X-RateLimit-Reset'];

        if ($response['status'] === 304) {
            header("HTTP/1.1 304 Not Modified");
            exit;
        }
        elseif ($response['status'] !== 200) {
            // something unexpected happend; maybe the ratelimit exceeded
            // handle errors here
            header("HTTP/1.1 500 Upstream error");
            exit;
        }

        return $response;
    }

    /**
     * Get the endpoint Urls
     *
     * @param type $repo repository name in :owner/:repo format
     * @return array assoc array of valid repository urls
     */
    protected function getRepoInfos($repo)
    {
        $url = 'https://api.github.com/repos/'.$repo;
        $content = $this->getContent($url);

        return json_decode($content['body']);
    }

    protected function parseLinkHeader($headers)
    {
        $parts = array();
        $links = array(
            'first' => null,
            'previous' => null,
            'next' => null,
            'last' => null
        );

        if (isset($headers['link'])) {
            // Split parts by comma
            $parts = explode(',', $headers['link']);
        }

        // Parse each part into a named link
        foreach ($parts as $part) {
            $section = explode(';', $part);

            $url = preg_replace('/\s*<(.*)>\s*/', '$1', $section[0]);
            $name = preg_replace('/\s*rel="(.*)"\s*/', '$1', $section[1]);

            $links[$name] = $url;
        }

        return $links;
    }
}