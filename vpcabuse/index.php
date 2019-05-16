<?php
declare(strict_types=1);

namespace vpcabuse;

define('APP_ROOT', dirname(__FILE__));
define('APP_LOG_FILE', APP_ROOT.DIRECTORY_SEPARATOR.'log.txt');

include 'Interface/vpcabuse/Controller.php';
include 'Interface/vpcabuse/Whitelist.php';
include 'Interface/Incident.php';
include 'Interface/vpcabuse/Slack.php';
#include 'Interface/vpcabuse/Opsgenie.php';
include 'Interface/Logger.php';

include 'Class/Router/Request.php';
include 'Class/Router/RouterConfig.php';
include 'Class/Router/Router.php';
include 'Class/Database.php';
include 'Class/Shell.php';
include 'Class/vpcabuse/DatabaseController.php';
include 'Class/vpcabuse/IncidentWriter.php';
include 'Class/vpcabuse/Controller.php';
include 'Class/vpcabuse/Padlock.php';
include 'Class/vpcabuse/Whitelist.php';
include 'Class/vpcabuse/Whitelistlog.php';
include 'Class/vpcabuse/WhitelistMetadata.php';
include 'Class/vpcabuse/Slack.php';
#include 'Class/vpcabuse/Opsgenie.php';
include 'Class/vpcabuse/Logger.php';

include 'Controllers/BaseController.php';
include 'Controllers/BadRequestController.php';
include 'Controllers/AlertManagerController.php';
include 'Controllers/WhitelistController.php';
include 'Controllers/CronController.php';

use vpcabuse\Controller;
use vpcabuse\Whitelist;
use vpcabuse\ControllerException;
use vpcabuse\IncidentWriter;
use vpcabuse\DatabaseController;
use vpcabuse\Logger\Logger;

$routerConfig = new \RouterConfig();

$routerConfig->Add('/alertmanager', 'POST', 'AlertManagerController');
$routerConfig->Add('/index.php', 'POST', 'AlertManagerController');

$routerConfig->Add('/api/whitelist', 'GET', 'WhitelistController');
$routerConfig->Add('/api/whitelist/:int', 'GET', 'WhitelistController');
$routerConfig->Add('/api/whitelist/:int', 'PUT', 'WhitelistController');
$routerConfig->Add('/api/whitelist/:int', 'DELETE', 'WhitelistController');

$routerConfig->Add('/api/whitelist/expire', 'GET', 'CronController');
$routerConfig->Add('/api/whitelist/expire', 'DELETE', 'CronController');
$routerConfig->Add('/api/cron/expire', 'GET', 'CronController');
$routerConfig->Add('/api/cron/expire', 'DELETE', 'CronController');

$routerConfig->AddDefault('BadRequestController');

$request = new \Request($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);

try {
    \Router::SetConfig($routerConfig);
    $controller = \Router::Route($request);
    $controller->Run();
} catch (\Throwable $e) {
    // unknown route
    if (defined('DEBUG'))
        echo $e->GetMessage();
}

