<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if ( ! function_exists('css_url'))
{
	function css_url($nom)
	{
		return base_url() . 'assets/css/' . $nom . '.css';
	}
}

if ( ! function_exists('js_url'))
{
	function js_url($nom)
	{
		return base_url() . 'assets/js/' . $nom . '.js';
	}
}

if ( ! function_exists('img_url'))
{
	function img_url($nom)
	{
		return base_url() . 'assets/img/' . $nom;
	}
}

if ( ! function_exists('structures_url'))
{
	function structures_url($nom)
	{
		$CI = & get_instance();
		return base_url().$CI->config->item('dir_pictures_creche').$nom;
	}
}

if ( ! function_exists('actualite_url'))
{
	function actualite_url($nom)
	{
		$CI = & get_instance();
		return base_url().$CI->config->item('dir_logo_bulle_info').$nom;
	}
}

if ( ! function_exists('logos_structures_url'))
{
	function logos_structures_url($nom)
	{
		$CI = & get_instance();
		return base_url().$CI->config->item('dir_logo_creche').$nom;
	}
}

if ( ! function_exists('ville_url'))
{
	function ville_url($nom)
	{
		$CI = & get_instance();
		return base_url().$CI->config->item('dir_logo_ville').$nom;
	}
}

if ( ! function_exists('img'))
{
	function img($nom, $alt = '')
	{
		return '<img src="' . img_url($nom) . '" alt="' . $alt . '" />';
	}
}

if ( ! function_exists('tiny_mce'))
{
	function tiny_mce($nom)
	{
		return base_url() . 'assets/tiny_mce/' . $nom;
	}
}

if ( ! function_exists('avatar_url'))
{
	function avatar_url($nom)
	{
		return base_url() . 'assets/avatars/' . $nom;
	}
}

if ( ! function_exists('thumb_avatar_url'))
{
	function thumb_avatar_url($nom)
	{
		return base_url() . 'assets/avatars/thumbs/' . $nom;
	}
}

if ( ! function_exists('illustr_url'))
{
	function illustr_url($nom)
	{
		return base_url() . 'assets/illustrations/' . $nom;
	}
}

if ( ! function_exists('thumb_illustr_url'))
{
	function thumb_illustr_url($nom)
	{
		return base_url() . 'assets/illustrations/thumbs/' . $nom;
	}
}


if ( ! function_exists('assets_url'))
{
	function assets_url($nom)
	{
		return base_url() . 'assets/' . $nom;
	}
}
