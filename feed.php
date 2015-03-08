<?php
require 'vendor/autoload.php';

use octom\Issues;

// set you github token here to raise the request limit
$github_token = '';

// change it to enable this module and prevent abusing by other people
$octom_token = '';

function getHTTPInputValues($octom_token)
{
    $values = array('etag' => '');

    // check for required input values
    if ($octom_token === '') {
        header("HTTP/1.1 400 Module is disabled");
        exit;
    }
    elseif (! isset($_GET['token']) || $_GET['token'] !== $octom_token) {
        header("HTTP/1.1 401 Token missmatch");
        exit;
    }
    elseif (empty($_GET['content'])) {
        header("HTTP/1.1 400 Target repository/content missing");
        exit;
    }
    else {
        list($values['owner'], $values['repo'], $values['contenttype']) = explode('/', $_GET['content']);

        if (empty($values['owner']) || empty($values['repo']) || empty($values['contenttype'])) {
            header("HTTP/1.1 400 Content parameter missing or incomplete");
            exit;
        }

        if (! empty($_SERVER['HTTP_IF_NONE_MATCH'])) {
            $values['etag'] = $_SERVER['HTTP_IF_NONE_MATCH'];
        }
    }

    return $values;
}

$values = getHTTPInputValues($octom_token);
$issues = new Issues($values['owner'].'/'.$values['repo'], $github_token, $values['etag']);

// always return github ratelimit headers
foreach ($issues->rate_limit as $header => $value) {
        header($header.': '.$value);
}

// send github etag header
header('Etag: '.$issues->getEtag());

echo $issues->getFeed();
?>
