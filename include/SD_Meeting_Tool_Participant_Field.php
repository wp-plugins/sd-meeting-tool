<?php
/**
	Will mostly be the column namn, using the column's computer name as the name and the human-readable name as a description.
	
	@brief		The field of a participant. 
	@see		SD_Meeting_Tool_Participants
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Participant_Field
{
	/**
		Name of the field.
		
		Will most probably be a single word or two lowercase words with an underscore.
		
		No umlauts or special chars, just a-z, 0-9 and an underscore or two.
		
		example: first_name, last2names
		
		@var	$name
	**/
	public $name;
	
	/**
		Description of field.
		
		Human-reable string.
		
		example: "First name", "Last two names"
		
		@var	$description
	**/
	public $description;
	
	/**
		Type of field.
		
		Can be anything the modules want: text, textfield, integer, datettime, barcode, etc.
		
		Default is "text".
		$var	$type
	**/
	public $type = 'text';
	
	/**
		Value.
		
		Used when just about to display a participant field value, using the sd_mt_display_participant_field filter.
		Otherwise unused.
		
		@var	$value
	**/
	public $value;
	
	/**
		Whether this field comes from the participant database or is tacked on by an extra plugin.
		@var	$is_native;
	**/
	public $is_native = true;
	
	/**
		Returns this field's slug.
		
		return	The sanitized slug of this field.
	**/
	public function get_slug()
	{
		return self::slug( $this->name );
	}
	
	/**
		Returns the sanitized slug of the name.
		
		@return	The slug of the field's name.
	**/
	public static function slug( $field_name )
	{
		$slug = sanitize_title( $field_name );
		$slug = str_replace( '-', '_', $slug );
		return $slug;
	}
}
