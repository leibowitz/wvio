<?php

include __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Guzzle\Http;
use Leibowitz\Github\Utils\ProjectInfo;

$environment = trim(file_get_contents( __DIR__ . '/../config/environment.txt' ));

// Get config from file
$config_file = __DIR__ . '/../config/'.$environment.'/settings.yml';
$config = Yaml::parse($config_file);

$app = new Silex\Application();

$app['debug'] = true;
$app['info'] = new ProjectInfo($config);


function getProjectStatusInfo($url)
{
    // find commit hash of deployed version
    $httpclient = new Http\Client($url);

    $request = $httpclient->get();
    $resp = $request->send();

    return json_decode($resp->getBody(), true);
}

function getProjectDetails($app, $project)
{
    $project_data = $app['info']->getProjectConfig($project);

    $status = getProjectStatusInfo($project_data['url']);

    $commit = $status[ $project_data['field'] ];

    $details = $app['info']->getCommitDetails($commit, $project);

    $details['branches'] = $app['info']->getBranchesForCommit($commit, $project);

    return $details;
}

$app->match('{project}', function(Request $request) use ($app) {
    $resp = new Response();

    $project = $request->get('project');

    if( !$app['info']->hasProject($project) ) {
        $resp->setStatusCode(404);
        $payload = null;
    } else {
        $payload = getProjectDetails($app, $project);
    }

    $content = json_encode(
        array(
            'status' => $resp->getStatusCode() != 404,
            'payload' => $payload
        ));

    $resp->setContent($content);

    return $resp;
})->assert('project', '.*');

$app->run();

