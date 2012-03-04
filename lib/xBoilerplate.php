<?php
/**
 * xBoilerplate: Xodoa Boilerplate package
 *
 * @category Xodoa
 * @package  xBoilerplate
 * @copyright Copyright (c) 2007-2012 Xodoa (http://xodoa.com)
 * @author   Nicolas Ruflin <ruflin@xodoa.com>
 **/

class xBoilerplate {

	/**
	 * @var string Page title
	 */
	public $title = null;

	/**
	 * @var string Page description
	 */
	public $description = null;

	/**
	 * @var string Page keywords
	 */
	public $keywords = null;

	protected $_params = array();
	protected $_basePath = '';
	protected $_config = null;

	public function __construct($uri, $params) {
		$uriParse = parse_url($_SERVER['REQUEST_URI']);
		$this->_params = $params;
		$this->_basePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'httpdocs' . DIRECTORY_SEPARATOR;
	}

	/**
	 * Renders the page template
	 *
	 * @return string Returns the rendered page
	 */
	public function render() {

		$body = $this->loadLayout('template.php');

		if ($this->_getParam('raw')) {
			$content = $this->loadPage();
		} else {
			// Template has be loaded first to set title, description, keywords first
			$header = $this->loadLayout('header.php');
			$footer = $this->loadLayout('footer.php');

			$content = $header . $body . $footer;
		}

		return $content;
	}

	/**
	 * Loads the content for a specific page
	 *
	 * If the page is not found, content of page page-not-found is returned
	 *
	 * @param string $category OPTIONAL Takes category from params if not set
	 * @param string $page OPTIONAL Takes page from params if not set
	 * @return string Page content
	 */
	public function loadPage($category = null, $page = null) {
		$category = $category ? $category : $this->getCategory();
		$page = $page ? $page : $this->getPage();

		try {
			$content = $this->loadContent($category . '/' . $page . '.php');
		} catch (Exception $e) {
			$content = $this->loadContent('page-not-found.php');
		}
		return $content;
	}

	/**
	 * @param string $file Path to content file
	 * @return string Content of file
	 */
	public function loadContent($file) {
		$file = 'content/' . $file;
		return $this->_loadFile($file);
	}

	/**
	 * Loads a file relative to the base path
	 *
	 * @param strin $file Path to file
	 * @return string
	 * @throws Exception If invalid file
	 */
	protected function _loadFile($file) {
		// TODO: add more security checks
		if (strpos($file, '..') !== false || substr($file, 0, 1) == '/') {
			throw new Exception('Invalid file path:' . $file);
		}

		$file = $this->_basePath . $file;

		if (!file_exists($file)) {
			throw new Exception('File does not exist: ' . $file);
		}

		ob_start();
		include($file);
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}

	/**
	 * Returns the page param. Can be overwritten by first param
	 *
	 * @param string $page OPTIONAL
	 * @return mixed|null|string
	 */
	public function getPage($page = '') {
		$p = $this->_getParam('p');

		if (!empty($page)) {
			$p = $page;
		}

		if (empty($p)) {
			$p = 'index';
		}

		$p = $this->_filterParam($p);

		return $p;
	}

	public function getCategory($category = '') {
		$c = $this->_getParam('c');

		if (!empty($category)) {
			$c = $category;
		}

		if (empty($c)) {
			$c = 'index';
		}

		// For security reasons
		$c = $this->_filterParam($c);

		return $c;
	}

	/**
	 * Loads layout files
	 *
	 * @param string $name Loads a layout file
	 * @return string Layout content
	 */
	public function loadLayout($name) {
		return $this->_loadFile('layout/' . $name);
	}

	/**
	 * @param string $name Param to read out
	 * @return mixed|null|string
	 */
	protected function _getParam($name) {
		$param = null;
		if (isset($this->_params[$name])) {
			$param = $this->_params[$name];
		}
		$param = $this->_filterParam($param);
		return $param;
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @return xBoilerplate
	 */
	public function setParam($key, $value) {
		$this->_params[$key] = $value;
		return $this;
	}


	protected function _filterParam($param) {
		// For security reasons
		$param = stripslashes(strip_tags($param));

		$notAllowed = array('\'', '*', '/', ' ',);
		$param = str_replace($notAllowed, '', $param);

		return $param;
	}

	/**
	 * Loads dynamically a menu. If no menu name is set, the menu is loaded
	 * based on the category from the menu folder
	 *
	 * @param string $name OPTIONAL Menu name to load
	 * @return string
	 */
	public function getMenu($name = null) {
		if (!$name) {
			$name = $this->_getParam('c');
		}

		$file = $this->_basePath . 'menu/' . $name . '.php';
		$content = '';

		if (!empty($name) && file_exists($file)) {
			ob_start();
			include $file;
			$content = ob_get_contents();
			ob_end_clean();
		}

		return $content;
	}

	/**
	 * Checks if the given category / page is active
	 *
	 * @param string $c
	 * @param string $p
	 * @return string Returns 'active' if is active
	 */
	public function getActive($c = '', $p = '') {

		// TODO Remove get params
		if (empty($p) && isset($_GET['c'])) {
			return $c == $_GET['c']?'active':'';
		}

		if (!empty($p) && !empty($c)) {
			if (isset($_GET['c']) && isset($_GET['p'])) {
				if ($_GET['c'] == $c && $_GET['p'] == $p) {
					return 'active';
				}
			}
		}

		return '';
	}

	/**
	 * Returns the config object
	 *
	 * This is the merged array of config.php and local.php (if exists)
	 * The config is only loaded once and also can be modified during runtime
	 *
	 * @return object
	 */
	public function getConfig() {

		// Only loads config once
		if (!$this->_config) {
			// Load default config file
			$configDir = dirname($this->_basePath) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
			include $configDir . 'config.php';

			$localFile = $configDir . 'local.php';

			// Include local config file if exists
			if (file_exists($localFile)) {
				// Store current config
				$baseConfig = $config;
				include $localFile;
				$config = array_merge($baseConfig, $config);
			}
			$this->_config = (object) $config;
		}

		return $this->_config;
	}
}
