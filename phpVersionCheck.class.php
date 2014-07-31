<?php


	/**
	 * PHP Functions and Classes Version Checker
	 * Checks PHP project files against the internal functions and classes of PHP to determine the minimum required PHP version
	 *
	 * @author      Ronald Edelschaap <rlwedelschaap@gmail.com> <first autohor>
	 * @authors     ...
	 * @lastupdated 31-07-2014
	 * @license     http://www.gnu.org/licenses/gpl-2.0 GPL v2.0
	 * @version     1.0
	 *
	 * @example     Use this class this way: $php_version_checker = new phpVersionCheck('functions/functions.xml'); $php_version_checker->checkFiles(); $php_min_version = $php_version_checker->getResults();
	 *
	 * @todo        Add the functionality to follow objects with their functions. Some classes have methods with different minimum PHP versions. In the current version, when an object is assigned to a variable and that variable executes a method <eg. $var = new object(); $var->method();>, methods are handles like normal functions without classes. When you can connect methods to classes from objects, we will get a more reliable result. I don't know how to do this, so be my guest to build it!
	 */


	class phpVersionCheck
	{
		private $fileTypes = array('php', 'phtml');
		private $filesToCheck = array();
		private $functionFile;
		private $functions = array();
		private $requiredVersion = null;
		private $requiredVersionWarning = false;
		private $requiredVersionWarningFunctions = array();
		private $root;
		private $usedClasses = array();
		private $usedExtensions = array();
		private $usedFunctions = array();


		/**
		 * @param string $xmlFile Set the file with functions
		 * @param string $root    The root directory of the project
		 */
		public function __construct($xmlFile, $root = '.')
		{
			//Enable error reporting
			ini_set('display_errors', 'On');
			error_reporting(E_ALL);


			//Longer time limit so our script wont stop after 30 sec
			set_time_limit(0);


			//Set root dir
			$this->root = realpath($root);

			if (empty($this->root)) {
				trigger_error('Root dir could not be resolved.', E_USER_ERROR);
				exit;
			}


			//Read XML file with functions
			if (!file_exists($xmlFile) || !is_readable($xmlFile)) {
				trigger_error('XML file not found or not readable.', E_USER_ERROR);
				exit;
			}

			$this->functionFile = $xmlFile;
			$this->readFunctionFile();
		}


		/**
		 * Scan the files in this project for used functions and classes and match those against the internal functions of PHP
		 *
		 * @return int Returns the number of files this function walked through
		 */
		public function checkFiles()
		{
			$filesCount = 0;
			$fileFunctions = array();

			//Get files that we are going to check
			$this->getFilesToCheck();

			//Loop all the files
			if (!empty($this->filesToCheck) && !empty($this->functions)) {

				foreach ($this->filesToCheck as $file) {
					$filesCount++;
					$fileContent = file_get_contents($file);
					$fileContentOffset = 0;

					//Get the PHP code as long we have php opening tags left
					while (stripos($fileContent, '<?php', $fileContentOffset) !== false) {

						//Some scripts have no end tag
						if (stripos($fileContent, '?>', $fileContentOffset) !== false) {
							$filePhpCode = substr($fileContent, stripos($fileContent, '<?php', $fileContentOffset), (stripos($fileContent, '?>', $fileContentOffset) + 2));
						} else {
							$filePhpCode = substr($fileContent, stripos($fileContent, '<?php', $fileContentOffset));
						}

						//Get all functions we can find in this piece of code
						$loopFileFunctions = array();
						preg_match_all("/((new|function)[\s]*)?[a-zA-Z0-9\_:]+\s*\([^\)]*\)\s*;/", $filePhpCode, $loopFileFunctions);

						if (!empty($loopFileFunctions)) {
							$loopFileFunctions['fine'] = array();

							foreach ($loopFileFunctions[0] as $loopFileFunction) {
								$loopFileFunction = strstr($loopFileFunction, '(', true);

								if (
									strtolower(substr(str_ireplace(array('new', ' ', '::'), '', $loopFileFunction), 0, 4)) != 'self'
									&&
									strtolower(substr(str_ireplace(array('new', ' ', '::'), '', $loopFileFunction), 0, 6)) != 'parent'
									&&
									strtolower(substr($loopFileFunction, 0, 8)) != 'function'
								) {
									$loopFileFunctions['fine'][] = $loopFileFunction;
								}
							}

							$fileFunctions = array_unique(array_merge($fileFunctions, $loopFileFunctions['fine']));
						}

						//Set a new offset for the next loop. Keep it simple, just add one position to the current start position.
						$fileContentOffset = stripos($fileContent, '<?php', $fileContentOffset) + 1;
					}
				}


				//Match the functions from the code against our functions
				if (!empty($fileFunctions)) {

					foreach ($fileFunctions as $fileFunction) {
						$functionSearch = array();

						if (strtolower(substr($fileFunction, 0, 3)) == 'new') {
							$fileClass = str_ireplace(array('new', ' '), '', $fileFunction);
							$functionSearch['class'] = $fileClass;
						} else {

							if (strpos($fileFunction, '::') !== false) {
								$fileFunctionExp = explode('::', $fileFunction, 2);
								$functionSearch['name'] = $fileFunctionExp[0];
								$functionSearch['class'] = $fileFunctionExp[1];
							} else {
								$functionSearch['name'] = $fileFunction;
								$functionSearch['class'] = null;
							}
						}

						$result = searchArrayPairs($this->functions, $functionSearch);


						//If $result has at least one match, add the corresponding values to the global $used* arrays
						if (!empty($result)) {
							$functionMatch = $this->functions[$result[0]];
							$functionMatchName = $functionMatch['name'];

							if (!empty($functionMatch['class'])) {
								$functionMatchName = $functionMatch['class'] . '::' . $functionMatchName;

								if (!array_key_exists(strtolower($functionMatch['class']), $this->usedClasses)) {
									$this->usedClasses[strtolower($functionMatch['class'])] = $functionMatch['class'];
								}
							}

							if (!array_key_exists(strtolower($functionMatchName), $this->usedFunctions)) {
								$this->usedFunctions[strtolower($functionMatchName)] = $functionMatchName;
							}

							if (!empty($functionMatch['extension']) && !array_key_exists(strtolower($functionMatch['extension']['name']), $this->usedExtensions)) {
								$this->usedExtensions[strtolower($functionMatch['extension']['name'])] = array('name' => $functionMatch['extension']['name']);

								if (!empty($functionMatch['extension']['version'])) {
									$this->usedExtensions[strtolower($functionMatch['extension']['name'])]['required_version'] = $functionMatch['extension']['version'];
								}
							} else {
								if (!empty($functionMatch['extension']['version']) && (empty($this->usedExtensions[strtolower($functionMatch['extension']['name'])]['required_version']) || $functionMatch['extension']['version'] > $this->usedExtensions[strtolower($functionMatch['extension']['name'])]['required_version'])) {
									$this->usedExtensions[strtolower($functionMatch['extension']['name'])]['required_version'] = $functionMatch['extension']['version'];
								}
							}

							if (!empty($functionMatch['version'])) {
								if (is_null($this->requiredVersion) || $functionMatch['version'] > $this->requiredVersion) {
									$this->requiredVersion = $functionMatch['version'];
								}
							} else {
								$this->requiredVersionWarning = true;

								if (!array_key_exists(strtolower($functionMatchName), $this->requiredVersionWarningFunctions)) {
									$this->requiredVersionWarningFunctions[strtolower($functionMatchName)] = $functionMatch;
								}
							}
						}
					}
				}

				sort($this->usedFunctions);
				sort($this->usedClasses);
				ksort($this->usedExtensions);
				$this->usedExtensions = array_values($this->usedExtensions);
				sort($this->requiredVersionWarningFunctions);
			}

			return $filesCount;
		}


		/**
		 * Get the results after you used the checkFiles() method
		 *
		 * @return array Returns an array containing the following keys: required_version, used_functions, used_classes, used_extensions and warning. The key required_version contains the minimum PHP version needed in this project, or 'unknown' if the version could not be determined. The key warning is FALSE by default, or contains a message when a function was used wherefor no version information is documented.
		 */
		public function getResults()
		{
			$output = array(
				'required_version' => $this->requiredVersion,
				'used_functions'   => $this->usedFunctions,
				'used_classes'     => $this->usedClasses,
				'used_extensions'  => $this->usedExtensions,
				'warning'          => false
			);

			if (empty($output['required_version'])) {
				$output['required_version'] = 'Unknown';
			}

			if ($this->requiredVersionWarning) {
				$output['warning'] = 'WARNING: the given required version could be higher, as the PHP.net documentation of some functions used in this project lacks details about the required PHP version.';

				if (!empty($this->requiredVersionWarningFunctions)) {
					$output['warning'] .= "\r\nThis is due to the use of the following functions: ";
					$output['warning'] .= implode(', ', array_map(function ($v) {
						return (!empty($v['class']) ? $v['class'] . '::' : '') . $v['name'];
					}, $this->requiredVersionWarningFunctions));
					$output['warning'] .= '.';
				}
			}

			return $output;
		}


		/**
		 * Scan a directory recursively for php files
		 *
		 * @param string $path Specify a path to get the files from, or leave null to scan the whole project
		 */
		private function getFilesToCheck($path = null)
		{
			if ($path === null) {
				$path = $this->root;
			} else {
				$path = realpath($path);
			}

			//Search for all files
			if (!isLinkReal($path)) {
				if (is_dir($path) && is_readable($path)) {
					$pathContent = scandirPathnames($path, true);

					foreach ($pathContent as $subPath) {
						$this->getFilesToCheck($subPath);
					}
				} elseif (is_file($path) && is_readable($path)) {
					$file = pathinfo($path);

					if ($path != __FILE__ && array_key_exists('extension', $file) && in_array(strtolower($file['extension']), $this->fileTypes)) {
						$this->filesToCheck[] = $path;
					}
				}
			}
		}


		/**
		 * Read the ginven function file and put them in a global array
		 */
		private function readFunctionFile()
		{
			//Load XML file
			$xml = simplexml_load_file($this->functionFile);

			//Convert XML object to an array and store it
			$this->functions = json_decode(json_encode($xml), true);
			$this->functions = $this->functions['function'];
		}
	}


	/**
	 * Lists files and directories with their full paths inside the specified path. Alternative for scandir().
	 *
	 * @param string   $directory
	 * @param bool     $exclude_symbolic_links
	 * @param int      $sorting_order
	 * @param resource $context
	 *
	 * @see scandir()
	 *
	 * @return array|bool
	 */
	function scandirPathnames($directory, $exclude_symbolic_links = true, $sorting_order = SCANDIR_SORT_ASCENDING, $context = null)
	{
		$output = array();
		$directory = realpath($directory);

		if ($directory === false) {
			return false;
		}

		if (is_null($context)) {
			$files = @scandir($directory, $sorting_order);
		} else {
			$files = @scandir($directory, $sorting_order, $context);
		}

		if (!empty($files)) {
			foreach ($files as $file) {
				$file = $directory . DIRECTORY_SEPARATOR . $file;
				if (!$exclude_symbolic_links || !isLinkReal($file)) {
					$output[] = ($file);
				}
			}
		}

		return $output;
	}

	/**
	 * Search for one or more key-value pairs in a multidimensional array within the first level
	 *
	 * @param array $haystack The multidimensional array
	 * @param array $needle   The key-value pair(s) in an array. When more than one key-value pair is given, this functions will try to match an array with at least these pairs (like an AND search function). If a value is null and $strict is set to FALSE, this function also matches arrays where that key does not exist
	 * @param bool  $strict   Whether to also check the variable types of the values in the key-value pair(s)
	 *
	 * @return array This function returns an array containing the first level keys of $haystack where $needle was found
	 */
	function searchArrayPairs($haystack, $needle, $strict = false)
	{
		$needle_matches = array();

		if (is_array($needle) && !empty($needle) && is_array($haystack) && !empty($haystack)) {
			$needle_num = count($needle);

			foreach ($haystack as $h_key => $h_value) {
				$needle_match = 0;

				if (is_array($h_value)) {

					foreach ($needle as $n_key => $n_value) {

						if ($n_value === null && !$strict && !array_key_exists($n_key, $h_value)) {
							$needle_match++;
						} elseif (array_key_exists($n_key, $h_value)) {
							if (($strict && $h_value[$n_key] === $n_value) || ((!$strict) && $h_value[$n_key] == $n_value)) {
								$needle_match++;
							}
						}

						if ($needle_match == $needle_num) {
							$needle_matches[] = $h_key;
							break;
						}
					}
				}
			}
		}

		return $needle_matches;
	}


	/**
	 * Tells whether the filename is a symbolic link. Other than is_link(), is_link_real() also checks if a directory is a symbolic link
	 *
	 * @param string $filename
	 *
	 * @return bool
	 */
	function isLinkReal($filename)
	{
		if (!is_dir($filename) && is_link($filename)) {
			return true;
		} elseif (is_dir($filename)) {

			if (substr($filename, -1) == DIRECTORY_SEPARATOR) {
				$filename = substr($filename, 0, -1);
			}

			return ($filename != realpath($filename));
		}

		return false;
	}
