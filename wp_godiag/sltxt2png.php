<?php
/**
    SLtxt2PNG.php -- create a PNG image from Sensei's Library diagram format
    Copyright (C) 2001-2004 by
    Arno Hollosi <ahollosi@xmp.net>, Morten Pahle <morten@pahle.org.uk>
    
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program (see bottom of file); if not, write to the
    Free Software Foundation, Inc., 59 Temple Place, Suite 330,
    Boston, MA  02111-1307  USA


    See demo function after the class definition on how to use it.
*/



/**
* The GoDiagram class
* All you need to know are the following methods and variables
*
* - create image with new GoDiagram($string) where $string contains
*   the diagram in Sensei's Library diagram format. See
*   http://senseis.xmp.net/?HowDiagramsWork for format info
*
* - to get the PNG image call $diagram->createPNG()
*
* - image size and width can be read from $diagram->img_width and
*   $diagram->img_height
*
* - for the client side link map call $diagram->getLinkmap()
*
* - for the (escaped) title call $diagram->getTitle()
* 
* - for the SGF file call $diagram->createSGF()
* 
*/
class GoDiagram
{
    // values extracted from the title line
    var $firstColor;	// 'B' or 'W'
    var $coordinates;	// boolean
    var $boardSize;	// integer
    var $title;		// raw text of title

    var $diagram;	// raw copy of diagram contents (single string)
    var $rows;		// normalized copy of $diagram (array of lines)
    var $linkmap;	// array of imagemap links (bracketlinks)
    var $image;		// image object of PNG graphic

    // image properties
    var $font;		// base unit for dimensions
    var $font_height;
    var $font_width;
    var $radius;	// based on font size
    var $img_width;
    var $img_height;
    var $offset_x;
    var $offset_y;

    // information about rows, columns
    var $startrow;
    var $startcol;
    var $endrow;
    var $endcol;

    // wether there is a boarder at the top, bottom, left, right (boolean)
    var $topborder;
    var $bottomborder;
    var $leftborder;
    var $rightborder;


    /**
    * Constructor of class GoDiagram
    * $diagram ... diagram string in SL's diagram format
    * sets $this->diagram to NULL if invalid diagram found
    */
    function &GoDiagram($diagram)
    {
	$content = explode("\n", $diagram);

	// parse the parameters of the first line
	preg_match('/^\$\$([WB])?(c)?(\d+)?(.*)/', $content[0], $match);
	$this->firstColor = ($match[1] == 'W') ? 'W' : 'B';
	$this->coordinates = !empty($match[2]);
	$this->boardSize = empty($match[3]) ? 19 : $match[3]+0;
	$this->title = trim($match[4]);

	// fill diagram and linkmap variables
        $this->diagram = '';
	$this->linkmap = array();
        for ($line = 1; $line < count($content); $line++)
	{
	    if (preg_match('"^\$\$\s*([^[\s].*)"', $content[$line], $match))
		$this->diagram .= "$match[1]\n";
	    if (preg_match('"^\$\$\s*\[(.*)\|(.*)\]"', $content[$line], $match))
	    {
		$anchor = trim($match[1]);
		if (preg_match('/^[a-z0-9WB@#CS]$/', $anchor))
		    $this->linkmap[$anchor] = trim($match[2]);
	    }
        }

	$this->_initBoardAndDimensions();

	if ($this->startrow > $this->endrow	// check if diagram is at least
	||  $this->startcol > $this->endcol	// 1x1
	||  $this->endrow < 0 || $this->endcol < 0
	||  $this->img_width < $this->font_width
	||  $this->img_height < $this->font_height)
	    $this->diagram = NULL;
    }


    /**
    * Parse diagram and calculate board dimensions
    */
    function _initBoardAndDimensions()
    {
	// remove unnecessary chars, replace border chars
	$diag = preg_replace("/[-|+]/", "%", $this->diagram);
	$diag = preg_replace("/[ \t\r\$]/", '', $diag);
	$diag = trim(preg_replace("/\n+/", " \n", $diag));
	$this->rows = explode("\n", "$diag ");

	// find borders
	$this->startrow = 0;
	$this->startcol = 0;
	$this->endrow = count($this->rows) - 1;

	// top border
	if ($this->rows[0][1] == '%')
	{
	    $this->startrow++;
	    $this->topborder = 1;
	}
	else
	    $this->topborder = 0;

	// bottom border
	if ($this->rows[$this->endrow][1] == '%')
	{
	    $this->endrow--;
	    $this->bottomborder = 1;
	}
	else
	    $this->bottomborder = 0;

	// left border
	if ($this->rows[$this->startrow][0] == '%')
	{
	    $this->startcol++;
	    $this->leftborder = 1;
	}
	else
	    $this->leftborder = 0;

	// right border
	$this->endcol = strlen($this->rows[$this->startrow]) - 2;
	if ($this->rows[$this->endrow][$this->endcol] == '%')
	{
	    $this->endcol--;
	    $this->rightborder = 1;
	}
	else
	    $this->rightborder = 0;


	// init dimensions
	$this->font = 4;
	$this->font_height = ImageFontHeight($this->font);
	$this->font_width = ImageFontWidth($this->font);
	$diameter = floor(sqrt(pow($this->font_height,2)+pow($this->font_width,2)))+6;
	$this->radius = $diameter/2;
	$this->img_width = $diameter * (1+$this->endcol-$this->startcol) + 4;
	$this->img_height = $diameter * (1+$this->endrow-$this->startrow) + 4;
	$this->offset_x = 2;
	$this->offset_y = 2;

	// calculate image size
	if ($this->coordinates)
	{
	    if (($this->bottomborder || $this->topborder)
	    &&  ($this->leftborder || $this->rightborder))
	    {
		$x = $this->font_width*2+4;
		$y = $this->font_height+2;
   		$this->img_width += $x;
		$this->offset_x += $x;
		$this->img_height += $y;
		$this->offset_y += $y;
	    }
	    else {
               // cannot determine X *and* Y coordinates (missing borders)
               $this->coordinates = 0;
	    }
	}
    }


    function getTitle()
    {
	return htmlspecialchars($this->title);
    }


    /**
    * get HTML code for client side image map
    * $URI ... URI of map
    */
    function getLinkMap($mapName)
    {
	if (!count($this->linkmap))
	    return NULL;

	$html .= "<map name='$mapName'>\n";
	for ($ypos=$this->startrow; $ypos<=$this->endrow; $ypos++)
	{
	    for ($xpos=$this->startcol; $xpos<=$this->endcol; $xpos++)
	    {
		$curchar = $this->rows[$ypos][$xpos];
		if (isset($this->linkmap[$curchar]))
		{
		    list($x, $y, $xx, $yy) = $this->_getLinkArea($xpos, $ypos);
		    $destination = $this->linkmap[$curchar];
		    $title = htmlspecialchars($destination);
		    $html .= "<area shape='rect' coords='$x,$y,$xx,$yy' href='$destination' title='$title'>\n";
		}
	    }
	}
	$html .= "</map>\n";
	return $html;
    }


    function _getLinkArea($xpos, $ypos)
    {
	$x = ($xpos - $this->startcol)*($this->radius*2) + $this->offset_x;
	$y = ($ypos - $this->startrow)*($this->radius*2) + $this->offset_y;
	return array($x, $y, $x + $this->radius*2 - 1,
			     $y + $this->radius*2 - 1);
    }


    /**
    * Creates PNG graphic based on ASCII diagram
    * returns image object
    */
    function &createPNG()
    {
	// create image
	$img = ImageCreate($this->img_width, $this->img_height);
	$this->image =& $img;

	// set up colors
	$black = ImageColorAllocate($img, 0, 0, 0);
	$white = ImageColorAllocate($img, 255, 255, 255);
	$red   = ImageColorAllocate($img, 255, 55, 55);
	$goban = ImageColorAllocate($img, 242, 176, 109);
	$gobanborder = ImageColorAllocate($img, 150, 110, 65);
	$gobanborder2 = ImageColorAllocate($img, 210, 145, 80);
	$gobanopen = ImageColorAllocate($img, 255, 210, 140);
	$link  = ImageColorAllocate($img, 202, 106, 69);

	// create background 
	ImageFill($img, 0, 0, $goban);

	// draw coordinates
	if ($this->coordinates)
	    $this->_drawCoordinates($black);

	$this->_drawGobanBorder($gobanborder,$gobanborder2, $gobanopen, $white);

	if ($this->firstColor == 'W')
	{
	    $evencolour = $black;
	    $oddcolour = $white;
	} else {
	    $evencolour = $white;
	    $oddcolour = $black;
	}

	// output stones, numbers etc. for each row
	for ($ypos=$this->startrow; $ypos<=$this->endrow; $ypos++)
	{
	    $img_y = ($ypos-$this->startrow)*($this->radius*2)
		   + $this->radius + $this->offset_y;

	    // for each character in row
	    for ($xpos=$this->startcol; $xpos<=$this->endcol; $xpos++)
	    {
        	$img_x = ($xpos-$this->startcol)*($this->radius*2)
		       + $this->radius + $this->offset_x;
        	$curchar = $this->rows[$ypos][$xpos];

		// is this a linked area? if so, paint with link color
		if (isset($this->linkmap[$curchar]))
		{
		   list($x, $y, $xx, $yy) = $this->_getLinkArea($xpos, $ypos);
		   ImageFilledRectangle($img, $x, $y, $xx, $yy, $link);
		}

        	switch ($curchar)
        	{
		    case ('X'):   // Make black stone
		    case ('B'):
		    case ('#'):
			$this->_drawStone($img_x, $img_y, $black, $black);
			if ($curchar != 'X')
			    $this->_markIntersection($img_x, $img_y, $this->radius, $red, $curchar);
			break;

        	    case ('O'):   // Make white stone
		    case ('W'):
		    case ('@'):
        		$this->_drawStone($img_x, $img_y, $black, $white);
			if ($curchar != 'O')
	        	   $this->_markIntersection($img_x, $img_y, $this->radius, $red, $curchar);
        		break;

        	    case ('.'):   // empty intersection; check location
		    		  // (edge, corner)
		    case (','):   // hoshi
		    case ('C'):
		    case ('S'):
			$type = $this->_getIntersectionType($xpos, $ypos);
        		$this->_drawIntersection($img_x, $img_y, $black, $type);
			if ($curchar != '.')
			{
	        	    $col = ($curchar == ',') ? $black : $red;
	        	    $this->_markIntersection($img_x, $img_y, $this->radius, $col, $curchar);
			}
        		break;

		    // Any other markup (including &/()! etc.)
        	    default:
			$font = $this->font;
        		if ($curchar % 2 == 1)      // odd numbers
			{
	        	    $this->_drawStone($img_x, $img_y, $black, $oddcolour);
                	    $markupcolour = $evencolour;
			}
        		elseif ($curchar * 2 > 0 || $curchar == '0')
			{  			    // even numbers
                	    $this->_drawStone($img_x, $img_y, $black, $evencolour);
                	    $markupcolour = $oddcolour;
			    if ($curchar == '0')
				$curchar = '10';
			}
        		elseif (($curchar >= 'a') AND ($curchar <= 'z'))
			{
	       		   $type = $this->_getIntersectionType($xpos, $ypos);
			   $this->_drawIntersection($img_x, $img_y, $black, $type);
			   $bgcol = (isset($this->linkmap[$curchar]))
			   	  ? $link : $goban;
			   $this->_markIntersection($img_x, $img_y, $this->radius+4, $bgcol, "@");
                	   $markupcolour = $black;
			   $font++;
			}
			else	// unknown char
			    break;

			$xoffset = (strlen($curchar)==2)
				 ? $this->font_width : $this->font_width/2;
        		ImageString($img, $font, $img_x-$xoffset, $img_y-($this->font_height/2), $curchar, $markupcolour);
			break;
        	} //switch $curchar
	    } //for xpos loop
	} //for ypos loop

	//all done
	return $img;
    }


    /**
    * $x and $y are relative to $image
    * $colourring, $colourinside are stone colors (edge and body resp.)
    */
    function _drawstone ($x, $y, $colourring, $colourinside)
    {
	$radius = $this->radius*2;
	ImageArc($this->image, $x, $y, $radius, $radius , 0, 360, $colourring);
	ImageFill($this->image, $x, $y, $colourinside);
    }


    /**
    * used to draw board markup and hoshi marks.
    * $x and $y are relative to $image 
    * $type one of W,B,C for circle or S,@,# for square
    */
    function _markIntersection ($x, $y, $radius, $colour, $type)
    {
	switch($type)
	{
	    case 'W':
	    case 'B':
	    case 'C':
        	ImageArc($this->image, $x,$y, $radius-2,$radius-2 , 0,360, $colour);
        	ImageArc($this->image, $x,$y, $radius-1,$radius-1 , 0,360, $colour);
        	ImageArc($this->image, $x,$y, $radius,$radius, 0,360, $colour);
		break;

	    case 'S':
	    case '#':
	    case '@':
		ImageFilledRectangle($this->image, $x-$radius/2+2, $y-$radius/2+2, $x+$radius/2-2, $y+$radius/2-2, $colour);
		break;

	    case ',': // hoshi marker drawn as 3 concentric circles
        	ImageArc($this->image, $x, $y, 6,6, 0,360, $colour);
        	ImageArc($this->image, $x, $y, 5,5, 0,360, $colour);
        	ImageArc($this->image, $x, $y, 4,4, 0,360, $colour);
		break;
	}
    }


    /**
    * $x,$y are the relative to $this->rows (0,0)=UL
    * Return value one of these:
    * 'M', 'U', 'L', 'R', 'B', 'UL', 'BL', 'UR', 'BR'
    */
    function _getIntersectionType($x, $y)
    {
	$type='';
	if ($this->rows[$y-1]{$x} == '%') {$type = 'U';}
	if ($this->rows[$y+1]{$x} == '%') {$type .= 'B';}
	if ($this->rows[$y]{$x+1} == '%') {$type .= 'R';}
	if ($this->rows[$y]{$x-1} == '%') {$type .= 'L';}
	return $type;
    }

    /**
    * $x and $y are relative to image
    * $type can be 'M', 'U', 'L', 'R', 'B', 'UL', 'BL', 'UR', 'BR'
    */
    function _drawIntersection($x, $y, $black, $type)
    {
	if (strpos($type, 'U') === FALSE)
	    ImageLine($this->image, $x, $y-$this->radius, $x, $y, $black);
	if (strpos($type, 'B') === FALSE)
	    ImageLine($this->image, $x, $y+$this->radius, $x, $y, $black);
	if (strpos($type, 'L') === FALSE)
	    ImageLine($this->image, $x-$this->radius, $y, $x, $y, $black);
	if (strpos($type, 'R') === FALSE)
	    ImageLine($this->image, $x+$this->radius, $y, $x, $y, $black);

	// linear board?
	if ((strpos($type, 'UB') !== FALSE) || (strpos($type, 'LR') !== FALSE))
    	    $this->_markIntersection($x, $y, $this->radius, $black, ',');
    }


    function _drawCoordinates($color)
    {
	$coordchar =
		'ABCDEFGHJKLMNOPQRSTUVWXYZabcdefghjklmnopqrstuvwxyz123456789';

	if ($this->bottomborder)
	{
	    $coordy = $this->endrow - $this->startrow + 1;
	}
	elseif ($this->topborder)
	{
	    $coordy = $this->boardSize;
	}

	if ($this->leftborder)
	{
	    $coordx = 0;
	}
	elseif ($this->rightborder)
	{
	    $coordx = $this->boardSize - $this->endcol - 1;
	    if ($coordx < 0)
		$coordx = 0;
	}

	// coordinate calculations according to offsets and sizes in createPNG!
	// see createPNG for values
	$leftx = 2 + $this->font_width;
	$img_y = 2 + $this->font_height+2
		   + $this->radius - ($this->font_height/2);
	for ($y = 0; $y <= $this->endrow-$this->startrow; $y++)
	{
	    $xoffset = ($coordy >= 10)
	    	     ? $this->font_width : $this->font_width/2;
	    ImageString($this->image, $this->font, $leftx-$xoffset, $img_y, "$coordy", $color);
	    $img_y += $this->radius*2;
	    $coordy--;
	}

	$topy = 2;
	$img_x = 2 + $this->font_width*2+4
		   + $this->radius - $this->font_width/2;
	for ($x = 0; $x <= $this->endcol-$this->startcol; $x++)
	{
	    ImageString($this->image, $this->font, $img_x, $topy, $coordchar[$coordx], $color);
	    $img_x += $this->radius*2;
	    $coordx++;
	}
    }


    function _drawGobanBorder($color, $color2, $open, $white)
    {
	$xl1 = $xl2 = 0;	// x-offset left
	$xr1 = $xr2 = 0;	// x-offset right
	$yt1 = $yt2 = 0;	// y-offset top
	$yb1 = $yb2 = 0;	// y-offset bottom

	if ($this->topborder)		{ $yt1 = 2; $yt2 = 1; }
	if ($this->bottomborder)	{ $yb1 = 2; $yb2 = 1; }
	if ($this->leftborder)		{ $xl1 = 2; $xl2 = 1; }
	if ($this->rightborder)		{ $xr1 = 2; $xr2 = 1; }

	if ($this->topborder)
	{
	    ImageSetPixel($this->image, 0, 0, $white);
	    ImageSetPixel($this->image, $this->img_width-1, 0, $white);
	    ImageLine($this->image, $xl1, 0, $this->img_width-1-$xr1, 0, $color);
	    ImageLine($this->image, $xl2, 1, $this->img_width-1-$xr2, 1, $color2);
	}
	else
            ImageLine($this->image, 0, 0, $this->img_width-1, 0, $open);

	if ($this->bottomborder)
	{
	    ImageSetPixel($this->image, 0, $this->img_height-1, $white);
	    ImageSetPixel($this->image, $this->img_width-1, $this->img_height-1, $white);
	    ImageLine($this->image, $xl1, $this->img_height-1, $this->img_width-1-$xr1, $this->img_height-1, $color);
	    ImageLine($this->image, $xl2, $this->img_height-2, $this->img_width-1-$xr2, $this->img_height-2, $color2);
	}
	else
	    ImageLine($this->image, 0, $this->img_height-1, $this->img_width-1, $this->img_height-1, $open);

	if ($this->leftborder)
	{
	    ImageSetPixel($this->image, 0, 0, $white);
	    ImageSetPixel($this->image, 0, $this->img_height-1, $white);
	    ImageLine($this->image, 0, $yt1, 0, $this->img_height-1-$yb1, $color);
	    ImageLine($this->image, 1, $yt2, 1, $this->img_height-1-$yb2, $color2);
	}
	else
	    ImageLine($this->image, 0, 0, 0, $this->img_height-1, $open);

	if ($this->rightborder)
	{
	    ImageSetPixel($this->image, $this->img_width-1, 0, $white);
	    ImageSetPixel($this->image, $this->img_width-1, $this->img_height-1, $white);
	    ImageLine($this->image, $this->img_width-1, $yt1, $this->img_width-1, $this->img_height-1-$yb1, $color);
	    ImageLine($this->image, $this->img_width-2, $yt2, $this->img_width-2, $this->img_height-1-$yb2, $color2);
	}
	else
	    ImageLine($this->image, $this->img_width-1, 0, $this->img_width-1, $this->img_height-1, $open);
    }


    /**
    * Creates SGF based on ASCII diagram and title
    * returns SGF as string or FALSE (if board not a square)
    */
    function createSGF()
    {
	// Calc size of SGF board and offset of diagram within
	$sizex = $this->endcol - $this->startcol + 1;
	$sizey = $this->endrow - $this->startrow + 1;
	$SGFOffestX = 0;
	$SGFOffestY = 0;
	$heightdefined = ($this->topborder && $this->bottomborder);
	$widthdefined = ($this->leftborder && $this->rightborder);
	if ($heightdefined)
	{
	    if ($widthdefined && $sizex != $sizey)
	       return FALSE;
	    if ($sizex > $sizey)
	       return FALSE;
	    $size = $sizey;
	    if ($this->rightborder)	$SGFOffsetX = $size-$sizex;
	    elseif (!$this->leftborder)	$SGFOffsetX = ($size-$sizex)/2;
	}
	elseif ($widthdefined)
	{
	    if ($sizey > $sizex)
	       return FALSE;
	    $size = $sizex;
	    if ($this->bottomborder)	$SGFOffsetY = $size-$sizey;
	    elseif (!$this->topborder)	$SGFOffsetY = ($size-$sizey)/2;
	}
	else
	{
	    $size = max($sizex, $sizey, $this->boardsize);

	    if ($this->rightborder)	$SGFOffsetX = $size-$sizex;
	    elseif (!$this->leftborder)	$SGFOffsetX = ($size-$sizex)/2;

	    if ($this->bottomborder)	$SGFOffsetY = $size-$sizey;
	    elseif (!$this->topborder)	$SGFOffsetY = ($size-$sizey)/2;
	}

	// SGF Root node string
	$gamename = str_replace(']', '\]', $this->title);
	$SGFString = "(;GM[1]FF[4]SZ[$size]\n\n" .
		     "GN[$gamename]\n" .
		     "AP[GoWiki by Arno & Morten]\n" .
		     "DT[".date("Y-m-d")."]\n" .
		     "PL[$this->firstColor]\n";

	$AB = array();
	$AW = array();
	$CR = array();
	$SQ = array();
	$LB = array();						

	if ($this->firstcolor == 'W') {
	   $oddplayer = 'W';
	   $evenplayer = 'B';
	} else {
	   $oddplayer = 'B';
	   $evenplayer = 'W';
	}

	// output stones, numbers etc. for each row
	for ($ypos=$this->startrow; $ypos<=$this->endrow; $ypos++)
	{
	    // for each character in row
	    for ($xpos=$this->startcol; $xpos<=$this->endcol; $xpos++)
	    {
		$curchar = $this->rows[$ypos]{$xpos};
		$position = chr(97-$this->startcol+$xpos+$SGFOffsetX) .
			    chr(97-$this->startrow+$ypos+$SGFOffsetY);

		if ($curchar == 'X' || $curchar == 'B' || $curchar == '#')
		    $AB[] = $position;    // Add black stone

		if ($curchar == 'O' || $curchar == 'W' || $curchar == '@')
		    $AW[] = $position;    // Add white stone

		if ($curchar == 'B' || $curchar == 'W' || $curchar == 'C')
		    $CR[] = $position;    // Add circle markup

		if ($curchar == '#' || $curchar == '@' || $curchar == 'S')
		    $SQ[] = $position;    // Add circle markup

		// other markup
		if ($curchar % 2 == 1)	// odd numbers (moves)
		{
		   $Moves[$curchar][1] = $position;
		   $Moves[$curchar][2] = $oddplayer;
		}
		elseif ($curchar*2 > 0 || $curchar == '0')  // even num (moves)
		{
        	    if ($curchar == '0')
			$curchar = '10';
		    $Moves[$curchar][1] = $position;
		    $Moves[$curchar][2] = $evenplayer;
		}
		elseif (($curchar >= 'a') && ($curchar <= 'z')) // letter markup
		    $LB[] = "$position:$curchar";
	    } //for xpos loop
	}//for ypos loop

	// parse title for hint of more moves
	if ($cnt = preg_match_all('"(\d|10) at (\d)"', $this->title, $match))
	{
	    for ($i=0; $i < $cnt; $i++)
	    {
		if (!isset($Moves[$match[1][$i]])  // only if not set on board
		&&   isset($Moves[$match[2][$i]])) // referred move must be set
		{
		    $mvnum = $match[1][$i];
		    $Moves[$mvnum][1] = $Moves[$match[2][$i]][1];
		    $Moves[$mvnum][2] = $mvnum % 2 ? $oddplayer : $evenplayer;
		}
	    }
	}

	// Build SGF string
	if (count($AB)) $SGFString .= 'AB[' . join('][', $AB) . "]\n";
	if (count($AW)) $SGFString .= 'AW[' . join('][', $AW) . "]\n";
	$Markup = '';
	if (count($CR)) $Markup = 'CR[' . join('][', $CR) . "]\n";
	if (count($SQ)) $Markup .= 'SQ[' . join('][', $SQ) . "]\n";
	if (count($LB)) $Markup .= 'LB[' . join('][', $LB) . "]\n";
	$SGFString .= "$Markup\n";

	for ($mv=1; $mv <= 10; $mv++)
	{
	    if (isset($Moves[$mv]))
	    {
		$SGFString .= ';' . $Moves[$mv][2] . '[' . $Moves[$mv][1] . ']';
		$SGFString .= 'C['. $Moves[$mv][2] . $mv . "]\n";
	    }
	}

	if (count($Moves))	// if there are any moves, then repeat markup
	   $SGFString .= ";$Markup";
	$SGFString .= ")\n";

	return $SGFString;
    }
}

/* The following are utility functions put in place by Adrian Petrescu
 * to assist with the GoDiagrams Wordpress plugin.
 */

function SaveDiagram($diagramsrc, $diagram_filename)
{

	$diagram =& new GoDiagram($diagramsrc);
	if ($diagram->diagram)
	{
	    $png =& $diagram->createPNG();
	    ImagePng($png, $diagram_filename);
	    ImageDestroy($png);
	    return true;
	} else {
	    return false;
	}
}

function SaveDiagramSGF($diagramsrc, $sgf_filename)
{
	
	$diagram =& new GoDiagram($diagramsrc);
	if ($diagram->diagram)
	{
		$fh = fopen($sgf_filename, 'w');
		fwrite($fh, $diagram->createSGF());
		fclose($fh);
		return true;
	} else {
		return false;
	}
}

?>
