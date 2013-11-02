<?php

include __DIR__ . '/../vendor/autoload.php';

use Github\Client;
use Symfony\Component\Yaml\Yaml;


// Get config from file
$config_file = __DIR__ . '/../config/settings.yml';
$config = Yaml::parse($config_file);

// commit to look for
$commit_version = '';

// Authentification
$client = new Client();
$client->authenticate($config['token'], '', Client::AUTH_HTTP_TOKEN);

// start to query the github api about the commit
echo sprintf("Looking for informations about commit %s\n", $commit_version);
// get info about the commit
$commit_details = $client->api('repo')->commits()->show($config['user'], $config['project'], $commit_version);

// display commit info
echo sprintf("Author: %s\n", $commit_details['commit']['author']['name']);
echo sprintf("Date: %s\n", $commit_details['commit']['author']['date']);
echo sprintf("Message: %s\n", $commit_details['commit']['message']);

// Get all branches
echo "Retrieving branches information\n";
$branches = $client->api('repo')->branches($config['user'], $config['project']);

echo sprintf("Found %d branches\n", count($branches));

// to store the branches names where the commit was found
$founds = array();

// Looking into each branches
foreach($branches as $branch) {
    echo "Retrieving commits for branch: ".$branch['name']."\n";

    // Get latest 30 commits on this branch
    $commits = $client->api('repo')->commits()->all($config['user'], $config['project'], array('sha' => $branch['commit']['sha']) );

    echo sprintf("Found %d commits\n", count($commits));

    // Scan each commit and compare the hash
    foreach($commits as $commit) {
        if( $commit_version == $commit['sha'] ) {
            // We found the commit in this branch
            echo sprintf("Found commit in branch %s\n", $branch['name']);
            $founds[] = $branch['name'];
            break;
        }
    }
}

echo sprintf("Commit found in branches: %s\n", implode(', ', $founds));
