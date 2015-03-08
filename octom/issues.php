<?php

namespace octom;

use PicoFeed\Syndication\Atom;
use PicoFeed\Parser\DateParser;

/**
 * Class to export GitHub issues
 *
 * @author Mathias Kresin
 * @copyright Copyright (c) 2015, Mathias Kresin
 */
class Issues extends Api
{
    /**
     * @access private
     * @var int number of issues to return
     */
    private $nb_issues = 25;

    /**
     * @access private
     * @var array URLs to fetch the data from
     */
    private $api_endpoints = array();

    /**
     * @access private
     * @var array event whitelist
     */
    private $event_whitelist = array('closed', 'reopened');

    /**
     * @access private
     * @var array fetched and merged github issues
     */
    public $issues = array();

    /**
     * Constructor
     *
     * @access public
     * @param  string  $repo    Github repository for which the data should be fetched
     * @param  string  $token   API access token
     * @param  string  $etag    eTag from last successfull request
     */
    public function __construct($repo, $token = '', $etag = '')
    {
        parent::__construct($repo, $token, $etag);

        // store the endpoints URLs, which to get information from
        $this->api_endpoints['issues_url'] = str_replace('{/number}', '', $this->repo->issues_url);
        $this->api_endpoints['issue_events_url'] = str_replace('{/number}', '', $this->repo->issue_events_url);
        $this->etag = $etag;

        // get the issues and events from github
        $this->getMerged();

        // convert events to issues
        $this->unifyIssues();
    }

    /**
     * Return the issues as atom feed
     *
     * @return string
     */
    public function getFeed()
    {
        $date_parser = new DateParser();
        $feed = new Atom();

        foreach($this->issues as $issue) {
            $feed->items[] = array(
                'id' => $issue->url,
                'title' =>  'Issue '.$issue->number.' ('.$issue->title.') '.$issue->state,
                'url' => $issue->html_url,
                'author' => array('name' => $issue->user->login),
                'updated' => $date_parser->getDateTime($issue->created_at)->getTimestamp(),
                'content' => $issue->body,
            );
        }

        $feed->title = $this->repo->name.' issues';
        $feed->site_url = $this->repo->html_url.'/issues/';
        $feed->updated = $feed->items[0]['updated'];
        $feed->author = array(
            'name' => 'octom',
            'url' => 'https://www.github.com/mkresin/octom/'
        );

        return $feed->execute();
    }

    /**
     * Remove the first item if it's a pull request or a not whitelisted event
     *
     * @param array $items
     * @return boolean true if item is removed
     */
    private function filterFirstItem(&$items) {
        $item = current($items);

        if ((isset($item->pull_request) || isset($item->issue->pull_request)) // drop pull requests
            || (isset($item->event) && ! in_array($item->event, $this->event_whitelist))) { // drop not whitelisted events
            array_shift($items);
            return true;
        }

        return false;
    }

    /**
     * Get the bug reports and store the URL for the next batch of reports
     *
     * @access private
     * @param $etag
     * @return array
     */
    private function getIssues($etag = '')
    {
        $endpoint_name = 'issues_url';

        if (! isset($this->api_endpoints[$endpoint_name])) {
            return array('etag' => '', 'content' => array());
        }

        // get the issues
        $content = $this->getContent($this->api_endpoints[$endpoint_name], $etag);

        // store the url for the next batch of events
        $pagination = $this->parseLinkHeader($content['headers']);
        $this->api_endpoints[$endpoint_name] = $pagination['next'];

        return array(
            'etag' => $content['headers']['etag'],
            'content' => json_decode($content['body']),
        );
    }

    /**
     * Get the issue events and store the URL for the next batch of events
     *
     * @return array
     */
    private function getIssueEvents()
    {
        $endpoint_name = 'issue_events_url';

        if (! isset($this->api_endpoints[$endpoint_name])) {
            return array();
        }

        // get the issue events
        $content = $this->getContent($this->api_endpoints[$endpoint_name]);

        // store the url for the next batch of events
        $pagination = $this->parseLinkHeader($content['headers']);
        $this->api_endpoints[$endpoint_name] = $pagination['next'];

        return json_decode($content['body']);
    }

    /**
     * Merge issues with repository event data, order by create time. Fetch more
     * events and issues if necessary.
     *
     * @access private
     */
    private function getMerged()
    {
        // get the first batch of issues and store the returned etag
        $issues = $this->getIssues($this->etag);
        $this->etag = $issues['etag'];

        // get the first batch of issues events
        $events = $this->getIssueEvents();

        // stop conditions are: the number of requested issues fetched OR
        // (no more issues to fetch AND no more events to fetch)
        while (count($this->issues) < $this->nb_issues && (! empty($issues['content']) || ! empty($events))) {

            if ($this->filterFirstItem($issues['content']) || $this->filterFirstItem($events)) {
                // do nothing if the input values where modified
            }
            elseif (empty($issues['content'])) { // use events if no more issues are present
                $this->issues[] = array_shift($events);
            }
            elseif (empty($events)) { // use issues if no more events are present
                $this->issues[] = array_shift($issues['content']);
            }
            elseif (current($events)->created_at > current($issues['content'])->created_at) { // use event data if newer
                $this->issues[] = array_shift($events);
            }
            else { // use issue data
                $this->issues[] = array_shift($issues['content']);
            }

            // if the last issue is filtered or moved, try to get more issues
            if (empty($issues['content'])) {
                $issues = $this->getIssues();
            }

            // if the last event is filtered or moved, try to get more events
            if (empty($events)) {
                $events = $this->getIssueEvents();
            }
        }
    }

    /**
     * Convert events to issues, use past tense for state
     *
     * @access private
     */
    private function unifyIssues()
    {
        foreach($this->issues as &$issue) {
            if (isset($issue->event)) {
                $issue->issue->id = $issue->id;
                $issue->issue->url = $issue->url;
                $issue->issue->user = $issue->actor;
                $issue->issue->state = $issue->event;
                $issue->issue->created_at = $issue->created_at;
                $issue->issue->commit_id = $issue->commit_id;

                $issue = $issue->issue;
                unset($issue->issue);

                if (isset($issue->commit_id)) {
                    // assume the commit hash is from the current repository,
                    // which isn't necessary true
                    $issue->body = sprintf('%1$s with commit <a href="%2$s/commit/%3$s">%3$s</a>', $issue->state, $this->repo->html_url, $issue->commit_id);
                }
                else {
                    $issue->body = ''; // it's always the content of the original issue
                }

                // add anchor to html url
                $issue->html_url .= '#event-'.$issue->id;
            }
            elseif ($issue->state === 'open') {
                $issue->state .= 'ed';
            }
        }
    }
}