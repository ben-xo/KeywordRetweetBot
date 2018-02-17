<?php

/**
 *  @author      Ben XO (me@ben-xo.com)
 *  @copyright   Copyright (c) 2018 Ben XO
 *  @license     MIT License (http://www.opensource.org/licenses/mit-license.html)
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is
 *  furnished to do so, subject to the following conditions:
 *  
 *  The above copyright notice and this permission notice shall be included in
 *  all copies or substantial portions of the Software.
 *  
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

require_once("twitter.oauth.class.php");
require_once("twitter.class.php");

/* 
 * retweet.php <app config file> <session file> <watermark file> <user from> <keyword> [<keyword> ...] [--dry-run]
 */

class KeywordRetweetBot {

    private $dry_run = false;

    private $app_config_file;
    private $session_file;
    private $watermark_file;
    private $from_user;
    private $since_id;
    private $new_watermark;
    private $keywords = array();
    
    private $twitter_client;
    
    public function main($argc, $argv) {
        $this->parse_options($argc, $argv);
        $since_id = $this->load_watermark_file($this->watermark_file);
        $this->init_twitter_client($this->app_config_file, $this->session_file);
        $relevant_tweets = $this->get_relevant_tweets($this->from_user, $since_id, $this->keywords);
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
            throw new RuntimeException("Usage: retweet.php <app config file> <session file> <user from> <keyword> [<keyword> ...] [--dry-run]", 1);
        }

        $appname = array_shift($argv);

        $this->app_config_file = array_shift($argv);
        $this->session_file = array_shift($argv);
        $this->watermark_file = array_shift($argv);
        $this->from_user = array_shift($argv);

        while ($keyword = array_shift($argv)) {
            $this->keywords[] = $keyword;
        }

        if($this->keywords[count($this->keywords) - 1] == "--dry-run") {
            # last keyword was --dry-run
            $this->dry_run = true;
            array_pop($this->keywords);
            echo "DRY RUN MODE\n";
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
        if(!$this->dry_run) {
            file_put_contents($watermark_file, $watermark."\n");
            echo "Saved new watermark of $watermark\n";
        } else {
            echo "Didn't save new watermark because in --dry-run mode\n";
        }
    }    

    private function get_relevant_tweets($from_user, $since_id, $keywords)
    {
        $remaining_tweets = true;
        $this->new_watermark = $since_id;
        $retweet_ids = array();
        $keywords_text = implode(', ', $this->keywords);
        while($remaining_tweets) {
            echo "Fetching tweets newer than {$this->new_watermark} with keywords $keywords_text...\n";
            $tweets = $this->get_relevant_tweets_page($from_user, $this->new_watermark);
            if(empty($tweets)) {
                $remaining_tweets = false;
            } else {
                foreach($tweets as $tweet)
                {
                    if($tweet->id_str > $this->new_watermark) {
                        $this->new_watermark = $tweet->id_str;
                    }
                    $found = 0;
                    foreach ($this->keywords as $keyword) {
                        if(strstr($tweet->text, $keyword)) {
                            $found++;
                        }
                    }
                    if($found == count($this->keywords)) {
                        echo "FOUND: " . $tweet->text . "\n";
                        $retweet_ids[] = $tweet->id_str;
                    }
                }
            }
        }
        $this->save_new_watermark($this->watermark_file, $this->new_watermark);
        echo "Done.\n";
        return $retweet_ids;
    }

    private function get_relevant_tweets_page($from_user, $since_id)
    {
        return $this->twitter_client->request('statuses/user_timeline', 'GET', array(
            'screen_name' => $from_user,
            'since_id' => $since_id, 
            'count' => 200
        ));
    }

    private function dump_tweets($relevant_tweets)
    {
        echo "Would retweet the following tweets:\n";
        foreach($relevant_tweets as $tweet_id)
        {
            echo "* https://twitter.com/{$this->from_user}/status/$tweet_id\n";
        }
    }

    private function retweet_all($relevant_tweets)
    {
        foreach($relevant_tweets as $tweet_id)
        {
            echo "Retweeting https://twitter.com/{$this->from_user}/status/$tweet_id\n";
            $this->twitter_client->request("statuses/retweet/$tweet_id", 'POST', array());
        }
    }
}

try {
    $retweet_bot = new KeywordRetweetBot();
    $retweet_bot->main($argc, $argv);
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(-1);
}