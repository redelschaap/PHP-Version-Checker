<?php


	/**
	 * PHP Functions and Classes Crawler
	 * Crawls the PHP.net website to get all known functions and classes with their names and required PHP versions and extensions
	 *
	 * @author      Ronald Edelschaap <rlwedelschaap@gmail.com> <first autohor>
	 * @lastupdated 31-07-2014
	 * @license     http://www.gnu.org/licenses/gpl-2.0 GPL v2.0
	 * @version     1.0
	 * @use         This class makes use of the simple_html_dom class. This class can be found at http://simplehtmldom.sourceforge.net/
	 *              NOTE! By default, the max file pocessing size for the simple_html_dom class can be too low for the PHP.net website to crawl. You can set the MAX_FILE_SIZE constant to a higher value, something like 1500000
	 *
	 * @example     Use this class this way: $php_crawler = new phpFunctionsCrawler(); $php_crawler->scrapeFunctionIndex(); $php_crawler->scrapeFunctions(); $php_crawler->saveToXMLFile('functions/functions.xml');
	 */
	class phpFunctionsCrawler
	{

		public $scraperData = array(
			'all_functions'      => array(),
			'functions'          => array(),
			'functions_link'     => 'http://php.net/manual/en/indexes.functions.php',
			'function_base_link' => 'http://php.net/manual/en/',
			'http_status'        => 0
		);
		public $storeDescriptions = false;

		private $alphabet = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '_');
		private $alphabetLetter = null;
		private $encoding = 'UTF-8';
		private $scraper;
		private $scraperExec;
		private $scraperHtml;
		private $scraperOptions = array(
			CURLOPT_COOKIESESSION  => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTPHEADER     => array(
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
				'Accept-Charset: UTF-8',
				'Accept-Language: en-US,en;q=0.8',
				'Connection: keep-alive',
				'Content-Type: charset=UTF-8'
			),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.114 Safari/537.36'
		);


		function __construct()
		{
			if (!class_exists('simple_html_dom')) {
				trigger_error('Class "simple_html_dom" does not exist on this system. Please include the "simple_html_dom.class.php" file before calling the "' . __CLASS__ . '" class', E_USER_ERROR);
			}

			if (!in_array('curl', get_loaded_extensions())) {
				trigger_error('Extension cURL is not installed on this system. Please install cURL first', E_USER_ERROR);
			}

			//Enable error reporting
			ini_set('display_errors', 'On');
			error_reporting(E_ALL);

			//Longer time limit so our script wont stop after 30 sec
			set_time_limit(0);
		}


		/**
		 * Return all found functions in raw format (function names and their link to the PHP.net doc page), optionally selected by a letter
		 *
		 * @param string $letter
		 *
		 * @return array
		 */
		public function getFunctionIndex($letter = null)
		{
			if ($letter === null || !in_array(strtolower($letter), $this->alphabet)) {
				return $this->scraperData['functions'];
			} else {
				return array((int)array_search(strtolower($letter), $this->alphabet) => $this->scraperData['functions'][(int)array_search(strtolower($letter), $this->alphabet)]);
			}
		}


		/**
		 * Return all found functions in pretty format, with name, version and extension info
		 *
		 * @return array
		 */
		public function getFunctions()
		{
			return $this->scraperData['all_functions'];
		}


		/**
		 * Save all found functions to a XML file
		 *
		 * @param string $file      The destination filepath (eg. functions/functions.xml), relative to the root. If the file does not exist, a new file will be created
		 * @param bool   $clearFile If the destination file exists and $clearFile is TRUE, the file will be erased first. If $clearFile is FALSE, new functions will be appended to the file
		 *
		 * @todo When $clearFile is FALSE and the destination file already exists, check if identical functions (same name AND class) already exist in the file
		 *
		 * @return bool
		 */
		public function saveToXMLFile($file, $clearFile = false)
		{
			$path = pathinfo(ltrim($file, ' /\\'));

			if (!empty(realpath($path['dirname']))) {
				$path['dirname'] = realpath($path['dirname']);
			} elseif (!is_dir(DIRECTORY_SEPARATOR . $path['dirname']) && (mkdir(realpath('.') . DIRECTORY_SEPARATOR . $path['dirname'], 0777, true) !== false)) {
				$path['dirname'] = realpath(realpath('.') . DIRECTORY_SEPARATOR . $path['dirname']);
			} else {
				trigger_error('Could not resolve given file path or could not create a non-existing folder to put the file in.', E_USER_ERROR);

				return false;
			}

			if (!isset($path['extension']) || strtolower($path['extension']) != 'xml') {
				$path['extension'] = 'xml';
			}

			$file = $path['dirname'] . DIRECTORY_SEPARATOR . $path['filename'] . '.' . $path['extension'];

			if (file_exists($file) && !is_writeable($file) && (chmod($file, 0777) === false)) {
				trigger_error($file . ' is not writeable. Please chmod the file manually to 0777.', E_USER_ERROR);

				return false;
			} elseif (!file_exists($file) && !is_writeable($path['dirname']) && (chmod($path['dirname'], 0777) === false)) {
				trigger_error($path['dirname'] . ' is not writeable. Please chmod the file manually to 0777.', E_USER_ERROR);

				return false;
			}


			//Begin output
			if (file_exists($file) && $clearFile == false) {
				$xml = simplexml_load_file($file);
			} else {
				$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" ?><functions></functions>');
				$xml->addAttribute('xmlns', '');
				$xml->addAttribute('xsi:noNamespaceSchemaLocation', 'functions.xsd');
				$xml->addAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
			}

			foreach ($this->scraperData['all_functions'] as $function) {
				$child = $xml->addChild('function');
				$child->addChild('name', $function['name']);

				if ($this->storeDescriptions) {
					$child->addChild('desc', $function['desc']);
				}

				$child->addChild('version', $function['version']);

				if (isset($function['class'])) {
					$child->addChild('class', $function['class']);
				}

				if (isset($function['extension']) && !empty($function['extension'])) {
					$extension = $child->addChild('extension');
					$extension->addChild('name', $function['extension']['name']);

					if (!empty($function['extension']['version'])) {
						$extension->addChild('version', $function['extension']['version']);
					}
				}
			}

			//Save the output to the file and clear our memory
			$xml->asXML($file);
			unset($xml);

			return true;
		}


		/**
		 * Scrape the PHP.net function index page
		 *
		 * @return bool
		 */
		public function scrapeFunctionIndex()
		{
			//Init curl
			$this->scraper = curl_init();

			//Set the first url to get all the functions and apply curl options
			$this->scraperOptions[CURLOPT_URL] = $this->scraperData['functions_link'];
			curl_setopt_array($this->scraper, $this->scraperOptions);

			//Execute curl and load the page, check if we received the page right and close the connection
			$this->scraperExec = mb_convert_encoding(curl_exec($this->scraper), $this->encoding, 'HTML-ENTITIES');
			$this->scraperData['http_status'] = (int)curl_getinfo($this->scraper, CURLINFO_HTTP_CODE);
			curl_close($this->scraper);

			//Reset the curl instance
			$this->scraper = null;


			if ($this->scraperData['http_status'] == 200 || $this->scraperData['http_status'] == 304) {
				//The response is in HTML format. Lets convert it to an object and put the usefull info in a associative array
				$this->scraperHtml = new simple_html_dom();
				$this->scraperHtml->load($this->scraperExec);


				//Get all letters
				$letters = $this->scraperHtml->find('ul.index-for-refentry', 0)->find('li.gen-index');

				foreach ($letters as $letter) {
					$letter = array('letter' => strtolower(substr($letter->plaintext, 0, 1)), 'element' => $letter);

					if (in_array($letter['letter'], $this->alphabet)) {
						$letter['letter'] = (int)array_search($letter['letter'], $this->alphabet);

						//Get all functions for this alphabet letter and put it in an array
						$functions = $letter['element']->find('a');

						foreach ($functions as $function) {
							$this->scraperData['functions'][$letter['letter']][trim($function->plaintext)] = $function->href;
						}
					}
				}


				//We won't need this anymore
				$this->scraperHtml->clear();


				if (empty($this->scraperData['functions'])) {
					trigger_error('Could not find any functions on the crawled page', E_USER_ERROR);

					return false;
				}
			} else {
				trigger_error('Could not load the index file. PHP.net may be unreachable or the function index url may have been changed', E_USER_ERROR);

				return false;
			}

			return true;
		}


		/**
		 * @param null $alphabetLetter
		 *
		 * @return bool
		 */
		public function scrapeFunctions($alphabetLetter = null)
		{
			if (empty($this->scraperData['functions'])) {
				trigger_error('Functions are not yet indexed. Call scrapeFunctionIndex() first', E_USER_ERROR);

				return false;
			}

			if ($alphabetLetter !== null) {
				$this->setAlphabetLetter($alphabetLetter);

				if (!is_array($this->scraperData['functions'][$this->alphabetLetter])) {
					$this->scraperData['functions'][$this->alphabetLetter] = array();
				}


				//Scrape each function from this letter
				foreach ($this->scraperData['functions'][$this->alphabetLetter] as $function) {
					//Init curl
					$this->scraper = curl_init();

					//Set the first url to get all the functions and apply curl options
					$this->scraperOptions[CURLOPT_URL] = $this->scraperData['function_base_link'] . $function;
					curl_setopt_array($this->scraper, $this->scraperOptions);

					//Execute curl and load the page, check if we received the page right and close the connection
					$this->scraperExec = mb_convert_encoding(curl_exec($this->scraper), $this->encoding, 'HTML-ENTITIES');
					$this->scraperData['http_status'] = (int)curl_getinfo($this->scraper, CURLINFO_HTTP_CODE);
					curl_close($this->scraper);

					//Reset the curl instance
					$scraper = null;

					if ($this->scraperData['http_status'] == 200 || $this->scraperData['http_status'] == 304) {

						//The response is in HTML format. Lets convert it to an object and put the usefull info in a associative array
						$this->scraperHtml = new simple_html_dom();
						$this->scraperHtml->load($this->scraperExec);

						$data = array(
							'version'   => '',
							'extension' => array(),
							'desc'      => ''
						);


						//Each useful page has an paragraph element with a class named verinfo. If there is no such paragraph, this call will result in a non-object
						if (is_object($this->scraperHtml->find('p.verinfo', 0))) {

							//Get version information
							$data['version_info'] = $this->scraperHtml->find('p.verinfo', 0)->plaintext;

							if (strtolower(substr($data['version_info'], 0, 4)) == '(php') {
								$data['version_info'] = explode(',', str_replace(', ', ',', trim($data['version_info'], '()')));

								$data['version'] = array_shift($data['version_info']);
								$data['version'] = array_reverse(explode('>', str_ireplace('&gt;', '>', str_ireplace(array('PHP', ' ', '='), '', $data['version']))));
								$data['version'] = trim($data['version'][0]);

								if (count($data['version_info']) > 0) {
									$data['version_info'] = array_values($data['version_info']);
									$data['version_info'] = array_reverse($data['version_info']);

									if (strtolower(substr($data['version_info'][0], 0, 4)) != 'php ') {
										$data['version_info'][0] = str_replace('>=', '>', str_ireplace('&gt;', '>', $data['version_info'][0]));

										if (strpos($data['version_info'][0], '>') !== false) {
											$data['version_info'][0] = explode('>', $data['version_info'][0]);
											$data['extension'] = array('name' => trim($data['version_info'][0][0]), 'version' => trim($data['version_info'][0][1]));
										} else {
											$data['version_info'][0] = explode(' ', $data['version_info'][0]);
											$data['extension']['version'] = trim(array_pop($data['version_info'][0]));
											$data['extension']['name'] = trim(implode(' ', $data['version_info'][0]));
										}
									}
								}
							} elseif (strtolower(substr($data['version_info'], 0, 4)) != '(no ' && strtolower(substr($data['version_info'], 0, 4)) != '(unk') {
								$data['version'] = '0';
								$data['version_info'] = explode(',', str_replace(', ', ',', trim($data['version_info'], '()')));

								if (count($data['version_info']) > 0) {
									if (strtolower(substr($data['version_info'][0], 0, 4)) != 'php ') {
										$data['version_info'][0] = str_replace('>=', '>', str_ireplace('&gt;', '>', $data['version_info'][0]));

										if (strpos($data['version_info'][0], '>') !== false) {
											$data['version_info'][0] = explode('>', $data['version_info'][0]);
											$data['extension'] = array('name' => trim($data['version_info'][0][0]), 'version' => trim($data['version_info'][0][1]));
										} else {
											$data['version_info'][0] = explode(' ', $data['version_info'][0]);
											$data['extension']['version'] = trim(array_pop($data['version_info'][0]));
											$data['extension']['name'] = trim(implode(' ', $data['version_info'][0]));
										}
									}
								}
							} else {
								$data['version'] = '0';
							}

							//Get description
							$data['desc'] = str_replace(array('     ', '    ', '   '), ' ', str_replace('  ', ' ', trim($this->scraperHtml->find('span.dc-title', 0)->plaintext)));

							//Get all function names. Some functions have more than one name (like object oriented and preocedural styles)
							foreach ($this->scraperHtml->find('div.methodsynopsis span.methodname') as $methodName) {
								$methodName = trim($methodName->plaintext);

								if (strpos($methodName, '::') !== false) {
									$method = explode('::', $methodName, 2);
								} else {
									$method = array(0 => '', 1 => $methodName);
								}

								$this->scraperData['all_functions'][$methodName] = array(
									'name'    => $method[1],
									'desc'    => $data['desc'],
									'version' => $data['version']
								);

								if (!empty($method[0])) {
									$this->scraperData['all_functions'][$methodName]['class'] = $method[0];
								}

								if (!empty($data['extension'])) {
									$this->scraperData['all_functions'][$methodName]['extension'] = $data['extension'];
								}
							}

							//We won't need this anymore
							unset($data);
							$this->scraperHtml->clear();
						}
					}
				}
			} else {
				if (empty($_GET['do']) || $_GET['do'] != 'crawl_all_phpfunctions') {
					trigger_error('No alphabet letter selected. PHP has almost 10 000 functions. It will take some time to crawl all those functions in one batch. Set the first parameter of this method to select a letter (a-z or _), or add "?do=crawl_all_phpfunctions" to the current URL to crawl all functions', E_USER_NOTICE);

					return false;
				} else {
					trigger_error('No alphabet letter selected. PHP has almost 10 000 functions. It will take some time to crawl all those functions in one batch', E_USER_NOTICE);

					foreach ($this->alphabet as $letter) {
						$this->scrapeFunctions($letter);
					}
				}
			}

			return true;
		}


		/**
		 * Set the current alphabet letter
		 *
		 * @param null $letter
		 *
		 * @return bool
		 */
		private function setAlphabetLetter($letter = null)
		{
			if ($letter === null || !in_array(strtolower($letter), $this->alphabet)) {
				$this->alphabetLetter = null;
			} else {
				$this->alphabetLetter = (int)array_search(strtolower($letter), $this->alphabet);
			}

			return true;
		}
	}
