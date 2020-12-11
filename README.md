# BlockCacher

BlockCacher provides an efficient file-based caching mechanism
that can store serialised data, text and HTML content using simple
`store` and `get` methods. HTML and data generation times can be
reduced to fractions of a millisecond after caching, and this cacher
is designed to be used wherever it can.

It provides straight-forward key-based storage and retrieval as
well as start/end output buffer caching as well as lazy data generation.

Keys are directly used as filenames on disk, allowing key-based cache
clearing using wildcards.

## Usage

You must first instantiate an instance of `BlockCacher` with a cache
storage directory. Only single-level cache directories are supported.
Cache keys must not include sub-directories.

The first instance of BlockCacher will become the default and can then
be retrieved through the global `blockCacher()` function, or
`BlockCacher::getDetault()`.

The cache directory specified will be created by default if it does not exist. 

```php
$cacher = \BlockCacher\BlockCacher::createDefault(__DIR__ . '/cache');
$sameCacher = \blockCacher();
```
    
`BlockCacher` is not a singleton but it does provide a default instance.
This allows other cache directories to be used in parallel for more
specific use cases.

```php
$otherCacher = new \BlockCacher\BlockCacher(__DIR__ . '/other-cache');
```

### Storing & Retrieving Values

BlockCacher doesn't store expiry times and instead asks for the maximum
age of the cache whenever cached data is to be retrieved. This removes
the need to "package" values with expiry times when they are being stored
on the disk.

#### Storing Text & Data

To store data in the cache, use the `store()` and `storeText()` methods.

```php
$serialisable = [ ... ];
$cacher->store('dataKey', $serialisable);

$text = "This text is stored directly without serialisation.";
$cacher->storeText('textKey', $text);
```

#### Retrieve Text & Data

To retrieve data from the cache, use the `get()` and `getText()` methods.
Pass in the maximum age of the cache in seconds to ensure that existing
cache files that over the desired age are ignored. The default expiry
lifetime is set to 86,400 seconds, or one day.

**NOTE:** Cache files are ignored but not deleted if they have expired.

```php
$data = $cacher->get('dataKey', 3600);
// $data = [ ... ]

$text = $cacher->getText('textKey', 3600);
// $text = "This text is stored directly without serialisation."
```

### Generating Blocks of HTML & Data

`BlockCacher` includes helper methods for generating blocks of HTML
content and serialisable data. These are `start()` + `end()` as well as
 `generate()`.
 
#### Generating HTML Blocks

The `start()` method determines if a block should be regenerated based
on the cache status then starts a buffer if there is a cache miss. The
`end()` method then closes the buffer, stores the contents and echoes
it.

```php
if ($cacher->start('cached-list.html'))
{
    $data = $this->getArticleData();
    $items = $data['items'];
    ?><div>
        <h1>
            <?php echo htmlentities($data['title']) ?>
        </h1>
        <ul>
        <?php foreach($items as $item) { ?>
            <li>
                <?php echo htmlentities($item['text']); ?>
            </li>
        <?php ?>
    </div><?php
}
$cacher->end();
```

The `end()` method must be called outside the `if()` statement.
This will echo the content and clean up the buffer stack regardless
of the status of the cache.

#### Lazily Generating Data

To generate cacheable data only if necessary, use the `generate()`
method, which takes a closure that will generate the data to be
cached if it does not already exist.

```php
$data = $cacher->generate('cached-data.object', function() use($someVar)
{
    $data = // calculate data...
    
    printf("Cache does not exist, generating data...");
    return $data;
});
```

### Clearing Caches

Since cached data and text is stored in files under the cache
directory, clearing caches uses glob file patterns. Use the
`clear()` method and specify a filename or file pattern.

```php
$cacher->clear('*.html'); // clear all HTML files
$cacher->clear('*.object'); // clear all .object files
$cacher->clear(); // clear ALL cache files
```
    
#### NOTE: Regular Cache Pruning

It can be prudent to prune old files regularly in large systems
to ensure the `clear()` is fast when matching file patterns.
With hundreds of thousands of cache files, `glob()` can take a
long time to build its list of matching files. 

`clear()` takes an optional `$minimumAge` parameter to specify
the minimum age of files that can be cleared.

```php
// clear all cache files older than 30 days
$minimumAge = 86400 * 30;
$cacher->clear('*', true, false, $minimumAge);
```
