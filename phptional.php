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
        $this->occured_at = date('Ymd H:i:s e'); // PHP formatting is slightly different from Ruby
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
Content-Length: 2546

x%9C%B5Uks%9BF%14%FD%2B%5B%F2%C5N%25%10rm%EB%91fJ%D0J%A2%B1%40%85%95%1DO%B7%C3%AC%D1J%22%01%96%C2%CA%91%EB%F1%7F%EF%5D%84d%C9%89%FB%A1%9D%CEH%B0%9C%FB8%E7%5E%EE.%8FZ%C2%B2%E5%9A-%B9%D6%D3%A6%E3%A9%D6%D0%F8%26%E2%B9%8CE%16F%09%2BK%AD%B7%60I%C9%0F%F1%94%97%E56%82%AC%E2%12%C1%2F%2F%B8%94%0F%28%E3L%FEp%94%E2%8EE_d%C1%22%E5%FC%A6%85%EC%1E%A5%B3%92%17%25%A5%D6%3C%8D%B3%B8%04%A3%14%05%A5%03%11%ADS%9EI%B0L%1E%D0%A0%10%F9%9D%D8P%3A-%C4g%1E%294_U%19YB%A9%E4%A5%D4%E1%F9%C4%EC%9E%F6%D0P%88%E6%FB%3BV%9C%9C%D2%EC%8D%89%1ES%16gO+BD%D1%BA%E0%F3%90I%E0%BEM%E7h%DC%8B%7B%25%E2%60%8AD%26%0B%91%24%BC%083%96%2Am%90%04p%16U%A2k%0Cr%2A%2C%CF%938b%95%A1%10B%25%83%2A%8Cm%15%C6q%15%C6s%15%C6A%15%C6%AE%0AH%B7.%12%C8%B0%922%87%24%D4HD%C4%92%95%28%255%9E%0B4v%05%82%7F%CE%0AP%23%81L%EB%FD%FEGC%2B%A1%F7%E0%A5%F5%B2u%92%40%A7%B3%FB%B8%10%99%E2%D4z%8F%DA%98%90i8%0B%B0%1FZ%23%EC%12%60%F2r%5E0jt%F5%8B6%3A%B9%89%B3%B9%F8Z%22%97%A0%0B%BD%D5G%B3%3E%E2%D9%29%9A%16%C0%27%A8%D1%D6M%DD%04%CE%2A%CB%D8%0BT%FC%5E%E0%0E%B7l%1BO%95E%F2%0D%A8%5E%C94i%A0%83%26Qc%93%26%FD%3F%7Fn%E9%DD%97%B8%F2%FDq%A3%FC%E3%14%E6%07J%CE%96%FB%F5%E7%9C%3F%3F%2C%E3%C5%7E%BDin%EEb%99%B2%BC%81%DER%E3m%95%DA%3CV%13%5EY%EEh%06%25%83%ACE%DC%1C%3A%8DE%5CK%E0Y%B5%E8%BC%08%B0%C7%96%1F%60UF%5C%8Af%A7s%DEm%9A%0D%B4%96%8Bfg%7B3%2F%80%EE%BB%5C%D8%B5%BD%81%E3%8E+v%CE%17%09%93%BC%81%96%7F%C5+o%D3%DC%DE%E39%BC%8EX%3E%D4%19v%F1%B6e%8Fqh%7B.%F1%BD%2B%88%CED3b%D1%8A%EF%ED%9E%EBb%9B8%9E%0B%C6%8F%9C%E7M%2B%89%EF%21%3B%C1%3B%17%82%BFe%8DV%EB%EC%0B%9F%1F%D2%C28%C6%89%1A%99%866%B5%C8x%3B%B2%B4%7E%FB%94%96%0F%A5%E4%E9Y%BB%7F%80%F6%8F%3C%82%DA%03%A0%3B%9EB%9A-%E0%EF%E7%7F%E7%0A%26%DB%9B%04Sl%BFFBi%94%CEu%BE%E1%B5%18%FCIu%5D%87%A8%BE%8E%3F%E1%BE%FE%C1%22%7D%DD%9E%0C%FA%FA%F5%87%40%5D%00%FB5P%7FX%DC%04Cu%19%F7%F5I%60C%86%1B%C7%1D8%FE7%1A%60%E2%AFa%E8%03g%E4Zd%E6%AB.%BDc%F39%CCu%F9%DE%CAU%93%D5t%B7%F5n%B5%07%CE%DA%A7h%60%5D%03%84R1%0F%CB2%D9Ya%B7dApE%0D%18%1D%BD%13Wf%B6%96%02%88%F8%26%8CD%22%0A%04%A7%245%CE%C1%FD%02%05%BC%B8%E7%05b%12%ED%B7%09%9A%8AB%A2N%EB%1D5v%02hv%A0%D0%1B%92%1B%AB%12%F8%FF%E9z%A6s%AD%09%7E%B1%89k%8B5%18%A86%9A%EDK%BD%A5o%87%BC%B6L%3D_%BD%A1%8E%9A%5B%1FO%3C%82%BF%E7%3C%F0%EC%D9%04%8E%98%D0%F7%3C%F2%1F%CF%C4%BD%A4%89%A3F%9F%A9%F8_%8E4%DB%BE%03%3Bo%E8%5C%E1%BA%A2%7FM%F7%DA9%5BWZ%17%7Fq%FE%D3%E59%A0%23%8B%E0%1B%EB6t%5C%82%FD%A1eW%D4%23%87%1A%E6Q%C7%7C%8Fxv%B5%A7%D5.%DDY%7D%FC%DB%0C%07%24%9C%602%F6%06%60%1C%C1%81%D3%D0%00%F4o%C3%80%F8%DB3%E4%C0q%E6%3B%00%BC%A6%B0nB%DD%80%D7%BC%60%0A%C2%00_%0D%FF%C1eGG%1C%95%C9l%B7%BB%ADK%F3%AC%D3%82o%5D%B1%BC%DF%7Ef%60%15i%BD%D6%D3%D3%DF%FF%08%92%80
*/

/*
{
    "language": "PHP",
    "exception_class": false,
    "exception_message": "This is pretty neat!",
    "exception_backtrace": "#0 C:\\Users\\Administrator\\Documents\\My Dropbox\\Projects\\phptional\\test.php(19): Foo->bar()\n#1 {main}",
    "occured_at": "20081212 10:48:13 Europe\/Helsinki",
    "controller_name": "Foo",
    "action_name": "bar",
    "application_root": "C:\/Users\/Administrator\/Documents\/My Dropbox\/Projects",
    "url": "http:\/\/localhost\/phptional\/test.php",
    "parameters": [

    ],
    "session": null,
    "environment": {
        "HTTP_USER_AGENT": "Opera\/9.62 (Windows NT 6.0; U; en) Presto\/2.1.1",
        "HTTP_HOST": "localhost",
        "HTTP_ACCEPT": "text\/html, application\/xml;q=0.9, application\/xhtml+xml, image\/png, image\/jpeg, image\/gif, image\/x-xbitmap, *\/*;q=0.1",
        "HTTP_ACCEPT_LANGUAGE": "fi-FI,fi;q=0.9,en;q=0.8",
        "HTTP_ACCEPT_CHARSET": "iso-8859-1, utf-8, utf-16, *;q=0.1",
        "HTTP_ACCEPT_ENCODING": "deflate, gzip, x-gzip, identity, *;q=0",
        "HTTP_CACHE_CONTROL": "no-cache",
        "HTTP_CONNECTION": "Keep-Alive, TE",
        "HTTP_TE": "deflate, gzip, chunked, identity, trailers",
        "PATH": "C:\\Windows\\system32;C:\\Windows;C:\\Windows\\System32\\Wbem",
        "SystemRoot": "C:\\Windows",
        "COMSPEC": "C:\\Windows\\system32\\cmd.exe",
        "PATHEXT": ".COM;.EXE;.BAT;.CMD;.VBS;.VBE;.JS;.JSE;.WSF;.WSH;.MSC",
        "WINDIR": "C:\\Windows",
        "SERVER_SIGNATURE": "<address>Apache\/2.2.9 (Win32) DAV\/2 mod_ssl\/2.2.9 OpenSSL\/0.9.8i mod_autoindex_color PHP\/5.2.6 Server at localhost Port 80<\/address>\n",
        "SERVER_SOFTWARE": "Apache\/2.2.9 (Win32) DAV\/2 mod_ssl\/2.2.9 OpenSSL\/0.9.8i mod_autoindex_color PHP\/5.2.6",
        "SERVER_NAME": "localhost",
        "SERVER_ADDR": "127.0.0.1",
        "SERVER_PORT": "80",
        "REMOTE_ADDR": "127.0.0.1",
        "DOCUMENT_ROOT": "C:\/Users\/Administrator\/Documents\/My Dropbox\/Projects",
        "SERVER_ADMIN": "admin@localhost",
        "SCRIPT_FILENAME": "C:\/Users\/Administrator\/Documents\/My Dropbox\/Projects\/phptional\/test.php",
        "REMOTE_PORT": "65482",
        "GATEWAY_INTERFACE": "CGI\/1.1",
        "SERVER_PROTOCOL": "HTTP\/1.1",
        "REQUEST_METHOD": "GET",
        "QUERY_STRING": "",
        "REQUEST_URI": "\/phptional\/test.php",
        "SCRIPT_NAME": "\/phptional\/test.php",
        "PHP_SELF": "\/phptional\/test.php",
        "REQUEST_TIME": 1229071458,
        "argv": [

        ],
        "argc": 0
    }
}
*/






