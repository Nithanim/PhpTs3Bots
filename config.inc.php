<?php
function getDefaultTS3Config() {
    return array(
        'addr' => '127.0.0.1:10011',
        'virtualserver_port' => 9987,
        'user' => 'bot',
        'pass' => 'password',
        'port' => 9987,
        'client_nickname' => 'TS3-Bot ChanWatch',
        'daemon' => array(
            "authorName" => "name",
            "authorEmail" => "email",
            "appRunAsUID" => getmyuid(),
            "appRunAsGID" => getmygid(),
            "appDir" => getcwd(),
        ),
    );
}

function mergeConfigWithDefaultConfig($config) {
    $default = getDefaultTS3Config();
    $conf = array_merge($default, $config);
    $conf['daemon'] = array_merge($default['daemon'], $config['daemon']);
    
    return $conf;
}

require_once("./".basename($_SERVER['SCRIPT_FILENAME'], ".php").'.config.inc.php');