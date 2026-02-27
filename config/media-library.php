<?php

return [
    /*
     * The disk on which to store added files and derived images. Choose
     * one or more of the disks you've configured in config/filesystems.php.
     */
    'disk_name' => env('MEDIA_DISK', 'cloudinary'),

    /*
     * The maximum file size of an item in bytes.
     * Adding a larger file will result in an exception.
     */
    'max_file_size' => env('MEDIA_MAX_FILE_SIZE', 1024 * 1024 * 100), // 100MB

    /*
     * This queue connection will be used to generate derived and responsive
     * images. Leave empty to use the default queue connection.
     */
    'queue_connection_name' => env('QUEUE_CONNECTION', 'sync'),

    /*
     * This queue will be used to generate derived and responsive images.
     * Leave empty to use the default queue.
     */
    'queue_name' => '',

    /*
     * By default all conversions will be processed synchronously
     * in order to keep things simple. In a production environment,
     * you can enable async processing here.
     */
    'async' => false,

    /*
     * By default the media library will accept common image extensions.
     * Here you can override the extension to mimetype mapping.
     */
    'extension_to_mime_type_map' => [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'mp4' => 'video/mp4',
    ],

    'image_optimizers' => [
        \Spatie\ImageOptimizer\Optimizers\Jpegoptim::class => [
            '--strip-all', // this option will strip all comments from the jpeg file
            '--all-progressive', // this will make the image progressive
        ],
        \Spatie\ImageOptimizer\Optimizers\Pngquant::class => [
            '--force', // required parameter for this package
        ],
        \Spatie\ImageOptimizer\Optimizers\Optipng::class => [
            '-i2', // this will cause the image to interlace
            '-strip', // this will strip all auxiliary chunks
            '-zc',
            '9', // this will set the zlib compression level
            '-zm',
            '8', // maximal memory for deflate
            '-zs',
            '0', // strategies to reduce size
            '-f0-5',
        ],
        \Spatie\ImageOptimizer\Optimizers\Svgo::class => [
            '--enable=cleanupEnableBackground',
        ],
        \Spatie\ImageOptimizer\Optimizers\Gifsicle::class => [
            '-b', // required parameter for this package
            '-O3', // this will result in slowest process on the level of optimization, the property is optional
        ],
    ],

    /*
     * The path generator to be used by media having the guidance of
     * config. Here you can override the default ImagePathGenerator
     * if you have an own implementation.
     *
     * The path generator converts characters like /, and : into valid
     * names for the used storage. If you define own "processors"
     * you also are responsible for an own path generator implementation.
     */
    'path_generator' => \App\Media\CloudinaryPathGenerator::class,

    /*
     * Medialibrary will index all media and files. By default all files are indexed
     * except deleted ones. You can customize how the indexing works
     * by provide your own custom class that implements the IsIndexed interface.
     */
    'is_indexed' => \Spatie\MediaLibrary\MediaCollections\Models\Collections\IsIndexed::class,

    /*
     * The class that contains the strategy for determining a media file's path.
     */
    'file_namer' => \Spatie\MediaLibrary\Support\FileNamer\FileNamer::class,

    /*
     * The fully qualified class name of the media model.
     */
    'media_model' => \Spatie\MediaLibrary\MediaCollections\Models\Media::class,

    'custom_headers' => [],

    'url_generator' => \Spatie\MediaLibrary\Support\UrlGenerator\UrlGenerator::class,
];
