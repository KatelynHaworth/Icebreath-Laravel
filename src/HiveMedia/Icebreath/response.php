<?php namespace HiveMedia\Icebreath;

class Response {

    // Whether or not the module errored and supplied a error
    private $errored = false;

    // The error the module supplied
    private $error   = null;

    // The HTTP code the module wants the response returned with
    private $code    = 200;

    // The response the module wants returned if there are no errors
    private $output  = null;

    // Array of headers to be used when making the response
    private $headers = array();

    // Response type, defaults to JSON
    private $type    = null;

    /**
     * Builds a response to pass back to Icebreath for it to parse and return to the requester
     *
     * @param $output
     * @param int $code
     * @param null $error
     * @param array $headers
     */
    public function __construct($output, $code=200, $error=null, $headers=array(), $type="application/json") {
        $this->output = $output;
        $this->code = $code;
        $this->headers = $headers;

        $this->type = $type;
        $this->headers["Content-type"] = $this->type;

        if(isset($error)) {
            $this->errored = true;
            $this->error = $error;
        }
    }

    /**
     * Gets whether or not the module threw a error while handling a request
     *
     * @return bool
     */
    public function hasErrored() { return $this->errored; }

    /**
     * Returns the error the module supplied when it ended processing the request
     *
     * @return mixed
     */
    public function getError() { return $this->error; }

    /**
     * Returns the HTTP code to use when returning the response to the requester
     * @return int
     */
    public function getHTTPCode() { return $this->code; }

    /**
     * Returns the output from the module
     *
     * @return mixed
     */
    public function getOutput() { return $this->output; }

    /**
     * Returns an array of headers that make
     * up the response
     *
     * @return array
     */
    public function getHeaders() { return $this->headers; }

    /**
     * Returns the response type
     *
     * @return string
     */
    public function getResponseType() { return $this->type; }

    /**
     * Adds a header key/value pair that shall be
     * returned as part of the response
     *
     * @param string $key
     * @param string $value
     * @return Response
     */
    public function header($key, $value) {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * Sets the response type, defaults
     * to application/json
     *
     * @param string $type
     * @return Response
     */
    public function setResponseType($type) {
        $this->type = $type;
        $this->headers['Content-type'] = $this->type;

        return $this;
    }
}