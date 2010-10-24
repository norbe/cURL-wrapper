<?php

namespace Curl;

use Nette;
use Nette\String;


/**
 * Parses the response from a cURL request into an object containing
 * the response body and an associative array of headers
 *
 * <code>
 * $response = new Curl\Response(curl_exec($curl_handle));
 * echo $response->body;
 * echo $response->headers['Status'];
 * </code>
 *
 * @package cURL
 * @author Sean Huber <shuber@huberry.com>
 * @author Filip Procházka <hosiplan@kdyby.org>
 *
 * @property-read \Curl\Request $request
 * @property-read string $body
 * @property-read string $response
 * @property-read string $headers
 * @property-read string $query
 */
class Response extends Nette\Object
{

	/**#@+ regexp's for parsing */
	const HEADER_REGEXP = '~(?P<header>.*?)\:\s(?P<value>.*)~';
	const VERSION_AND_STATUS = '~HTTP/(?P<version>\d\.\d)\s(?P<code>\d\d\d)\s(?P<status>.*)~';
	const CONTENT_TYPE = '~^(?P<type>[^;]+);[\t ]*charset=(?P<charset>.+)$~i';
	/**#@- */

	/**
	 * @var string
	 */
	private $Body = '';


	/**
	 * @var array
	 */
	private $Headers = array();


	/**
	 * @var \Curl\Request
	 */
	private $Request;


	/**
	 * Contains resource for last downloaded file
	 * @var resource
	 */
	private $DownloadedFile;


	/**
	 * Accepts the result of a curl request as a string
	 * @param string $response
	 * @param \Curl\Request $request
	 */
	public function __construct($response, Request $request = NULL)
	{
		$this->Request = $request;

		if ($this->request->method === Request::DOWNLOAD) {
			$this->parseFile();

		} else {
			# Extract headers from response
			$headers = String::split(substr($response, 0, $this->request->info['header_size']), "~[\n\r]+~", PREG_SPLIT_NO_EMPTY);
			$this->Headers = array_merge($this->Headers, static::parseHeaders($headers));

			# Remove headers from the response body
			$this->Body = substr($response, $this->request->info['header_size']);

			$this->Headers = static::parseHeaders($headers);
		}
	}


	/**
	 * Parses headers from given list
	 * @param array $headers
	 * @return array
	 */
	public static function parseHeaders($headers)
	{
		$found = array();

		# Extract the version and status from the first header
		$version_and_status = array_shift($headers);
		$matches = String::match($version_and_status, self::VERSION_AND_STATUS);
		if (count($matches) > 0) {
			$found['Http-Version'] = $matches['version'];
			$found['Status-Code'] = $matches['code'];
			$found['Status'] = $matches['code'].' '.$matches['status'];
		}

		# Convert headers into an associative array
		foreach ($headers as $header) {
			$matches = String::match($header, self::HEADER_REGEXP);
			$found[$matches['header']] = $matches['value'];
		}

		return $found;
	}


	/**
	 * Fix downloaded file
	 * @throws \Curl\CurlException
	 * @throws \InvalidStateException
	 * @return \Curl\Response
	 */
	private function parseFile()
	{
		if ($this->request->method === Request::DOWNLOAD) {
			$path_p = $this->request->downloadPath;
			@fclose($this->request->getOption('file')); // internationaly @

			if (($fp = @fopen($this->request->fileProtocol . '://' . $path_p, "rb")) === FALSE) { // internationaly @
				throw new \InvalidStateException("Fopen error for file '{$path_p}'");
			}

			$headers = String::split(@fread($fp, $this->request->info['header_size']), "~[\n\r]+~", PREG_SPLIT_NO_EMPTY); // internationaly @
			$this->Headers = array_merge($this->Headers, static::parseHeaders($headers));

			@fseek($fp, $this->request->info['header_size']); // internationaly @

			$path_t = $this->request->downloadPath . '.tmp';

			if (($ft = @fopen($this->request->fileProtocol . '://' . $path_t, "wb")) === FALSE) { // internationaly @
				throw new \InvalidStateException("Write error for file '{$path_t}' ");
			}

			while (!feof($fp)) {
				$row = fgets($fp, 4096);
				fwrite($ft, $row);
			}

			@fclose($fp); // internationaly @
			@fclose($ft); // internationaly @

			if (!@unlink($this->request->fileProtocol . '://' . $path_p)) { // internationaly @
				throw new \InvalidStateException("Error while deleting file {$path_p} ");
			}

			if (!@rename($path_t, $path_p)) { // internationaly @
				throw new \InvalidStateException("Error while renaming file '{$path_t}' to '".basename($path_p)."'. ");
			}

			chmod($path_p, 0755);

			if (!$this->Headers) {
				throw new CurlException("Headers parsing failed", NULL, $this);
			}
		}

		return $this;
	}


	/**
	 * Returns the response body
	 *
	 * <code>
	 * $curl = new Curl\Request;
	 * $response = $curl->get('google.com');
	 * echo $response;  # => echo $response->body;
	 * </code>
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->body;
	}


	/**
	 * Returns the response body
	 * @return string
	 */
	public function getBody()
	{
		if ($this->Body === NULL && $this->Request->downloadPath) {
			return file_get_contents($this->Request->downloadPath);
		}

		return $this->Body;
	}


	/**
	 * Alias for getBody
	 * @return string
	 */
	public function getResponse()
	{
		return $this->body;
	}


	/**
	 * @return \phpQuery\phpQuery
	 */
	public function getQuery()
	{
		$contentType = NULL;
		if (isset($this->request->info['content_type'])) {
			$contentType = static::getContentType($this->request->info['content_type'], $contentType);
		}

		return \phpQuery\phpQuery::newDocument($this->body, $contentType);
	}


	/**
	 * @param string $charset
	 * @return \Curl\CurlResponse
	 */
	public function convert($to = "UTF-8", $from = NULL)
	{
		if ($from === NULL) {
			$charset = $this->query['head > meta[http-equiv=Content-Type]']->attr('content');
			$charset = $charset ?: $this->query['head > meta[http-equiv=content-type]']->attr('content');
			$charset = $charset ?: $this->headers['Content-Type'];

			$from = static::getCharset($charset);
		}

		$from = String::upper($from);
		$to = String::upper($to);

		if ($from != $to && $from && $to) {
			if ($body = @iconv($from, $to, $this->body)) {
				$this->Body = ltrim($body);

			} else {
				throw new CurlException("Charset conversion from $from to $to failed");
			}
		}

		$this->Body = self::fixContentTypeMeta($this->body);

		return $this;
	}


	/**
	 *
	 * @param string $document
	 * @param string $charset
	 * @return string
	 */
	public static function fixContentTypeMeta($document, $charset = 'utf-8')
	{
		return String::replace($document, // hack for DOMDocument
			'~<meta([^>]+http-equiv\\s*=\\s*)["\']*Content-Type["\']*([^>]+content\\s*=\\s*["\'][^;]+;)[\t ]*charset=[^"\']+(["\'][^>]*)>~i',
			'<meta\\1"Content-Type"\\2 charset=' . $charset . '\\3>');
	}


	/**
	 * @param string $header
	 * @return string
	 */
	public static function getCharset($header, $default = NULL)
	{
		$match = String::match($header, self::CONTENT_TYPE);
		return isset($match['charset']) ? $match['charset'] : $default;
	}


	/**
	 * @param string $header
	 * @return string
	 */
	public static function getContentType($header, $default = NULL)
	{
		$match = String::match($header, self::CONTENT_TYPE);
		return isset($match['type']) ? $match['type'] : $default;
	}


	/**
	 * Returns the response headers
	 * @return array
	 */
	public function getHeaders()
	{
		return $this->Headers;
	}


	/**
	 * Returns specified header
	 * @param string $header
	 * @return string
	 */
	public function getHeader($header)
	{
		if (isset($this->Headers[$header])) {
			return $this->Headers[$header];

		}

		return NULL;
	}


	/**
	 * Returns resource to downloaded file
	 * @throws \InvalidStateException
	 * @return resource
	 */
	public function openFile()
	{
		$path = $this->request->downloadPath;
		if (($this->DownloadedFile = fopen($this->request->fileProtocol . '://' . $path, "r")) === FALSE) {
			throw new \InvalidStateException("Read error for file '{$path}'");
		}

		return $this->DownloadedFile;
	}


	/**
	 * @return bool
	 */
	public function closeFile()
	{
		return @fclose($this->DownloadedFile);
	}



	/**
	 * Move uploaded file to new location.
	 * @param  string
	 * @throws \InvalidStateException
	 * @return \Curl\Response
	 */
	public function moveFile($dest)
	{
		$dir = dirname($dest);
		$file = $this->request->downloadPath;

		if (@mkdir($dir, 0755, TRUE)) { // @ - $dir may already exist
			chmod($dir, 0755);
		}

		if (!@rename($this->request->fileProtocol . '://' . $file, $dest)) {
			throw new \InvalidStateException("Unable to move uploaded file '$file' to '$dest'.");
		}
		chmod($dest, 0644);

		$this->request->downloadPath = $file;

		return $this;
	}


	/**
	 * Returns the Curl request object
	 * @return \Curl\Request
	 */
	public function getRequest()
	{
		return $this->Request;
	}


	/**
	 * @return string
	 */
	public function errorDump()
	{
		$request = $this->Request;
		$response = $this;

		ob_start();
		require_once "panel.phtml";
		return ob_get_clean();
	}


}