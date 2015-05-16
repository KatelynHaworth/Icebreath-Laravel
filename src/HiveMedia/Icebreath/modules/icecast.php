<?php namespace HiveMedia\Icebreath\Modules;

use HiveMedia\Icebreath\Module;
use HiveMedia\Icebreath\Response;

class icecast extends Module {

    private $VERSION = "0.1.2";

    /**
     * Asks the module to handle a PUT (Create) request
     *
     * @param $view
     * @param $args
     * @return Response
     */
    protected function handlePut($view, $args)
    {
        return new Response(null, 404, "This module doesn't support this HTTP method");
    }

    /**
     * Asks the module to handle a POST (Update) request
     *
     * @param $view
     * @param $args
     * @return Response
     */
    protected function handlePost($view, $args)
    {
        return new Response(null, 404, "This module doesn't support this HTTP method");
    }

    /**
     * Asks the module to handle a DELETE (Well duh, Delete) request
     *
     * @param $view
     * @param $args
     * @return Response
     */
    protected function handleDelete($view, $args)
    {
        return new Response(null, 404, "This module doesn't support this HTTP method");
    }

    /************************************* REAL CODE ***********************************/

    /**
     * Asks the module to handle a GET (Read) request
     *
     * @param $view
     * @param $args
     * @return Response
     */
    protected function handleGet($view, $args)
    {
        if(!isset($view))
            return new Response(array(
                'message' => "Icecast module version $this->VERSION for Icebreath",
                'usage' => 'icecast/[stats]/{mount}'
            ));
        else {
            if(count($args) >= 1) {
                return $this->getStats($args[0]);
            } else {
                return $this->getStats();
            }
        }
    }

    /**
     * Generates a URL address for the Icecast server
     * using the settings defined in the modules config
     *
     * @param $uri
     * @return string
     */
    private function getServerURL($uri) {
        return "http://" .
               \Config::get("icebreath.icecast.username") . ":" . \Config::get("icebreath.icecast.password") .
               "@" . \Config::get("icebreath.icecast.hostname") . ":" . \Config::get("icebreath.icecast.port") . $uri;
    }

    /**
     * Opens a cURL connection to the URL to retrieve and
     * return the data from the address
     *
     * Throws a RuntimeException when it fails to connect
     * to the server or returns a non-OK http response code
     *
     * @param $url
     * @return mixed
     * @throws RuntimeException
     */
    private function getDataFromServer($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.2) Icebreath/Icecast (Firefox cURL emulated)');

        $data = curl_exec($curl);
        $http_resp = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if(empty($data) || $http_resp != "200")
            throw new \RuntimeException("Failed to connect to the requested server ***MASKED***, got HTTP response code [$http_resp]");

        return curl_exec($curl);
    }

    /**
     * Gets the current nowplaying stats from Icecast server
     * and formats in JSON for easy access
     *
     * @param string $mount_point_name
     * @return Response
     */
    private function getStats($mount_point_name="")
    {
        $data = null;
        try {
            $data = $this->getDataFromServer($this->getServerURL("/admin/stats"));
        } catch(\RuntimeException $ex) {
            return new Response(null, 500, $ex->getMessage());
        }

        $data = new \SimpleXMLElement($data);

        $server_data = new ServerStruct();
        $server_data->server_version = (string)$data->server_id;
        $server_data->server_admin = (string)$data->admin;
        $server_data->server_location = \Config::get('icebreath.icecast.location');
        $server_data->server_listeners_total = (int)$data->listeners;
        $server_data->server_listeners_unique = 0;
        $server_data->server_listeners_peak = 0;
        $server_data->server_listeners_max = 0;
        $server_data->server_streams = array();

        foreach($data->source as $mount_point)
        {
 
            $ignore_this_mount = false;
            foreach(\Config::get('icebreath.icecast.mount_point_ignore') as $id => $ignore_mount) {
                if((string)$mount_point["mount"] == $ignore_mount) {
                    $ignore_this_mount = true;
                }
            }

            if($ignore_this_mount) {
                continue;
            }

            if(!empty($mount_point_name) && str_replace('/', '', $mount_point["mount"]) != $mount_point_name)
                continue;

           $server_data->server_listeners_peak += (int)$mount_point->listener_peak;
            if($mount_point->max_listeners == "unlimited")
                $server_data->server_listeners_max =  -1;
            else
                $server_data->server_listeners_max += (int)$mount_point->max_listeners;
            $unique_listeners = $this->getUniqueListenersOnMount((string)$mount_point["mount"]);
            $server_data->server_listeners_unique += $unique_listeners;

            if($mount_point->source_ip == null)
            {
                $stream = new StreamStruct();
                $stream->stream_online = false;
                $stream->stream_name = (string)$mount_point["mount"];
                $stream->stream_error = "Stream is offline! It has no connected source!";
                array_push($server_data->server_streams, $stream);
                continue;
            }

            $stream = new StreamStruct();
            $stream->stream_online = true;
            $stream->stream_title = (string)$mount_point->server_name;
            $stream->stream_description = (string)$mount_point->server_description;
            $stream->stream_name = (string)$mount_point["mount"];
            $stream->stream_genre = (string)$mount_point->genre;

            $stream->stream_audio_info = new AudioInfoStruct();
            $audio_info = explode(';', (string)$mount_point->audio_info);
            foreach($audio_info as $info)
            {
                $info_data = explode('=', $info);
                $stream->stream_audio_info->$info_data[0] = $info_data[1];
            }

            $stream->stream_mime = (string)$mount_point->server_type;
            $stream->stream_listeners = (int)$mount_point->listeners;
            $stream->stream_listeners_peak = (int)$mount_point->listener_peak;
            $stream->stream_listeners_max = ($mount_point->max_listeners == "unlimited" ? -1 : (int)$mount_point->max_listeners);
            $stream->stream_listeners_unique = $unique_listeners;

            if(\Config::get('icebreath.icecast.custom_stream_url_base') != null) {
                $stream->stream_url = \Config::get('icebreath.icecast.custom_stream_url_base') . $stream->stream_name;
            } else {
                $stream->stream_url = (string)$mount_point->listenurl;
            }

            $stream->stream_nowplaying = new NowPlayingStruct();
            $stream->stream_nowplaying->text = (string)$mount_point->title;

            $nowplaying = explode(" - ", (string)$mount_point->title);
            $stream->stream_nowplaying->artist = $nowplaying[0];

            for($index = 1; $index < count($nowplaying); $index++) {
                if ($index > 1)
                    $stream->stream_nowplaying->song .= " - ";

                $stream->stream_nowplaying->song .= $nowplaying[$index];
            }

            array_push($server_data->server_streams, $stream);
        }

        return $this->generateResponse($server_data);
    }

    /**
     * Gets the ammount of unique listeners that are listening
     * to the specified mount point on Icecast
     *
     * @param $mount
     * @return int
     */
    private function getUniqueListenersOnMount($mount)
    {
        $data = $this->getDataFromServer($this->getServerURL("/admin/listclients?mount=$mount"));

        $data = new \SimpleXMLElement($data);
        $unique_listener_array = array();

        foreach($data->source->listener as $listener)
            if(!in_array($listener->IP, $unique_listener_array))
                array_push($unique_listener_array, $listener->IP);

        return count($unique_listener_array);
    }

    /**
     * Takes the data structures built by getStats() and
     * builds an array out of the information to return
     * to Icebreath
     *
     * @param $server_data
     * @return Response
     */
    private function generateResponse($server_data)
    {
        $response = array();

        $response["server_version"] = $server_data->server_version;
        $response["server_admin"] = $server_data->server_admin;
        $response["server_location"] = $server_data->server_location;
        $response["server_listeners_total"] = $server_data->server_listeners_total;
        $response["server_listeners_unique"] = $server_data->server_listeners_unique;
        $response["server_listeners_peak"] = $server_data->server_listeners_peak;
        $response["server_listeners_max"] = $server_data->server_listeners_max;

        $server_streams = array();

        if($server_data->server_streams)
            foreach($server_data->server_streams as $stream_data)
            {
                $stream_nowplaying = array();
                if($stream_data->stream_nowplaying)
                    $stream_nowplaying = array(
                        "song" => $stream_data->stream_nowplaying->song,
                        "artist" => $stream_data->stream_nowplaying->artist,
                        "text" => $stream_data->stream_nowplaying->text,
                        "dj" => $stream_data->stream_nowplaying->dj
                    );

                $stream_audio_info = array();
                if($stream_data->stream_audio_info)
                    $stream_audio_info = array(
                        "bitrate" => $stream_data->stream_audio_info->bitrate,
                        "samplerate" => $stream_data->stream_audio_info->samplerate,
                        "channels" => $stream_data->stream_audio_info->channels
                    );

                array_push($server_streams, array(
                    "stream_online" => $stream_data->stream_online,
                    "stream_title" => $stream_data->stream_title,
                    "stream_description" => $stream_data->stream_description,
                    "stream_name" => $stream_data->stream_name,
                    "stream_genre" => $stream_data->stream_genre,
                    "stream_audio_info" => $stream_audio_info,
                    "stream_mime" => $stream_data->stream_mime,
                    "stream_listeners" => $stream_data->stream_listeners,
                    "stream_listeners_unique" => $stream_data->stream_listeners_unique,
                    "stream_listeners_peak" => $stream_data->stream_listeners_peak,
                    "stream_listeners_max" => $stream_data->stream_listeners_max,
                    "stream_url" => $stream_data->stream_url,
                    "stream_nowplaying" => $stream_nowplaying,
                    "stream_song_history" => $stream_data->stream_song_history,
                    "stream_error" => $stream_data->stream_error
                ));
            }

        $response["server_streams"] = $server_streams;

        return new Response($response);
    }
}

class ServerStruct {
    public $server_version;
    public $server_admin;
    public $server_location;
    public $server_listeners_total;
    public $server_listeners_unique;
    public $server_listeners_peak;
    public $server_listeners_max;
    public $server_streams;
}

class StreamStruct {
    public $stream_online;
    public $stream_title;
    public $stream_description;
    public $stream_name;
    public $stream_genre;
    public $stream_audio_info;
    public $stream_mime;
    public $stream_listeners;
    public $stream_listeners_unique;
    public $stream_listeners_peak;
    public $stream_listeners_max;
    public $stream_url;
    public $stream_nowplaying;
    public $stream_song_history;
    public $stream_error;
}

class NowPlayingStruct {
    public $song;
    public $artist;
    public $text;
    public $dj;
}

class AudioInfoStruct {
    public $bitrate;
    public $samplerate;
    public $channels;
}
