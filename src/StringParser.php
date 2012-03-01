<?php
/**
 * Generic string parsing infrastructure
 *
 * These classes provide the means to parse any kind of string into a tree-like
 * memory structure. It would e.g. be possible to create an HTML parser based
 * upon this class.
 *
 * Version: 0.3.3
 *
 * @author Christian Seiler <spam@christian-seiler.de>
 * @copyright Christian Seiler 2004-2008
 * @package stringparser
 *
 * The MIT License
 *
 * Copyright (c) 2004-2009 Christian Seiler
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * String parser mode: Search for the next character
 * @see StringParser::_parserMode
 */
define ('STRINGPARSER_MODE_SEARCH', 1);
/**
 * String parser mode: Look at each character of the string
 * @see StringParser::_parserMode
 */
define ('STRINGPARSER_MODE_LOOP', 2);
/**
 * Filter type: Prefilter
 * @see StringParser::addFilter, StringParser::_prefilters
 */
define ('STRINGPARSER_FILTER_PRE', 1);
/**
 * Filter type: Postfilter
 * @see StringParser::addFilter, StringParser::_postfilters
 */
define ('STRINGPARSER_FILTER_POST', 2);

/**
 * Generic string parser class
 *
 * This is an abstract class for any type of string parser.
 *
 * @package stringparser
 */
class StringParser {
	/**
	 * String parser mode
	 *
	 * There are two possible modes: searchmode and loop mode. In loop mode
	 * every single character is looked at in a loop and it is then decided
	 * what action to take. This is the most straight-forward approach to
	 * string parsing but due to the nature of PHP as a scripting language,
	 * it can also cost performance. In search mode the class posseses a
	 * list of relevant characters for parsing and uses the
	 * {@link PHP_MANUAL#strpos strpos} function to search for the next
	 * relevant character. The search mode will be faster than the loop mode
	 * in most circumstances but it is also more difficult to implement.
	 * The subclass that does the string parsing itself will define which
	 * mode it will implement.
	 *
	 * @access protected
	 * @var int
	 * @see STRINGPARSER_MODE_SEARCH, STRINGPARSER_MODE_LOOP
	 */
	var $_parserMode = STRINGPARSER_MODE_SEARCH;

	/**
	 * Raw text
	 * @access protected
	 * @var string
	 */
	var $_text = '';

	/**
	 * Parse stack
	 * @access protected
	 * @var array
	 */
	var $_stack = array ();

	/**
	 * Current position in raw text
	 * @access protected
	 * @var integer
	 */
	var $_cpos = -1;

	/**
	 * Root node
	 * @access protected
	 * @var mixed
	 */
	var $_root = null;

	/**
	 * Length of the text
	 * @access protected
	 * @var integer
	 */
	var $_length = -1;

	/**
	 * Flag if this object is already parsing a text
	 *
	 * This flag is to prevent recursive calls to the parse() function that
	 * would cause very nasty things.
	 *
	 * @access protected
	 * @var boolean
	 */
	var $_parsing = false;

	/**
	 * Strict mode
	 *
	 * Whether to stop parsing if a parse error occurs.
	 *
	 * @access public
	 * @var boolean
	 */
	var $strict = false;

	/**
	 * Characters or strings to look for
	 * @access protected
	 * @var array
	 */
	var $_charactersSearch = array ();

	/**
	 * Characters currently allowed
	 *
	 * Note that this will only be evaluated in loop mode; in search mode
	 * this would ruin every performance increase. Note that only single
	 * characters are permitted here, no strings. Please also note that in
	 * loop mode, {@link StringParser::_charactersSearch _charactersSearch}
	 * is evaluated before this variable.
	 *
	 * If in strict mode, parsing is stopped if a character that is not
	 * allowed is encountered. If not in strict mode, the character is
	 * simply ignored.
	 *
	 * @access protected
	 * @var array
	 */
	var $_charactersAllowed = array ();

	/**
	 * Current parser status
	 * @access protected
	 * @var int
	 */
	var $_status = 0;

	/**
	 * Prefilters
	 * @access protected
	 * @var array
	 */
	var $_prefilters = array ();

	/**
	 * Postfilters
	 * @access protected
	 * @var array
	 */
	var $_postfilters = array ();

	/**
	 * Recently reparsed?
	 * @access protected
	 * @var bool
	 */
	var $_recentlyReparsed = false;

	/**
	 * Constructor
	 *
	 * @access public
	 */
	function StringParser () {
	}

	/**
	 * Add a filter
	 *
	 * @access public
	 * @param int $type The type of the filter
	 * @param mixed $callback The callback to call
	 * @return bool
	 * @see STRINGPARSER_FILTER_PRE, STRINGPARSER_FILTER_POST
	 */
	function addFilter ($type, $callback) {
		// make sure the function is callable
		if (!is_callable ($callback)) {
			return false;
		}

		switch ($type) {
			case STRINGPARSER_FILTER_PRE:
				$this->_prefilters[] = $callback;
				break;
			case STRINGPARSER_FILTER_POST:
				$this->_postfilters[] = $callback;
				break;
			default:
				return false;
		}

		return true;
	}

	/**
	 * Remove all filters
	 *
	 * @access public
	 * @param int $type The type of the filter or 0 for all
	 * @return bool
	 * @see STRINGPARSER_FILTER_PRE, STRINGPARSER_FILTER_POST
	 */
	function clearFilters ($type = 0) {
		switch ($type) {
			case 0:
				$this->_prefilters = array ();
				$this->_postfilters = array ();
				break;
			case STRINGPARSER_FILTER_PRE:
				$this->_prefilters = array ();
				break;
			case STRINGPARSER_FILTER_POST:
				$this->_postfilters = array ();
				break;
			default:
				return false;
		}
		return true;
	}

	/**
	 * This function parses the text
	 *
	 * @access public
	 * @param string $text The text to parse
	 * @return mixed Either the root object of the tree if no output method
	 *               is defined, the tree reoutput to e.g. a string or false
	 *               if an internal error occured, such as a parse error if
	 *               in strict mode or the object is already parsing a text.
	 */
	function parse ($text) {
		if ($this->_parsing) {
			return false;
		}
		$this->_parsing = true;
		$this->_text = $this->_applyPrefilters ($text);
		$this->_output = null;
		$this->_length = strlen ($this->_text);
		$this->_cpos = 0;
		unset ($this->_stack);
		$this->_stack = array ();
		if (is_object ($this->_root)) {
			StringParser_Node::destroyNode ($this->_root);
		}
		unset ($this->_root);
		$this->_root = new StringParser_Node_Root ();
		$this->_stack[0] =& $this->_root;

		$this->_parserInit ();

		$finished = false;

		while (!$finished) {
			switch ($this->_parserMode) {
				case STRINGPARSER_MODE_SEARCH:
					$res = $this->_searchLoop ();
					if (!$res) {
						$this->_parsing = false;
						return false;
					}
					break;
				case STRINGPARSER_MODE_LOOP:
					$res = $this->_loop ();
					if (!$res) {
						$this->_parsing = false;
						return false;
					}
					break;
				default:
					$this->_parsing = false;
					return false;
			}

			$res = $this->_closeRemainingBlocks ();
			if (!$res) {
				if ($this->strict) {
					$this->_parsing = false;
					return false;
				} else {
					$res = $this->_reparseAfterCurrentBlock ();
					if (!$res) {
						$this->_parsing = false;
						return false;
					}
					continue;
				}
			}
			$finished = true;
		}

		$res = $this->_modifyTree ();

		if (!$res) {
			$this->_parsing = false;
			return false;
		}

		$res = $this->_outputTree ();

		if (!$res) {
			$this->_parsing = false;
			return false;
		}

		if (is_null ($this->_output)) {
			$root =& $this->_root;
			unset ($this->_root);
			$this->_root = null;
			while (count ($this->_stack)) {
				unset ($this->_stack[count($this->_stack)-1]);
			}
			$this->_stack = array ();
			$this->_parsing = false;
			return $root;
		}

		$res = StringParser_Node::destroyNode ($this->_root);
		if (!$res) {
			$this->_parsing = false;
			return false;
		}
		unset ($this->_root);
		$this->_root = null;
		while (count ($this->_stack)) {
			unset ($this->_stack[count($this->_stack)-1]);
		}
		$this->_stack = array ();

		$this->_parsing = false;
		return $this->_output;
	}

	/**
	 * Apply prefilters
	 *
	 * It is possible to specify prefilters for the parser to do some
	 * manipulating of the string beforehand.
	 */
	function _applyPrefilters ($text) {
		foreach ($this->_prefilters as $filter) {
			if (is_callable ($filter)) {
				$ntext = call_user_func ($filter, $text);
				if (is_string ($ntext)) {
					$text = $ntext;
				}
			}
		}
		return $text;
	}

	/**
	 * Apply postfilters
	 *
	 * It is possible to specify postfilters for the parser to do some
	 * manipulating of the string afterwards.
	 */
	function _applyPostfilters ($text) {
		foreach ($this->_postfilters as $filter) {
			if (is_callable ($filter)) {
				$ntext = call_user_func ($filter, $text);
				if (is_string ($ntext)) {
					$text = $ntext;
				}
			}
		}
		return $text;
	}

	/**
	 * Abstract method: Manipulate the tree
	 * @access protected
	 * @return bool
	 */
	function _modifyTree () {
		return true;
	}

	/**
	 * Abstract method: Output tree
	 * @access protected
	 * @return bool
	 */
	function _outputTree () {
		// this could e.g. call _applyPostfilters
		return true;
	}

	/**
	 * Restart parsing after current block
	 *
	 * To achieve this the current top stack object is removed from the
	 * tree. Then the current item
	 *
	 * @access protected
	 * @return bool
	 */
	function _reparseAfterCurrentBlock () {
		// this should definitely not happen!
		if (($stack_count = count ($this->_stack)) < 2) {
			return false;
		}
		$topelem =& $this->_stack[$stack_count-1];

		$node_parent =& $topelem->_parent;
		// remove the child from the tree
		$res = $node_parent->removeChild ($topelem, false);
		if (!$res) {
			return false;
		}
		$res = $this->_popNode ();
		if (!$res) {
			return false;
		}

		// now try to get the position of the object
		if ($topelem->occurredAt < 0) {
			return false;
		}
		// HACK: could it be necessary to set a different status
		// if yes, how should this be achieved? Another member of
		// StringParser_Node?
		$this->_setStatus (0);
		$res = $this->_appendText ($this->_text{$topelem->occurredAt});
		if (!$res) {
			return false;
		}

		$this->_cpos = $topelem->occurredAt + 1;
		$this->_recentlyReparsed = true;

		return true;
	}

	/**
	 * Abstract method: Close remaining blocks
	 * @access protected
	 */
	function _closeRemainingBlocks () {
		// everything closed
		if (count ($this->_stack) == 1) {
			return true;
		}
		// not everything closed
		if ($this->strict) {
			return false;
		}
		while (count ($this->_stack) > 1) {
			$res = $this->_popNode ();
			if (!$res) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Abstract method: Initialize the parser
	 * @access protected
	 */
	function _parserInit () {
		$this->_setStatus (0);
	}

	/**
	 * Abstract method: Set a specific status
	 * @access protected
	 */
	function _setStatus ($status) {
		if ($status != 0) {
			return false;
		}
		$this->_charactersSearch = array ();
		$this->_charactersAllowed = array ();
		$this->_status = $status;
		return true;
	}

	/**
	 * Abstract method: Handle status
	 * @access protected
	 * @param int $status The current status
	 * @param string $needle The needle that was found
	 * @return bool
	 */
	function _handleStatus ($status, $needle) {
		$this->_appendText ($needle);
		$this->_cpos += strlen ($needle);
		return true;
	}

	/**
	 * Search mode loop
	 * @access protected
	 * @return bool
	 */
	function _searchLoop () {
		$i = 0;
		while (1) {
			// make sure this is false!
			$this->_recentlyReparsed = false;

			list ($needle, $offset) = $this->_strpos ($this->_charactersSearch, $this->_cpos);
			// parser ends here
			if ($needle === false) {
				// original status 0 => no problem
				if (!$this->_status) {
					break;
				}
				// not in original status? strict mode?
				if ($this->strict) {
					return false;
				}
				// break up parsing operation of current node
				$res = $this->_reparseAfterCurrentBlock ();
				if (!$res) {
					return false;
				}
				continue;
			}
			// get subtext
			$subtext = substr ($this->_text, $this->_cpos, $offset - $this->_cpos);
			$res = $this->_appendText ($subtext);
			if (!$res) {
				return false;
			}
			$this->_cpos = $offset;
			$res = $this->_handleStatus ($this->_status, $needle);
			if (!$res && $this->strict) {
				return false;
			}
			if (!$res) {
				$res = $this->_appendText ($this->_text{$this->_cpos});
				if (!$res) {
					return false;
				}
				$this->_cpos++;
				continue;
			}
			if ($this->_recentlyReparsed) {
				$this->_recentlyReparsed = false;
				continue;
			}
			$this->_cpos += strlen ($needle);
		}

		// get subtext
		if ($this->_cpos < strlen ($this->_text)) {
			$subtext = substr ($this->_text, $this->_cpos);
			$res = $this->_appendText ($subtext);
			if (!$res) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Loop mode loop
	 *
	 * @access protected
	 * @return bool
	 */
	function _loop () {
		// HACK: This method ist not yet implemented correctly, the code below
		// DOES NOT WORK! Do not use!

		return false;
		/*
		while ($this->_cpos < $this->_length) {
			$needle = $this->_strDetect ($this->_charactersSearch, $this->_cpos);

			if ($needle === false) {
				// not found => see if character is allowed
				if (!in_array ($this->_text{$this->_cpos}, $this->_charactersAllowed)) {
					if ($strict) {
						return false;
					}
					// ignore
					continue;
				}
				// lot's of FIXMES
				$res = $this->_appendText ($this->_text{$this->_cpos});
				if (!$res) {
					return false;
				}
			}

			// get subtext
			$subtext = substr ($this->_text, $offset, $offset - $this->_cpos);
			$res = $this->_appendText ($subtext);
			if (!$res) {
				return false;
			}
			$this->_cpos = $subtext;
			$res = $this->_handleStatus ($this->_status, $needle);
			if (!$res && $strict) {
				return false;
			}
		}
		// original status 0 => no problem
		if (!$this->_status) {
			return true;
		}
		// not in original status? strict mode?
		if ($this->strict) {
			return false;
		}
		// break up parsing operation of current node
		$res = $this->_reparseAfterCurrentBlock ();
		if (!$res) {
			return false;
		}
		// this will not cause an infinite loop because
		// _reparseAfterCurrentBlock will increase _cpos by one!
		return $this->_loop ();
		*/
	}

	/**
	 * Abstract method Append text depending on current status
	 * @access protected
	 * @param string $text The text to append
	 * @return bool On success, the function returns true, else false
	 */
	function _appendText ($text) {
		if (!strlen ($text)) {
			return true;
		}
		// default: call _appendToLastTextChild
		return $this->_appendToLastTextChild ($text);
	}

	/**
	 * Append text to last text child of current top parser stack node
	 * @access protected
	 * @param string $text The text to append
	 * @return bool On success, the function returns true, else false
	 */
	function _appendToLastTextChild ($text) {
		$scount = count ($this->_stack);
		if ($scount == 0) {
			return false;
		}
		return $this->_stack[$scount-1]->appendToLastTextChild ($text);
	}

	/**
	 * Searches {@link StringParser::_text _text} for every needle that is
	 * specified by using the {@link PHP_MANUAL#strpos strpos} function. It
	 * returns an associative array with the key <code>'needle'</code>
	 * pointing at the string that was found first and the key
	 * <code>'offset'</code> pointing at the offset at which the string was
	 * found first. If no needle was found, the <code>'needle'</code>
	 * element is <code>false</code> and the <code>'offset'</code> element
	 * is <code>-1</code>.
	 *
	 * @access protected
	 * @param array $needles
	 * @param int $offset
	 * @return array
	 * @see StringParser::_text
	 */
	function _strpos ($needles, $offset) {
		$cur_needle = false;
		$cur_offset = -1;

		if ($offset < strlen ($this->_text)) {
			foreach ($needles as $needle) {
				$n_offset = strpos ($this->_text, $needle, $offset);
				if ($n_offset !== false && ($n_offset < $cur_offset || $cur_offset < 0)) {
					$cur_needle = $needle;
					$cur_offset = $n_offset;
				}
			}
		}

		return array ($cur_needle, $cur_offset, 'needle' => $cur_needle, 'offset' => $cur_offset);
	}

	/**
	 * Detects a string at the current position
	 *
	 * @access protected
	 * @param array $needles The strings that are to be detected
	 * @param int $offset The current offset
	 * @return mixed The string that was detected or the needle
	 */
	function _strDetect ($needles, $offset) {
		foreach ($needles as $needle) {
			$l = strlen ($needle);
			if (substr ($this->_text, $offset, $l) == $needle) {
				return $needle;
			}
		}
		return false;
	}


	/**
	 * Adds a node to the current parse stack
	 *
	 * @access protected
	 * @param object $node The node that is to be added
	 * @return bool True on success, else false.
	 * @see StringParser_Node, StringParser::_stack
	 */
	function _pushNode (&$node) {
		$stack_count = count ($this->_stack);
		$max_node =& $this->_stack[$stack_count-1];
		if (!$max_node->appendChild ($node)) {
			return false;
		}
		$this->_stack[$stack_count] =& $node;
		return true;
	}

	/**
	 * Removes a node from the current parse stack
	 *
	 * @access protected
	 * @return bool True on success, else false.
	 * @see StringParser_Node, StringParser::_stack
	 */
	function _popNode () {
		$stack_count = count ($this->_stack);
		unset ($this->_stack[$stack_count-1]);
		return true;
	}

	/**
	 * Execute a method on the top element
	 *
	 * @access protected
	 * @return mixed
	 */
	function _topNode () {
		$args = func_get_args ();
		if (!count ($args)) {
			return; // oops?
		}
		$method = array_shift ($args);
		$stack_count = count ($this->_stack);
		$method = array (&$this->_stack[$stack_count-1], $method);
		if (!is_callable ($method)) {
			return; // oops?
		}
		return call_user_func_array ($method, $args);
	}

	/**
	 * Get a variable of the top element
	 *
	 * @access protected
	 * @return mixed
	 */
	function _topNodeVar ($var) {
		$stack_count = count ($this->_stack);
		return $this->_stack[$stack_count-1]->$var;
	}
}
