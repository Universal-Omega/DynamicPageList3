<?php
/**
 * DynamicPageList3
 * DPL Logger Class
 *
 * @author		IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 **/
namespace DPL;

class Logger {
	/**
	 * Buffer of debug messages.
	 *
	 * @var		array
	 */
	private $buffer = [];

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
		$args = func_get_args();
		$errorId = array_shift($args);
		$errorLevel = floor($errorId / 1000);
		$errorMessageId = $errorId % 1000;
		if (\DynamicPageListHooks::getDebugLevel() >= $errorLevel) {
			if (\DynamicPageListHooks::isLikeIntersection()) {
				if ($errorId == \DynamicPageListHooks::FATAL_TOOMANYCATS) {
					$text = wfMessage('intersection_toomanycats', $args)->text();
				} elseif ($errorId == \DynamicPageListHooks::FATAL_TOOFEWCATS) {
					$text = wfMessage('intersection_toofewcats', $args)->text();
				} elseif ($errorId == \DynamicPageListHooks::WARN_NORESULTS) {
					$text = wfMessage('intersection_noresults', $args)->text();
				} elseif ($errorId == \DynamicPageListHooks::FATAL_NOSELECTION) {
					$text = wfMessage('intersection_noincludecats', $args)->text();
				}
			}
			if (empty($text)) {
				$text = wfMessage('dpl_log_' . $errorMessageId, $args)->text();
			}
			$this->buffer[] = '<p>Extension:DynamicPageList (DPL), version ' . DPL_VERSION . ': ' . $text . '</p>';
		}
		return false;
	}
}
