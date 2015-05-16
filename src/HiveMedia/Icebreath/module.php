<?php namespace HiveMedia\Icebreath;

abstract class Module {

    /**
     * Handles the processing of getting a response from a module
     *
     * @param $view
     * @param $args
     * @param $method
     * @return Response
     */
    public function getModuleResponse($view, $args, $method)
    {
        $resp = null;

        if ($method == "PUT") {
            $resp = $this->handlePut($view, $args);
        } else if ($method == "GET") {
            $resp = $this->handleGet($view, $args);
        } else if ($method == "POST") {
            $resp = $this->handlePost($view, $args);
        } else if ($method == "DELETE") {
            $resp = $this->handleDelete($view, $args);
        } else {
            $resp = new Response(null, $code = 400, $error = "Icebreath doesn't support the HTTP method [$method]");
        }

        if (!isset($resp)) {
            return new Response(null, $code = 500, $error = "The module didn't supply a response for view [$view] via [$method]");
        } else {
            return $resp;
        }
    }

    /**
     * Asks the module to handle a PUT (Create) request
     *
     * @param $view
     * @param $args
     * @return Response
     */
    abstract protected function handlePut($view, $args);

    /**
     * Asks the module to handle a GET (Read) request
     *
     * @param $view
     * @param $args
     * @return Response
     */
    abstract protected function handleGet($view, $args);

    /**
     * Asks the module to handle a POST (Update) request
     *
     * @param $view
     * @param $args
     * @return Response
     */
    abstract protected function handlePost($view, $args);

    /**
     * Asks the module to handle a DELETE (Well duh, Delete) request
     *
     * @param $view
     * @param $args
     * @return Response
     */
    abstract protected function handleDelete($view, $args);
}