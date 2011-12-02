<?php
class sd_tree
{
	var $root_node;
	var $node_orphans = array();			// In case a node is supposed to be added to a node that hasn't added yet.
	
	public function __construct()
	{
		$root_node_id = hash( 'sha512', time() . rand(0, time() ) );
		$this->root_node = new sd_tree_node( $root_node_id, null );
	}
	
	/**
		Adds a node to the tree.
		
		@param	$key	Unique key of this node.
		@param	$data	Mixed array to store with this key.
		@param	$parent_key		Parent key, if any, under which to store this key.
	**/
	public function add( $key, $data, $parent_key = null )
	{
		if ( $parent_key === null )		// Parentless nodes go straight to the root.
			$this->root_node->add_subnode( $key, $data );
		else
		{
			$node_parent = $this->find_node( $this->root_node, $parent_key );
			if ( $node_parent !== false )
				$node_parent->add_subnode( $key, $data );
			else
				// This node has a parent node that hasn't been added yet.
				$this->node_orphans[$parent_key][$key] = $data; 
		}
		
		// Orphan cleanup.
		if (isset( $this->node_orphans[$key]))
		{
			foreach( $this->node_orphans[$key] as $orphan_key=>$orphan_data )
			{
				$this->add( $orphan_key, $orphan_data, $key );
				unset( $this->node_orphans[$key][$orphan_key] );
			}
		}
	}
	
/*
	public function contains_data( $key, $data_key, $data_value )
	{
		// Start the node of this key
		$this_node = $this->find_node( $this->root_node, $key );

		if ( $this_node !== false )
		{
			// Do we have this data?
			$nodeData = $this_node->get_data(); 
			if ( $nodeData[$data_key] == $data_value )
				return $this_node;

			foreach ( $this_node->get_subnodes() as $index=>$ignore )
			{
				$result = $this->contains_data( $index, $data_key, $data_value );
				if ( $result !== false )
					return $result;
			}
		} 

		return false;
	}
*/
	/**
		Returns the data of a specific key.
		
		@param	$key	Key to look for.
		@return			The key's data, else false if the key wasn't found.
	**/
	public function get_data( $key )
	{
		$found_node = $this->find_node( $this->root_node, $key );
		if ( $found_node !== false )
			return $found_node->get_data();
		return false;
	}
	
	/**
		Sets the data of a key.
		
		@param	$key		Key whose data we're replacing. 
		@param	$new_data	The new data.
	**/
	public function set_data( $key, $new_data )
	{
		$found_node =& $this->find_node( $this->root_node, $key );
		if ( $found_node !== false )
			$found_node->set_data( $new_data );
	}
	
	/**
		Returns a list of subnode keys of the root or an existing node.
		
		@param	$key		Get the subnodes keys of this key.
		@return				Array of subnode keys, else false if the key doesn't exist.
	**/
	public function get_subnodes( $key = null )
	{
		if ( $key === null )
			$key = $this->root_node->get_key();

		$found_node = $this->find_node( $this->root_node, $key );
		if ( $found_node !== false )
			if ( $found_node->has_subnodes() )
				return array_keys( $found_node->get_subnodes() );
			else
				return array();
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- PRIVATE
	// --------------------------------------------------------------------------------------------

	private function add_node( &$intoNode, $key, $data, $parent )
	{
		if ( $intoNode->get_key() === $parent )		// The node we are currently looking at has the key
		{
			$intoNode->add_subnode( $key, $Data );
			return true;
		}
		else
		{
			foreach( $intoNode->get_subnodes() as $tempkey => $value )
				if ( $this->add_node( $intoNode->sub_nodes[$tempkey], $key, $data, $parent ) === true)
					return true;
		}
		return false;
	}
	
	/**
		Tries to find a node with a specific key.
		
		Acts as a recursive function of itself.
		
		@param	$node		Node whose subnodes to search.
		@param	$key		Key to search for.
		@return				The found node, else false.
	*/
	private function &find_node( &$node, $key )
	{	
		if ( $node->get_key() === $key )
			return $node;
		else
			foreach ( $node->get_subnodes() as $index=>$ignore )
			{
				$subNode =& $node->sub_nodes[$index];
				$result =& $this->find_node( $subNode, $key );
				if ( $result !== false )
					return $result;
			}
		$falsie = false;	// Otherwise notice: "Only variable references should be returned by reference" 
		return $falsie;		// Nothing found here.
	}

/*	
	/** Does $key contain $subKey? 
	private function contains_key( $key, $subKey )
	{
		// Find the node that has this key.
		$baseNode = $this->find_node( $this->root_node, $key );
		if ( $baseNode !== false )
			return $this->find_node( $baseNode, $subKey ) !== false;
		else
			return false;
	}
*/	
}

class sd_tree_node
{
	var $key;
	var $data;
	var $sub_nodes = array();

	function sd_tree_node( $newKey=null, $new_data=null )
	{
		$this->key = $newKey;
		$this->data = $new_data;
	}
	
	function add_subnode( $newKey, $new_data )
	{
		$this->sub_nodes[$newKey] = new sd_tree_node( $newKey, $new_data );
	}
	
	function get_key()					{	return $this->key;											}
	function &get_data()				{	return $this->data;											}
	function set_data( $new_data )		{	$this->data = $new_data;									}
	function has_subnodes()			{	return count( $this->sub_nodes )>0;							}
	function get_subnodes()			{	return $this->has_subnodes() ? $this->sub_nodes : array();	}
}
