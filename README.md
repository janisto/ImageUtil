ImageUtil
=========

Easy resize, filter and compression class for images.

All image manipulations are applied to a temporary image. Only the save() method is permanent.

Requires: PHP5 and GD2 library.

Optional: jpegtran, pngcrush.

Changelog
---------

### v2.1

- Better support for Debian / Ubuntu
- Code clean up

### v2.0

- BC break!
- Code refactor and DocBlock clean up.
- Implemented method chaining.
- Added option to render generated image without saving.

### v1.6

- Fixed bug #1: Invalid shell command with jpegtran.
- Enabled jpegtran/pngcrush compression by default.

### v1.5

- DocBlock clean up plus option to use background color with createPolaroid().
- Added option to rotate image and create polaroid like image. 

### v1.4

- Added option to rasterbate image.
- Added some DocBlock documentation.

### v1.3

- Added jpegtran and pngcrush support plus options to position watermark.

### v1.2

- Added contrast, sepia, smooth, blur, interlace, scatter, pixelate and noise.

### v1.1

- Added sharpen image, png alpha channels, watermark and rewrote the code.


Usage
-----

	include 'ImageUtil.php';

	// an absolute URL or path to file. Can be .jpg, .jpeg, .gif or .png
	$img = new ImageUtil('images/large/input1.jpg');
	// set width and height and then render image. Can be .jpg, .jpeg, .gif or .png
	$img->resize(320, 240)->render('output.jpg');
	
	// an absolute URL or path to file. Can be .jpg, .jpeg, .gif or .png
	$img = new ImageUtil('images/large/input2.jpg');
	// set width, height and image resize option
	$img->resize(150, 100, 'crop')
		// add watermark
		->watermark('images/large/watermark.png')
		// save path to file. Can be .jpg, .jpeg, .gif or .png
		->save('images/small/output.jpg');

Documentation
--------

Documentation can be found here: [http://janisto.github.com/ImageUtil/](http://janisto.github.com/ImageUtil/)

Examples
--------

Examples can be found here: [http://www.mikkonen.info/imageutil/](http://www.mikkonen.info/imageutil/)

License
-------

ImageUtil is free and unencumbered [public domain][Unlicense] software.

[Unlicense]: http://unlicense.org/
