# BlockCacher

BlockCacher provides an extremely efficient file-based caching mechanism
that can be used to store serialised data, text and HTML using simple
methods. HTML and data generation times can be reduced to fractions of a
millisecond after caching, and this cacher is meant to be used wherever
it can.

It provides simple key-based storage and retrieval as well as start/end
output buffer caching as well as lazy data generation.

## Usage

You must first instantiate an instance of `BlockCacher` with a cache
storage directory. At this time, only single-level cache directories
are supported. Cache names must not include sub-directory paths.

The first instance of BlockCacher will become the default and can then
be retrieved through the global `blockCacher()` function, or
`BlockCacher::getDetault()`. 

The cache directory specified will be created by default if it does not exist. 

    $cacher = \BlockCacher\BlockCacher::createDefault(__DIR__ . '/cache');
    $sameCacher = \blockCacher();
    
`BlockCacher` is not a singleton but it does provide a default instance.
This allows other cache directories to be used in paralell for more
specific use cases.

    $otherCacher = new \BlockCacher\BlockCacher(__DIR__ . '/other-cache');

### Storing & Retrieving Values

BlockCacher doesn't store expiry times and instead asks for the maximum
age of the cache whenever cached data is to be retrieved. This removes
the need to "package" values with expiry times when they are being stored
on the disk.

#### Storing Text & Data

To store data in the cache, use the `store()` and `storeText()` methods.

    $serialisable = [ ... ];
    $cacher->store('dataKey', $serialisable);
    
    $text = "This text is stored directly without serialisation.";
    $cacher->storeText('textKey', $text);

#### Retrieve Text & Data

To retrieve data from the cache, use the `get()` and `getText()` methods.
Pass in the maximum age of the cache in seconds to ensure that existing
cache files expire and are regenerated. The default age is set to 86400
seconds, or one day.

    $data = $cacher->get('dataKey', 3600);
    // $data = [ ... ]
    
    $text = $cacher->getText('textKey', 3600);
    // $text = "This text is stored directly without serialisation."
