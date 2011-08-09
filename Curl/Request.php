<?php

namespace Curl;

use Nette;
use Nette\Utils\Strings;
use Nette\Http\Url as Uri;


// we'll need this
require_once __DIR__ . "/Response.php";
require_once __DIR__ . "/exceptions.php";


/**
 * An advanced cURL wrapper
 *
 * See the README for documentation/examples or http://php.net/curl for more information about the libcurl extension for PHP
 *
 * @author Sean Huber <shuber@huberry.com>
 * @author Filip Procházka <hosiplan@kdyby.org>
 *
 * @property-write callback $confirmRedirect
 * @property string $cookieFile
 * @property string $downloadFolder
 * @property string $downloadPath
 * @property-read string $error
 * @property-read array $info
 * @property string $fileProtocol
 * @property boolean $followRedirects
 * @property array $headers
 * @property-read string $info
 * @property-read string $method
 * @property array $options
 * @property-read array $proxies
 * @property boolean $returnTransfer
 * @property string $referer
 * @property string $userAgent
 * @property string $url
 */
class Request extends Nette\Object
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
	 * @var callback
	 */
	private $ConfirmRedirect;

	/**
	 * Used http method
	 * @var string
	 */
	private $Method;


	/**
	 * @var string
	 */
	private $DownloadFolder;


	/**
	 * The last downloaded file
	 * @var string
	 */
	private $DownloadPath;


	/**
	 * An associative array of headers to send along with requests
	 * @var array
	 */
	private $Headers = array();


	/**
	 * An associative array of CURLOPT options to send along with requests
	 * @var array
	 */
	private $Options = array();


	/**
	 * @var array
	 */
	private $Proxies = array();


	/**
	 * Available userAgents shortcuts
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
	 * @var string
	 */
	private $Error = '';


	/**
	 * Stores informations about last request
	 * @var string
	 */
	private $Info = '';


	/**
	 * Stores resource handle for the current cURL request
	 * @var resource
	 */
	private $RequestResource;


	/**
	 * @var url
	 */
	private $Url;


	/**
	 * Protocol name for file manipulation at server
	 * @var string
	 */
	private $FileProtocol = 'file';


	/**
	 * Maximum number of request cycles after follow location
	 * @var int
	 */
	private $MaxCycles = 15;


	/**
	 * List of status codes which should generate exception
	 * @var array
	 */
	static $badStatusCodes = array(
		400, // Bad Request
		401, // Unauthorized
		402, // Payment Required
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
	   );


	/**
	 * Initializes a Curl object
	 * Also sets the $userAgent to $_SERVER['HTTP_USER_AGENT'] if it exists, 'Curl/PHP '.PHP_VERSION.' (http://curl.kdyby.org/)' otherwise
	 * @param string $url
	 * @param array|\Nette\Config\Config $config
	 * @throws \Curl\CurlException
	 */
	public function __construct($url = NULL, $config = array())
	{
		if (!function_exists('curl_init')) {
			throw new CurlException("Curl extension is not loaded!");
		}

		if (is_string($url) && strlen($url)>0) {
			$this->url = $url;
		}

		$this->returnTransfer = TRUE;
		$this->userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Curl/PHP '.PHP_VERSION.' (http://curl.kdyby.org/)';

		$this->configure($config);
	}


	/**
	 * Configures Request
	 * @param array|\Nette\Config\Config $config
	 */
	public function configure($config)
	{
		if ($config instanceof Nette\Config\Config) {
			$config = $config->toArray();
		}

		if (!is_array($config)) {
			throw new \InvalidArgumentException("Argument \$config must be array, ".gettype($config)." given");
		}

		foreach ($config as $option => $value) {
			$option[0] = $option[0] & "\xDF";

			if ($option == 'CookieFile') {
				$this->setCookieFile($value);

			} elseif ($option == 'DownloadFolder') {
				$this->setDownloadFolder($value);

			} elseif ($option == 'Referer') {
				$this->setReferer($value);

			} elseif ($option == 'UserAgent') {
				$this->setUserAgent($value);

			} elseif ($option == 'FollowRedirects') {
				$this->setFollowRedirects($value);

			} elseif ($option == 'ReturnTransfer') {
				$this->setReturnTransfer($value);

			} elseif ($option == 'ConfirmRedirect') {
				$this->setConfirmRedirect($value);

			} elseif (is_array($value)) {
				foreach ((array)$value as $key => $set) {
					$this->{$option}[$key] = $set;
				}

			} else {
				$this->{$option} = $value;
			}
		}
	}


	/**
	 * @param string $ip
	 * @param int $port
	 * @param string $username
	 * @param string $password
	 * @param int $timeout
	 * @return \Curl\Request
	 */
	public function addProxy($ip, $port = 3128, $username = NULL, $password = NULL, $timeout = 15)
	{
		$this->Proxies[] = array(
			'ip' => $ip,
			'port' => $port,
			'user' => $username,
			'pass' => $password,
			'timeout' => $timeout
		);

		return $this;
	}


	/**
	 * @return string
	 */
	public function getProxies()
	{
		return $this->Proxies;
	}


	/**
	 * Sets option for request
	 * @param string $option
	 * @param string $value
	 * @return \Curl\Request
	 */
	public function setOption($option, $value)
	{
		$option = str_replace('CURLOPT_', '', strtoupper($option));
		$this->Options[$option] = $value;

		if ($option === 'MAXREDIRS') {
			$this->MaxCycles = $value;
		}

		return $this;
	}


	/**
	 * Returns specific option value
	 * @param string $option
	 * @return string
	 */
	public function getOption($option)
	{
		$option = str_replace('CURLOPT_', '', strtoupper($option));
		if (isset($this->Options[$option])) {
			return $this->Options[$option];
		}

		return NULL;
	}


	/**
	 * Sets options for request
	 * @param array $options
	 * @return \Curl\Request
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
	 * @return string
	 */
	public function getOptions()
	{
		$options = array();
		foreach ($this->Options as $key => $option) {
			$options[strtolower($key)] = $option;
		}

		return $options;
	}


	/**
	 * The maximum number of seconds to allow cURL functions to execute.
	 * @param int
	 * @return \Curl\Request
	 */
	public function setTimeOut($seconds = 15)
	{
		$this->setOption('timeout', (int) $seconds);
		return $this;
	}


	/**
	 * Return option value for CURLOPT_TIMEOUT
	 * @return int|NULL
	 */
	public function getTimeOut()
	{
		return $this->getOption('timeout');
	}


	/**
	 * The number of seconds to wait while trying to connect. Use 0 to wait indefinitely.
	 * @param int
	 * @return \Curl\Request
	 */
	public function setConnectionTimeOut($seconds = 15)
	{
		$this->setOption('connecttimeout', (int) $seconds);
		return $this;
	}


	/**
	 * Return option value for CURLOPT_CONNECTTIMEOUT
	 * @return int|NULL
	 */
	public function getConnectionTimeOut()
	{
		return $this->getOption('connecttimeout');
	}

	/**
	 * Set option value for CURLOPT_MAXREDIRS, if safe_mode or open_basedir is on set only $MaxCycles
	 * @param int $maxCycles
	 * @return \Curl\Request
	 */
	public function setMaxCycles($maxCycles)
	{
		if (!$this->safeMode() && ini_get('open_basedir') == "") {
			$this->setOption('MAXREDIRS', $maxCycles);
		}else
			$this->MaxCycles = $maxCycles;

		return $this;
	}

	/**
	 * Get option value for $MaxCycles
	 * @return int
	 */
	public function getMaxCycles(){
		return $this->MaxCycles;
	}

	/**
	 * @param string $header
	 * @param string $value
	 * @return \Curl\Request
	 */
	public function setHeader($header, $value = NULL)
	{
		if ($header !== NULL) {
			if ($value !== NULL) {
				$this->Headers[$header] = $value;

			} else {
				unset($this->Headers[$header]);
			}
		}

		return $this;
	}


	/**
	 * @param string $header
	 * @return string
	 */
	public function getHeader($header)
	{
		return $this->Headers[$header];
	}


	/**
	 * @param array $headers
	 * @return \Curl\Request
	 */
	public function setHeaders(array $headers)
	{
		foreach ($headers as $header => $value) {
			$this->setHeader($header, $value);
		}

		return $this;
	}


	/**
	 * @return array
	 */
	public function getHeaders()
	{
		return $this->Headers;
	}


	/**
	 * @param string $url
	 * @return \Curl\Request
	 */
	public function setReferer($url = NULL)
	{
		$this->setOption('referer', $url);

		return $this;
	}


	/**
	 * @return string
	 */
	public function getReferer()
	{
		return $this->getOption('referer');
	}


	/**
	 * Sets user agent for request
	 * @param string $userAgent
	 * @return \Curl\Request
	 */
	public function setUserAgent($userAgent = NULL)
	{
		$userAgent = isset(self::$userAgents[$userAgent]) ? self::$userAgents[$userAgent] : $userAgent;

		$this->setOption('useragent', $userAgent);

		return $this;
	}


	/**
	 * @return string
	 */
	public function getUserAgent()
	{
		return $this->getOption('useragent');
	}


	/**
	 * Sets whether follow redirects or not from request
	 * @param bool $follow
	 * @return \Curl\Request
	 */
	public function setFollowRedirects($follow = TRUE)
	{
		$this->setOption('followlocation', (bool)$follow);

		return $this;
	}


	/**
	 * Returns whether follow redirects or not from request
	 * @return bool
	 */
	public function getFollowRedirects()
	{
		return $this->getOption('followlocation');
	}


	/**
	 * Sets whether return result page or not
	 * @param bool $return
	 * @return \Curl\Request
	 */
	public function setReturnTransfer($return = TRUE)
	{
		$this->setOption('returntransfer', (bool)$return);

		return $this;
	}


	/**
	 * @return bool
	 */
	public function getReturnTransfer()
	{
		return $this->getOption('returntransfer');
	}


	/**
	 * @param string $url
	 * @return \Curl\Request
	 */
	public function setUrl($url = NULL)
	{
		$this->Url = $url;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getUrl()
	{
		return $this->Url;
	}


	/**
	 * Returns used http method
	 * @return string
	 */
	public function getMethod()
	{
		return $this->Method;
	}


	/**
	 * Sets path for last downloaded file
	 * @param string $path
	 * @return \Curl\Request
	 */
	public function setDownloadPath($path)
	{
		$this->DownloadPath = $path;

		return $this;
	}


	/**
	 * Returns path for last downloaded file
	 * @return string
	 */
	public function getDownloadPath()
	{
		return $this->DownloadPath;
	}


	/**
	 * @param string $protocol
	 * @return \Curl\Request
	 */
	public function setFileProtocol($protocol)
	{
		$this->FileProtocol = $protocol;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getFileProtocol()
	{
		return $this->FileProtocol;
	}


	/**
	 * @param string $cookieFile
	 * @throws \InvalidArgumentException
	 * @throws \InvalidStateException
	 * @return \Curl\Request
	 */
	public function setCookieFile($cookieFile)
	{
		if (!is_string($cookieFile) || $cookieFile === "") {
			throw new \InvalidArgumentException("Invalid argument \$cookieFile");
		}

		if (is_writable($cookieFile)) {
			$this->setOption('cookiefile', $cookieFile);
			$this->setOption('cookiejar', $cookieFile);

		} elseif (is_writable(dirname($cookieFile))) {
			if (($fp = @fopen($this->FileProtocol . '://' . $cookieFile, "wb")) === FALSE) {
				throw new \InvalidStateException("Write error for file '" . $cookieFile . "'");
			}

			fwrite($fp,"");
			fclose($fp);

			$this->setOption('cookiefile', $cookieFile);
			$this->setOption('cookiejar', $cookieFile);

		} else {
			throw new \InvalidStateException("You have to make writable '" . $cookieFile . "'");
		}

		return $this;
	}


	/**
	 * @return string
	 */
	public function getCookieFile()
	{
		return $this->getOption('cookiefile');
	}


	/**
	 * @param string $downloadFolder
	 * @throws \InvalidStateException
	 * @throws \InvalidArgumentException
	 * @return \Curl\Request
	 */
	public function setDownloadFolder($downloadFolder)
	{
		if (is_string($downloadFolder) && $downloadFolder === "") {
			throw new \InvalidArgumentException("Invalid argument \$downloadFolder");
		}

		@mkdir($downloadFolder); // may already exists
		@chmod($downloadFolder, "0754");

		if (is_dir($downloadFolder) && is_writable($downloadFolder)) {
			$this->DownloadFolder = $downloadFolder;

		} else {
			throw new \InvalidStateException("You have to create download folder '".$downloadFolder."' and make it writable!");
		}

		return $this;
	}


	/**
	 * @return string
	 */
	public function getDownloadFolder()
	{
		return $this->DownloadFolder;
	}


	/**
	 * Sets if all certificates are trusted in default
	 * @param bool $verify
	 * @return \Curl\Request
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
	 * @throws \InvalidStateException
	 * @return \Curl\Request
	 */
	public function setTrustedCertificate($certificate, $verifyhost = 2)
	{
		if (!in_array($verifyhost, range(0,2))) {
			throw new CurlException("Verifyhost must be 0, 1 or 2");

		}

		if (file_exists($certificate) && in_array($verifyhost, range(0,2))) {
			unset($this->Options['CAPATH']);

			$this->setOption('ssl_verifypeer', TRUE);
			$this->setOption('ssl_verifyhost', $verifyhost); // 2=secure
			$this->setOption('cainfo', $certificate);

		} else {
			throw new \InvalidStateException("Certificate ".$certificate." is not readable!");
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
	 * @throws \Curl\CurlException
	 * @return \Curl\Request
	 */
	public function setTrustedCertificatesDirectory($directory, $verifyhost = 2)
	{
		if (!in_array($verifyhost, range(0,2))) {
			throw new CurlException("Verifyhost must be 0, 1 or 2");

		}

		if (is_dir($certificate)) {
			unset($this->Options['CAINFO']);

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
	 * @return string
	 */
	public function getTrustedCertificatesPath()
	{
		if (isset($this->Options['CAPATH'])) {
			return $this->Options['CAPATH'];
		}

		if (isset($this->Options['CAINFO'])) {
			return $this->Options['CAINFO'];
		}
	}


	/**
	 * Returns the error string of the current request if one occurred
	 * @return string
	 */
	public function getError()
	{
		return $this->Error;
	}


	/**
	 * Returns the error string of the current request if one occurred
	 * @return array
	 */
	public function getInfo()
	{
		return $this->Info;
	}


	/**
	 * Makes a HTTP DELETE request to the specified $url with an optional array or string of $vars
	 * Returns a Response object if the request was successful, false otherwise
	 * @param string    [optional] $url
	 * @param array $post
	 * @return \Curl\Response
	 */
	public function delete($url = NULL, $post = array())
	{
		if (!empty($this->url)) {
			$post = $url;
			$url = $this->url;
		}

		return $this->sendRequest(self::DELETE, $url, $post);
	}


	/**
	 * Makes a HTTP GET request to the specified $url with an optional array or string of $vars
	 * Returns a Response object if the request was successful, false otherwise
	 * @param string    [optional] $url
	 * @param array $vars
	 * @return \Curl\Response
	 */
	public function get($url = NULL, $vars = array())
	{
		if (!empty($this->url)) {
			$vats = $url;
			$url = $this->url;
		}

		if (!empty($vars)) {
			$url .= (stripos($url, '?') !== FALSE) ? '&' : '?';
			$url .= (is_string($vars)) ? $vars : http_build_query($vars, '', '&');
		}

		return $this->sendRequest(self::GET, $url);
	}


	/**
	 * Makes a HTTP HEAD request to the specified $url with an optional array or string of $vars
	 * Returns a Response object if the request was successful, false otherwise
	 * @param string    [optional] $url
	 * @param array $post
	 * @return \Curl\Response
	 */
	public function head($url = NULL, $post = array())
	{
		if (!empty($this->url)) {
			$post = $url;
			$url = $this->url;
		}

		return $this->sendRequest(self::HEAD, $url, $post);
	}


	/**
	 * Makes a HTTP POST request to the specified $url with an optional array or string of $vars
	 * Returns a Response object if the request was successful, false otherwise
	 * @param string    [optional] $url
	 * @param array $post
	 * @throws \Curl\CurlException
	 * @return \Curl\Response
	 */
	public function post($url = NULL, $post = array())
	{
		if (!empty($this->url)) {
			$post = $url;
			$url = $this->url;
		}

		if (!$post || !is_array($post)){
			throw new CurlException("Empty post fields, use Request::get(\$url) instead.");
		}

		return $this->sendRequest(self::POST, $url, $post);
	}


	/**
	 * Makes a HTTP PUT request to the specified $url with an optional array or string of $vars
	 * Returns a Response object if the request was successful, false otherwise
	 * @param string    [optional] $url
	 * @param array $post
	 * @return \Curl\Response
	 */
	public function put($url = NULL, $post = array())
	{
		if (!empty($this->url)) {
			$post = $url;
			$url = $this->url;
		}

		return $this->sendRequest(self::PUT, $url, $post);
	}


	/**
	 * Downloads file from specified url and saves as fileName if isset or if not the name will be taken from url
	 * Returns a Response object if the request was successful, false otherwise
	 * @param string [optional] $url
	 * @param string $fileName
	 * @param array $post
	 * @throws \InvalidStateException
	 * @return \Curl\Response
	 */
	public function download($url = NULL, $fileName = NULL, $post = array())
	{
		if (!empty($this->url)) {
			$fileName = $url;
			$url = $this->url;
		}

		if (!is_string($fileName) OR $fileName === '') {
			$fileName = basename($url);
		}

		if (!is_dir($this->downloadFolder)) {
			throw new \InvalidStateException("You have to setup existing and writable folder using ".__CLASS__."::setDownloadFolder().");
		}

		$this->DownloadPath = $this->downloadFolder . '/' . basename($fileName);

		if (($fp = fopen($this->fileProtocol . '://' . $this->DownloadPath, "wb")) === FALSE) {
			throw new \InvalidStateException("Write error for file '{$this->DownloadPath}'");
		}

		$this->setOption('file', $fp);
		$this->setOption('binarytransfer', TRUE);

		$response = $this->sendRequest(self::DOWNLOAD, $url, $post);

		@fclose($fp);

		return $response;
	}


//	/* *
//	 * Uploads file
//	 * Returns a bool value whether an upload was succesful
//	 * @param string $file
//	 * @param string $url
//	 * @param string $username
//	 * @param string $password
//	 * @param int $timeout
//	 * @throws \Curl\CurlException
//	 * @return bool
//	 */
// 	public function ftpUpload($file, $url, $username = NULL, $password = NULL, $timeout = 300)
// 	{
// 		$file_name = basename($file);
// 		$login = NULL;
//
// 		if (is_string($username) && $username !== '' && is_string($password) && $password !== '') {
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
// 		$this->sendRequest(self::UPLOAD_FTP, $url, $vars);
//
// 		fclose($fp);
//
// 		return TRUE;
// 	}


	/**
	 * @param string $url
	 * @return resource
	 */
	protected function openRequest($url)
	{
		$this->closeRequest();

		return $this->RequestResource = curl_init($url);
	}


	/**
	 * Makes an HTTP request of the specified $method to a $url with an optional array or string of $vars
	 * Returns a Curl\Response object if the request was successful, false otherwise
	 * @param string $method
	 * @param string $url
	 * @param array $post
	 * @param int $cycles
	 * @throws \Curl\CurlException
	 * @throws \BadStatusException
	 * @throws \FailedRequestException
	 * @return \Curl\Response
	 */
	public function sendRequest($method, $url, $post = array(), $cycles = 1)
	{
		if ($cycles > $this->MaxCycles) {
				throw new CurlException("Redirect loop");
		}

		$this->Error = NULL;
		$used_proxies = 0;

		if (!is_string($url) && $url !== '') {
			throw new CurlException("Invalid URL: " . $url);
		}

		do {
			$requestResource = $this->openRequest($url);

			$this->tryProxy($used_proxies++);
			$this->setRequestOptions($requestResource, $method, $url, $post);
			$this->setRequestHeaders($requestResource);

			$response = $this->executeRequest($requestResource);
		} while (curl_errno($requestResource) == 6 && count($this->proxies) < $used_proxies);

		$this->closeRequest();

		if ($response) {
			$response = $this->createResponse($response);

		} else {
			throw new FailedRequestException($this->error, $this->info['http_code']);
		}

		if (!in_array($response->getHeader('Status-Code'), self::$badStatusCodes)) {
			$response_headers = $response->getHeaders();

			if (isset($response_headers['Location']) && $this->getFollowRedirects())  {
				$url = static::fixUrl($this->info['url'], $response_headers['Location']);

				if ($this->tryConfirmRedirect($response)) {
					$response = $this->sendRequest($this->getMethod(), (string)$url, $post, ++$cycles);
				}
			}

		} else {
			throw new BadStatusException('Response status: '.$response->getHeader('Status'), $this->info['http_code'], $response);
		}

		return $response;
	}


	/**
	 * @param resource $requestResource
	 * @return string
	 */
	protected function executeRequest($requestResource)
	{
		$response = curl_exec($requestResource);
		$this->Error = curl_errno($requestResource).' - '.curl_error($requestResource);
		$this->Info = curl_getinfo($requestResource);

		return $response;
	}


	/**
	 * @param string $response
	 * @return \Curl\Response
	 */
	protected function createResponse($response)
	{
		return $response = new Response($response, $this);
	}


	/**
	 * Closes the current request
	 */
	protected function closeRequest()
	{
		if (gettype($this->RequestResource) == 'resource' && get_resource_type($this->RequestResource) == 'curl') {
			@curl_close($this->RequestResource);

		} else {
			$this->RequestResource = NULL;
		}
	}


	/**
	 * @param string $from
	 * @param string $to
	 * @throws \InvalidStateException
	 * @return \Nette\Web\Uri
	 */
	public static function fixUrl($from, $to)
	{
		$url = new Uri($to);
		$lastUrl = new Uri($from);

		if (empty($url->scheme)) { // scheme
			if (empty($lastUrl->scheme)) {
				throw new \InvalidStateException("Missign URL scheme!");
			}

			$url->scheme = $lastUrl->scheme;
		}

		if (empty($url->host)) { // host
			if (empty($lastUrl->host)) {
				throw new \InvalidStateException("Missign URL host!");
			}

			$url->host = $lastUrl->host;
		}

		if (empty($url->path)) { // path
			$url->path = $lastUrl->path;
		}

		return $url;
	}


	/**
	 * Starts with 0
	 * @param int $used_proxies
	 */
	protected function tryProxy($used)
	{
		if (count($this->proxies) > $used) {
			//$this->setOption['HTTPPROXYTUNNEL'] = TRUE;
			$this->setOption('proxy', $this->proxies[$used]['ip'] . ':' . $this->proxies[$used]['port']);
			$this->setOption('proxyport', $this->proxies[$used]['port']);
			//$this->setOption('PROXYTYPE', CURLPROXY_HTTP);
			$this->setOption('timeout', $this->proxies[$used]['timeout']);

			if ($this->proxies[$used]['user'] !== NULL && $this->proxies[$used]['pass'] !== NULL) {
				$this->setOption('proxyuserpwd', $this->proxies[$used]['user'] . ':' . $this->proxies[$used]['pass']);
			}

		} else {
			unset($this->Options['PROXY'], $this->Options['PROXYPORT'], $this->Options['PROXYTYPE'], $this->Options['PROXYUSERPWD']);
		}
	}



	/**
	 * Formats and adds custom headers to the current request
	 */
	protected function setRequestHeaders($request)
	{
		$headers = array();
		foreach ($this->headers as $key => $value) {
			//fix HTTP_ACCEPT_CHARSET to Accept-Charset
			$key = Strings::replace($key, array(
					'~^HTTP_~i' => '',
					'~_~' => '-'
				));
			$key = Strings::replace($key, array(
					'~(?P<word>[a-z]+)~i',
				), function($match) {
					return ucfirst(strtolower(current($match)));
				});

			if ($key == 'Et') {
				$key = 'ET';
			}

			$headers[] = (!is_int($key) ? $key.': ' : '').$value;
		}

		if (count($this->headers) > 0) {
			curl_setopt($request, CURLOPT_HTTPHEADER, $headers);
		}

		return $headers;
	}


	/**
	 * Set the associated Curl options for a request method
	 * @param string $method
	 */
	protected function setRequestMethod($method)
	{
		$this->Method = strtoupper($method);

		switch ($this->method) {
			case self::HEAD:
				$this->setOption('nobody', TRUE);
				break;

			case self::GET:
			case self::DOWNLOAD:
				$this->setOption('httpget', TRUE);
				break;

			case self::POST:
				$this->setOption('post', TRUE);
				break;

//			case self::UPLOAD_FTP:
//				$this->setOption('upload', TRUE);
//				break;

			default:
				$this->setOption('customrequest', $this->Method);
				break;
		}
	}


	/**
	 * Sets the CURLOPT options for the current request
	 * @param string $url
	 */
	protected function setRequestOptions($requestResource, $method, $url, $post = array())
	{
		$this->setRequestMethod($method);

		$this->setOption('url', $url);

		if ($post && is_array($post)) {
			$post = http_build_query($post, '', '&');
			$this->setOption('postfields', $post);
		}

		// Prepend headers in response
		$this->setOption('header', TRUE); // this makes me literally cry sometimes

		// we shouldn't trust to all certificates but we have to!
		if ($this->getOption('ssl_verifypeer') === NULL) {
			$this->setOption('ssl_verifypeer', FALSE);
		}

		// fix:Sairon http://forum.nette.org/cs/profile.php?id=1844 thx
		if ($this->followRedirects === NULL && !$this->safeMode() && ini_get('open_basedir') == ""){
			$this->followRedirects = TRUE;
		}

		// Set all cURL options
		foreach ($this->Options as $option => $value) {
			if($option == "FOLLOWLOCATION" && ( $this->safeMode() || ini_get('open_basedir') != "" )){
				continue;
			}
			curl_setopt($requestResource, constant('CURLOPT_'.$option), $value);
		}
	}


	/**
	 * @param array|\Nette\Callback $callback
	 * @throws \InvalidArgumentException
	 * @return Request
	 */
	public function setConfirmRedirect($callback)
	{
		if (!is_callable($callback)) {
			throw new \InvalidArgumentException((is_array($callback) ? implode('::', $callback) : (string) $callback)." is not callable");
		}

		$this->ConfirmRedirect = $callback;

		return $this;
	}


	/**
	 * Asks for confirmation whether to manualy follow redirect
	 * @param \Curl\Response $response
	 * @return bool
	 */
	protected function tryConfirmRedirect(Response $response)
	{
		if(is_callable($this->ConfirmRedirect)) {
			return (bool) call_user_func($this->ConfirmRedirect, $response);

		}

		return TRUE;
	}

	protected function safeMode()
	{
		$status = strtolower(ini_get('safe_mode'));
		return $status === 'on' || $status === 'true' || $status === 'yes' || $status % 256;
	}

}
