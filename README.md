# tolkam/sitemap-generator

Dead-simple XML sitemaps generator.

## Documentation

The code is rather self-explanatory and API is intended to be as simple as possible. Please, read the sources/Docblock if you have any questions. See [Usage](#usage) for quick start.

## Usage

````php
use Tolkam\Sitemap\Generator as SitemapGenerator;
use Tolkam\Sitemap\Url;

$targetDir = sys_get_temp_dir();
$generator = new SitemapGenerator($targetDir);

$url = (new Url('https://example.com/'))
    ->setChangeFrequency('daily')
    ->setPriority(0.5);

$urlProvider = fn() => yield from [$url];

$generator->generate($urlProvider());

$index = $targetDir . DIRECTORY_SEPARATOR . $generator::FILENAME_INDEX;
$firstSitemap = $targetDir . DIRECTORY_SEPARATOR . sprintf($generator::FILENAME_SITEMAP, 0);

foreach ([$index, $firstSitemap] as $file) {
    echo file_get_contents($file) . PHP_EOL;
    @unlink($file);
}
````

## License

Proprietary / Unlicensed 🤷
