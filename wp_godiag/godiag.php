<?php
/*
Plugin Name: GoDiagram Plugin 
Plugin URI: http://blog.komrades.org/?p=44
Description: A plugin to convert ASCII go boards into PNG diagrams.
Version: 1.0
Author: Adrian Petrescu
Author URI: http://blog.komrades.org/
*/

	require("sltxt2png.php");

	function createDiagram($diagramsrc)
	{
		/*************************CONFIGURATION******************************************
		 *
		 * The values below should be modified to fit your server layout.
		 * Please ensure tha the $imgFolder and $sgfFolder directories exist and that
		 * write access is enabled!
		 */

		$host_prefix_img = 'http://blog.komrades.org/wp-content/plugins/godiagram/img/';
		$host_prefix_sgf = 'http://blog.komrades.org/wp-content/plugins/godiagram/sgf/';		

		$imgFolder = '/home/adrianp/public_html/blog/wp-content/plugins/godiagram/img/';
		$pngExt = '.png';
		
		$sgfFolder = '/home/adrianp/public_html/blog/wp-content/plugins/godiagram/sgf/';
		$sgfExt = '.sgf';

		$hash = md5($diagramsrc);

		$img_name = $hash . $pngExt;
		$sgf_name = $hash . $sgfExt;

		/**************************END CONFIGURATION**************************************/	
		
		if (file_exists($imgFolder . $img_name) || SaveDiagram($diagramsrc, $imgFolder . $img_name)) 
		{
			if (file_exists($sgfFolder . $sgf_name) || SaveDiagramSGF($diagramsrc, $sgfFolder . $sgf_name))
			{
				return "<a href=\"" . $host_prefix_sgf . $sgf_name . "\"><img src=\"" . $host_prefix_img . $img_name . "\"></a>";
			} else {
				return "<img src=\"" . $host_prefix_img . $img_name . "\">";
			}
		} else {
			return "Sorry, error creating SGF diagram!!";
		} 
	}

	/* Iterates through all instances of the [go]..[/go] tags and calls the CreateDiagram function on
	 * its contents. Replaces the tags with the linked image.
	 */
	function replaceDiagrams($content="") {
		preg_match_all("#\[go\](.*?)\[/go\]#si",$content,$go_matches);

		for ($i=0; $i < count($go_matches[0]); $i++) {
			$pos = strpos($content, $go_matches[0][$i]);
			$ascii_diagram = $go_matches[1][$i];

			$png_diagram = createDiagram($ascii_diagram);
			$content = substr_replace($content, $png_diagram, $pos, strlen($go_matches[0][$i]));
		}
		return $content;
	}

	/* Add the filter to all post views. */
	add_filter('the_content', 'replaceDiagrams', 1);

	/* Add the filter to all comment views. Comment out the following line if you do not want guests to be
	 * able to post diagrams in the comments.
	 */
	add_filter('comment_text', 'replaceDiagrams', 1);
?>
