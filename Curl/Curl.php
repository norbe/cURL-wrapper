<?php

namespace Curl;

use Nette;


// we'll need this
require_once __DIR__ . "/CurlResponse.php";


/**
 * An advanced Curl wrapper
 *
 * See the README for documentation/examples or http://php.net/curl for more information about the libcurl extension for PHP
 *
 * @package Curl
 * @author Sean Huber <shuber@huberry.com>
 * @author Filip Procházka <hosiplan@kdyby.org>
 */
class Curl extends Nette\Object
{

	/**#@+ Available types of requests */
	const GET = 'GET';
	const POST = 'POST';
	const PUT = 'PUT';
	const DELETE = 'DELETE';
	const HEAD = 'HEAD';
	const DOWNLOAD = 'DOWNLOAD';
	//const UPLOAD_FTP = 'UPLOAD_FTP';
	/**#@- */

	/**
	 * Used http method
	 *
	 * @var string
	 */
	private $method;


	/**
	 * The file to read and write cookies to for requests
	 *
	 * @var string
	 */
	private $cookieFile;


	/**
	 * The folder for saving downloaded files
	 *
	 * @var string
	 */
	protected $downloadFolder;


	/**
	 * The last downloaded file
	 *
	 * @var string
	 */
	private $downloadPath;


	/**
	 * Determines whether or not the requests should follow redirects
	 *
	 * @var boolean
	 */
	private $followRedirects = TRUE;


	/**
	 * Determines whether or not the requests has to return transfer
	 *
	 * @var boolean
	 */
	private $returnTransfer = TRUE;


	/**
	 * An associative array of headers to send along with requests
	 *
	 * @var array
	 */
	private $headers = array();


	/**
	 * An associative array of CURLOPT options to send along with requests
	 *
	 * @var array
	 */
	private $options = array();


	/**
	 * An associative array of available proxy servers
	 *
	 * @var array
	 */
	private $proxies = array();


	/**
	 * Variables defined on request
	 *
	 * @var string
	 */
	private $vars;


	/**
	 * The referer header to send along with requests
	 *
	 * @var string
	 */
	private $referer;


	/**
	 * The user agent to send along with requests
	 *
	 * @var string
	 */
	private $userAgent;


	/**
	 * Available userAgents
	 *
	 * @var array
	 */
	public static $userAgents = array(
		'FireFox3' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; pl; rv:1.9) Gecko/2008052906 Firefox/3.0',
		'GoogleBot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
		'IE7' => 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0)',
		'Netscape' => 'Mozilla/4.8 [en] (Windows NT 6.0; U)',
		'Opera' => 'Opera/9.25 (Windows NT 6.0; U; en)'
	);


	/**
	 * Stores an error string for the last request if one occurred
	 *
	 * @var string
	 */
	private $error = '';


	/**
	 * Stores informations about last request
	 *
	 * @var string
	 */
	private $info = '';


	/**
	 * Stores resource handle for the current CURL request
	 *
	 * @var resource
	 */
	private $request;


	/**
	 * Stores url for the current CURL request
	 *
	 * @var url
	 */
	private $url;


	/**
	 * Stores protocol name for file manipulation at server
	 *
	 * @var string
	 */
	private $fileProtocol = 'file';


	/**
	 * Maximum number of request cycles after follow location
	 *
	 * @var int
	 */
	static $maxCycles = 15;


	/**
	 * List of status codes which should generate exception
	 *
	 * @var array
	 */
	static $badStatusCodes = array(
		400, // Bad Request
		401, // Unauthorized
		403, // Forbidden
		404, // Not Found
		405, // Method Not Allowed
		406, // Not Acceptable ; TODO: workaround!
		407, // Proxy Authentication Required
		408, // Request Timeout
		409, // Conflict
		410, // Gone
		411, // Length Required
		412, // Precondition Failed
		413, // Request Entity Too Large
		414, // Request-URI Too Long
		415, // Unsupported Media Type
		416, // Requested Range Not Satisfiable
		417, // Expectation Failed
		418, // I'm a teapot (joke)
		422, // Unprocessable Entity (WebDAV) (RFC 4918)
		423, // Locked (WebDAV) (RFC 4918)
		424, // Failed Dependency (WebDAV) (RFC 4918)
		425, // Unordered Collection (RFC 3648)
		426, // Upgrade Required (RFC 2817)
		449, // Retry With
		450, // Blocked by Windows Parental Controls

		500, // Internal Server Error
		501, // Not Implemented
		502, // Bad Gateway
		503, // Service Unavailable
		504, // Gateway Timeout
		505, // HTTP Version Not Supported
		506, // Variant Also Negotiates (RFC 2295)
		507, // Insufficient Storage (WebDAV) (RFC 4918)[4]
		509, // Bandwidth Limit Exceeded (Apache bw/limited extension)
		510 // Not Extended (RFC 2774)
	   ) ;



	/**
	 * Initializes a Curl object
	 *
	 * <strike>Sets the $cookieFile to "curl_cookie.txt" in the current directory</strike>
	 * Also sets the $userAgent to $_SERVER['HTTP_USER_AGENT'] if it exists, 'Curl/PHP '.PHP_VERSION.' (http://curl.kdyby.org/)' otherwise
	 *
	 * @param string $url
	 */
	public function __construct($url = NULL)
	{
		if (!function_exists('curl_init')) {
			throw new CurlException("Curl extension is not loaded!");
		}

		if (is_string($url) && strlen($url)>0) {
			$this->setUrl($url);
		}

		// $this->cookieFile(dirname(__FILE__).DIRECTORY_SEPARATOR.'curl_cookie.txt');
		$this->setUserAgent(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Curl/PHP '.PHP_VERSION.' (http://curl.kdyby.org/)');
		$config = Nette\Environment::getConfig('curl');

		foreach ((array)$config as $option => $value) {
			if ($option == 'cookieFile') {
				$this->setCookieFile($value);

			} elseif ($option == 'downloadFolder') {
				$this->setDownloadFolder($value);

			} elseif ($option == 'referer') {
				$this->setReferer($value);

			} elseif ($option == 'userAgent') {
				$this->setUserAgent($value);

			} elseif ($option == 'followRedirects') {
				$this->setFollowRedirects($value);

			} elseif ($option == 'returnTransfer') {
				$this->setReturnTransfer($value);

			} elseif (is_array($this->{$option})) {
				foreach ((array)$value as $key => $set) {
					$this->{$option}[$key] = $set;
				}

			} else {
				$this->{$option} = $value;
			}
		}
	}



	/**
	 * Sets option for request
	 *
	 * @param string $ip
	 * @param int $port
	 * @param string $username
	 * @param string $password
	 * @param int $timeout
	 * @return Curl
	 */
	public function addProxy($ip, $port = 3128, $username = NULL, $password = NULL, $timeout = 15)
	{
		$this->proxies[] = array(
			'ip' => $ip,
			'port' => $port,
			'user' => $username,
			'pass' => $password,
			'timeout' => $timeout
		);

		return $this;
	}


	/**
	 * Returns list of avalaible proxies
	 *
	 * @return string
	 */
	public function getProxies()
	{
		return $this->proxies;
	}


	/**
	 * Sets option for request
	 *
	 * @param string $option
	 * @param string $value
	 * @return Curl
	 */
	public function setOption($option, $value)
	{
		$option = str_replace('CURLOPT_', '', strtoupper($option));
		$this->options[$option] = $value;

		if ($option === 'MAXREDIRS') {
			self::$maxCycles = $value;
		}

		return $this;
	}


	/**
	 * Returns specific option value
	 *
	 * @param string $option
	 * @return string
	 */
	public function getOption($option)
	{
		$option = str_replace('CURLOPT_', '', strtoupper($option));
		if (isset($this->options[$option])) {
			return $this->options[$option];
		}

		return NULL;
	}


	/**
	 * Sets options for request
	 *
	 * @param array $options
	 * @return Curl
	 */
	public function setOptions(array $options)
	{
		foreach ($options as $option => $value) {
			$this->setOption($option, $value);
		}

		return $this;
	}


	/**
	 * Returns all options
	 *
	 * @return string
	 */
	public function getOptions()
	{
		return $this->options;
	}
        
        /**
         * The maximum number of seconds to allow cURL functions to execute.
         * @param int
         * @return Curl
         */
        public function setTimeOut($seconds){
            $this->setOption('CURLOPT_TIMEOUT', (int) $seconds);
            return $this;
        }

        /**
         * Return option value for CURLOPT_TIMEOUT
         * @return int|NULL
         */
        public function getTimeOut(){
            return $this->getOption('CURLOPT_TIMEOUT');
        }

        /**
         * The number of seconds to wait while trying to connect. Use 0 to wait indefinitely.
         * @param int
         * @return Curl
         */
        public function setConnectionTimeOut($seconds){
            $this->setOption('CURLOPT_CONNECTTIMEOUT', (int) $seconds);
            return $this;
        }

        /**
         * Return option value for CURLOPT_CONNECTTIMEOUT
         * @return int|NULL
         */
        public function getConnectionTimeOut(){
             return $this->getOption('CURLOPT_CONNECTTIMEOUT');
        }

        /**
	 * Returns vars
	 *
	 * @return string
	 */
	public function getVars()
	{
		return $this->vars;
	}


	/**
	 * Sets header for request
	 *
	 * @param string $header
	 * @param string $value
	 * @return Curl
	 */
	public function setHeader($header, $value)
	{
		if ($header != NULL && $value != NULL) {
			$this->headers[$header] = $value;
		}

		return $this;
	}


	/**
	 * Returns specific header value
	 *
	 * @param string $header
	 * @return string
	 */
	public function getHeader($header)
	{
		return $this->headers[$header];
	}


	/**
	 * Sets array of headers for request
	 *
	 * @param array $headers
	 * @return Curl
	 */
	public function setHeaders(array $headers)
	{
		foreach ($headers as $header => $value) {
			$this->setHeader($header, $value);
		}

		return $this;
	}


	/**
	 * Returns all headers
	 *
	 * @return array
	 */
	public function getHeaders()
	{
		return $this->headers;
	}


	/**
	 * Sets referer for request
	 *
	 * @param string $url
	 * @return Curl
	 */
	public function setReferer($url = NULL)
	{
		$this->referer = $url;

		return $this;
	}


	/**
	 * Returns referer
	 *
	 * @return string
	 */
	public function getReferer()
	{
		return $this->referer;
	}


	/**
	 * Sets user agent for request
	 *
	 * @param string $userAgent
	 * @return Curl
	 */
	public function setUserAgent($userAgent = NULL)
	{
		$userAgent = (string)$userAgent;
		$this->userAgent = (isset(self::$userAgents[$userAgent]) ? self::$userAgents[$userAgent] : $userAgent);

		return $this;
	}


	/**
	 * Returns user agent
	 *
	 * @return string
	 */
	public function getUserAgent()
	{
		return $this->userAgent;
	}


	/**
	 * Sets whether follow redirects or not from request
	 *
	 * @param bool $follow
	 * @return Curl
	 */
	public function setFollowRedirects($follow = TRUE)
	{
		$this->followRedirects = (bool)$follow;

		return $this;
	}


	/**
	 * Returns whether follow redirects or not from request
	 *
	 * @return bool
	 */
	public function getFollowRedirects()
	{
		return $this->followRedirects;
	}


	/**
	 * Sets whether return result page or not
	 *
	 * @param bool $return
	 * @return Curl
	 */
	public function setReturnTransfer($return = TRUE)
	{
		$this->returnTransfer = (bool)$return;

		return $this;
	}


	/**
	 * Returns whether return result page or not
	 *
	 * @return bool
	 */
	public function getReturnTransfer()
	{
		return $this->returnTransfer;
	}


	/**
	 * Sets URL for request
	 *
	 * @param string $url
	 * @return Curl
	 */
	public function setUrl($url = NULL)
	{
		$this->url = $url;

		return $this;
	}


	/**
	 * Returns requested URL
	 *
	 * @return string
	 */
	public function getUrl()
	{
		return $this->url;
	}


	/**
	 * Returns used http method
	 *
	 * @return string
	 */
	public function getMethod()
	{
		return $this->method;
	}


	/**
	 * Returns path for last downloaded file
	 *
	 * @return string
	 */
	public function getDownloadPath()
	{
		return $this->downloadPath;
	}


	/**
	 * Returns used file protocol
	 *
	 * @return string
	 */
	public function getFileProtocol()
	{
		return $this->fileProtocol;
	}


	/**
	 * Sets cookie file for request
	 *
	 * @param string $cookieFile
	 * @throws CurlException
	 * @return Curl
	 */
	public function setCookieFile($cookieFile)
	{
		if (is_string($cookieFile) AND $cookieFile === "") {
			throw new CurlException("Invalid argument \$cookieFile");
		}

		if (is_writable($cookieFile)) {
			$this->cookieFile = $cookieFile;

		} elseif (is_writable(dirname($cookieFile))) {
			if (($fp = @fopen($this->fileProtocol . '://' . $cookieFile, "wb")) === FALSE) {
				throw new CurlException("Write error for file '" . $cookieFile . "'");
			}

			fwrite($fp,"");
			fclose($fp);

			$this->cookieFile = $cookieFile;

		} else {
			throw new CurlException("You have to make writable '" . $cookieFile . "'");
		}

		return $this;
	}


	/**
	 * Returns cookieFile
	 *
	 * @return string
	 */
	public function getCookieFile()
	{
		return $this->cookieFile;
	}


	/**
	 * Sets download folder for request
	 *
	 * @param string $downloadFolder
	 * @throws CurlException
	 * @return Curl
	 */
	public function setDownloadFolder($downloadFolder)
	{
		if (is_string($downloadFolder) AND $downloadFolder === "") {
			throw new CurlException("Invalid argument \$downloadFolder");
		}

		@mkdir($downloadFolder); // may already exists
		@chmod($downloadFolder, "0754");

		if (is_dir($downloadFolder) AND is_writable($downloadFolder)) {
			$this->downloadFolder = $downloadFolder;

		} else {
			throw new CurlException("You have to create download folder '".$downloadFolder."' and make it writable!");
		}

		return $this;
	}


	/**
	 * Returns downloadFolder
	 *
	 * @return string
	 */
	public function getDownloadFolder()
	{
		return $this->downloadFolder;
	}


	/**
	 * Sets if all certificates are trusted in default
	 *
	 * @param bool $verify
	 * @return Curl
	 */
	public function setCertificationVerify($verify = TRUE)
	{
		$this->setOption('ssl_verifypeer', (bool)$verify);

		return $this;
	}


	/**
	 * Adds path to trusted certificate and unsets directory with certificates if set
	 * WARNING: Overwrites the last given vertificate
	 *
	 * CURLOPT_SSL_VERIFYHOST:
	 *	0: Don’t check the common name (CN) attribute
	 *	1: Check that the common name attribute at least exists
	 *	2: Check that the common name exists and that it matches the host name of the server
	 *
	 * @param string $certificate
	 * @param int $verifyhost
	 * @throws CurlException
	 * @return Curl
	 */
	public function setTrustedCertificate($certificate, $verifyhost = 2)
	{
		if (!in_array($verifyhost, range(0,2))) {
			throw new CurlException("Verifyhost must be 0, 1 or 2");

		}

		if (file_exists($certificate) AND in_array($verifyhost, range(0,2))) {
			unset($this->options['CAPATH']);

			$this->setOption('ssl_verifypeer', TRUE);
			$this->setOption('ssl_verifyhost', $verifyhost); // 2=secure
			$this->setOption('cainfo', $certificate);

		} else {
			throw new CurlException("Certificate ".$certificate." is not readable!");
		}

		return $this;
	}


	/**
	 * Adds path to directory which contains trusted certificate and unsets single certificate if set
	 * WARNING: Overwrites the last one
	 *
	 * CURLOPT_SSL_VERIFYHOST:
	 *	0: Don’t check the common name (CN) attribute
	 *	1: Check that the common name attribute at least exists
	 *	2: Check that the common name exists and that it matches the host name of the server
	 *
	 * @param string $directory
	 * @param string $verifyhost
	 * @throws CurlException
	 * @return Curl
	 */
	public function setTrustedCertificatesDirectory($directory, $verifyhost = 2)
	{
		if (!in_array($verifyhost, range(0,2))) {
			throw new CurlException("Verifyhost must be 0, 1 or 2");

		}

		if (is_dir($certificate)) {
			unset($this->options['cainfo']);

			$this->setOption('ssl_verifypeer', TRUE);
			$this->setOption('ssl_verifyhost', $verifyhost); // 2=secure
			$this->setOption('capath', $directory);

		} else {
			throw new CurlException("Directory ".$directory." is not readable!");
		}

		return $this;
	}


	/**
	 * Returns path to trusted certificate or certificates directory
	 *
	 * @return string
	 */
	public function getTrustedCertificatesPath()
	{
		if (isset($this->options['capath'])) {
			return $this->options['capath'];
		}

		if (isset($this->options['cainfo'])) {
			return $this->options['cainfo'];
		}
	}


	/**
	 * Returns the error string of the current request if one occurred
	 *
	 * @return string
	 */
	public function getError()
	{
		return $this->error;
	}


	/**
	 * Makes a HTTP DELETE request to the specified $url with an optional array or string of $vars
	 *
	 * Returns a CurlResponse object if the request was successful, false otherwise
	 *
	 * @param string    [optional] $url
	 * @param array $vars
	 * @return CurlResponse
	 */
	public function delete($url = NULL, $vars = array())
	{
		if (!empty($this->url)) {
			$vars = $url;
			$url = $this->getUrl();
		}

		return $this->request(self::DELETE, $url, $vars);
	}


	/**
	 * Makes a HTTP GET request to the specified $url with an optional array or string of $vars
	 *
	 * Returns a CurlResponse object if the request was successful, false otherwise
	 *
	 * @param string    [optional] $url
	 * @param array $vars
	 * @return CurlResponse
	 */
	public function get($url = NULL, $vars = array())
	{
		if (!empty($this->url)) {
			$vars = $url;
			$url = $this->getUrl();
		}

		if (!empty($vars)) {
			$url .= (stripos($url, '?') !== FALSE) ? '&' : '?';
			$url .= (is_string($vars)) ? $vars : http_build_query($vars, '', '&');
		}

		return $this->request(self::GET, $url);
	}


	/**
	 * Makes a HTTP HEAD request to the specified $url with an optional array or string of $vars
	 *
	 * Returns a CurlResponse object if the request was successful, false otherwise
	 *
	 * @param string    [optional] $url
	 * @param array $vars
	 * @return CurlResponse
	 */
	public function head($url = NULL, $vars = array())
	{
		if (!empty($this->url)) {
			$vars = $url;
			$url = $this->getUrl();
		}

		return $this->request(self::HEAD, $url, $vars);
	}


	/**
	 * Makes a HTTP POST request to the specified $url with an optional array or string of $vars
	 *
	 * @param string    [optional] $url
	 * @param array $vars
	 * @return CurlResponse
	 */
	public function post($url = NULL, $vars = array())
	{
		if (!empty($this->url)) {
			$vars = $url;
			$url = $this->getUrl();
		}

		return $this->request(self::POST, $url, $vars);
	}


	/**
	 * Makes a HTTP PUT request to the specified $url with an optional array or string of $vars
	 *
	 * Returns a CurlResponse object if the request was successful, false otherwise
	 *
	 * @param string    [optional] $url
	 * @param array $vars
	 * @return CurlResponse
	 */
	public function put($url = NULL, $vars = array())
	{
		if (!empty($this->url)) {
			$vars = $url;
			$url = $this->getUrl();
		}

		return $this->request(self::PUT, $url, $vars);
	}


	/**
	 * Downloads file from specified url and saves as fileName if isset or if not the name will be taken from url
	 *
	 * Returns a boolean value whatever a download was succesful and file was downloaded to $this->downloadFolder.$fileName
	 *
	 * @param string [optional] $url
	 * @param string $fileName
	 * @param array $vars
	 * @throws CurlException
	 * @return CurlResponse
	 */
	public function download($url = NULL, $fileName = NULL, $vars = array())
	{
		if (!empty($this->url)) {
			$fileName = $url;
			$url = $this->getUrl();
		}

		if (!is_string($fileName) OR $fileName === '') {
			$fileName = basename($url);
		}

		if (!is_dir($this->downloadFolder)) {
			throw new CurlException("You have to setup existing and writable folder using ".__CLASS__."::setDownloadFolder().");
		}

		$this->downloadPath = $this->downloadFolder . '/' . basename($fileName);

		if (($fp = fopen($this->fileProtocol . '://' . $this->downloadPath, "wb")) === FALSE) {
			throw new CurlException("Write error for file '{$this->downloadPath}'");
		}

		$this->setOption('file', $fp);
		$this->setOption('binarytransfer', TRUE);

		try{
			$response = $this->request(self::DOWNLOAD, $url, $vars);

		} catch (CurlException $e) {
			throw new CurlException("Error during file download: ".$e->getMessage());
		}

		@fclose($fp);

		return $response;
	}


	/* *
	 * Uploads file
	 *
	 * Returns a bool value whether an upload was succesful
	 *
	 * @param string $file
	 * @param string $url
	 * @param string $username
	 * @param string $password
	 * @param int $timeout
	 * @throws CurlException
	 * @return bool
	 */
// 	public function ftpUpload($file, $url, $username = NULL, $password = NULL, $timeout = 300)
// 	{
// 		$file_name = basename($file);
// 		$login = NULL;
//
// 		if (is_string($username) AND $username !== '' AND is_string($password) AND $password !== '') {
// 			$login = $username . ':' . $password . '@';
// 		}
//
// 		$dest = "ftp://" . $login . $url . '/' . $file_name;
//
// 		if (($fp = @fopen($this->fileProtocol . '://' . $file, "rb")) === FALSE) {
// 			throw new FileNotFoundException("Read error for file '{$file}'");
// 		}
//
// 		$this->setOption('URL', $dest);
//
// 		$this->setOption('TIMEOUT', $timeout);
// 		//curl_setopt($ch, CURLE_OPERATION_TIMEOUTED, 300);
// 		$this->setOption('INFILE', $fp);
// 		$this->setOption('INFILESIZE', filesize($src));
//
// 		$this->request(self::UPLOAD_FTP, $url, $vars);
//
// 		fclose($fp);
//
// 		return TRUE;
// 	}


	/**
	 * Makes an HTTP request of the specified $method to a $url with an optional array or string of $vars
	 *
	 * Returns a CurlResponse object if the request was successful, false otherwise
	 *
	 * @param string $method
	 * @param string $url
	 * @param array $vars
	 * @param int $cycles
	 * @throws CurlException
	 * @return CurlResponse
	 */
	public function request($method, $url, $vars = array(), $cycles = 1)
	{
		if ($cycles > self::$maxCycles) {
				throw new CurlException("Redirect loop");
		}

		$this->error = NULL;
		$used_proxies = 0;

		if (is_array($vars)) {
			$this->vars = http_build_query($vars, '', '&');
		}

		if (!is_string($url) AND $url !== '') {
			throw new CurlException("Invalid URL: " . $url);
		}

		do{
			$this->closeRequest();

			$this->request = curl_init();

			if (count($this->proxies) > $used_proxies) {
				//$this->setOption['HTTPPROXYTUNNEL'] = TRUE;
				$this->setOption('proxy', $this->proxies[$used_proxies]['ip'] . ':' . $this->proxies[$used_proxies]['port']);
				$this->setOption('proxyport', $this->proxies[$used_proxies]['port']);
				//$this->setOption('PROXYTYPE', CURLPROXY_HTTP);
				$this->setOption('timeout', $this->proxies[$used_proxies]['timeout']);

				if ($this->proxies[$used_proxies]['user'] !== NULL AND $this->proxies[$used_proxies]['pass'] !== NULL) {
					$this->setOption('proxyuserpwd', $this->proxies[$used_proxies]['user'] . ':' . $this->proxies[$used_proxies]['pass']);
				}

				$used_proxies++;

			} else {
				unset($this->options['PROXY'], $this->options['PROXYPORT'], $this->options['PROXYTYPE'], $this->options['PROXYUSERPWD']);
			}

			$this->setRequestMethod = $method;
			$this->setRequestOptions($url);
			$this->setRequestHeaders();

			$response = curl_exec($this->request);
			$this->error = curl_errno($this->request).' - '.curl_error($this->request);
			$this->info = curl_getinfo($this->request);

		} while (curl_errno($this->request) == 6 AND count($this->proxies) < $used_proxies) ;

		$this->closeRequest();

		if ($response) {
			$response = new CurlResponse($response, $this);

		} else {
//			if ($this->info['http_code'] == 400) {
//				throw new CurlException('Bad request');
//
//			} elseif ($this->info['http_code'] == 401) {
//				throw new CurlException('Permission Denied');
//
//			} else {
			throw new CurlException($this->error);
//			}
		}

		if (!in_array($response->getHeader('Status-Code'), self::$badStatusCodes)) {

			$response_headers = $response->getHeaders();

			if (isset($response_headers['Location']) AND $this->getFollowRedirects())  {
				$url = new Nette\Web\Uri($response_headers['Location']);
				$lastUrl = new Nette\Web\Uri($this->info['url']);

				if (empty($url->scheme)) { // scheme
					if (empty($lastUrl->scheme)) {
						throw new CurlException("Missign URL scheme!");
					}

					$url->scheme = $lastUrl->scheme;
				}

				if (empty($url->host)) { // host
					if (empty($lastUrl->host)) {
						throw new CurlException("Missign URL host!");
					}

					$url->host = $lastUrl->host;
				}

				if (empty($url->path)) { // path
					$url->path = $lastUrl->path;
				}

				$response = $this->request($this->getMethod(), (string)$url, array(), ++$cycles);
			}

		} else {
			throw new CurlException('Response status: '.$response->getHeader('Status'), NULL, $response);
		}

		return $response;
	}


	/**
	 * Closes the current request
	 */
	private function closeRequest()
	{
		if (gettype($this->request) == 'resource' AND get_resource_type($this->request) == 'curl') {
			curl_close($this->request);

		} else {
			$this->request = NULL;
		}
	}


	/**
	 * Formats and adds custom headers to the current request
	 */
	private function setRequestHeaders()
	{
		$headers = array();
		foreach ($this->getHeaders() as $key => $value) {
			$headers[] = (!is_int($key) ? $key.': ' : '').$value;
		}

		if (count($this->headers) > 0) {
			curl_setopt($this->request, CURLOPT_HTTPHEADER, $headers);
		}
	}


	/**
	 * Set the associated Curl options for a request method
	 *
	 * @param string $method
	 */
	private function setRequestMethod($method)
	{
		$this->method = strtoupper($method);

		switch ($this->getMethod()) {
			case self::HEAD:
				curl_setopt($this->request, CURLOPT_NOBODY, TRUE);
				break;

			case self::GET:
			case self::DOWNLOAD:
				curl_setopt($this->request, CURLOPT_HTTPGET, TRUE);
				break;

			case self::POST:
				curl_setopt($this->request, CURLOPT_POST, TRUE);
				break;

			case self::UPLOAD_FTP:
				curl_setopt($ch, CURLOPT_UPLOAD, TRUE);
				break;

			default:
				curl_setopt($this->request, CURLOPT_CUSTOMREQUEST, $this->getMethod());
				break;
		}
	}


	/**
	 * Sets the CURLOPT options for the current request
	 *
	 * @param string $url
	 */
	private function setRequestOptions($url)
	{
		$this->setOption('url', $url);

		if (!empty($this->vars)) {
			$this->setOption('postfields', $this->getVars());
		}

		// Set some default CURL options
		$this->setOption('header', TRUE); // this makes me literally cry sometimes
		$this->setOption('useragent', $this->getUserAgent());

		// we shouldn't trust to all certificates but we have to!
		if (!isset($this->options['SSL_VERIFYPEER'])) {
			$this->setOption('ssl_verifypeer', FALSE);
		}

		if ($this->getReturnTransfer()) {
			$this->setOption('returntransfer', TRUE);
		}

		if ($this->getCookieFile()) {
			$this->setOption('cookiefile', $this->getCookieFile());
			$this->setOption('cookiejar', $this->getCookieFile());
		}

		// fix:Sairon http://forum.nette.org/cs/profile.php?id=1844 thx
		if ($this->getFollowRedirects() AND strtolower(ini_get('safe_mode')) !== 'on' AND ini_get('open_basedir') == ""){
			$this->setOption('followlocation', TRUE);
		}

		if ($this->getReferer()) {
			$this->setOption('referer', $this->getReferer());
		}

		// Set all cURL options
		foreach ($this->options as $option => $value) {
			curl_setopt($this->request, constant('CURLOPT_'.$option), $value);
		}
	}


}



/**
 * Exception thrown by Curl wrapper
 *
 * @package Curl
 * @author Filip Procházka <hosiplan@kdyby.org>
 *
 * @property-read \Curl\CurlResponse $response
 */
class CurlException extends \Exception
{
	/** @var \Curl\CurlResponse */
	var $response;


	public function __construct($message, $code = 0, CurlResponse $response = NULL)
	{
		parent::__construct($message, $code);

		$this->response = $response;
	}



	/**
	 * @return \Curl\CurlResponse
	 */
	public function getResponse()
	{
		return $this->response;
	}

}