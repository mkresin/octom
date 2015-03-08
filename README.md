# Octom
Octom allows to export content from the GitHub API to different output formats. Right now, only the export of issues to an Atom feed is implemented.

Octom can be used standalone or as library. This document covers the standalone usage. For the use as library please have a look at the file ```feed.php```, which is the reference implementation.

Octom uses [picoFeed](https://github.com/fguillot/picoFeed) for HTTP communication and feed creation.

Any help to improve documentation or code, even it's only a typo, is highly welcome.

#Requirements
 * PHP >= 5.3
 * PHP XML extensions (SimpleXML and DOM)
 * cURL extension for PHP or Stream Context with allow_url_fopen=On

#Documentation
Your feed reader should use and evaluate the [eTag](http://en.wikipedia.org/wiki/HTTP_ETag) to limit the GitHub API usage to a minimum. Please refer to the [GitHub API Documentation](https://developer.github.com/v3/#conditional-requests) for further details.

##Getting issues as Atom feed
Octom returns up to 25 issue events, chronological ordered, of the following kind:

 * open
 * reopen
 * close

The endpoint for the Atom feed of GitHub issues is https://example.com/octom/feed.php.

###Setup###
You have to change the variable ```$octom_token``` within the file ```feed.php``` to a random string of a reasonable length. The content of this variable has to be send with every request to the endpoint. Keep this token secret, since it's your only protection of abusing the endpoint by others.

You are strongly advised to setup the variable ```$github_token``` with your personal GitHub token as well. This token is used to authenticate you against the GitHub API. Unauthenticated requests to the GitHub API are limited to 60 requests per hour. Albeit up to 5000 authenticated requests per hour are allowed. The setup of the GitHub token is mandatory if you want to get issues from private repositories you have access to.

Follow the steps outlined in the article ["Creating an access token for command-line use"](https://help.github.com/articles/creating-an-access-token-for-command-line-use/) to get your own GitHub API token.


###Usage###
The target repository is specified using the ```content``` URL parameter. The contents of the URL parameters are illustrated by the following abstract example:

```
https://example.com/octom/feed.php?token=<random_string>&content=<user>/<repository>/<contenttype>
```

The content type is limited to issues for now.

To get the issues for the octom repository, use the following parameters:

```
https://example.com/octom/feed.php?token=random_string&content=mkresin/octom/issues
```

###Debugging###
Octom sends custom messages along the HTTP status code, if the Octom token doesn't match or the content URL parameter doesn't match the expected format.

The following HTTP headers received from GitHub

 * X-RateLimit-Limit
 * X-RateLimit-Remaining
 * X-RateLimit-Reset

as well as the number of send API requests

 * X-Number-Queries

are send as response HTTP headers with every feed.


##Limitations/Known issues##
 - due to limitations of the GitHub API there is no cheap way to get the comments of reopen or close events (or at least I couldn't find one).
 - almost no error handling is implemented
 - the only input are issues
 - the only output is an atom feed
 - the link to the closing commit may be wrong (the GitHub API doesn't indicate to which repository the commit belongs, but allows closing an issue with a commit to foreign repositories)
 - the feed item text is always in text format, even with links to images
