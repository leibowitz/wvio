<?php

include __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Guzzle\Http;
use Leibowitz\Github\Utils\ProjectInfo;
use Leibowitz\Utils\Json\JsonPath;

$app = new Silex\Application();

$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
));


function getProjectStatusInfo($url)
{
    // find commit hash of deployed version
    $httpclient = new Http\Client($url);

    $request = $httpclient->get();
    $resp = $request->send();

    return json_decode($resp->getBody(), true);
}

function getResult($resp)
{
    return $resp && is_array($resp) ? array_shift($resp) : null;
}

function getProjectDetails($info, $project)
{
    $project_data = $info->getProjectConfig($project);

    $status = getProjectStatusInfo($project_data['url']);

    $statusInfo = new JsonPath( $status );

    $commit = getResult($statusInfo->getPath( $project_data['commit'] ));

    $details = $info->getCommitDetails($commit, $project);

    if( array_key_exists('version', $project_data) ) {
        $details['version'] = getResult(
            $statusInfo->getPath( $project_data['version'] )
        );
    }

    $details['branches'] = $info->getBranchesForCommit($commit, $project);

    return $details;
}

$app->get('status/{environment}/{project}', function(Request $request) use ($app) {
    $environment = $request->get('environment');
    $project = $request->get('project');

    // Get config from file
    $config_file = __DIR__ . '/../config/'.$environment.'/settings.yml';
    $config = Yaml::parse($config_file);

    /*if( $project ) {
        $project_data = $config['projects'][ $project ];

        $url = 'http://wvio.dev/'.$environment.'/'.$project_data['name'];

        $httpclient = new Http\Client($url);

        $request = $httpclient->get();
        $info = $request->send();
        $content = json_decode((string)$info->getBody(), true);

        //$results[ $project_data['name'] ] = $content['payload'];

        $info = $content['payload'];
    } else {
        $info = null;
    }*/

    return $app['twig']->render('status.twig',
        array(
            //'info' => $info,
            'environment' => $environment,
            'projects' => $config['projects']
        )
    );
})
    ->assert('project', '.*')
    ->assert('environment', 'boxen|staging|production');

$app->match('{environment}/{project}', function(Request $request) use ($app) {
    //$environment = trim(file_get_contents( __DIR__ . '/../config/environment.txt' ));
    $environment = $request->get('environment');

    // Get config from file
    $config_file = __DIR__ . '/../config/'.$environment.'/settings.yml';
    $config = Yaml::parse($config_file);

    $info = new ProjectInfo($config);

    $resp = new Response();

    $project = $request->get('project');

    if( !$info->hasProject($project) ) {
        $resp->setStatusCode(404);
        $payload = null;
    } else {
        $payload = getProjectDetails($info, $project);
    }

    $content = json_encode(
        array(
            'status' => $resp->getStatusCode() != 404,
            'payload' => $payload
        ));

    $resp->setContent($content);

    return $resp;
})
    ->assert('project', '.*')
    ->assert('environment', 'boxen|staging|production');

$app->run();

