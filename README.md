ImageUtil
=========

Easy resize, filter and compression class for images.

Changelog
---------

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
	$resize = new ImageUtil('path/to/image.jpg');
	// an absolute URL or path to file. Can be .jpg, .jpeg, .gif or .png
	$resize->resizeImage(320, 240);
	// width and height
	$resize->saveImage('path/to/output_1.jpg');
	// path to file. Can be .jpg, .jpeg, .gif or .png

Examples
--------

Examples can be found here: [http://www.mikkonen.info/imageutil/](http://www.mikkonen.info/imageutil/)

License
-------

ImageUtil is free and unencumbered [public domain][Unlicense] software.

[Unlicense]: http://unlicense.org/
