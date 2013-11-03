<?php

include __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Guzzle\Http;

// Get config from file
$config_file = __DIR__ . '/../config/settings.yml';
$config = Yaml::parse($config_file);

$project = $config['projects'][ $config['project'] ];

// find commit hash of deployed version
$httpclient = new Http\Client($project['url']);
$request = $httpclient->get();
$resp = $request->send();
$content = json_decode($resp->getBody(), true);
$commit_version = $content[ $project['field'] ];

// Authentification
$githubclient = new Github\Client();
$githubclient->authenticate($config['token'], '', Github\Client::AUTH_HTTP_TOKEN);

// start to query the github api about the commit
echo sprintf("Looking for informations about project %s commit %s\n", $project['name'], $commit_version);
// get info about the commit
$commit_details = $githubclient->api('repo')->commits()->show($config['user'], $project['name'], $commit_version);

// display commit info
echo sprintf("Author: %s\n", $commit_details['commit']['author']['name']);
echo sprintf("Date: %s\n", $commit_details['commit']['author']['date']);
echo sprintf("Message: %s\n", $commit_details['commit']['message']);
echo sprintf("Url: %s\n", $commit_details['html_url']);

// Get all branches
echo "Retrieving branches information\n";
$branches = $githubclient->api('repo')->branches($config['user'], $project['name']);

echo sprintf("Found %d branches\n", count($branches));

// to store the branches names where the commit was found
$founds = array();

// Looking into each branches at HEAD
foreach($branches as $branch) {
    // Check if HEAD is the commit we are looking for
    if( $branch['commit']['sha'] == $commit_version ) {
        $founds[ $branch['name'] ] = 0;
        continue;
    }

}

// Looking for closed Pull Requests
echo "Retrieving closed pull requests\n";
$pull_requests = $githubclient
    ->api('pull_request')
    ->all(
        $config['user'],
        $project['name'],
        array(
            'state' => 'closed',
            'base' => 'master'));

// Looking at HEAD of all closed Pull Requests
foreach($pull_requests as $preq)
{
    $branch = $preq['head']['ref'];
    $sha = $preq['head']['sha'];
    echo sprintf("Analysing branch %s\n", $branch);

    if( $commit_version == $sha ) {
        echo sprintf("Found commit at HEAD of branch %s\n", $branch);
        $founds[ $branch ] = 0;
        break;
    }

}

if( count($founds) == 0 ) {
    // Look into each branches latest 30 commits
    foreach($branches as $branch) {
        echo "Retrieving commits for branch: ".$branch['name']."\n";
        // Get latest 30 commits on this branch
        $commits = $githubclient
            ->api('repo')
            ->commits()
            ->all(
                $config['user'],
                $project['name'],
                array('sha' => $branch['commit']['sha']) );

        echo sprintf("Found %d commits\n", count($commits));

        // Scan each commit and compare the hash
        foreach($commits as $index => $commit) {
            if( $commit_version == $commit['sha'] ) {
                // We found the commit in this branch
                echo sprintf("Found commit in branch %s\n", $branch['name']);
                $founds[ $branch['name'] ] = $index;
                break 2;
            }
        }
    }
}

if( count($founds) == 0 ) {
    // Looking at last 30 commits of all closed Pull Requests
    foreach($pull_requests as $preq)
    {
        //echo sprintf("Retrieving branch %s informations\n", $preq['head']['ref']);
        //$branch_info = $githubclient->api('repo')->branches($config['user'], $project['name'], $preq['head']['ref']);

        echo "Retrieving branch commits\n";
        $commits = $githubclient->api('repo')->commits()->all($config['user'], $project['name'], array('sha' => $preq['head']['sha']));

        echo sprintf("Found %d commits\n", count($commits));

        // Scan each commit and compare the hash
        foreach($commits as $index => $commit) {
            if( $commit_version == $commit['sha'] ) {
                // We found the commit in this branch
                echo sprintf("Found commit in branch %s\n", $preq['head']['ref']);
                $founds[ $preq['head']['ref'] ] = $index;
                break 2;
            }
        }
        //$preq['merge_commit_sha'];
    }
}

echo sprintf("Commit found in branches: %s\n", implode(', ', array_keys($founds)));


