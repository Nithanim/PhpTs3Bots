<?php
require_once "System/Daemon.php";
$runmode = array(
    'daemon' => false,
);
foreach ($argv as $k=>$arg) {
    if (substr($arg, 0, 2) == '--' && isset($runmode[substr($arg, 2)])) {
        $runmode[substr($arg, 2)] = true;
    }
}
foreach ($ts3config['daemon'] as $key => $value) {
    System_Daemon::setOption($key, $value);
}
System_Daemon::setOption("logLocation", './logs/'.basename($_SERVER['SCRIPT_FILENAME'], ".php").'.log');


if(isset($runmode['daemon']) && $runmode['daemon']) {
    System_Daemon::start();
    System_Daemon::log(System_Daemon::LOG_INFO, "Daemon: '".System_Daemon::getOption("appName")."' spawned! Stdout will be written to ".System_Daemon::getOption("logLocation"));
}

function __daemon_exit() {
    System_Daemon::log(System_Daemon::LOG_INFO, "Daemon shutdown!");
    System_Daemon::stop();
}
register_shutdown_function('__daemon_exit');