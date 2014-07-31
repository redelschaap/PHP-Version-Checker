<?php
	require_once(realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'phpFunctionsCrawler.class.php');
	require_once(realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'phpVersionCheck.class.php');

	if (!empty($_GET['action']) && $_GET['action'] == 'crawl_functions') {
		$php_crawler = new phpFunctionsCrawler();
		$php_crawler->scrapeFunctionIndex();
		$php_crawler->scrapeFunctions();

		$php_crawler->saveToXMLFile('functions/functions.xml', true);
	}

	if (!empty($_GET['action']) && $_GET['action'] == 'check_version') {
		$php_version_checker = new phpVersionCheck('functions/functions.xml');
		$php_version_checker->checkFiles();
		$php_min_version = $php_version_checker->getResults();

		echo $php_min_version['required_version'];
	}
