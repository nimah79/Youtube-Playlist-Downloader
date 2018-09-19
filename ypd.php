#!/usr/bin/env php
<?php

/**
 * YouTube Playlist Downloader
 * Simple script to download YouTube playlists, written in pure PHP
 * Usage example: php ypd.php https://www.youtube.com/playlist?list=PLoWdboo7g0w4K5Ws8ddeGfv9iD8sS3U58
 * By NimaH79
 * NimaH79.ir
 */

class YouTubeDownloader {

    private $google_api_key;
    private $download_path;
    private $last_progress;
    private $is_download_size_printed;

    private $itags_info = array(
        17 => '3GP 144p',
        36 => '3GP 240p',
        5 => 'FLV 240p',
        34 => 'FLV 360p',
        43 => 'WebM 360p',
        18 => 'MP4 360p',
        35 => 'FLV 480p',
        44 => 'WebM 480p',
        78 => 'MP4 480p',
        59 => 'MP4 480p',
        45 => 'WebM 720p',
        22 => 'MP4 720p',
        37 => 'MP4 1080p',
        46 => 'WebM 1080p',
        38 => 'MP4 3072p',
    );

    private $foreground_colors = array(
        'black' => '0;30',
        'dark_gray' => '1;30',
        'blue' => '0;34',
        'light_blue' => '1;34',
        'green' => '0;32',
        'light_green' => '1;32',
        'cyan' => '0;36',
        'light_cyan' => '1;36',
        'red' => '0;31',
        'light_red' => '1,31',
        'purple' => '0;35',
        'light_purple' => '1,35',
        'brown' => '0;33',
        'yellow' => '1;33',
        'light_gray' => '0;37',
        'white' => '1;37'
    );

    private $background_colors = array(
        'black' => '40',
        'red' => '41',
        'green' => '42',
        'yellow' => '43',
        'blue' => '44',
        'magenta' => '45',
        'cyan' => '46',
        'light_gray' => '47'
    );

    public function __construct($google_api_key, $download_path = __DIR__.'/') {
        $this->google_api_key = $google_api_key;
        $this->download_path = $download_path;
    }

    public function downloadPlaylistVideos($id) {
        $videos = $this->getPlaylistVideos($id);
        if(!empty($videos) && empty($videos['error'])) {
            $videos_count = count($videos);
            $this->echoColoredString($videos_count.' videos found!', 'green');
            for($i = 0; $i < $videos_count; $i++) {
                $this->echoColoredString(($i + 1).'/'.$videos_count.': '.$videos[$i]['title'], 'yellow');
                $download_links = $this->getDownloadLinks($videos[$i]['video_id']);
                if(!empty($download_links)) {
                    reset($download_links);
                    $itag = key($download_links);
                    $this->echoColoredString('Selected quality: '.$this->itags_info[$itag], 'green');
                    if(in_array($itag, [18, 22, 37, 38, 59, 78])) {
                        $extension = 'mp4';
                    } elseif(in_array($itag, [46, 43, 44, 45])) {
                        $extension = 'webm';
                    } elseif(in_array($itag, [5, 34, 35])) {
                        $extension = 'flv';
                    } else {
                        $extension = '3gp';
                    }
                    $file_name = str_replace(' ', '_', $videos[$i]['title']).'.'.$extension;
                    $file_name = str_replace('/', '', $file_name);
                    $file_name = preg_replace('/[_]{2,}/', '_', $file_name);
                    $file_name = preg_replace('/[-]{2,}/', '_', $file_name);
                    $file_path = $this->download_path.$file_name;
                    $this->echoColoredString('Downloading video to '.$file_path.'…', 'yellow');
                    $this->downloadVideo($download_links[$itag]['url'], $file_path);
                    $this->echoColoredString('Video downloaded successfully!', 'green');
                }
                else {
                    $this->echoColoredString('A problem occured while getting download links of video.', 'red', 'light_gray');
                }
            }
        }
        else {
            switch($videos['error']['errors'][0]['reason']) {
                case 'playlistNotFound':
                    $this->echoColoredString('Playlist not found.', 'red', 'light_gray');
                    break;
                case 'keyInvalid':
                    $this->echoColoredString('Given Google API key is invalid.', 'red', 'light_gray');
                    break;
                default:
                    $this->echoColoredString('An error occured while getting playlist videos.', 'red', 'light_gray');
                    break;
            }
        }
    }

    public function getPlaylistVideos($id) {
        if(preg_match('/list=([a-zA-Z0-9_-]+)/', $id, $playlist_id)) {
            $playlist_id = $playlist_id[1];
        }
        else {
            $playlist_id = $id;
        }
        $videos = $this->curl_get_contents('https://www.googleapis.com/youtube/v3/playlistItems?'.http_build_query(array('part' => 'snippet', 'maxResults' => 50, 'playlistId' => $playlist_id, 'key' => $this->google_api_key)));
        $videos = json_decode($videos, true);
        $result = array();
        if(!empty($videos['items'])) {
            foreach($videos['items'] as $video) {
                $result[] = array('title' => $video['snippet']['title'], 'video_id' => $video['snippet']['resourceId']['videoId']);
            }
            return $result;
        }
        return $videos;
    }

    public function getDownloadLinks($id) {
        $result = array();
        $instructions = array();
        if(strpos($id, '<div id="player') !== false) {
            $html = $id;
        } else {
            if(preg_match('/[a-zA-Z0-9_-]{11}/', $id, $video_id)) {
                $video_id = $video_id[0];
            }
            else {
                $video_id = $id;
            }
            $html = $this->curl_get_contents('https://www.youtube.com/watch?v='.$video_id);
        }
        $gvi = $this->curl_get_contents('https://www.youtube.com/get_video_info?el=embedded&eurl=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3D'.urlencode($video_id).'&ps=default&hl=en_US&video_id='.$video_id);
        if(preg_match('/url_encoded_fmt_stream_map=([^\&\s]+)/', $gvi, $matches_gvi)) {
            $uefsm = urldecode($matches_gvi[1]);
        }
        else {
            if(preg_match('/url_encoded_fmt_stream_map["\']:\s*["\']([^"\'\s]*)/', $html, $matches)) {
                $uefsm = $matches[1];
            } else {
                return false;
            }
        }
        $parts = explode(",", $uefsm);
        foreach($parts as $p) {
            $query = str_replace('\u0026', '&', $p);
            parse_str($query, $arr);
            $url = $arr['url'];
            if(isset($arr['sig'])) {
                $url = $url.'&signature='.$arr['sig'];
            } else if(isset($arr['signature'])) {
                $url = $url.'&signature='.$arr['signature'];
            } else if(isset($arr['s'])) {
                if(count($instructions) == 0) {
                    $instructions = (array)$this->getInstructions($html);
                }
                $dec = $this->sig_decipher($arr['s'], $instructions);
                $url = $url.'&signature='.$dec;
            }
            $itag = $arr['itag'];
            $format = isset($this->itags_info[$itag]) ? $this->itags_info[$itag] : 'Unknown';
            $result[$itag] = array(
                'url' => $url,
                'format' => $format
            );
        }
        if(empty($result)) {
            return $this->getDownloadLinks($id);
        }
        $sorted_keys = array_keys($result);
        usort($sorted_keys, function($a, $b) {
            foreach(array_keys($this->itags_info) as $value) {
                if($a == $value) {
                    return 0;
                }
                return 1;
            } 
        });
        $sorted_result = array();
        foreach($sorted_keys as $key) {
            $sorted_result[$key] = $result[$key];
        }
        return array_reverse($sorted_result, true);
    }

    private function downloadVideo($url, $path) {
        $this->is_download_size_printed = false;
        $this->last_progress = 0;
        if(is_file($path)) {
            unlink($path);
        }
        $file = fopen($path, 'w');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION,
            function($resource, $download_size, $downloaded, $upload_size, $uploaded) {
                if($download_size > 0) {
                    if(!$this->is_download_size_printed) {
                        $this->echoColoredString('File size: '.$this->formatBytes($download_size), 'cyan');
                        $this->is_download_size_printed = true;
                    }
                    $progress = round($downloaded / $download_size * 100);
                    if($progress > $this->last_progress + 4) {
                        $this->echoColoredString('Download progress: '.$progress.'%', 'blue');
                        $this->last_progress = $progress;
                    }
                }
            }
        );
        curl_setopt($ch, CURLOPT_FILE, $file);
        curl_exec($ch);
        curl_close($ch);
        fclose($file);
    }

    private function curl_get_contents($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function getInstructions($html) {
        if(preg_match('/<script\s*src="([^"]+player[^"]+js)/', $html, $matches)) {
            $player_url = $matches[1];
            if(strpos($player_url, '//') === 0) {
                $player_url = 'http://'.substr($player_url, 2);
            } elseif(strpos($player_url, '/') === 0) {
                $player_url = 'http://www.youtube.com'.$player_url;
            }
            $js_code = $this->curl_get_contents($player_url);
            $instructions = $this->sig_js_decode($js_code);
            if($instructions) {
                return $instructions;
            }
        }
        return false;
    }

    private function sig_decipher($signature, $instructions) {
        foreach($instructions as $opt) {
            $command = $opt[0];
            $value = $opt[1];
            switch($command) {
                case 'swap':
                    $temp = $signature[0];
                    $signature[0] = $signature[$value % strlen($signature)];
                    $signature[$value] = $temp;
                    break;
                case 'splice':
                    $signature = substr($signature, $value);
                    break;
                case 'reverse':
                    $signature = strrev($signature);
                    break;
                default:
                    break;
            }
        }
        return trim($signature);
    }

    private function sig_js_decode($player_html) {
        if(preg_match('/signature",([a-zA-Z0-9$]+)\(/', $player_html, $func_name)) {
            $func_name = $func_name[1];       
            $func_name = preg_quote($func_name);
            if(preg_match('/'.$func_name.'=function\([a-z]+\){(.*?)}/', $player_html, $js_code)) {   
                $js_code = $js_code[1];
                if(preg_match_all('/([a-z0-9]{2})\.([a-z0-9]{2})\([^,]+,(\d+)\)/i', $js_code, $matches)) {
                    $obj_list = $matches[1];
                    $func_list = $matches[2];
                    preg_match_all('/('.implode('|', $func_list).'):function(.*?)\}/m', $player_html, $matches2, PREG_SET_ORDER);
                    $functions = array();
                    foreach($matches2 as $m) {
                        if(strpos($m[2], 'splice') !== false) {
                            $functions[$m[1]] = 'splice';                       
                        } elseif(strpos($m[2], 'a.length') !== false) {
                            $functions[$m[1]] = 'swap';
                        } elseif(strpos($m[2], 'reverse') !== false) {
                            $functions[$m[1]] = 'reverse';
                        }
                    }
                    $instructions = array();
                    foreach($matches[2] as $index => $name) {
                        $instructions[] = array($functions[$name], $matches[3][$index]);
                    }
                    return $instructions;
                }
            }
        }
        return false;
    }

    private function echoColoredString($string, $foreground_color = null, $background_color = null) {
        $colored_string = '';
        if(isset($this->foreground_colors[$foreground_color])) {
            $colored_string .= "\033[".$this->foreground_colors[$foreground_color].'m';
        }
        if(isset($this->background_colors[$background_color])) {
            $colored_string .= "\033[".$this->background_colors[$background_color].'m';
        }
        $colored_string .= $string."\033[0m";
        echo $colored_string.PHP_EOL;
    }

    private function formatBytes($bytes, $precision = 1) { 
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        $bytes = max($bytes, 0);
        $pow = min(floor(($bytes ? log($bytes) : 0) / log(1024)), count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision).' '.$units[$pow]; 
    }

}

if(!empty($argv[1])) {
    $yt = new YouTubeDownloader('YOUR_GOOGLE_API_KEY');
    $yt->downloadPlaylistVideos($argv[1]);
} else {
    echo 'No arguments passed.'.PHP_EOL;
}
