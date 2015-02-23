<?php

function connect() {
    global $ts3config;
    try {
        $ts3server = TeamSpeak3::factory("serverquery://{$ts3config['user']}:{$ts3config['pass']}@{$ts3config['addr']}/?server_port={$ts3config['port']}&blocking=0");
    } catch (TeamSpeak3_Adapter_ServerQuery_Exception $ex) {
        echo $ex->getMessage();
        exit;
    }
	
    try {
        $ts3server->selfUpdate(array("client_nickname" => $ts3config['client_nickname']));
    } catch (TeamSpeak3_Adapter_ServerQuery_Exception $ex) {
        System_Daemon::log(System_Daemon::LOG_WARNING, 'Unable to change nickname: '.$ex->getMessage());
    }
    return $ts3server;
}

function onTimeout($seconds, TeamSpeak3_Adapter_ServerQuery $adapter) {
    //http://forum.teamspeak.com/showthread.php/54132-API-TS3-PHP-Framework/page39
    if($adapter->getQueryLastTimestamp() < time() - 300) {
        $adapter->request("clientupdate");
    }
}

function attachTimeoutHander() {
    TeamSpeak3_Helper_Signal::getInstance()->subscribe("serverqueryWaitTimeout", "onTimeout");
}