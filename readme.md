# PhpTs3Bots
## Description
Although PHP is not not the best for this task, it was chosen because it is lightweight and most likely pre-installed on the system.
Currently, the only bot is the channelwatcher which creates channels on demand. This solution makes sure that there are not too many empty and unused channels around but enough to keep up and therefore scale with the amount of users on the server.

As an example there may be ony one channel called "Talk" which is restricted to be able to hold only one user. As soon as a user joins it, a new sub-channel (e.g. "Group 1") is created, pre-defined ts3-flags applied (audio-quality, topic, ...) and finally the user is moved to that channel.
A second user may either join the first user in the existing channel or create another one by again joining the "Talk"-channel.

## Configuration
1. Edit the default config in "config.inc.php".
2. Edit the config specific for the ChannelWatcher in "channelwatcher.config.inc.php".

## Setup
1. Place the libraries in the include_path of PHP.
2. Either use [screen](http://wiki.ubuntuusers.de/screen) and run it with "php channelwatcher.php" or start it as deamon with System_Daemon.


## Dependencies
This software depends on [System_Daemon](http://pear.php.net/package/System_Daemon) (tested with version 1.0.0). 

It also depends on the [TeamSpeak 3 PHP Framework](https://www.planetteamspeak.com/powerful-php-framework/) (tested with version 1.1.23) but with a minor modification:
In Node/Channel.php the public function modify(...):
```php
$this->nodeInfo['cid'] = $properties["cid"];
```
must be added after
```php
$this->resetNodeInfo();
```

## License
This software is released under the GNU General Public License since it builds on TeamSpeak 3 PHP Framework. See LICENSE.txt for further information.