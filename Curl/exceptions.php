<?php

namespace Curl;


/**
 * Exception thrown by cURL wrapper
 * @author Filip Procházka <hosiplan@kdyby.org>
 */
class CurlException extends \Exception implements \Nette\Diagnostics\IBarPanel
{
	/** @var \Curl\Response */
	var $response;


	public function __construct($message, $code = 0, Response $response = NULL)
	{
		parent::__construct($message, $code);

		$this->response = $response;
	}



	/**
	 * @return \Curl\Response
	 */
	public function getResponse()
	{
		return $this->response;
	}



	/********************* interface Nette\IDebugPanel *********************/



	/**
	 * Returns HTML code for custom tab.
	 * @return mixed
	 */
	public function getTab()
	{
		return 'Curl dump';
	}



	/**
	 * Returns HTML code for custom panel.
	 * @return mixed
	 */
	public function getPanel()
	{
		return $this->response ? $this->response->errorDump() : NULL;
	}



	/**
	 * Returns panel ID.
	 * @return string
	 */
	public function getId()
	{
		return __CLASS__;
	}

}


/**
 * Thrown when one of Curl\Request::$badStatusCodes occures
 * @author Filip Procházka <hosiplan@kdyby.org>
 */
class BadStatusException extends CurlException
{

}


/**
 * Thrown when curl_exec returns false
 * @author Filip Procházka <hosiplan@kdyby.org>
 */
class FailedRequestException extends CurlException
{

}
