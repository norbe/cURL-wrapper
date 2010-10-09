<?php

/**
 * cURL Test bootstrap file.
 *
 * @copyright  Copyright (c) 2009 Filip Procházka
 * @package    Vdsc
 */

require_once dirname(__FILE__) . '/Nette/loader.php';
require_once dirname(__FILE__) . '/Curl/Request.php';

use Curl\Request,
	Curl\CurlException;

// register wrapper safe for file manipulation
// SafeStream::register();


Nette\Debug::enable();
Nette\Debug::$strictMode = True;

Nette\Environment::loadConfig('config.ini');
$config = (array)Nette\Environment::getConfig('curl');


function proxy(&$test)
{
// 	$test->addProxy('192.168.1.160', 3128);
}


if( true ){ // test 1: get
	$test = new Request("http://curl.kdyby.org/prevodnik.asm.zdrojak", $config);
// 	$test = new Curl("http://iskladka.cz/iCopy/downloadBalancer.php?file=1222561395_obava_bojov+cz.avi&ticket=pc1660-1265493063.25");


	echo "<hr>test 1: get ... init ok<hr>", "<h2>Setup:</h2>";

	proxy($test); // for debbuging at school
	dump($test);


	$response =  $test->get();


	echo "<h2>Headers:</h2>";
	dump($response->getHeaders());

	echo "<h2>Response:</h2>", "<pre>";
	var_dump(htmlspecialchars($response->getResponse()));
	echo "</pre>";
}



if( true ){ // test 2: get non existing file
	$test = new Request("http://curl.kdyby.org/prevodnik.asm.zdrojak.nonexisting", $config);


	echo "<hr>test 2: get 404 ... init ok<hr>", "<h2>Setup:</h2>";

	proxy($test); // for debbuging at school
	dump($test);


	try {
	    $response =  $test->get();

	    echo "<h2>Headers:</h2>";
	    dump($response->getHeaders());

	    echo "<h2>Response:</h2>", "<pre>";
	    var_dump($response->getResponse());
	    echo "</pre>";

	} catch( CurlException $e ){
		echo "<h1>",get_class($e),"</h1>";

		if( $response = $e->getResponse() ){
			echo "<h2>Headers:</h2>";
			dump($response->getHeaders());

			echo "<h2>Response:</h2>", "<pre>";
			var_dump(htmlspecialchars($response->getResponse()));
			echo "</pre>";

		} else {
			dump("shit happens!", $e->getMessage());
		}
	}
}



if( true ){ // test 3: get secured file
	$test = new Request("http://curl.kdyby.org/secured.php", $config);


	echo "<hr>test 3: get secured ... init ok<hr>", "<h2>Setup:</h2>";

	proxy($test); // for debbuging at school
	dump($test);


	try {
	    $response =  $test->get();

	    echo "<h2>Headers:</h2>";
	    dump($response->getHeaders());

	    echo "<h2>Response:</h2>", "<pre>";
	    var_dump($response->getResponse());
	    echo "</pre>";

	} catch( CurlException $e ){
		echo "<h1>",get_class($e),"</h1>";

		if( $response = $e->getResponse() ){
			echo "<h2>Headers:</h2>";
			dump($response->getHeaders());

			echo "<h2>Response:</h2>", "<pre>";
			var_dump(htmlspecialchars($response->getResponse()));
			echo "</pre>";

		} else {
			dump("shit happens!", $e->getMessage());
		}
	}
}


if( true ){ // test 4: post
	$test = new Request("http://curl.kdyby.org/dump_post.php", $config);

	echo "<hr>test 4: post ... init ok<hr>", "<h2>Setup:</h2>";

	proxy($test); // for debbuging at school
	dump($test);

	$response =  $test->post(array(
		'var1' => 'Lorem ipsum dot sit amet',
		'var2' => 0,
		'var3' => 23,
		'var4' => True,
		'var5' => False,
	));


	echo "<h2>Headers:</h2>";
	dump($response->getHeaders());

	echo "<h2>Response:</h2>", "<pre>";
	var_dump(htmlspecialchars($response->getResponse()));
	echo "</pre>";
}



if( true ){ // test 5: download
	$test = new Request("http://curl.kdyby.org/prevodnik.asm.zdrojak", $config);

	echo "<hr>test 5: download ... init ok<hr>", "<h2>Setup:</h2>";

	proxy($test); // for debbuging at school
	dump($test);


	$test->setDownloadFolder(realpath('./download'));

	$response =  $test->download();


	echo "<h2>Headers:</h2>";
	dump($response->getHeaders());

	echo "<h2>Response:</h2>", "<pre>";
	$fp = $response->openFile();
	var_dump(htmlspecialchars(fread($fp, $response->getHeader('Content-Length'))));
	fclose($fp);
	echo "</pre>";
}
