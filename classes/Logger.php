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

	static public $loaded = true;

	public $iDebugLevel;

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		$this->iDebugLevel = Options::$options['debug']['default'];
	}

	/**
	 * Get a message, with optional parameters
	 * Parameters from user input must be escaped for HTML *before* passing to this function
	 *
	 * @access	public
	 * @param	integer	Message ID
	 * @return	string
	 */
	public function msg($msgid) {
		if ($this->iDebugLevel >= \ExtDynamicPageList::$debugMinLevels[$msgid]) {
			$args = func_get_args();
			array_shift($args);
			$val = '';
			if (array_key_exists(0, $args)) {
				$val = $args[0];
			}
			array_shift($args);
			/**
			 * @todo add a DPL id to identify the DPL tag that generates the message, in case of multiple DPLs in the page
			 */
			$text = '';
			if (\ExtDynamicPageList::$behavingLikeIntersection) {
				if ($msgid == \ExtDynamicPageList::FATAL_TOOMANYCATS)
					$text = wfMessage('intersection_toomanycats', $args)->text();
				else if ($msgid == \ExtDynamicPageList::FATAL_TOOFEWCATS)
					$text = wfMessage('intersection_toofewcats', $args)->text();
				else if ($msgid == \ExtDynamicPageList::WARN_NORESULTS)
					$text = wfMessage('intersection_noresults', $args)->text();
				else if ($msgid == \ExtDynamicPageList::FATAL_NOSELECTION)
					$text = wfMessage('intersection_noincludecats', $args)->text();
			}
			if ($text == '') {
				$text = wfMessage('dpl_log_' . $msgid, $args)->text();
				$text = str_replace('$0', $val, $text);
			}
			return '<p>Extension:DynamicPageList (DPL), version ' . DPL_VERSION . ' : ' . $text . '</p>';
		}
		return '';
	}

	/**
	 * Get a message. 
	 * Parameters may be unescaped, this function will escape them for HTML.
	 *
	 * @access	public
	 * @param	integer	Message ID
	 * @return	string
	 */
	public function escapeMsg($msgid) {
		$args = func_get_args();
		$args = array_map('htmlspecialchars', $args);
		return call_user_func_array([$this, 'msg'], $args);
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
		$msgid = \ExtDynamicPageList::WARN_WRONGPARAM;
		switch ($paramvar) {
			case 'namespace':
			case 'notnamespace':
				$msgid = \ExtDynamicPageList::FATAL_WRONGNS;
				break;
			case 'linksto':
			case 'notlinksto':
			case 'linksfrom':
				$msgid = \ExtDynamicPageList::FATAL_WRONGLINKSTO;
				break;
			case 'titlemaxlength':
			case 'includemaxlength':
				$msgid = \ExtDynamicPageList::WARN_WRONGPARAM_INT;
				break;
			default:
				$msgid = \ExtDynamicPageList::WARN_UNKNOWNPARAM;
				break;
		}

		if (Options::$options[$paramvar] != null) {
			$paramoptions = array_unique(Options::$options[$paramvar]);
			sort($paramoptions);
			$paramoptions = implode(' | ', $paramoptions);
		} else {
			$paramoptions = null;
		}

		return $this->escapeMsg($msgid, $paramvar, htmlspecialchars($val), Options::$options[$paramvar]['default'], $paramoptions);
	}
}
?>