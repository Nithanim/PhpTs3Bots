<?php
$ts3config = mergeConfigWithDefaultConfig(array(
    'user' => 'ts3bot', //serverquery-username
    'pass' => 'yourpassword',
    'daemon' => array(
        "appName" => "ts3channelwatcherdaemon",
        "appDescription" => "ChannelWatcher-Daemon for TS3",
    )
));


//the keys are the ChannelIds
$enabledChannels = array(
     1 => array(),
    25 => array(),
    50 => array(),
    10 => array(
        'use_password' => true, //a password will be set and set to the channel creator
        'tsflags' => array( //with "tsflags" you can pass additional channel flags
            'CHANNEL_DELETE_DELAY' => 1 * 60
        )
    ),
   20 => array(
       'tsflags' => array(
           'CHANNEL_DELETE_DELAY' => 5 * 60,
       )
    ),
);