<?php

class History {
    private $history;
    
    public function __construct() {
        $this->history = array();
    }
    
    public function add($uid, $time = null) {
        $this->history[] = array(
            "uid" => $uid,
            "time" => $time?$time:time()
        );
        $this->trimHistory();
    }
    
    private function trimHistory() {
        if(count($this->history) > 10) {
            array_shift($this->history);
        }
    }
    
    public function getLatest($seconds) {
        $out = array();
        for($i = count($this->history)-1; $i >= 0; $i--) {
            if(time() - $seconds <= $this->history[$i]["time"]) {
                $out[] = $this->history[$i];
            } else {
                break;
            }
        }
        return array_reverse($out);
    }
    
    public function getLatestOf($uid, $seconds) {
        $out = array();
        foreach ($this->getLatest($seconds) as $entry) {
            if($uid == $entry['uid']) {
                $out[] = $entry;
            }
        }
        return $out;
    }
}