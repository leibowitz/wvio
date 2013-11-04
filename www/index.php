<?php

include __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Leibowitz\Github\Utils\ProjectInfo;

// Get config from file
$config_file = __DIR__ . '/../config/settings.yml';
$config = Yaml::parse($config_file);

$app = new Silex\Application();

$app['debug'] = true;
$app['info'] = new ProjectInfo($config);

$app->match('{project}', function(Request $request) use ($app) {
    $resp = new Response();

    $project = $request->get('project');

    if( !$app['info']->hasProject($project) ) {
        $resp->setStatusCode(404);
        $payload = null;
    } else {
        $payload = $app['info']->getProjectInfo($project);
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

