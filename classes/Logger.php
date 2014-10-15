<?php
/**
 * DynamicPageList
 * DPL Logger Class
 *
 * @author		IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license		GPL
 * @package		DynamicPageList
 *
 **/
namespace DPL;

class Logger {
	/**
	 * Level of Debug Messages to Show
	 *
	 * @var		integer
	 */
	public $debug;

	/**
	 * Buffer of debug messages.
	 *
	 * @var		array
	 */
	private $buffer = [];

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		$this->iDebugLevel = ParametersData::$data['debug']['default'];
	}

	/**
	 * Function Documentation
	 *
	 * @access	public
	 * @return	void
	 */
	public function addMessage($errorId) {
		$args = func_get_args();
		$args = array_map('htmlspecialchars', $args);
		return call_user_func_array([$this, 'msg'], $args);
	}

	/**
	 * Return the buffer of messages.
	 *
	 * @access	public
	 * @param	boolean	[Optional] Clear the message buffer.
	 * @return	array	Messages in the order added.
	 */
	public function getMessages($clearBuffer = true) {
		$buffer = $this->buffer;
		if ($clearBuffer === true) {
			$this->buffer = [];
		}
		return $buffer;
	}

	/**
	 * Get a message, with optional parameters
	 * Parameters from user input must be escaped for HTML *before* passing to this function
	 *
	 * @access	public
	 * @param	integer	Message ID
	 * @return	string
	 */
	public function msg($errorId) {
		if ($this->iDebugLevel >= \DynamicPageListHooks::$debugMinLevels[$errorId]) {
			$args = func_get_args();
			array_shift($args);
			$val = '';
			if (array_key_exists(0, $args)) {
				$val = $args[0];
			}
			array_shift($args);

			if (\DynamicPageListHooks::isLikeIntersection()) {
				if ($errorId == \DynamicPageListHooks::FATAL_TOOMANYCATS)
					$text = wfMessage('intersection_toomanycats', $args)->text();
				else if ($errorId == \DynamicPageListHooks::FATAL_TOOFEWCATS)
					$text = wfMessage('intersection_toofewcats', $args)->text();
				else if ($errorId == \DynamicPageListHooks::WARN_NORESULTS)
					$text = wfMessage('intersection_noresults', $args)->text();
				else if ($errorId == \DynamicPageListHooks::FATAL_NOSELECTION)
					$text = wfMessage('intersection_noincludecats', $args)->text();
			}
			if (empty($text)) {
				$text = wfMessage('dpl_log_' . $errorId, $args)->text();
				$text = str_replace('$0', $val, $text);
			}
			$this->buffer[] = '<p>Extension:DynamicPageList (DPL), version ' . DPL_VERSION . ' : ' . $text . '</p>';
		}
		return false;
	}

	/**
	 * Get a "wrong parameter" message.
	 *
	 * @access	public
	 * @param	string	The parameter name
	 * @param	string	The unescaped input value
	 * @return	string	HTML error message
	 */
	public function msgWrongParam($paramvar, $val) {
		$errorId = \DynamicPageListHooks::WARN_WRONGPARAM;
		switch ($paramvar) {
			case 'namespace':
			case 'notnamespace':
				$errorId = \DynamicPageListHooks::FATAL_WRONGNS;
				break;
			case 'linksto':
			case 'notlinksto':
			case 'linksfrom':
				$errorId = \DynamicPageListHooks::FATAL_WRONGLINKSTO;
				break;
			case 'titlemaxlength':
			case 'includemaxlength':
				$errorId = \DynamicPageListHooks::WARN_WRONGPARAM_INT;
				break;
			default:
				$errorId = \DynamicPageListHooks::WARN_UNKNOWNPARAM;
				break;
		}

		if (Options::$options[$paramvar] != null) {
			$paramoptions = array_unique(Options::$options[$paramvar]);
			sort($paramoptions);
			$paramoptions = implode(' | ', $paramoptions);
		} else {
			$paramoptions = null;
		}

		return $this->escapeMsg($errorId, $paramvar, htmlspecialchars($val), Options::$options[$paramvar]['default'], $paramoptions);
	}
}
?>