<?php

require_once("twitter.oauth.class.php");
require_once("twitter.class.php");

/* 
 *retweet.php <app config file> <session file> <watermark file> <user from> <keyword> [--dry-run]
 */

class KeywordRetweetBot {

    private $dry_run = false;

    private $app_config_file;
    private $session_file;
    private $watermark_file;
    private $from_user;
    private $since_id;
    private $new_watermark;
    private $keyword;
    
    private $twitter_client;
    
    public function main($argc, $argv) {
        $this->parse_options($argc, $argv);
        $since_id = $this->load_watermark_file($this->watermark_file);
        $this->init_twitter_client($this->app_config_file, $this->session_file);
        $relevant_tweets = $this->get_relevant_tweets($this->from_user, $since_id, $this->keyword);
        if($this->dry_run) {
            $this->dump_tweets($relevant_tweets);
        } else {
            $this->retweet_all($relevant_tweets);
        }
    }

    private function init_twitter_client($app_config_file, $session_file) {
        list($consumer_key, $consumer_secret)   = $this->load_app_config($this->app_config_file);
        list($oauth_token, $oauth_token_secret) = $this->load_session_config($this->session_file);
        $this->twitter_client = new Twitter($consumer_key, $consumer_secret, $oauth_token, $oauth_token_secret);
    }

    private function parse_options($argc, $argv) {
        if ($argc < 6) {
            throw new RuntimeException("Usage: retweet.php <app config file> <session file> <user from> <since id> <keyword> [--dry-run]", 1);
        }

        $appname = array_shift($argv);

        $this->app_config_file = array_shift($argv);
        $this->session_file = array_shift($argv);
        $this->watermark_file = array_shift($argv);
        $this->from_user = array_shift($argv);
        $this->keyword = array_shift($argv);

        $dry_run = array_shift($argv);
        if($dry_run == "--dry-run") {
            $this->dry_run = true;
        }
    }

    private function load_app_config($app_config_file) {
        list(
            $consumer_key, 
            $consumer_secret
        ) = explode("\n", trim(file_get_contents($app_config_file)));

        if(empty($consumer_key) || empty($consumer_secret)) {
            throw new RuntimeException("Didn't find any application config in $app_config_file", 1);
        }

        return array($consumer_key, $consumer_secret);
    }

    private function load_session_config($session_file) {
        list(
            $oauth_token, 
            $oauth_token_secret
        ) = explode("\n", trim(file_get_contents($session_file)));

        if(empty($oauth_token) || empty($oauth_token_secret)) {
            throw new RuntimeException("Didn't find any session config in $session_file", 1);
        }

        return array($oauth_token, $oauth_token_secret);
    }

    private function load_watermark_file($watermark_file) {
        list(
            $since_id
        ) = explode("\n", trim(file_get_contents($watermark_file)));

        if(empty($since_id)) {
            throw new RuntimeException("Didn't find any watermark in $watermark_file", 1);
        }

        return $since_id;
    }    

    private function save_new_watermark($watermark_file, $watermark) {
        file_put_contents($watermark_file, $watermark."\n");
    }    

    private function get_relevant_tweets($from_user, $since_id, $keyword)
    {
        $remaining_tweets = true;
        $this->new_watermark = $since_id;
        $retweet_ids = array();
        while($remaining_tweets) {
            echo "Fetching tweets newer than $most_recent_seen_id...\n";
            $tweets = $this->get_relevant_tweets_page($from_user, $this->new_watermark, $keyword);
            if(empty($tweets)) {
                $remaining_tweets = false;
            } else {
                foreach($tweets as $tweet)
                {
                    if($tweet->id_str > $this->new_watermark) {
                        $this->new_watermark = $tweet->id_str;
                    }
                    if(strstr($tweet->text, $keyword)) {
                        echo "FOUND: " . $tweet->text . "\n";
                        $retweet_ids[] = $tweet->id_str;
                    }
                }
            }
        }
        $this->save_new_watermark($this->watermark_file, $this->new_watermark);
        echo "Done.\n";
    }

    private function get_relevant_tweets_page($from_user, $since_id, $keyword)
    {
        return $this->twitter_client->request('statuses/user_timeline', 'GET', array(
            'screen_name' => $from_user,
            'since_id' => $since_id, 
            'count' => 200
        ));        
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
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(-1);
}