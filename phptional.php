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
        $this->occured_at = date('Ymd H:i:s T'); // PHP formatting is slightly different from Ruby
        $this->controller_name = $trace['class']; // Let's use the failed class
        $this->action_name = $trace['function']; // Let's use the failed function
        $this->application_root = $_SERVER['DOCUMENT_ROOT']; // Document root will do
        $this->url = $this->getCurrentUrl();
        $this->parameters = $_GET + $_POST;
        //$this->session = $_SESSION;
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
Content-Length: 2358

x%9C%9DUms%A2H%10%FE%2B%9C%FBE%F7%947%8D%82%96wG%E1%A8%D4%2A%B8%BC%24%B7ulQ%13%9CD.%C0%B00%E6Lr%F9%EF%DB%83%9A%E8nn%EF%EAJ%0A%86%E7%E9%7E%BA%7B%E8i%9F%1A%29%CEo%B7%F8%964%86%8D%D5%7C%D5h7%C8.%26%05Kh%1E%C5%29%AE%2A%C0%D1%119c3RU%7B%3F%7F%93T%02%5CEI%18%7B%10r%82%D9Og%A6%D78%BEc%25%8E%C1%F8%8F%C6%3BY%08%A5%A0%22e%15JI%1EJ%93%92%16%D7t%17J%AB%92%FEIb%06p%B1%A9%FDp%1AJ%8CTL%84%F7%A6%A2%B7%86%C2%94%D2%CE%2F%D7%B8l%B6%40%FF%9D%22%3Ce8%C9%9F%1B%9F%DB%0D%1A%C7%DB%92%AC%23%CC+%21U%965EUTA%E9%0Ee%B8z%02B%3Ex%C44g%25MSRF9%CEx%EA+%088%8E%EB4%0F%18%E8s%AC%28%D2%24%C65QR%CAe%7F%9C6%F8l%CB%14%CC6%8C%15%C3P%0A%25EWE%A5%AF%89pW%FAoV%F5%EBu%8A%CB%DB%CD%98%DF%C0%BF%C0%25%A4%C0+Fc%F8%D4%D8s+X%3F%9EaC%F3%FB%A4%A4yFr%C6%F9%B9%EF%AF%A2%B9%E3%F9%60r%16%0A%94j.%F0%90%1B%193ds%8B%25%7DL%D2%14%87%D2%85%28%0B%CD%AB%24_%D3%BF%AA%91%10%8C%84%C3Z%B0%7D%A1%2F%CA%23%81%E4%9D%C0%1B%09%E5%FDP%11uQ%16%7B-aF%E2%3B%1AJ%F5%BE%CA%AA%AE%CA%C24%29%C9%0D%2F%BF%CB-%8E%11%0D%D3D%2B%1E%8D%91%1D%0B%A5%0D%CB%D2%F6%C9F%86%D2%8EC%3F%EF%BE%83%B3t%F4e%2C%8Bz%FB%7D%28%BD%AF%97%DA%B9f%B40%ECY%00%C5%808%24%B8%AD%DA%24%AF%ED.%BE%B1C%B6%E9L%2C%7B%06v%B7%8FI%D1%5E%93%9B%143%F2%8D%9597%5C%0F%F1L-%CF%E9h%DA%85%DEQ%DA%5Bv%D3%D1j%D1A%7B%9F%C4%E0%E8%F6%01%21%F0%5DX%97%3C%7EW%96%8F%B8%E9%D862%7D%CB%B1%01%BF%23%A4%E8%E04%B9%27%AF%B4%F3%C1%E2.Q%B4e%19%1E%2B%BA%D2%1F%E8%B2%A6%8Az%B7%3B%E8%EB%5D%AD%A7%F5%E0%DE%EF%C9%B2%A8%A8%2At%AD%0C%C9%D4K%1D%16%3De%BF%D4%7B%3A%7C%E0%C1H%A8u%1EOtN%9D%E0%07l%5C%95%E3%E6%1A%3EO%CCZ%7F%F3%F78%3F%7F%CF%D6%E3fNs%D2z%C9%D20%E7%88%97%E2%BB%CE%02%92%CD%F0%AE%03%27%7B%CC%AB%5C%19%FE%BCn%FDmU%86RJc%DE%BE%D9C%F5%05%1E%D7I%0E%7DN%0B%F6B%7C%8FT%7B%A8%F6%DE%2FO%80%03yjs%26T%23%BF%2BJ%FD%0E%C9%40%3F_BK%7B%D6%CC6%FC%C0%E5%1B%7B%82%3AS%FF%CA%A8A%A3%C0%F1%86%40%BF%C2y%D0%84f%90%27%BB%96%90%D1uTU%E9%11u%0A%92%7B%DE%22%94%A0%EB%C4A%2AL%8CK%A0%04%98%81%FC%88%A8b%FFU%D96%96%E8%8D3v%60%8D%C9%C4%FDgv%E5%B8%BC%CB4%BE%97.Z%3A%3Ez%CBA%01v%E2%98%C1%12%CEj%E4%3A%8E%FF_%A6%CDK%FC%A5%C5%BB%EF%81n%7F%23%3B%9C%15%29%11c%9Aq%03%D3%B5%A0%D7%A7%D6%02%1DJ%F8%1F%83%F75%EFC%29%17%EA%40%E6%F9%CE%0C%1F%5D%19%9F%22%CB%F6%91%3B5L%AEo%CE%2C%18%7B%A2rR%BF%EB%F8%8EY%B7%15%EF%B5%23%EB%A2%8F%01%F2%FCh%89%FC%B93%01rV%0Fh%00%DDO%91%E7%BB%FB%03%7C%3E%1D%8F%3E%81k%D5%A5%FC%FB%3C%3Dl%C0K%F1o%97%07%9F%3C%F2%D0b%FA%03%93ch%DF%E2Jp%E6ty%A0%0F%B4%5E%9B%CF%E6%7B%FE%AFv%1A%F8s%0D%C7%60%F8%FC%FC%15XW%3C%B0
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
    "occured_at": "20081212 13:03:04 EET",
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
        "HTTP_COOKIE": "__utma=191679082.933769384849386400.1228120885.1228988541.1228994992.7; __utmz=191679082.1228120885.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none)",
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
        "REMOTE_PORT": "52701",
        "GATEWAY_INTERFACE": "CGI\/1.1",
        "SERVER_PROTOCOL": "HTTP\/1.1",
        "REQUEST_METHOD": "GET",
        "QUERY_STRING": "blargh=argh",
        "REQUEST_URI": "\/phptional\/test.php?blargh=argh",
        "SCRIPT_NAME": "\/phptional\/test.php",
        "PHP_SELF": "\/phptional\/test.php",
        "REQUEST_TIME": 1229079784,
        "argv": [
            "blargh=argh"
        ],
        "argc": 1
    }
}
*/