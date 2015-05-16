<?php namespace HiveMedia\Icebreath\Modules;

use HiveMedia\Icebreath\Module;
use HiveMedia\Icebreath\Response;
use Carbon\Carbon;

class covers extends Module {

    private $VERSION = "0.1.0";

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
        $artist = $view;

        if(!isset($artist)) {
            return new Response(array(
                'message' => "The Hive Radio music artist cover API version $this->VERSION",
                'usage'   => "cover/[artist]"
            ), 200);
        } else {
            if(\Cache::has("artist_cover_$artist")) {
                return $this->buildImageResponse(\Cache::get("artist_cover_$artist"));
            } else {
                $youtube_response = file_get_contents("https://www.googleapis.com/youtube/v3/search?q=$artist&key=" . \Config::get('icebreath.covers.youtube_api_key') . "&fields=items(id(kind,channelId),snippet(thumbnails(medium)))&part=snippet,id");
                $youtube_json = json_decode($youtube_response, true);
                $items = $youtube_json['items'];
                $youtube_source = null;

                for($i = 0; $i < sizeof($items); $i++)
                {
                    if(((string)$items[$i]["id"]["kind"]) != "youtube#channel")
                        continue;
                    else {
                        $youtube_source = $items[$i]["snippet"]["thumbnails"]["medium"]["url"];
                        break;
                    }
                }

                if(isset($youtube_source) && !empty($youtube_source))
                {
                    $data = file_get_contents($youtube_source);

                    $expiresAt = Carbon::now()->addHours(\Config::get('icebreath.covers.cache_time'));
                    \Cache::put("artist_cover_$artist", $data, $expiresAt);

                    return $this->buildImageResponse($data);
                }

                return $this->buildImageResponse(\File::get(base_path() . '/' . \Config::get('icebreath.covers.not_found_img')));
            }
        }
    }

    private function buildImageResponse($img) {
        return new Response(
            \Image::make($img)->encode('png'),
            200,
            null,
            array(),
            "image/png"
        );
    }
}