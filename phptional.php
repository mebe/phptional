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
        $this->exceptions[] = new PHPtional_Exception($exception);
        if($this->decoratedHandler) {
            $this->decoratedHandler();
        }
    }

    // We send the exceptions when the script is finishing after the exception
    // and destructing the objects -- quite clever
    public function __destruct()
    {
        foreach($this->exceptions as $exception) {
            $this->post('errors', $exception->toEscapedZlibbedJson());
            //$this->post('errors', $exception->toJson());
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

class PHPtional_Exception
{
    public function __construct($exception)
    {
        $trace = current($exception->getTrace()); // Get the first element

        $this->language = 'PHP';
        $this->exception_class = get_class($this->exception);
        if ($this->exception_class === false) {
            $this->exception_class = 'Exception';
        }
        $this->exception_message = $exception->getMessage();
        $this->exception_backtrace = explode("\n", $exception->getTraceAsString());
        $this->occurred_at = date('Ymd H:i:s T'); // PHP formatting is slightly different from Ruby
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
        return urlencode(gzcompress($this->toJson()));
    }

    private function getCurrentUrl()
    {
        return 'http' . (!empty($_SERVER['HTTPS']) ? 's' : '') . '://' .
        (!empty($_SERVER['HTTP_HOST']) ?
            $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']) .
        $_SERVER['REQUEST_URI'];
    }
}

/*
POST /errors?api_key=7f9203fe7928833d39688344fc44e36419ee2d07&protocol_version=3 HTTP/1.1
Host: getexceptional.com
Accept: application/x-gzip
User-Agent: phptional 0.0
Content-Type: application/x-gzip
Connection: close
Content-Length: 2527

x%9C%9DUms%A2H%10%FE%2B%9C%FBE%F7%947%DF%40%CB%BB%A3pTjU%5C%C0%E4%B6%8E-j%C4%89%B2%01%86%8511%C9%E5%BFo%0Fj%A2%BB%B9%BD%AB%2B-%98y%FA%ED%E9%9E%E9%E6%A9%12%E3t%B3%C3%1BR%E9U%16%93E%A5%5E%21%FB%90d%2C%A2i%10%C6%B8%28%00G%27%E4B%9A%90%A28%D8y%DB%A8%10%E0%9F%E5%84%B1%07%21%25%98%FDr%A1%BA%C2%E1-%CBq%08%CA%7FU%DE%C9%82%2F-%0B%92%17%BE%14%A5%BE4%CCi%B6%A2%7B_Z%E4%F4%0B%09%19%C0%D9%B6%B4%C3%B1%2F1R0%11%F6UE%AF%F5%84%11%A5%8D%DFV8%AF%D6%C0%FF%3BExJp%94%3EW%3E%D7%2B4%0CwyN%D6%01f%C0H%95eMQ%15UP%9A%3D%B5%D5S%DA%02B%1E%98%844e9%8Dc%92%07%29N8w%F0%088%0EK%9EG%0C%02p%2C%CB%E2%28%C4%A5+%A7%94%BB%FD9o%B0%D9%E51%A8m%19%CBz%BE%E4K%8A%AE%8AJG%13%E1%A9t%DEL%EB%F7U%8C%F3%CDv%C0%1F%60%9F%E1%1C%280%88Q%E9%3DU%0E2pX%BE%9E%A1%A2%E9%5D%94%D34%21%29%E3%F2%89%E7-%82%89%EDz%A0r%11%0A%3C%95%B2%A5%8B%9C%C0%18%A39%D7%98%D1%C7%28%8E%B1%2F%B5EY%A8%5EG%E9%9A%DE%17%7Da%D9%17%8Eka%EE%09%1DQ%EE%0B%24m%2C%DD%BE%90%DF%F5%14Q%17e%B1U%13%C6%24%BC%A5%BET%D6UVuU%16FQNnx%FAM%AEq%8Ah%98%26Z%F0h%8C%EC%99%2FmY%12%D7%CF%0A%E9K%7B%0E%FD%BA%FF%01N%E2%FE%D7%81%2C%EA%F5%F7%BE%F4%BE%5Cj%97%3E%83%A91%1F%2F%21%19p%0E%04wE%9D%A4%A5%5E%FB%3B%3D47%ED%A15%1F%83%DE%E61%CA%EAkr%13cF%BE%D32%27%86%E3%22%CE%D4r%ED%86%A6%B5%F5%86R%DF%B1%9B%86V%3A%ED%D6%0F%24%BA%27%B3%0F%08%81%ED%D4%BA%E2%F1%9B%B2%7C%C2M%7B%3EG%A6g%D9s%C0o%09%C9%1A8%8E%EE%C8%AB%D8%FE%60q%93+%D8%B1%04%0F%14%5D%E9tuYSE%BD%D9%ECv%F4%A6%D6%D2Z%F0%EC%B4dYTT%15n%AD%0Cd%CA%A5%AE%B7t%7E%AA%AA%0A%06r%A7%AD%8AZ_%28%FD%3C%9E%F997%82%1FH%C3%22%1FT%D7p%3C%21%AB%FD%CD%F7az%B9O%D6%83jJSR%EB%0B%F7%F7E%10%D3%CD%86%AC%07%0A8%BF%C7%0F%F7%A4+%24%28%A0%B9%F9%C5%8F%D6%03%5D%05%1A7k%AC%AD%94%E6%BA%7B%83eY%5D%AD%F4%10%82kMMW%D5%23%A7%D5%19%27%A0%22%9F%F1%3Ej%84%AF%1A%2F%E51%CC%09%E25%F4%1C%7B%0AUJ%F0%BE%013e%C0%CB%BB0%BCI%D9s%BB%22%F7%A5%98%86%BCo%92%87%E2%2B%BCVQ%0A%0DF3%F6%22%F8%11%29%0EPi%7DX%9E%01G%E1%B9%CE%85%A3%12%F9SQ%CA%3D%90%81F%BA%82%5Er%AD%F1%DC%F0%96%0E%3F%D13%D4%1Ey%D7F%09%1A%19%0E%B7%04%1A%05%1AQ%13%AA%CB4%DA%D7%84%84%AE%83%A2%88O%A8%9D%91%D4u%A7%BE%04%D7%5D%EC%C6%C2%D0%B8%02%91%00%D3%97%F7%A6%2Av%5E%3D%CF%8D%19z%A3%B9%8FRc8t%FEY%BA%B0%1D%7E%BD5%5EK%07%CDl%0F%BDe%A0%80th%9B%CB%19%0C%89%C0%B1m%EF%BF%8C%B9%97%F83%8B_%FB%07%BA%FB%83%ECq%92%C5D%0Ci%C2%15L%C7%82%26%1BYStL%E1%7F%8C%FCW%DE%C7T%DA%AA%D6%E2%D9%8C%0D%0F%5D%1B%9F%02k%EE%21gd%98%DC%BF9%B6%60%DE%8A%CAY%FE%8E%ED%D9fy%AD%F8%5D%3BI%1D%F4q%89%5C%2F%98%21ob%0FA8.%BF%0C%00%3A%9F%02%D7s%0E%93%E3r%2C%9Fl%96%8EU%A6%F2%EF%83%FCX%80%97%E4%DFN%0F%8E%3Cp%D1t%F4%13%95Sh%CF%E2%9E%0E%3D%A5%C8%EDv%9D%7F%14%EE%F8%F7%F4%3C%F0%E7%12%0EA%F1%F9%F9%1BP%FF%60_
*/

/*
{
    "language": "PHP",
    "exception_class": "Exception",
    "exception_message": "This is pretty neat!",
    "exception_backtrace": [
        "#0 \/Users\/in\/Dropbox\/Projects\/phptional\/test.php(19): Foo->bar()",
        "#1 {main}"
    ],
    "occurred_at": "20081212 13:24:15 EET",
    "controller_name": "Foo",
    "action_name": "bar",
    "application_root": "\/Users\/in\/Dropbox\/Projects",
    "url": "http:\/\/192.168.2.116\/phptional\/test.php?blargh=argh",
    "parameters": {
        "blargh": "argh"
    },
    "environment": {
        "HTTP_HOST": "192.168.2.116",
        "HTTP_USER_AGENT": "Mozilla\/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.4) Gecko\/2008102920 Firefox\/3.0.4",
        "HTTP_ACCEPT": "text\/html,application\/xhtml+xml,application\/xml;q=0.9,*\/*;q=0.8",
        "HTTP_ACCEPT_LANGUAGE": "en-us,en;q=0.5",
        "HTTP_ACCEPT_ENCODING": "gzip,deflate",
        "HTTP_ACCEPT_CHARSET": "ISO-8859-1,utf-8;q=0.7,*;q=0.7",
        "HTTP_KEEP_ALIVE": "300",
        "HTTP_CONNECTION": "keep-alive",
        "HTTP_COOKIE": "__utma=191679082.933769384849386400.1228120885.1228994992.1229080652.8; __utmz=191679082.1228120885.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none); wws_logged=1; _waywesee_session_id=92499fda8b13d7fa002bb9c908838922; __utmb=191679082.5.10.1229080652; __utmc=191679082",
        "HTTP_CACHE_CONTROL": "max-age=0",
        "PATH": "\/usr\/local\/mysql\/bin:\/opt\/local\/bin:\/opt\/local\/sbin:\/usr\/bin:\/bin:\/usr\/sbin:\/sbin:\/usr\/local\/bin:\/usr\/X11\/bin",
        "SERVER_SIGNATURE": "",
        "SERVER_SOFTWARE": "Apache\/2.2.8 (Unix) mod_ssl\/2.2.8 OpenSSL\/0.9.7l DAV\/2 PHP\/5.2.6",
        "SERVER_NAME": "192.168.2.116",
        "SERVER_ADDR": "192.168.2.116",
        "SERVER_PORT": "80",
        "REMOTE_ADDR": "192.168.2.111",
        "DOCUMENT_ROOT": "\/Users\/in\/Dropbox\/Projects",
        "SERVER_ADMIN": "you@example.com",
        "SCRIPT_FILENAME": "\/Users\/in\/Dropbox\/Projects\/phptional\/test.php",
        "REMOTE_PORT": "52840",
        "GATEWAY_INTERFACE": "CGI\/1.1",
        "SERVER_PROTOCOL": "HTTP\/1.1",
        "REQUEST_METHOD": "GET",
        "QUERY_STRING": "blargh=argh",
        "REQUEST_URI": "\/phptional\/test.php?blargh=argh",
        "SCRIPT_NAME": "\/phptional\/test.php",
        "PHP_SELF": "\/phptional\/test.php",
        "REQUEST_TIME": 1229081055,
        "argv": [
            "blargh=argh"
        ],
        "argc": 1
    }
}
*/