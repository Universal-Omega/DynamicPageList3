<?php

namespace DPL;

use Article;
use CommentStoreComment;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use ReadOnlyError;
use RequestContext;
use Title;

class UpdateArticle {
	/**
	 * this fucntion hast three tasks (depending on $exec):
	 * (1) show an edit dialogue for template fields (exec = edit)
	 * (2) set template parameters to values specified in the query (exec=set)v
	 * (2) preview the source code including any changes of these parameters made in the edit form or with other changes (exec=preview)
	 * (3) save the article with the changed value set or with other changes (exec=save)
	 * "other changes" means that a regexp can be applied to the source text or arbitrary text can be
	 * inserted before or after a pattern occuring in the text
	 */
	public static function updateArticleByRule( $title, $text, $rulesText ) {
		// we use ; as command delimiter; \; stands for a semicolon
		// \n is translated to a real linefeed
		$rulesText = str_replace( ";", '°', $rulesText );
		$rulesText = str_replace( '\°', ';', $rulesText );
		$rulesText = str_replace( "\\n", "\n", $rulesText );
		$rules = explode( '°', $rulesText );
		$exec = 'edit';
		$replaceThis = '';
		$replacement = '';
		$after = '';
		$insertionAfter = '';
		$before = '';
		$insertionBefore = '';
		$template = '';
		$parameter = [];
		$value = [];
		$afterparm = [];
		$format = [];
		$preview = [];
		$save = [];
		$tooltip = [];
		$optional = [];

		$lastCmd = '';
		$message = '';
		$summary = '';
		$editForm = false;
		$action = '';
		$hidden = [];
		$legendPage = '';
		$instructionPage = '';
		$table = '';
		$fieldFormat = '';

		$nr = -1;
		foreach ( $rules as $rule ) {
			if ( preg_match( '/^\s*#/', $rule ) > 0 ) {
				continue;
			}

			$rule = preg_replace( '/^[\s]*/', '', $rule );
			$cmd = preg_split( "/ +/", $rule, 2 );

			if ( count( $cmd ) > 1 ) {
				$arg = $cmd[1];
			} else {
				$arg = '';
			}

			$cmd[0] = trim( $cmd[0] );

			// after ... insert ..., before ... insert ...
			if ( $cmd[0] == 'before' ) {
				$before = $arg;
				$lastCmd = 'B';
			}
			if ( $cmd[0] == 'after' ) {
				$after = $arg;
				$lastCmd = 'A';
			}

			if ( $cmd[0] == 'insert' && $lastCmd != '' ) {
				if ( $lastCmd == 'A' ) {
					$insertionAfter = $arg;
				}

				if ( $lastCmd == 'B' ) {
					$insertionBefore = $arg;
				}
			}

			if ( $cmd[0] == 'template' ) {
				$template = $arg;
			}

			if ( $cmd[0] == 'parameter' ) {
				$nr++;
				$parameter[$nr] = $arg;
				if ( $nr > 0 ) {
					$afterparm[$nr] = [
						$parameter[$nr - 1]
					];

					$n = $nr - 1;
					while ( $n > 0 && array_key_exists( $n, $optional ) ) {
						$n--;
						$afterparm[$nr][] = $parameter[$n];
					}
				}
			}

			if ( $cmd[0] == 'value' ) {
				$value[$nr] = $arg;
			}

			if ( $cmd[0] == 'format' ) {
				$format[$nr] = $arg;
			}

			if ( $cmd[0] == 'tooltip' ) {
				$tooltip[$nr] = $arg;
			}

			if ( $cmd[0] == 'optional' ) {
				$optional[$nr] = true;
			}

			if ( $cmd[0] == 'afterparm' ) {
				$afterparm[$nr] = [
					$arg
				];
			}

			if ( $cmd[0] == 'legend' ) {
				$legendPage = $arg;
			}

			if ( $cmd[0] == 'instruction' ) {
				$instructionPage = $arg;
			}

			if ( $cmd[0] == 'table' ) {
				$table = $arg;
			}

			if ( $cmd[0] == 'field' ) {
				$fieldFormat = $arg;
			}

			if ( $cmd[0] == 'replace' ) {
				$replaceThis = $arg;
			}

			if ( $cmd[0] == 'by' ) {
				$replacement = $arg;
			}

			if ( $cmd[0] == 'editform' ) {
				$editForm = $arg;
			}

			if ( $cmd[0] == 'action' ) {
				$action = $arg;
			}

			if ( $cmd[0] == 'hidden' ) {
				$hidden[] = $arg;
			}

			if ( $cmd[0] == 'preview' ) {
				$preview[] = $arg;
			}

			if ( $cmd[0] == 'save' ) {
				$save[] = $arg;
			}

			if ( $cmd[0] == 'summary' ) {
				$summary = $arg;
			}

			if ( $cmd[0] == 'exec' ) {
				$exec = $arg; // desired action (set or edit or preview)
			}
		}

		if ( $summary == '' ) {
			$summary .= "\nbulk update:";
			if ( $replaceThis != '' ) {
				$summary .= "\n replace $replaceThis\n by $replacement";
			}

			if ( $before != '' ) {
				$summary .= "\n before $before\n insertionBefore";
			}

			if ( $after != '' ) {
				$summary .= "\n after $after\n insertionAfter";
			}
		}

		// perform changes to the wiki source text =======================================

		if ( $replaceThis != '' ) {
			$text = preg_replace( "$replaceThis", $replacement, $text );
		}

		if ( $insertionBefore != '' && $before != '' ) {
			$text = preg_replace( "/($before)/", $insertionBefore . '\1', $text );
		}

		if ( $insertionAfter != '' && $after != '' ) {
			$text = preg_replace( "/($after)/", '\1' . $insertionAfter, $text );
		}

		// deal with template parameters =================================================

		global $wgRequest;

		$user = RequestContext::getMain()->getUser();

		if ( $template != '' ) {
			if ( $exec == 'edit' ) {
				$tpv = self::getTemplateParmValues( $text, $template );
				$legendText = '';

				if ( $legendPage != '' ) {
					$legendTitle = '';

					$parser = clone MediaWikiServices::getInstance()->getParser();

					LST::text( $parser, $legendPage, $legendTitle, $legendText );
					$legendText = preg_replace( '/^.*?\<section\s+begin\s*=\s*legend\s*\/\>/s', '', $legendText );
					$legendText = preg_replace( '/\<section\s+end\s*=\s*legend\s*\/\>.*/s', '', $legendText );
				}

				$instructionText = '';
				$instructions = [];

				if ( $instructionPage != '' ) {
					$instructionTitle = '';

					$parser = clone MediaWikiServices::getInstance()->getParser();

					LST::text( $parser, $instructionPage, $instructionTitle, $instructionText );
					$instructions = self::getTemplateParmValues( $instructionText, 'Template field' );
				}

				// construct an edit form containing all template invocations
				$form = "<html><form method=post action=\"$action\" $editForm>\n";

				foreach ( $tpv as $call => $tplValues ) {
					$form .= "<table $table>\n";
					foreach ( $parameter as $nr => $parm ) {
						// try to extract legend from the docs of the template
						$myToolTip = '';

						if ( array_key_exists( $nr, $tooltip ) ) {
							$myToolTip = $tooltip[$nr];
						}

						$myInstruction = '';
						$myType = '';

						foreach ( $instructions as $instruct ) {
							if ( array_key_exists( 'field', $instruct ) && $instruct['field'] == $parm ) {
								if ( array_key_exists( 'doc', $instruct ) ) {
									$myInstruction = $instruct['doc'];
								}

								if ( array_key_exists( 'type', $instruct ) ) {
									$myType = $instruct['type'];
								}
								break;
							}
						}

						$myFormat = '';
						if ( array_key_exists( $nr, $format ) ) {
							$myFormat = $format[$nr];
						}

						$myOptional = array_key_exists( $nr, $optional );
						if ( $legendText != '' && $myToolTip == '' ) {
							$myToolTip = preg_replace( '/^.*\<section\s+begin\s*=\s*' . preg_quote( $parm, '/' ) . '\s*\/\>/s', '', $legendText );

							if ( strlen( $myToolTip ) == strlen( $legendText ) ) {
								$myToolTip = '';
							} else {
								$myToolTip = preg_replace( '/\<section\s+end\s*=\s*' . preg_quote( $parm, '/' ) . '\s*\/\>.*/s', '', $myToolTip );
							}
						}

						$myValue = '';
						if ( array_key_exists( $parm, $tpv[$call] ) ) {
							$myValue = $tpv[$call][$parm];
						}

						$form .= self::editTemplateCall( $text, $template, $call, $parm, $myType, $myValue, $myFormat, $myToolTip, $myInstruction, $myOptional, $fieldFormat );
					}

					$form .= "</table>\n<br/><br/>";
				}

				foreach ( $hidden as $hide ) {
					$form .= "<input type='hidden' " . $hide . " />";
				}

				$form .= "<input type='hidden' name='wpEditToken' value='{$user->getEditToken()}'/>";
				foreach ( $preview as $prev ) {
					$form .= "<input type='submit' " . $prev . " /> ";
				}

				$form .= "</form></html>\n";

				return $form;
			} elseif ( $exec == 'set' || $exec == 'preview' ) {
				// loop over all invocations and parameters, this could be improved to enhance performance
				$matchCount = 10;

				for ( $call = 0; $call < 10; $call++ ) {
					foreach ( $parameter as $nr => $parm ) {
						// set parameters to values specified in the dpl source or get them from the http request
						if ( $exec == 'set' ) {
							$myvalue = $value[$nr];
						} else {
							if ( $call >= $matchCount ) {
								break;
							}
							$myValue = $wgRequest->getVal( urlencode( $call . '_' . $parm ), '' );
						}

						$myOptional = array_key_exists( $nr, $optional );
						$myAfterParm = [];

						if ( array_key_exists( $nr, $afterparm ) ) {
							$myAfterParm = $afterparm[$nr];
						}

						$text = self::updateTemplateCall( $matchCount, $text, $template, $call, $parm, $myValue ?? '', $myAfterParm, $myOptional );
					}

					if ( $exec == 'set' ) {
						break;
					}
				}
			}
		}

		if ( $exec == 'set' ) {
			return self::doUpdateArticle( $title, $text, $summary );
		} elseif ( $exec == 'preview' ) {
			global $wgScriptPath, $wgRequest;

			$titleX = Title::newFromText( $title );
			$articleX = new Article( $titleX );

			$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();

			$form = '<html>
	<form id="editform" name="editform" method="post" action="' . $wgScriptPath . '/index.php?title=' . urlencode( $title ) . '&action=submit" enctype="multipart/form-data">
		<input type="hidden" value="" name="wpSection" />
		<input type="hidden" value="' . wfTimestampNow() . '" name="wpStarttime" />
		<input type="hidden" value="' . $articleX->getPage()->getTimestamp() . '" name="wpEdittime" />
		<input type="hidden" value="" name="wpScrolltop" id="wpScrolltop" />
		<textarea tabindex="1" accesskey="," name="wpTextbox1" id="wpTextbox1" rows="' . $userOptionsLookup->getIntOption( $user, 'rows' ) . '" cols="' . $userOptionsLookup->getIntOption( $user, 'cols' ) . '" >' . htmlspecialchars( $text ) . '</textarea>
		<input type="hidden" name="wpSummary value="' . $summary . '" id="wpSummary" />
		<input name="wpAutoSummary" type="hidden" value="" />
		<input id="wpSave" name="wpSave" type="submit" value="Save page" accesskey="s" title="Save your changes [s]" />
		<input type="hidden" value="' . $wgRequest->getVal( 'token' ) . '" name="wpEditToken" />
	</form>
</html>';
			return $form;
		}

		return "exec must be one of the following: edit, preview, set";
	}

	private static function doUpdateArticle( $title, $text, $summary ) {
		global $wgRequest, $wgOut;

		$user = RequestContext::getMain()->getUser();

		if ( !$user->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) ) ) {
			$wgOut->addWikiMsg( 'sessionfailure' );

			return 'session failure';
		}

		$titleX = Title::newFromText( $title );
		$permission_errors = MediaWikiServices::getInstance()->getPermissionManager()->getPermissionErrors( 'edit', $user, $titleX );

		if ( count( $permission_errors ) == 0 ) {
			$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();

			$page = $wikiPageFactory->newFromTitle( $titleX );
			$updater = $page->newPageUpdater( $user );
			$content = $page->getContentHandler()->makeContent( $text, $titleX );
			$updater->setContent( SlotRecord::MAIN, $content );
			$comment = CommentStoreComment::newUnsavedComment( $summary );

			$updater->saveRevision(
				$comment,
				EDIT_UPDATE | EDIT_DEFER_UPDATES | EDIT_AUTOSUMMARY
			);

			$wgOut->redirect( $titleX->getFullUrl( $page->isRedirect() ? 'redirect=no' : '' ) );

			return '';
		} else {
			$wgOut->showPermissionsErrorPage( $permission_errors );

			return 'permission error';
		}
	}

	private static function editTemplateCall( $text, $template, $call, $parameter, $type, $value, $format, $legend, $instruction, $optional, $fieldFormat ) {
		$matches = [];
		$nlCount = preg_match_all( '/\n/', $value, $matches );

		if ( $nlCount > 0 ) {
			$rows = $nlCount + 1;
		} else {
			$rows = floor( strlen( $value ) / 50 ) + 1;
		}

		if ( preg_match( '/rows\s*=/', $format ) <= 0 ) {
			$format .= " rows=$rows";
		}

		$cols = 50;
		if ( preg_match( '/cols\s*=/', $format ) <= 0 ) {
			$format .= " cols=$cols";
		}

		$textArea = "<textarea name=\"" . urlencode( $call . '_' . $parameter ) . "\" $format/>" . htmlspecialchars( $value ) . "</textarea>";

		return str_replace( '%NAME%', htmlspecialchars( str_replace( '_', ' ', $parameter ) ), str_replace( '%TYPE%', $type, str_replace( '%INPUT%', $textArea, str_replace( '%LEGEND%', "</html>" . htmlspecialchars( $legend ) . "<html>", str_replace( '%INSTRUCTION%', "</html>" . htmlspecialchars( $instruction ) . "<html>", $fieldFormat ) ) ) ) );
	}

	/**
	 * return an array of template invocations; each element is an associative array of parameter and value
	 */
	private static function getTemplateParmValues( $text, $template ) {
		$matches = [];
		$noMatches = preg_match_all( '/\{\{\s*' . preg_quote( $template, '/' ) . '\s*[|}]/i', $text, $matches, PREG_OFFSET_CAPTURE );

		if ( $noMatches <= 0 ) {
			return '';
		}

		$textLen = strlen( $text );
		$tval = [];
		$call = -1;

		foreach ( $matches as $matchA ) {
			foreach ( $matchA as $matchB ) {
				$match = $matchB[0];
				$start = $matchB[1];
				$tval[++$call] = [];
				$nr = 0;
				$parmValue = '';
				$parmName = '';
				$parm = '';

				if ( $match[strlen( $match ) - 1] == '}' ) {
					break;
				}

				// search to the end of the template call
				$cbrackets = 2;

				for ( $i = $start + strlen( $match ); $i < $textLen; $i++ ) {
					$c = $text[$i];
					if ( $c == '{' || $c == '[' ) {
						$cbrackets++;
					}

					if ( $c == '}' || $c == ']' ) {
						$cbrackets--;
					}

					if ( ( $cbrackets == 2 && $c == '|' ) || ( $cbrackets == 1 && $c == '}' ) ) {
						// parameter (name or value) found
						if ( $parmName == '' ) {
							$tval[$call][++$nr] = trim( $parm );
						} else {
							$tval[$call][$parmName] = trim( $parmValue );
						}

						$parmName = '';
						$parmValue = '';
						$parm = '';

						continue;
					} else {
						if ( $parmName == '' ) {
							if ( $c == '=' ) {
								$parmName = trim( $parm );
							}
						} else {
							$parmValue .= $c;
						}
					}

					$parm .= $c;
					if ( $cbrackets == 0 ) {
						break;
					}
				}
			}
		}

		return $tval;
	}

	/*
	 * Changes a single parameter value within a certain call of a template
	 */
	private static function updateTemplateCall( &$matchCount, $text, $template, $call, $parameter, $value, $afterParm, $optional ) {
		// if parameter is optional and value is empty we leave everything as it is (i.e. we do not remove the parm)
		if ( $optional && $value == '' ) {
			return $text;
		}

		$matches = [];
		$noMatches = preg_match_all( '/\{\{\s*' . preg_quote( $template, '/' ) . '\s*[|}]/i', $text, $matches, PREG_OFFSET_CAPTURE );

		if ( $noMatches <= 0 ) {
			return $text;
		}

		$beginSubst = -1;
		$endSubst = -1;
		$posInsertAt = 0;
		$apNrLast = 1000;

		foreach ( $matches as $matchA ) {
			$matchCount = count( $matchA );

			foreach ( $matchA as $occurence => $matchB ) {
				if ( $occurence < $call ) {
					continue;
				}

				$match = $matchB[0];
				$start = $matchB[1];

				if ( $match[strlen( $match ) - 1] == '}' ) {
					// template was called without parameters, add new parameter and value
					// append parameter and value
					$beginSubst = 0;
					$endSubst = 0;
					$substitution = "|$parameter = $value";
					break;
				} else {
					// there is already a list of parameters; we search to the end of the template call
					$cbrackets = 2;
					$parm = '';
					$pos = $start + strlen( $match ) - 1;
					$textLen = strlen( $text );

					for ( $i = $pos + 1; $i < $textLen; $i++ ) {
						$c = $text[$i];

						if ( $c == '{' || $c == '[' ) {
							$cbrackets++; // we count both types of brackets
						}

						if ( $c == '}' || $c == ']' ) {
							$cbrackets--;
						}

						if ( ( $cbrackets == 2 && $c == '|' ) || ( $cbrackets == 1 && $c == '}' ) ) {
							// parameter (name / value) found

							$token = explode( '=', $parm, 2 );
							if ( count( $token ) == 2 ) {
								// we need a pair of name / value
								$parmName = trim( $token[0] );

								if ( $parmName == $parameter ) {
									// we found the parameter, now replace the current value
									$parmValue = trim( $token[1] );

									if ( $parmValue == $value ) {
										break; // no need to change when values are identical
									}

									// keep spaces;
									if ( $parmValue == '' ) {
										if ( strlen( $token[1] ) > 0 && $token[1][strlen( $token[1] ) - 1] == "\n" ) {
											$substitution = str_replace( "\n", $value . "\n", $token[1] );
										} else {
											$substitution = $value . $token[1];
										}
									} else {
										$substitution = str_replace( $parmValue, $value, $token[1] );
									}

									$beginSubst = $pos + strlen( $token[0] ) + 2;
									$endSubst = $i;
									break;
								} else {
									foreach ( $afterParm as $apNr => $ap ) {
										// store position for insertion
										if ( $parmName == $ap && $apNr < $apNrLast ) {
											$posInsertAt = $i;
											$apNrLast = $apNr;
											break;
										}
									}
								}
							}

							if ( $c == '}' ) {
								// end of template call reached, insert at stored position or here
								if ( $posInsertAt != 0 ) {
									$beginSubst = $posInsertAt;
								} else {
									$beginSubst = $i;
								}

								$substitution = "|$parameter = $value";
								if ( $text[$beginSubst - 1] == "\n" ) {
									--$beginSubst;
									$substitution = "\n" . $substitution;
								}

								$endSubst = $beginSubst;
								break;
							}

							$pos = $i;
							$parm = '';
						} else {
							$parm .= $c;
						}

						if ( $cbrackets == 0 ) {
							break;
						}
					}
				}
				break;
			}
			break;
		}

		if ( $beginSubst < 0 ) {
			return $text;
		}

		return substr( $text, 0, $beginSubst ) . ( $substitution ?? '' ) . substr( $text, $endSubst );
	}

	public static function deleteArticleByRule( $title, $text, $rulesText ) {
		global $wgOut;

		// return "deletion of articles by DPL is disabled.";

		// we use ; as command delimiter; \; stands for a semicolon
		// \n is translated to a real linefeed
		$rulesText = str_replace( ";", '°', $rulesText );
		$rulesText = str_replace( '\°', ';', $rulesText );
		$rulesText = str_replace( "\\n", "\n", $rulesText );
		$rules = explode( '°', $rulesText );
		$exec = false;
		$message = '';
		$reason = '';

		foreach ( $rules as $rule ) {
			if ( preg_match( '/^\s*#/', $rule ) > 0 ) {
				continue; // # is comment symbol
			}

			$rule = preg_replace( '/^[\s]*/', '', $rule );
			$cmd = preg_split( "/ +/", $rule, 2 );

			if ( count( $cmd ) > 1 ) {
				$arg = $cmd[1];
			} else {
				$arg = '';
			}

			$cmd[0] = trim( $cmd[0] );

			if ( $cmd[0] == 'reason' ) {
				$reason = $arg;
			}

			// we execute only if "exec" is given, otherwise we merely show what would be done
			if ( $cmd[0] == 'exec' ) {
				$exec = true;
			}
		}

		$reason .= "\nbulk delete by DPL query";

		$titleX = Title::newFromText( $title );

		if ( $exec ) {
			$user = RequestContext::getMain()->getUser();

			# Check permissions
			$permission_errors = MediaWikiServices::getInstance()->getPermissionManager()->getPermissionErrors( 'delete', $user, $titleX );
			$isReadOnly = MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly();

			if ( count( $permission_errors ) > 0 ) {
				$wgOut->showPermissionsErrorPage( $permission_errors );
				return 'permission error';
			} elseif ( $isReadOnly ) {
				throw new ReadOnlyError;
			} else {
				$articleX = new Article( $titleX );
				$articleX->doDelete( $reason );
			}
		} else {
			$message .= "set 'exec yes' to delete &#160; &#160; <big>'''$title'''</big>\n";
		}

		$message .= "<pre><nowiki>\n{$text}</nowiki></pre>"; // <pre><nowiki>\n";

		return $message;
	}
}
