<?php

namespace Curl;

use Nette;
use Nette\String;


/**
 * Parses the response from a cURL request into an object containing
 * the response body and an associative array of headers
 *
 * <code>
 * $response = new CurlResponse(curl_exec($curl_handle));
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
	const HEADER_REGEXP = "#(?P<header>.*?)\:\s(?P<value>.*)#";
	const HEADERS_REGEXP = "#HTTP/\d\.\d.*?$.*?\r\n\r\n#ims";
	const VERSION_AND_STATUS = "#HTTP/(?P<version>\d\.\d)\s(?P<code>\d\d\d)\s(?P<status>.*)#";
	const FILE_CONTENT_START = "\r\n\r\n";
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
			$matches = String::matchAll($response, self::HEADERS_REGEXP);
			$headers_string = array_pop($matches[0]);
			$headers = explode("\r\n", str_replace("\r\n\r\n", '', $headers_string));

			# Remove headers from the response body
			$this->Body = str_replace($headers_string, '', $response);

			$this->Headers = $this->parseHeaders($headers);
		}
	}


	/**
	 * Parses headers from given list
	 * @param array $headers
	 * @return array
	 */
	public function parseHeaders($headers)
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
	public function parseFile()
	{
		if ($this->request->method === Request::DOWNLOAD) {
			$path_p = $this->request->downloadPath;
			@fclose($this->request->getOption('file'));

			if (($fp = @fopen($this->request->fileProtocol . '://' . $path_p, "rb")) === FALSE) {
				throw new \InvalidStateException("Fopen error for file '{$path_p}'");
			}

			$rows = array();
			do {
				if (feof($fp)) {
					break;
				}
				$rows[] = fgets($fp);

				$matches = String::matchAll(implode($rows), self::HEADERS_REGEXP);

			} while ($matches && count($matches[0]) == 0);


			if (isset($matches[0][0])) {
				$headers_string = array_pop($matches[0]);
				$headers = explode("\r\n", str_replace("\r\n\r\n", '', $headers_string));
				$this->Headers = array_merge($this->Headers, $this->parseHeaders($headers));

				fseek($fp, strlen($headers_string));
// 				$this->request->getFileProtocol();

				$path_t = $this->request->downloadPath . '.tmp';

				if (($ft = @fopen($this->request->fileProtocol . '://' . $path_t, "wb")) === FALSE) {
					throw new \InvalidStateException("Write error for file '{$path_t}' ");
				}

				while (!feof($fp)) {
					$row = fgets($fp, 4096);
					fwrite($ft, $row);
				}

				@fclose($fp);
				@fclose($ft);

				if (!@unlink($this->request->fileProtocol . '://' . $path_p)) {
					throw new \InvalidStateException("Error while deleting file {$path_p} ");
				}

				if (!@rename($this->request->fileProtocol . '://' . $path_t, $this->request->fileProtocol . '://' . $path_p)) {
					throw new \InvalidStateException("Error while renaming file '{$path_t}' to '".basename($path_p)."'. ");
				}

				@chmod($path_p, 0755);

			}

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
	 * $curl = new Request;
	 * $response = $curl->get('google.com');
	 * echo $response;  # => echo $response->body;
	 * </code>
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->Body;
	}


	/**
	 * Returns the response body
	 * @return string
	 */
	public function getBody()
	{
		return $this->Body;
	}


	/**
	 * Alias for getBody
	 * @return string
	 */
	public function getResponse()
	{
		return $this->Body;
	}


	/**
	 * @return \phpQuery\phpQuery
	 */
	public function getQuery()
	{
		$contentType = NULL;
		if (isset($this->Headers['Content-Type'])) {
			$contentType = $this->Headers['Content-Type'];
		}

		return \phpQuery\phpQuery::newDocument($this->body, $contentType);
	}


	/**
	 * @param string $charset
	 * @return \Curl\Response
	 */
	public function convert($to = "UTF-8", $from = NULL)
	{
		if ($from === NULL) {
			$charset = $this->query['head > meta[http-equiv=Content-Type]']->attr('content');
			$match = \Nette\String::match($charset, "~^(?P<type>[^;]+); charset=(?P<charset>.+)$~");

			$from = $match['charset'];
		}

		if ($body = @iconv($from, $to, $this->body)) {
			$this->Body = $body;

		} else {
			throw new CurlException("Charset conversion from $from to $to failed");
		}

		return $this;
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

