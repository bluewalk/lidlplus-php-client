<?php

/****************************************************************************\

barcode.php - Generate barcodes from a single PHP file. MIT license.

Copyright (c) 2016-2018 Kreative Software.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
DEALINGS IN THE SOFTWARE.

\****************************************************************************/

if (realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME'])) {
	if (isset($_POST['s']) && isset($_POST['d'])) {
		$generator = new barcode_generator();
		$format = (isset($_POST['f']) ? $_POST['f'] : 'png');
		$generator->output_image($format, $_POST['s'], $_POST['d'], $_POST);
		exit(0);
	}
	if (isset($_GET['s']) && isset($_GET['d'])) {
		$generator = new barcode_generator();
		$format = (isset($_GET['f']) ? $_GET['f'] : 'png');
		$generator->output_image($format, $_GET['s'], $_GET['d'], $_GET);
		exit(0);
	}
}

class barcode_generator {

	public function render_image($symbology, $data, $options) {
		list($code, $widths, $width, $height, $x, $y, $w, $h) =
			$this->encode_and_calculate_size($symbology, $data, $options);
		$image = imagecreatetruecolor($width, $height);
		imagesavealpha($image, true);
		$bgcolor = (isset($options['bc']) ? $options['bc'] : 'FFF');
		$bgcolor = $this->allocate_color($image, $bgcolor);
		imagefill($image, 0, 0, $bgcolor);
		$colors = array(
			(isset($options['cs']) ? $options['cs'] : ''),
			(isset($options['cm']) ? $options['cm'] : '000'),
			(isset($options['c2']) ? $options['c2'] : 'F00'),
			(isset($options['c3']) ? $options['c3'] : 'FF0'),
			(isset($options['c4']) ? $options['c4'] : '0F0'),
			(isset($options['c5']) ? $options['c5'] : '0FF'),
			(isset($options['c6']) ? $options['c6'] : '00F'),
			(isset($options['c7']) ? $options['c7'] : 'F0F'),
			(isset($options['c8']) ? $options['c8'] : 'FFF'),
			(isset($options['c9']) ? $options['c9'] : '000'),
		);
		foreach ($colors as $i => $color) {
			$colors[$i] = $this->allocate_color($image, $color);
		}
		$this->dispatch_render_image(
			$image, $code, $x, $y, $w, $h, $colors, $widths, $options
		);
		return $image;
	}

	/* - - - - INTERNAL FUNCTIONS - - - - */

	private function encode_and_calculate_size($symbology, $data, $options) {
		$code = $this->dispatch_encode($symbology, $data, $options);
		$widths = array(
			(isset($options['wq']) ? (int)$options['wq'] : 1),
			(isset($options['wm']) ? (int)$options['wm'] : 1),
			(isset($options['ww']) ? (int)$options['ww'] : 3),
			(isset($options['wn']) ? (int)$options['wn'] : 1),
			(isset($options['w4']) ? (int)$options['w4'] : 1),
			(isset($options['w5']) ? (int)$options['w5'] : 1),
			(isset($options['w6']) ? (int)$options['w6'] : 1),
			(isset($options['w7']) ? (int)$options['w7'] : 1),
			(isset($options['w8']) ? (int)$options['w8'] : 1),
			(isset($options['w9']) ? (int)$options['w9'] : 1),
		);
		$size = $this->dispatch_calculate_size($code, $widths, $options);
		$dscale = ($code && isset($code['g']) && $code['g'] == 'm') ? 4 : 1;
		$scale = (isset($options['sf']) ? (float)$options['sf'] : $dscale);
		$scalex = (isset($options['sx']) ? (float)$options['sx'] : $scale);
		$scaley = (isset($options['sy']) ? (float)$options['sy'] : $scale);
		$dpadding = ($code && isset($code['g']) && $code['g'] == 'm') ? 0 : 10;
		$padding = (isset($options['p']) ? (int)$options['p'] : $dpadding);
		$vert = (isset($options['pv']) ? (int)$options['pv'] : $padding);
		$horiz = (isset($options['ph']) ? (int)$options['ph'] : $padding);
		$top = (isset($options['pt']) ? (int)$options['pt'] : $vert);
		$left = (isset($options['pl']) ? (int)$options['pl'] : $horiz);
		$right = (isset($options['pr']) ? (int)$options['pr'] : $horiz);
		$bottom = (isset($options['pb']) ? (int)$options['pb'] : $vert);
		$dwidth = ceil($size[0] * $scalex) + $left + $right;
		$dheight = ceil($size[1] * $scaley) + $top + $bottom;
		$iwidth = (isset($options['w']) ? (int)$options['w'] : $dwidth);
		$iheight = (isset($options['h']) ? (int)$options['h'] : $dheight);
		$swidth = $iwidth - $left - $right;
		$sheight = $iheight - $top - $bottom;
		return array(
			$code, $widths, $iwidth, $iheight,
			$left, $top, $swidth, $sheight
		);
	}

	private function allocate_color($image, $color) {
		$color = preg_replace('/[^0-9A-Fa-f]/', '', $color);
		switch (strlen($color)) {
			case 1:
				$v = hexdec($color) * 17;
				return imagecolorallocate($image, $v, $v, $v);
			case 2:
				$v = hexdec($color);
				return imagecolorallocate($image, $v, $v, $v);
			case 3:
				$r = hexdec(substr($color, 0, 1)) * 17;
				$g = hexdec(substr($color, 1, 1)) * 17;
				$b = hexdec(substr($color, 2, 1)) * 17;
				return imagecolorallocate($image, $r, $g, $b);
			case 4:
				$a = hexdec(substr($color, 0, 1)) * 17;
				$r = hexdec(substr($color, 1, 1)) * 17;
				$g = hexdec(substr($color, 2, 1)) * 17;
				$b = hexdec(substr($color, 3, 1)) * 17;
				$a = round((255 - $a) * 127 / 255);
				return imagecolorallocatealpha($image, $r, $g, $b, $a);
			case 6:
				$r = hexdec(substr($color, 0, 2));
				$g = hexdec(substr($color, 2, 2));
				$b = hexdec(substr($color, 4, 2));
				return imagecolorallocate($image, $r, $g, $b);
			case 8:
				$a = hexdec(substr($color, 0, 2));
				$r = hexdec(substr($color, 2, 2));
				$g = hexdec(substr($color, 4, 2));
				$b = hexdec(substr($color, 6, 2));
				$a = round((255 - $a) * 127 / 255);
				return imagecolorallocatealpha($image, $r, $g, $b, $a);
			default:
				return imagecolorallocatealpha($image, 0, 0, 0, 127);
		}
	}

	/* - - - - DISPATCH - - - - */

	private function dispatch_encode($symbology, $data, $options) {
		switch (strtolower(preg_replace('/[^A-Za-z0-9]/', '', $symbology))) {
			case 'itf'        : return $this->itf_encode($data);
      case 'itf14'      : return $this->itf_encode($data);
    }
		return null;
	}

	private function dispatch_calculate_size($code, $widths, $options) {
		if ($code && isset($code['g']) && $code['g']) {
			switch ($code['g']) {
				case 'l':
					return $this->linear_calculate_size($code, $widths);
			}
		}
		return array(0, 0);
	}

	private function dispatch_render_image(
		$image, $code, $x, $y, $w, $h, $colors, $widths, $options
	) {
		if ($code && isset($code['g']) && $code['g']) {
			switch ($code['g']) {
				case 'l':
					$this->linear_render_image(
						$image, $code, $x, $y, $w, $h,
						$colors, $widths, $options
					);
					break;
			}
		}
	}

	/* - - - - LINEAR BARCODE RENDERER - - - - */

	private function linear_calculate_size($code, $widths) {
		$width = 0;
		foreach ($code['b'] as $block) {
			foreach ($block['m'] as $module) {
				$width += $module[1] * $widths[$module[2]];
			}
		}
		return array($width, 80);
	}

	private function linear_render_image(
		$image, $code, $x, $y, $w, $h, $colors, $widths, $options
	) {
		$textheight = (isset($options['th']) ? (int)$options['th'] : 10);
		$textsize = (isset($options['ts']) ? (int)$options['ts'] : 1);
		$textcolor = (isset($options['tc']) ? $options['tc'] : '000');
		$textcolor = $this->allocate_color($image, $textcolor);
		$width = 0;
		foreach ($code['b'] as $block) {
			foreach ($block['m'] as $module) {
				$width += $module[1] * $widths[$module[2]];
			}
		}
		if ($width) {
			$scale = $w / $width;
			$scale = (($scale > 1) ? floor($scale) : 1);
			$x = floor($x + ($w - $width * $scale) / 2);
		} else {
			$scale = 1;
			$x = floor($x + $w / 2);
		}
		foreach ($code['b'] as $block) {
			if (isset($block['l'])) {
				$label = $block['l'][0];
				$ly = (isset($block['l'][1]) ? (float)$block['l'][1] : 1);
				$lx = (isset($block['l'][2]) ? (float)$block['l'][2] : 0.5);
				$my = round($y + min($h, $h + ($ly - 1) * $textheight));
				$ly = ($y + $h + $ly * $textheight);
				$ly = round($ly - imagefontheight($textsize));
			} else {
				$label = null;
				$my = $y + $h;
			}
			$mx = $x;
			foreach ($block['m'] as $module) {
				$mc = $colors[$module[0]];
				$mw = $mx + $module[1] * $widths[$module[2]] * $scale;
				imagefilledrectangle($image, $mx, $y, $mw - 1, $my - 1, $mc);
				$mx = $mw;
			}
			if (!is_null($label)) {
				$lx = ($x + ($mx - $x) * $lx);
				$lw = imagefontwidth($textsize) * strlen($label);
				$lx = round($lx - $lw / 2);
				imagestring($image, $textsize, $lx, $ly, $label, $textcolor);
			}
			$x = $mx;
		}
	}

	/* - - - - ITF ENCODER - - - - */

	private function itf_encode($data) {
		$data = preg_replace('/[^0-9]/', '', $data);
		if (strlen($data) % 2) $data = '0' . $data;
		$blocks = array();
		/* Quiet zone, start. */
		$blocks[] = array(
			'm' => array(array(0, 10, 0))
		);
		$blocks[] = array(
			'm' => array(
				array(1, 1, 1),
				array(0, 1, 1),
				array(1, 1, 1),
				array(0, 1, 1),
			)
		);
		/* Data. */
		for ($i = 0, $n = strlen($data); $i < $n; $i += 2) {
			$c1 = substr($data, $i, 1);
			$c2 = substr($data, $i+1, 1);
			$b1 = $this->itf_alphabet[$c1];
			$b2 = $this->itf_alphabet[$c2];
			$blocks[] = array(
				'm' => array(
					array(1, 1, $b1[0]),
					array(0, 1, $b2[0]),
					array(1, 1, $b1[1]),
					array(0, 1, $b2[1]),
					array(1, 1, $b1[2]),
					array(0, 1, $b2[2]),
					array(1, 1, $b1[3]),
					array(0, 1, $b2[3]),
					array(1, 1, $b1[4]),
					array(0, 1, $b2[4]),
				),
				'l' => array($c1 . $c2)
			);
		}
		/* End, quiet zone. */
		$blocks[] = array(
			'm' => array(
				array(1, 1, 2),
				array(0, 1, 1),
				array(1, 1, 1),
			)
		);
		$blocks[] = array(
			'm' => array(array(0, 10, 0))
		);
		/* Return code. */
		return array('g' => 'l', 'b' => $blocks);
	}

	private $itf_alphabet = array(
		'0' => array(1, 1, 2, 2, 1),
		'1' => array(2, 1, 1, 1, 2),
		'2' => array(1, 2, 1, 1, 2),
		'3' => array(2, 2, 1, 1, 1),
		'4' => array(1, 1, 2, 1, 2),
		'5' => array(2, 1, 2, 1, 1),
		'6' => array(1, 2, 2, 1, 1),
		'7' => array(1, 1, 1, 2, 2),
		'8' => array(2, 1, 1, 2, 1),
		'9' => array(1, 2, 1, 2, 1),
	);
}