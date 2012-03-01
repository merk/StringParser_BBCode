<?php
/**
 * BB code string parsing class
 *
 * Version: 0.3.3
 *
 * @author Christian Seiler <spam@christian-seiler.de>
 * @copyright Christian Seiler 2004-2008
 * @package stringparser
 *
 * The MIT License
 *
 * Copyright (c) 2004-2008 Christian Seiler
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
 * Node type: Unknown node
 * @see StringParser_Node::_type
 */
define ('STRINGPARSER_NODE_UNKNOWN', 0);

/**
 * Node type: Root node
 * @see StringParser_Node::_type
 */
define ('STRINGPARSER_NODE_ROOT', 1);

/**
 * Node type: Text node
 * @see StringParser_Node::_type
 */
define ('STRINGPARSER_NODE_TEXT', 2);

/**
 * Global value that is a counter of string parser node ids. Compare it to a
 * sequence in databases.
 * @var int
 */
$GLOBALS['__STRINGPARSER_NODE_ID'] = 0;

/**
 * Generic string parser node class
 *
 * This is an abstract class for any type of node that is used within the
 * string parser. General warning: This class contains code regarding references
 * that is very tricky. Please do not touch this code unless you exactly know
 * what you are doing. Incorrect handling of references may cause PHP to crash
 * with a segmentation fault! You have been warned.
 *
 * @package stringparser
 */
class StringParser_Node {
	/**
	 * The type of this node.
	 *
	 * There are three standard node types: root node, text node and unknown
	 * node. All node types are integer constants. Any node type of a
	 * subclass must be at least 32 to allow future developements.
	 *
	 * @access protected
	 * @var int
	 * @see STRINGPARSER_NODE_ROOT, STRINGPARSER_NODE_TEXT
	 * @see STRINGPARSER_NODE_UNKNOWN
	 */
	var $_type = STRINGPARSER_NODE_UNKNOWN;

	/**
	 * The node ID
	 *
	 * This ID uniquely identifies this node. This is needed when searching
	 * for a specific node in the children array. Please note that this is
	 * only an internal variable and should never be used - not even in
	 * subclasses and especially not in external data structures. This ID
	 * has nothing to do with any type of ID in HTML oder XML.
	 *
	 * @access protected
	 * @var int
	 * @see StringParser_Node::_children
	 */
	var $_id = -1;

	/**
	 * The parent of this node.
	 *
	 * It is either null (root node) or a reference to the parent object.
	 *
	 * @access protected
	 * @var mixed
	 * @see StringParser_Node::_children
	 */
	var $_parent = null;

	/**
	 * The children of this node.
	 *
	 * It contains an array of references to all the children nodes of this
	 * node.
	 *
	 * @access protected
	 * @var array
	 * @see StringParser_Node::_parent
	 */
	var $_children = array ();

	/**
	 * Occured at
	 *
	 * This defines the position in the parsed text where this node occurred
	 * at. If -1, this value was not possible to be determined.
	 *
	 * @access public
	 * @var int
	 */
	var $occurredAt = -1;

	/**
	 * Constructor
	 *
	 * Currently, the constructor only allocates a new ID for the node and
	 * assigns it.
	 *
	 * @access public
	 * @param int $occurredAt The position in the text where this node
	 *                        occurred at. If not determinable, it is -1.
	 * @global __STRINGPARSER_NODE_ID
	 */
	function StringParser_Node ($occurredAt = -1) {
		$this->_id = $GLOBALS['__STRINGPARSER_NODE_ID']++;
		$this->occurredAt = $occurredAt;
	}

	/**
	 * Type of the node
	 *
	 * This function returns the type of the node
	 *
	 * @access public
	 * @return int
	 */
	function type () {
		return $this->_type;
	}

	/**
	 * Prepend a node
	 *
	 * @access public
	 * @param object $node The node to be prepended.
	 * @return bool On success, the function returns true, else false.
	 */
	function prependChild (&$node) {
		if (!is_object ($node)) {
			return false;
		}

		// root nodes may not be children of other nodes!
		if ($node->_type == STRINGPARSER_NODE_ROOT) {
			return false;
		}

		// if node already has a parent
		if ($node->_parent !== false) {
			// remove node from there
			$parent =& $node->_parent;
			if (!$parent->removeChild ($node, false)) {
				return false;
			}
			unset ($parent);
		}

		$index = count ($this->_children) - 1;
		// move all nodes to a new index
		while ($index >= 0) {
			// save object
			$object =& $this->_children[$index];
			// we have to unset it because else it will be
			// overridden in in the loop
			unset ($this->_children[$index]);
			// put object to new position
			$this->_children[$index+1] =& $object;
			$index--;
		}
		$this->_children[0] =& $node;
		return true;
	}

	/**
	 * Append text to last text child
	 * @access public
	 * @param string $text The text to append
	 * @return bool On success, the function returns true, else false
	 */
	function appendToLastTextChild ($text) {
		$ccount = count ($this->_children);
		if ($ccount == 0 || $this->_children[$ccount-1]->_type != STRINGPARSER_NODE_TEXT) {
			$ntextnode = new StringParser_Node_Text ($text);
			return $this->appendChild ($ntextnode);
		} else {
			$this->_children[$ccount-1]->appendText ($text);
			return true;
		}
	}

	/**
	 * Append a node to the children
	 *
	 * This function appends a node to the children array(). It
	 * automatically sets the {@link StrinParser_Node::_parent _parent}
	 * property of the node that is to be appended.
	 *
	 * @access public
	 * @param object $node The node that is to be appended.
	 * @return bool On success, the function returns true, else false.
	 */
	function appendChild (&$node) {
		if (!is_object ($node)) {
			return false;
		}

		// root nodes may not be children of other nodes!
		if ($node->_type == STRINGPARSER_NODE_ROOT) {
			return false;
		}

		// if node already has a parent
		if ($node->_parent !== null) {
			// remove node from there
			$parent =& $node->_parent;
			if (!$parent->removeChild ($node, false)) {
				return false;
			}
			unset ($parent);
		}

		// append it to current node
		$new_index = count ($this->_children);
		$this->_children[$new_index] =& $node;
		$node->_parent =& $this;
		return true;
	}

	/**
	 * Insert a node before another node
	 *
	 * @access public
	 * @param object $node The node to be inserted.
	 * @param object $reference The reference node where the new node is
	 *                          to be inserted before.
	 * @return bool On success, the function returns true, else false.
	 */
	function insertChildBefore (&$node, &$reference) {
		if (!is_object ($node)) {
			return false;
		}

		// root nodes may not be children of other nodes!
		if ($node->_type == STRINGPARSER_NODE_ROOT) {
			return false;
		}

		// is the reference node a child?
		$child = $this->_findChild ($reference);

		if ($child === false) {
			return false;
		}

		// if node already has a parent
		if ($node->_parent !== null) {
			// remove node from there
			$parent =& $node->_parent;
			if (!$parent->removeChild ($node, false)) {
				return false;
			}
			unset ($parent);
		}

		$index = count ($this->_children) - 1;
		// move all nodes to a new index
		while ($index >= $child) {
			// save object
			$object =& $this->_children[$index];
			// we have to unset it because else it will be
			// overridden in in the loop
			unset ($this->_children[$index]);
			// put object to new position
			$this->_children[$index+1] =& $object;
			$index--;
		}
		$this->_children[$child] =& $node;
		return true;
	}

	/**
	 * Insert a node after another node
	 *
	 * @access public
	 * @param object $node The node to be inserted.
	 * @param object $reference The reference node where the new node is
	 *                          to be inserted after.
	 * @return bool On success, the function returns true, else false.
	 */
	function insertChildAfter (&$node, &$reference) {
		if (!is_object ($node)) {
			return false;
		}

		// root nodes may not be children of other nodes!
		if ($node->_type == STRINGPARSER_NODE_ROOT) {
			return false;
		}

		// is the reference node a child?
		$child = $this->_findChild ($reference);

		if ($child === false) {
			return false;
		}

		// if node already has a parent
		if ($node->_parent !== false) {
			// remove node from there
			$parent =& $node->_parent;
			if (!$parent->removeChild ($node, false)) {
				return false;
			}
			unset ($parent);
		}

		$index = count ($this->_children) - 1;
		// move all nodes to a new index
		while ($index >= $child + 1) {
			// save object
			$object =& $this->_children[$index];
			// we have to unset it because else it will be
			// overridden in in the loop
			unset ($this->_children[$index]);
			// put object to new position
			$this->_children[$index+1] =& $object;
			$index--;
		}
		$this->_children[$child + 1] =& $node;
		return true;
	}

	/**
	 * Remove a child node
	 *
	 * This function removes a child from the children array. A parameter
	 * tells the function whether to destroy the child afterwards or not.
	 * If the specified node is not a child of this node, the function will
	 * return false.
	 *
	 * @access public
	 * @param mixed $child The child to destroy; either an integer
	 *                     specifying the index of the child or a reference
	 *                     to the child itself.
	 * @param bool $destroy Destroy the child afterwards.
	 * @return bool On success, the function returns true, else false.
	 */
	function removeChild (&$child, $destroy = false) {
		if (is_object ($child)) {
			// if object: get index
			$object =& $child;
			unset ($child);
			$child = $this->_findChild ($object);
			if ($child === false) {
				return false;
			}
		} else {
			// remove reference on $child
			$save = $child;
			unset($child);
			$child = $save;

			// else: get object
			if (!isset($this->_children[$child])) {
				return false;
			}
			$object =& $this->_children[$child];
		}

		// store count for later use
		$ccount = count ($this->_children);

		// index out of bounds
		if (!is_int ($child) || $child < 0 || $child >= $ccount) {
			return false;
		}

		// inkonsistency
		if ($this->_children[$child]->_parent === null ||
		    $this->_children[$child]->_parent->_id != $this->_id) {
			return false;
		}

		// $object->_parent = null would equal to $this = null
		// as $object->_parent is a reference to $this!
		// because of this, we have to unset the variable to remove
		// the reference and then redeclare the variable
		unset ($object->_parent); $object->_parent = null;

		// we have to unset it because else it will be overridden in
		// in the loop
		unset ($this->_children[$child]);

		// move all remaining objects one index higher
		while ($child < $ccount - 1) {
			// save object
			$obj =& $this->_children[$child+1];
			// we have to unset it because else it will be
			// overridden in in the loop
			unset ($this->_children[$child+1]);
			// put object to new position
			$this->_children[$child] =& $obj;
			// UNSET THE OBJECT!
			unset ($obj);
			$child++;
		}

		if ($destroy) {
			return StringParser_Node::destroyNode ($object);
			unset ($object);
		}
		return true;
	}

	/**
	 * Get the first child of this node
	 *
	 * @access public
	 * @return mixed
	 */
	function &firstChild () {
		$ret = null;
		if (!count ($this->_children)) {
			return $ret;
		}
		return $this->_children[0];
	}

	/**
	 * Get the last child of this node
	 *
	 * @access public
	 * @return mixed
	 */
	function &lastChild () {
		$ret = null;
		$c = count ($this->_children);
		if (!$c) {
			return $ret;
		}
		return $this->_children[$c-1];
	}

	/**
	 * Destroy a node
	 *
	 * @access public
	 * @static
	 * @param object $node The node to destroy
	 * @return bool True on success, else false.
	 */
	static function destroyNode (&$node) {
		if ($node === null) {
			return false;
		}
		// if parent exists: remove node from tree!
		if ($node->_parent !== null) {
			$parent =& $node->_parent;
			// directly return that result because the removeChild
			// method will call destroyNode again
			return $parent->removeChild ($node, true);
		}

		// node has children
		while (count ($node->_children)) {
			$child = 0;
			// remove first child until no more children remain
			if (!$node->removeChild ($child, true)) {
				return false;
			}
			unset($child);
		}

		// now call the nodes destructor
		if (!$node->_destroy ()) {
			return false;
		}

		// now just unset it and prey that there are no more references
		// to this node
		unset ($node);

		return true;
	}

	/**
	 * Destroy this node
	 *
	 *
	 * @access protected
	 * @return bool True on success, else false.
	 */
	function _destroy () {
		return true;
	}

	/**
	 * Find a child node
	 *
	 * This function searches for a node in the own children and returns
	 * the index of the node or false if the node is not a child of this
	 * node.
	 *
	 * @access protected
	 * @param mixed $child The node to look for.
	 * @return mixed The index of the child node on success, else false.
	 */
	function _findChild (&$child) {
		if (!is_object ($child)) {
			return false;
		}

		$ccount = count ($this->_children);
		for ($i = 0; $i < $ccount; $i++) {
			if ($this->_children[$i]->_id == $child->_id) {
				return $i;
			}
		}

		return false;
	}

	/**
	 * Checks equality of this node and another node
	 *
	 * @access public
	 * @param mixed $node The node to be compared with
	 * @return bool True if the other node equals to this node, else false.
	 */
	function equals (&$node) {
		return ($this->_id == $node->_id);
	}

	/**
	 * Determines whether a criterium matches this node
	 *
	 * @access public
	 * @param string $criterium The criterium that is to be checked
	 * @param mixed $value The value that is to be compared
	 * @return bool True if this node matches that criterium
	 */
	function matchesCriterium ($criterium, $value) {
		return false;
	}

	/**
	 * Search for nodes with a certain criterium
	 *
	 * This may be used to implement getElementsByTagName etc.
	 *
	 * @access public
	 * @param string $criterium The criterium that is to be checked
	 * @param mixed $value The value that is to be compared
	 * @return array All subnodes that match this criterium
	 */
	function &getNodesByCriterium ($criterium, $value) {
		$nodes = array ();
		$node_ctr = 0;
		for ($i = 0; $i < count ($this->_children); $i++) {
			if ($this->_children[$i]->matchesCriterium ($criterium, $value)) {
				$nodes[$node_ctr++] =& $this->_children[$i];
			}
			$subnodes = $this->_children[$i]->getNodesByCriterium ($criterium, $value);
			if (count ($subnodes)) {
				$subnodes_count = count ($subnodes);
				for ($j = 0; $j < $subnodes_count; $j++) {
					$nodes[$node_ctr++] =& $subnodes[$j];
					unset ($subnodes[$j]);
				}
			}
			unset ($subnodes);
		}
		return $nodes;
	}

	/**
	 * Search for nodes with a certain criterium and return the count
	 *
	 * Similar to getNodesByCriterium
	 *
	 * @access public
	 * @param string $criterium The criterium that is to be checked
	 * @param mixed $value The value that is to be compared
	 * @return int The number of subnodes that match this criterium
	 */
	function getNodeCountByCriterium ($criterium, $value) {
		$node_ctr = 0;
		for ($i = 0; $i < count ($this->_children); $i++) {
			if ($this->_children[$i]->matchesCriterium ($criterium, $value)) {
				$node_ctr++;
			}
			$subnodes = $this->_children[$i]->getNodeCountByCriterium ($criterium, $value);
			$node_ctr += $subnodes;
		}
		return $node_ctr;
	}

	/**
	 * Dump nodes
	 *
	 * This dumps a tree of nodes
	 *
	 * @access public
	 * @param string $prefix The prefix that is to be used for indentation
	 * @param string $linesep The line separator
	 * @param int $level The initial level of indentation
	 * @return string
	 */
	function dump ($prefix = " ", $linesep = "\n", $level = 0) {
		$str = str_repeat ($prefix, $level) . $this->_id . ": " . $this->_dumpToString () . $linesep;
		for ($i = 0; $i < count ($this->_children); $i++) {
			$str .= $this->_children[$i]->dump ($prefix, $linesep, $level + 1);
		}
		return $str;
	}

	/**
	 * Dump this node to a string
	 *
	 * @access protected
	 * @return string
	 */
	function _dumpToString () {
		if ($this->_type == STRINGPARSER_NODE_ROOT) {
			return "root";
		}
		return (string)$this->_type;
	}
}