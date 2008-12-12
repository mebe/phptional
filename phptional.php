<?php
/**
 * Exception handler and client for getexceptional.com
 *
 * @author Jan Lehnardt <jan@php.net>
 * @author Iikka Niinivaara <iikka.niinivaara@gipresence.com>
 **/

class PHPtional_Client
{
    /**
     * Installs the ExceptionalClient class as the default exception handler
     *
     **/
    public function __construct($api_key, $options = array())
    {
        // Set default options
        if (array_key_exists('ssl', $options) && $options['ssl'] === true) {
            if (!array_key_exists('port', $options)) {
                $options['port'] = 443;
            }
        }
        $options += array(
            'host' => 'getexceptional.com',
            'ssl' => false,
            'port' => 80
        );
        $this->options = $options;
        $this->api_key = $api_key;

        // set exception handler and keep decorated exception handler around
        $this->decoratedHandler = set_exception_handler(array($this, "handleException"));
        $this->exceptions = array();
    }

    public function handleException($exception)
    {
        $this->exceptions[] = new PHPtional_Data($exception);
        if($this->decoratedHandler) {
            $this->decoratedHandler();
        }
    }

    // We send the exceptions when the script is finishing after the exception
    // and destructing the objects -- quite clever
    public function __destruct()
    {
        foreach($this->exceptions as $exception) {
            $this->post('errors', $body = $exception->toEscapedZlibbedJson());
        }
    }

    public function authenticate()
    {
        $this->post('authenticate');
    }

    private function post($method, $data = null)
    {
        $uri = "/{$method}?api_key={$this->api_key}&protocol_version=3";
        
        $request = "POST {$uri} HTTP/1.1\r\n";
        $request .= "Host: {$this->options['host']}\r\n";
        $request .= "Accept: application/x-gzip\r\n";
        $request .= "User-Agent: phptional 0.0\r\n";
        $request .= "Content-Type: application/x-gzip\r\n";
        $request .= "Connection: close\r\n";

        if($data) {
            $request .= "Content-Length: ".strlen($data)."\r\n\r\n";
            $request .= "$data\r\n";
        } else {
            $request .= "Content-Length: 0\r\n\r\n";
        }

        $s = fsockopen($this->options['host'], $this->options['port'], $errno, $errstr);
        if(!$s) {
            echo "$errno: $errstr\n";
            return false;
        }

        flush();
        fwrite($s, $request);
        $response = "";
        while(!feof($s)) {
            $response .= fgets($s);
        }

         print($response);
         
        //print($request);
        
    }
}

class PHPtional_Data
{
    public function __construct($exception)
    {
        $trace = current($exception->getTrace()); // Get the first element

        $this->language = 'PHP';
        $this->exception_class = get_class($this->exception);
        $this->exception_message = $exception->getMessage();
        $this->exception_backtrace = $exception->getTraceAsString();
        $this->occured_at = 'Ymd H:i:s e'; // PHP formatting is slightly different from Ruby
        $this->controller_name = $trace['class']; // Let's use the failed class
        $this->action_name = $trace['function']; // Let's use the failed function
        $this->application_root = $_SERVER['DOCUMENT_ROOT']; // Document root will do
        $this->url = $this->getCurrentUrl();
        $this->parameters = $_GET + $_POST;
        $this->session = $_SESSION;
        $this->environment = $_SERVER; // Rails's request.env is equivalent
    }

    public function toJson()
    {
        return json_encode($this);
    }
    public function toEscapedZlibbedJson() {
        return gzcompress($this->toJson());
    }

    private function getCurrentUrl()
    {
        return 'http' . (!empty($_SERVER['HTTPS']) ? 's' : '') . '://' .
        (!empty($_SERVER['HTTP_HOST']) ?
            $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']) .
        $_SERVER['REQUEST_URI'];
    }
}