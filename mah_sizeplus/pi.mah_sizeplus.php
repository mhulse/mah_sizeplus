<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// --------------------------------------------------------------------

$plugin_info = array(
	'pi_name' => 'SizePlus',
	'pi_version' => '2.0',
	'pi_author' => 'Micky Hulse',
	'pi_author_url' => 'http://hulse.me/',
	'pi_description' => 'Get image size plus a few extras.',
	'pi_usage' => Mah_sizeplus::usage()
);

// --------------------------------------------------------------------

/**
 * Mah_SizePlus Class
 * 
 * @package       ExpressionEngine
 * @category      Plugin
 * @author        Micky Hulse
 * @copyright     Copyright (c) 2010, Micky Hulse
 * @link          http://hulse.me/
 */

class Mah_sizeplus {
	
	//--------------------------------------------------------------------------
	//
	// Set web root path here (optional):
	//
	//--------------------------------------------------------------------------
	
	var $root_path  = '';   // Example: '/home/user/public_html', with no trailing slash.
	
	//--------------------------------------------------------------------------
	//
	// Program:
	//
	//--------------------------------------------------------------------------
	
	var $debug = FALSE;
	var $prm_nms = array();
	var $dflt_nms = array();
	var $return_data = '';
	var $file = '';
	var $url_parts = array();
	var $remote = FALSE;
	var $domain = '';
	var $path = '';
	var $file_parts = array();
	var $query = array();
	var $base = '';
	var $dimensions = array();
	var $silent_fail = array(0, 0, 'mime' => '');
	var $ratio = 0;
	var $cond = array();
	var $append_query = 'spq_';
	var $er_prefix = 'Error: ';
	var $er_root = 'Could not determine a root path';
	var $er_file = 'Invalid "file" parameter';
	var $er_url_info = 'Faild parsing url';
	var $er_not_file = '"file" parameter is not a file';
	var $er_file_parts = 'Problem with file name and extension';
	
	/**
	 * Constructor
	 *
	 * @access     public
	 * @return     void
	 */
	
	function Mah_sizeplus()
	{
		
		# Performance Guidelines:
		# http://expressionengine.com/public_beta/docs/development/guidelines/performance.html
		# General Style and Syntax:
		# http://expressionengine.com/public_beta/docs/development/guidelines/general.html
		
		// ----------------------------------
		// Call super object:
		// ----------------------------------

		$this->EE =& get_instance();
		
		// ----------------------------------
		// Parameters:
		// ----------------------------------
		
		$this->prm_nms = array(
			'file' => 'file',
			'debug' => 'debug'
		);
		
		// ----------------------------------
		// Plugin variables:
		// ----------------------------------
		
		$this->dflt_nms = array(
			'url' => 'sp_url',
			'root' => 'sp_root',
			'domain' => 'sp_domain',
			'base' => 'sp_base',
			'width' => 'sp_width',
			'height' => 'sp_height',
			'name' => 'sp_name',
			'ext' => 'sp_ext',
			'ratio' => 'sp_ratio',
			'flash' => 'sp_flash'
		);
		
		// ----------------------------------
		// Debug?
		// ----------------------------------
		
		$this->debug = (strtolower($this->EE->TMPL->fetch_param($this->prm_nms['debug'])) == 'yes') ? TRUE : FALSE;
		
		// ----------------------------------
		// Root path:
		// ----------------------------------
		
		$this->root_path = ($this->_str_check($this->root_path) === TRUE) ? $this->root_path : $this->_doc_root();
		
		if ($this->_str_check($this->root_path) === TRUE)
		{
			
			// ----------------------------------
			// File:
			// ----------------------------------
			
			$this->file = $this->EE->TMPL->fetch_param($this->prm_nms['file']);
			
			if ($this->_str_check($this->file) === TRUE)
			{
				
				if (strpos($this->file, '\\') !== FALSE) $this->file = str_replace('\\', '/', $this->file);
				$this->file = $this->EE->functions->remove_double_slashes($this->file);
				
				// ----------------------------------
				// URL info:
				// ----------------------------------
				
				$this->url_parts = $this->_parse_url($this->file);
				
				if ($this->url_parts !== FALSE)
				{
					
					// ----------------------------------
					// Remote?
					// ----------------------------------
					 
					$this->remote = (strpos($this->file, 'http') !== FALSE) ? TRUE : FALSE;
					
					// ----------------------------------
					// Absolute or relative path?
					// ----------------------------------
					
					$this->path = ($this->remote === TRUE) ? $this->file : $this->root_path . $this->url_parts['path'];
					
					// ----------------------------------
					// Validation:
					// ----------------------------------
					
					if (($this->remote === TRUE) || (is_file($this->path) === TRUE))
					{
						
						// ----------------------------------
						// Domain?
						// ----------------------------------
						
						if ($this->remote === TRUE)
						{
							
							# Protocol:
							$this->domain = (isset($this->url_parts['scheme'])) ? $this->url_parts['scheme'] : 'http'; // "http" by default.
							$this->domain .= '://';
							# Username & password:
							if ((isset($this->url_parts['user'])) && (isset($this->url_parts['pass']))) $this->domain .= $this->url_parts['user'] . ':' . $this->url_parts['pass'] . '@'; // http://username:password@assets.registerguard.com
							# Host name:
							$this->domain .= $this->url_parts['host'];
							
						}
						
						// ----------------------------------
						// File name & extension:
						// ----------------------------------
						
						$this->file_parts = $this->_get_file_parts($this->file);
						
						if ($this->file_parts !== FALSE)
						{
							
							// ----------------------------------
							// Query string?
							// ----------------------------------
							
							if (isset($this->url_parts['query'])) $this->query = $this->_parse_str($this->url_parts['query']);
							
							// ----------------------------------
							// Base path:
							// ----------------------------------
							
							if (isset($this->url_parts['path'])) $this->base = $this->_safe_dir_name($this->url_parts['path']); // Path without file name and extension.
							
							// ----------------------------------
							// Dimensions:
							// ----------------------------------
							
							$this->dimensions = $this->_get_image_size($this->path);
							if ($this->dimensions === FALSE) $this->dimensions = $this->silent_fail;
							
							// ----------------------------------
							// Aspect ratio:
							// ----------------------------------
							
							$this->ratio = (($this->dimensions[0] + $this->dimensions[1]) > 0) ? $this->_round_digits($this->dimensions[0] / $this->dimensions[1], 3) : $this->ratio; // round(width / height) with a precision of 3.
							
							// ----------------------------------
							// Setup conditionals:
							// ----------------------------------
							
							$this->cond[$this->dflt_nms['url']] = $this->file;
							$this->cond[$this->dflt_nms['root']] = $this->path;
							$this->cond[$this->dflt_nms['domain']] = $this->domain;
							$this->cond[$this->dflt_nms['base']] = $this->base;
							$this->cond[$this->dflt_nms['width']] = $this->dimensions[0];
							$this->cond[$this->dflt_nms['height']] = $this->dimensions[1];
							$this->cond[$this->dflt_nms['name']] = $this->file_parts[0];
							$this->cond[$this->dflt_nms['ext']] = $this->file_parts[1];
							$this->cond[$this->dflt_nms['ratio']] = $this->ratio;
							$this->cond[$this->dflt_nms['flash']] = ($this->dimensions['mime'] == 'application/x-shockwave-flash') ? TRUE : FALSE;
							
							# User-defined:
							if (($this->query !== FALSE) && ($this->_arr_check($this->query) === TRUE))
							{
								foreach($this->query as $key => $val)
								{
									$this->cond[$this->append_query . $key] = ($val) ? $val : FALSE;
								}
							}
							
							// ----------------------------------
							// Process conditionals:
							// ----------------------------------
							
							$this->EE->TMPL->tagdata = $this->EE->functions->prep_conditionals($this->EE->TMPL->tagdata, $this->cond);
							
							// ----------------------------------
							// Process plugin single variables:
							// ----------------------------------
							
							foreach($this->EE->TMPL->var_single as $key => $val)
							{
								if ($key == $this->dflt_nms['url']) { $this->EE->TMPL->tagdata = $this->EE->TMPL->swap_var_single($val, $this->file, $this->EE->TMPL->tagdata); }
								if ($key == $this->dflt_nms['root']) { $this->EE->TMPL->tagdata = $this->EE->TMPL->swap_var_single($val, $this->path, $this->EE->TMPL->tagdata); }
								if ($key == $this->dflt_nms['domain']) { $this->EE->TMPL->tagdata = $this->EE->TMPL->swap_var_single($val, $this->domain, $this->EE->TMPL->tagdata); }
								if ($key == $this->dflt_nms['base']) { $this->EE->TMPL->tagdata = $this->EE->TMPL->swap_var_single($val, $this->base, $this->EE->TMPL->tagdata); }
								if ($key == $this->dflt_nms['width']) { $this->EE->TMPL->tagdata = $this->EE->TMPL->swap_var_single($val, $this->dimensions[0], $this->EE->TMPL->tagdata); }
								if ($key == $this->dflt_nms['height']) { $this->EE->TMPL->tagdata = $this->EE->TMPL->swap_var_single($val, $this->dimensions[1], $this->EE->TMPL->tagdata); }
								if ($key == $this->dflt_nms['name']) { $this->EE->TMPL->tagdata = $this->EE->TMPL->swap_var_single($val, $this->file_parts[0], $this->EE->TMPL->tagdata); }
								if ($key == $this->dflt_nms['ext']) { $this->EE->TMPL->tagdata = $this->EE->TMPL->swap_var_single($val, $this->file_parts[1], $this->EE->TMPL->tagdata); }
								if ($key == $this->dflt_nms['ratio']) { $this->EE->TMPL->tagdata = $this->EE->TMPL->swap_var_single($val, $this->ratio, $this->EE->TMPL->tagdata); }
							}
							
							# User-defined:
							if (($this->query !== FALSE) && ($this->_arr_check($this->query) === TRUE))
							{
								foreach($this->EE->TMPL->var_single as $key => $val)
								{
									foreach($this->query as $k => $v)
									{
										if ($key == $this->append_query . $k) $this->EE->TMPL->tagdata = $this->EE->TMPL->swap_var_single($val, $v, $this->EE->TMPL->tagdata);
									}
								}
							}
							
							// ----------------------------------
							// Return:
							// ----------------------------------
							
							$this->return_data = $this->EE->TMPL->tagdata;
							
						}
						else
						{
							$this->return_data = ($this->debug) ? $this->er_prefix . $this->er_file_parts : ''; // $this->file_parts
						}
						
					}
					else
					{
						$this->return_data = ($this->debug) ? $this->er_prefix . $this->er_not_file : ''; // $this->remote, $this->path
					}
						
				}
				else
				{
					$this->return_data = ($this->debug) ? $this->er_prefix . $this->er_url_info : ''; // $this->url_parts
				}
				
			}
			else
			{
				$this->return_data = ($this->debug) ? $this->er_prefix . $this->er_file : ''; // $this->file
			}
			
		}
		else
		{
			$this->return_data = ($this->debug) ? $this->er_prefix . $this->er_root : ''; // $this->root_path
		}
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Determine root path
	 * 
	 * Returns document root path.
	 * 
	 * @access     private
	 * @return     string
	 */
	
	function _doc_root()
	{
		return (array_key_exists('DOCUMENT_ROOT', $_ENV)) ? $_ENV['DOCUMENT_ROOT'] : $_SERVER['DOCUMENT_ROOT'];
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Checks string validity
	 * 
	 * Checks is variable is set and is string.
	 * 
	 * @access     private
	 * @param      string
	 * @return     boolean
	 */
	
	function _str_check($x = NULL)
	{
		return (($x !== NULL) && (isset($x)) && (is_string($x)) && (strlen(trim($x)))) ? TRUE : FALSE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Check array validity
	 * 
	 * Checks array and tests for any key values.
	 * 
	 * @access     private
	 * @param      array
	 * @return     boolean
	 */
	
	function _arr_check($x = NULL)
	{
		return (($x !== NULL) && (is_array($x)) && (count($x) > 0)) ? TRUE : FALSE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Get image size.
	 * 
	 * Wrapper for PHP's getimagesize().
	 * 
	 * @access     private
	 * @param      string
	 * @return     string
	 */
	
	function _get_image_size($x = NULL)
	{
		# [0] = Width
		# [1] = Height
		# [2] = Image Type Flag
		# [3] = width="xxx" height="xxx"
		# [4] = channels (PHP >= 4.3.0)
		# [5] = bits (PHP >= 4.3.0)
		# [6] = mime (PHP >= 4.3.0)
		if ($this->_str_check($x)) return @getimagesize(rtrim($x));
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Parse URL
	 * 
	 * Wrapper for PHP's parse_url().
	 * 
	 * @access     private
	 * @param      string
	 * @return     array
	 */
	
	function _parse_url($x = NULL)
	{
		# [scheme] => http
		# [host] => hostname
		# [user] => username
		# [pass] => password
		# [path] => /path
		# [query] => arg=value
		# [fragment] => anchor
		if ($this->_str_check($x)) return @parse_url(rtrim($x));
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Parse string
	 * 
	 * Wrapper for PHP's parse_str().
	 * 
	 * @access     private
	 * @param      string
	 * @return     array
	 */
	
	function _parse_str($x = NULL)
	{
		if ($this->_str_check($x)) {
			@parse_str($x, $return);
			return $return;
		}
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Get file parts
	 * 
	 * Returns file name and extension.
	 * 
	 * @access     private
	 * @param      string
	 * @return     array
	 */
	
	function _get_file_parts($x = NULL)
	{
		if ($this->_str_check($x)) return explode('.', preg_replace('/\?(.*)/', '', basename($x)));
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Safe dir name
	 * 
	 * Wrapper for PHP's dirname().
	 * http://us2.php.net/manual/en/function.dirname.php#87637
	 * 
	 * @access     private
	 * @param      string
	 * @return     string
	 */
	
	function _safe_dir_name($x = NULL)
	{
		if ($this->_str_check($x)) return (dirname($x) != '/') ? dirname($x) . '/' : '';
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Round digits
	 * 
	 * Wrapper for PHP's round(), with precision support.
	 * http://www.php.net/manual/en/function.round.php#86330
	 * 
	 * @access     private
	 * @param      float
	 * @param      integer
	 * @return     float
	 */
	
	function _round_digits($x = NULL, $p = 0)
	{
		if ($this->_is_natural($x)) {
			$p_factor = ($p == 0) ? 1 : pow(10, $p);
			return round($x * $p_factor) / $p_factor;
		}
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Is natural?
	 * 
	 * Checks if variable a natural number.
	 * Zero is often exclude from the natural numbers, that's why there's the second parameter.
	 * 
	 * @access     private
	 * @param      string/integer
	 * @param      boolean
	 * @return     boolean
	 */
	
	function _is_natural($x = NULL, $zero = FALSE)
	{
		return (((string) $x === (string) (int) $x) && (intval($x) < (($zero) ? 0 : 1))) ? FALSE : TRUE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Usage
	 * 
	 * Plugin Usage.
	 * 
	 * @access     public
	 * @return     string
	 */
	
	function usage()
	{
		
		ob_start();
		
		?>
		
		Please see forum thread for more information:
		http://expressionengine.com/forums/viewthread/52587/#607501
		
		<?php
		
		$buffer = ob_get_contents();
		
		ob_end_clean(); 
		
		return $buffer;
		
	}
	
	// --------------------------------------------------------------------
	
}

/* End of file pi.mah_sizeplus.php */
/* Location: ./system/expressionengine/mah_sizeplus/pi.mah_sizeplus.php */