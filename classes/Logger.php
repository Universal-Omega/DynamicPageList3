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
	public $debugLevel = 0;

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
	 * @param	integer	Debug Level
	 * @return	void
	 */
	public function __construct($debugLevel) {
		//@TODO: Fix up debug leveling and uncomment debug test in msg() below.  Leaving it broken on purpose during alpha testing.
		$this->debugLevel = $debugLevel;
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
	public function msg() {
		//@TODO: Test how bad/wrong parameter/option messages are returned.
		//if ($this->iDebugLevel >= \DynamicPageListHooks::$debugMinLevels[$errorId]) {
			$args = func_get_args();
			$errorId = array_shift($args);

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
				$text = wfMessage('dpl_log_'.$errorId, $args)->text();
			}
			$this->buffer[] = '<p>Extension:DynamicPageList (DPL), version '.DPL_VERSION.' : '.$text.'</p>';
		//}
		return false;
	}
}
?>