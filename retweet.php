<?php

/* 
 *retweet.php <session file> <user from> <since> <keyword> [--dry-run]
 */

class KeywordRetweetBot {

    private $dry_run = false;

    private $session_file;
    private $from_user;
    private $since_timestamp;
    private $keyword;
    
    private $oauth_token;
    private $oauth_token_secret;
    
    public function main($argc, $argv) {
        $this->parse_options($argc, $argv);
        $this->load_config($this->session_file);
        $relevant_tweets = $this->get_relevant_tweets($this->from_user, $this->since_timestamp, $this->keyword);
        if($this->dry_run) {
            $this->dump_tweets($relevant_tweets);
        } else {
            $this->retweet_all($relevant_tweets);
        }
    }

    private function parse_options($argc, $argv) {
        $appname = array_shift($argv);

        $this->session_file = array_shift($argv);
        $this->from_user = array_shift($argv);
        $this->since_timestamp = array_shift($argv);
        $this->keyword = array_shift($argv);

        $dry_run = array_shift($argv);
        if($dry_run == "--dry-run") {
            $this->dry_run = true;
        }
    }

    private function load_config($session_file) {
        list(
            $this->oauth_token, 
            $this->oauth_token_secret
        ) = explode("\n", trim(file_get_contents($session_file)));

        if(empty($this->oauth_token) || empty($this->oauth_token_secret)) {
            throw new RuntimeException("Didn't find any config in $session_file", 1);
        }
    }

    private function get_relevant_tweets($from_user, $since_timestamp, $keyword)
    {
        throw new Exception(__METHOD__ . " not implemented yet");
    }

    private function dump_tweets($relevant_tweets)
    {
        throw new Exception(__METHOD__ . " not implemented yet");
    }

    private function retweet_all($relevant_tweets)
    {
        throw new Exception(__METHOD__ . " not implemented yet");
    }
}

try {
    $retweet_bot = new KeywordRetweetBot();
    $retweet_bot->main($argc, $argv);
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage();
    exit(-1);
}