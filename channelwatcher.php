<?php
error_reporting(E_STRICT);
error_reporting(E_ALL);
ini_set('display_errors','1');


require_once('config.inc.php');
require_once './includes/history.inc.php';

require_once "System/Daemon.php";
System_Daemon::setSigHandler(SIGINT, "sighandler");
System_Daemon::setSigHandler(SIGTERM, "sighandler");
function sighandler($sig) {
    System_Daemon::log(System_Daemon::LOG_WARNING, "Received {$sig}, shutting down...");
    global $ts3server;
    unset($ts3server);
    System_Daemon::stop();
}
require_once('./daemoninit.inc.php');

require_once('TeamSpeak3/TeamSpeak3.php');

require_once('./commonfunc.inc.php');



function subscribeChannel($ts3server) {
    attachTimeoutHander();
    
    $ts3server->notifyRegister("channel"); 
    TeamSpeak3_Helper_Signal::getInstance()->subscribe("notifyClientmoved", "onChannel");
}



function wasChannelEventAlreadyTriggerd($cid) {
    static $alreadyTriggered = array();
    
    if(array_key_exists($cid, $alreadyTriggered)) {
        unset($alreadyTriggered[$cid]);
        return false;
    } else {
        $alreadyTriggered[$cid] = true;
        return true;
    }
}

function getFirstElementFromArray($array) {
    reset($array);
    $firstElement = $array[key($array)];
    reset($array);
    return $firstElement;
}

function getChannelPrefixAndNumber($channel) {
    //explode (limit 2) from right to left
    $arr = array_reverse(array_map('strrev', explode(' ', strrev($channel['channel_name']), 2)));
    $arr[0] = $arr[0].' ';
    return $arr;
}

function getChannelPrefix($channel) {
    return getChannelPrefixAndNumber($channel)[0];
}

function getChannelNumber($channel) {
    return intval(getChannelPrefixAndNumber($channel)[1]);
}

function findFirstFreeChannelNumber($channelList) {
    $number = 1;
    
    foreach ($channelList as $channel) {
        if(getChannelNumber($channel) != $number) {
            return $number;
        }
        $number++;
    }
    return $number;
}

function getNextChannelName($channelList) {
    if(count($channelList) != 0) {
        $channel = getFirstElementFromArray($channelList);
        $prefix = getChannelPrefix($channel);
    } else {
        $prefix = "Group ";
    }
    
    $number = findFirstFreeChannelNumber($channelList);
    return $prefix.$number;
}

/**
 * Returns a map where the key is the 'virtual'/visible (with numbers) order of the channels and the value is the channel istself.
 * 
 * @param type $channelList
 * @return Array where the key is the 'virtual'/visible order of channels.
 */
function getChannelOrderIdMap($channelList) {
    $newList = array();
    foreach ($channelList as $channel) {
        $newList[getChannelNumber($channel)] = $channel;
    }
    ksort($newList);
    return $newList;
}

/**
 * Sorts the channles in TS3 exactly as the array is sorted.
 * The keys need to be integers starting with 1 and sorted ascending.
 * 
 * @param Array Array of channels
 */
function sortChannelList($channelList) {
    $channelList[0] = array(
        'cid' => 0,
        'channel_name' => "dummy"
    );
    ksort($channelList);
    
    $previousChannelKey = 0;
    foreach ($channelList as $key => $currentChannel) {
        if($key == 0) continue;
        
        $previousChannel = $channelList[$previousChannelKey];
        try {
            $previousChannel['cid'];
        } catch (Exception $ex) {
            echo "Failed getting cid at {$previousChannel['channel_name']}\n";
            var_dump($currentChannel);
            echo '<hr/>';
            var_dump($previousChannel);
            break;
        }
        
        if($previousChannel['cid'] != $currentChannel['channel_order']) {
            $currentChannel->modify(array(
                'channel_order' => $previousChannel['cid'] //edited Node/Channel.php:421 (1.1.23) to include cid after reset because it would disappear normally
            ));
            $currentChannel->fetchNodeInfo();
        }
        $previousChannelKey = $key;
    }
}

function shouldBeKicked($uid) {
    global $history;
    return count($history->getLatestOf($uid, 40)) >= 3;
}

function onChannel(TeamSpeak3_Adapter_ServerQuery_Event $event, TeamSpeak3_Node_Host $host) {
    global $ts3server, $history;
    $cid = $event['ctid'];
    
    if(!isWatchedChannel($cid)) return;
    if(wasChannelEventAlreadyTriggerd($cid)) return;
    $channelConfig = getConfigForChannel($cid);
    
    $ts3server->channelListReset();
    $channel = $ts3server->channelGetById($cid);

    $channelList = getChannelOrderIdMap($channel->subChannelList());
    sortChannelList($channelList);
    
    if(count($channelList) >= $channelConfig['max_subchannels']) return;
    
    $client = $ts3server->clientGetById($event['clid']);
    if(shouldBeKicked($client['client_unique_identifier'])) {
        try {
            $client->kick(TeamSpeak3::KICK_SERVER, "Don't do that!");
            System_Daemon::log(System_Daemon::LOG_INFO, "Kicked {$client['client_nickname']} with ".count($history->getLatestOf($client['client_unique_identifier'], 40))." entries in history.");
        } catch (Exception $ex) {
            System_Daemon::log(System_Daemon::LOG_ALERT, "Unable to kick {$client['client_nickname']} (".count($history->getLatestOf($client['client_unique_identifier']), 40)."):".$ex->getMessage());
        }
        return;
    }
    
    $subchannel_name = getNextChannelName($channelList);

    try {
        //create channel
        $subcid = $ts3server->channelCreate(array(
            'channel_name' => $subchannel_name,
            #'channel_codec' => '',
            'channel_flag_semi_permanent' => true,
            'cpid' => $cid,
        ));

        //move client to new channel
        $ts3server->clientListReset();
        $client->move($subcid);


        $password = false;
        if($channelConfig['use_password']) {
            $password = generatePassword();
        }
        
        $ts3server->channelListReset();
        $subchannel = $ts3server->channelGetById($subcid);
        $subchannel->modify(array_merge($channelConfig['tsflags'], array(
            'channel_password' => $password===false?"":$password
        )));

        if($password !== false) {
            $client->message("Channelpasswort: ".$password);
            
            try {
                $client->setChannelGroup($subcid, 26);
            } catch(TeamSpeak3_Adapter_ServerQuery_Exception $ex) {
                System_Daemon::log(System_Daemon::LOG_WARNING, "Unable to set channelgroup! ".$ex);
            }
        }
        
        //sort after inserting
        $ts3server->channelListReset();
        $channelList = getChannelOrderIdMap($channel->subChannelList());
        sortChannelList($channelList);
        
        $history->add($client['client_unique_identifier']);
        
        System_Daemon::log(System_Daemon::LOG_INFO, "{$client['client_nickname']} ({$client['client_unique_identifier']}) created a channel in {$channel['channel_name']}");
    } catch(TeamSpeak3_Adapter_ServerQuery_Exception $ex) {
        System_Daemon::log(System_Daemon::LOG_ERR, "Unable to create new sub of \"{$channel['channel_name']}\"! {$ex->getMessage()}");
    }
}

function isWatchedChannel($cid) {
    global $enabledChannels;
    return array_key_exists($cid, $enabledChannels);
}

function generatePassword() {
    return substr(md5(rand()), 0, 7);
}

function getConfigForChannel($cid) {
    global $default_channel_config;
    global $enabledChannels;
    
    $channelconfig = $enabledChannels[$cid];
    if(!isset($channelconfig['tsflags'])) {
        $channelconfig['tsflags'] = array();
    }
    
    $merged = array_merge($default_channel_config, $channelconfig);
    $merged['tsflags'] = array_merge($default_channel_config['tsflags'], $channelconfig['tsflags']);
    return $merged;
}

$history = new History();

$default_channel_config = array(
    'use_password' => false,
    'max_subchannels' => 5,
    'tsflags' => array(
        'CHANNEL_FLAG_SEMI_PERMANENT' => false,
        'CHANNEL_FLAG_TEMPORARY' => true,
        'CHANNEL_DELETE_DELAY' => 3 * 60,
        'CHANNEL_CODEC_IS_UNENCRYPTED' => false,
    )
);

$ts3server = connect();
subscribeChannel($ts3server);


while (true) {
    if(System_Daemon::isDying()) break;
    
    try {
        $ts3server->getAdapter()->wait();
    } catch(TeamSpeak3_Transport_Exception $ex) {
        $ts3server = connect();
        subscribeChannel($ts3server);
    }
    
    System_Daemon::iterate(1);
}
System_Daemon::stop();