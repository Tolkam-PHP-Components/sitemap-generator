<?php declare(strict_types=1);

namespace Tolkam\Sitemap;

use InvalidArgumentException;
use Throwable;
use XMLWriter;

class Generator
{
    /**
     * Filename templates
     */
    const FILENAME_INDEX   = 'sitemap_index.xml';
    const FILENAME_SITEMAP = 'sitemap_%s.xml';
    
    /**
     * Max items per sitemap
     */
    const MAX_ITEMS = 50000;
    
    /**
     * Max sitemap file size
     */
    const MAX_FILE_SIZE = 1024 * 1024 * 49; // 49 mb
    
    /**
     * @var string
     */
    protected string $targetDir = '';
    
    /**
     * @var array
     */
    private array $options = [
        'sitemapLocPrefix' => '',
        'urlLocPrefix' => '',
    ];
    
    /**
     * @var int
     */
    private int $currentFileIndex = 0;
    
    /**
     * @var int
     */
    private int $itemsCount = 0;
    
    /**
     * @var array
     */
    private array $filesLastModified = [];
    
    /**
     * @param string $targetDir
     * @param array  $options
     */
    public function __construct(string $targetDir, array $options = [])
    {
        if (empty($targetDir)) {
            throw new InvalidArgumentException('Target directory should not be empty');
        }
        
        $this->targetDir = $targetDir;
        
        if ($unknown = array_diff(array_keys($options), array_keys($this->options))) {
            throw new InvalidArgumentException(sprintf(
                'Unknown options: "%s"',
                implode('", "', $unknown)
            ));
        }
        
        $this->options = array_replace($this->options, $options);
    }
    
    /**
     * Generates and stores sitemap files
     *
     * @param \Generator|Url[] $urls
     *
     * @throws Throwable
     */
    public function generate(\Generator $urls)
    {
        $this->generateSitemaps($urls);
        $this->generateIndex();
        
        $this->currentFileIndex = $this->itemsCount = 0;
        $this->filesLastModified = [];
    }
    
    /**
     * Generates individual sitemap files
     *
     * @param \Generator|Url[] $urls
     *
     * @throws Throwable
     */
    protected function generateSitemaps(\Generator $urls)
    {
        $bytesWritten = 0;
        $fileName = sprintf(self::FILENAME_SITEMAP, $this->currentFileIndex);
        
        if (!$urls->valid()) {
            return;
        }
        
        $this->writeDocument($fileName, function (XMLWriter $writer) use (
            $urls,
            $bytesWritten
        ) {
            $writer->startElement('urlset');
            $writer->writeAttribute(
                'xmlns',
                'http://www.sitemaps.org/schemas/sitemap/0.9'
            );
            
            // write urls
            while ($urls->valid()) {
                $url = $urls->current();
                if (!$url instanceof Url) {
                    throw new InvalidArgumentException(sprintf(
                        'Each url item must be an instance of %s',
                        Url::class
                    ));
                }
                
                $bytesWritten += $this->writeUrl($writer, $url);
                $this->trackSitemapLastModified($this->currentFileIndex, $url);
                $this->itemsCount++;
                $urls->next();
                
                // reached limits per sitemap - write new
                if (
                    $this->itemsCount % self::MAX_ITEMS === 0 ||
                    $bytesWritten > self::MAX_FILE_SIZE
                ) {
                    $this->currentFileIndex++;
                    $this->generateSitemaps($urls);
                    break;
                }
            }
            
            $writer->endElement();
        });
    }
    
    /**
     * Generates sitemaps index file
     *
     * @throws Throwable
     */
    protected function generateIndex()
    {
        $indexFile = self::FILENAME_INDEX;
        
        $sitemapLoc = $this->addPrefix(
            self::FILENAME_SITEMAP,
            $this->options['sitemapLocPrefix']
        );
        
        // write common index file
        $this->writeDocument($indexFile, function (XMLWriter $writer) use ($sitemapLoc) {
            $writer->startElement('sitemapindex');
            $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
            
            for ($i = 0; $i <= $this->currentFileIndex; ++$i) {
                $writer->startElement('sitemap');
                $writer->writeElement('loc', sprintf($sitemapLoc, $i));
                
                // get and write largest found lastmod value
                if ($lastModified = $this->filesLastModified[$i] ?? null) {
                    $writer->writeElement('lastmod', $lastModified->format(DATE_W3C));
                }
                
                $writer->endElement();
            }
            
            $writer->endElement();
        });
    }
    
    /**
     * @param callable $callable
     * @param string   $fileName
     *
     * @return void
     */
    protected function writeDocument(string $fileName, callable $callable)
    {
        $writer = new XMLWriter();
        $writer->openUri($this->buildUri($fileName));
        
        $writer->startDocument();
        $writer->setIndent(true);
        
        $callable($writer);
        
        $writer->flush();
        $writer->endDocument();
        unset($writer);
    }
    
    /**
     * Writes url element
     *
     * @param XMLWriter $writer
     * @param Url       $url
     *
     * @return mixed
     */
    protected function writeUrl(XMLWriter $writer, Url $url)
    {
        $loc = $this->addPrefix(
            $url->getLocation(),
            $this->options['urlLocPrefix']
        );
        
        $writer->startElement('url');
        $writer->writeElement('loc', $loc);
        
        if ($lastModified = $url->getLastModified()) {
            $writer->writeElement('lastmod', $lastModified->format(DATE_W3C));
        }
        
        if ($changeFrequency = $url->getChangeFrequency()) {
            $writer->writeElement('changefreq', $changeFrequency);
        }
        
        if ($priority = $url->getPriority()) {
            $writer->writeElement('priority', $priority);
        }
        
        $writer->endElement();
        
        return $writer->flush();
    }
    
    /**
     * Tracks largest lastModified url value in sitemap
     *
     * @param int $sitemapIndex
     * @param Url $url
     */
    private function trackSitemapLastModified(int $sitemapIndex, Url $url)
    {
        if ($lastModified = $url->getLastModified()) {
            if ($lastKnown = $this->filesLastModified[$sitemapIndex] ?? null) {
                $lastModified = $lastModified > $lastKnown
                    ? $lastModified
                    : $lastKnown;
            }
            
            $this->filesLastModified[$sitemapIndex] = $lastModified;
        }
    }
    
    /**
     * Adds prefix
     *
     * @param string $str
     * @param string $prefix
     *
     * @return string
     */
    private function addPrefix(string $str, string $prefix)
    {
        $sep = '/';
        
        if (!$prefix) {
            return $str;
        }
        
        return rtrim($prefix, $sep) . $sep . ltrim($str, $sep);
    }
    
    /**
     * Builds file path
     *
     * @param string $fileName
     *
     * @return string
     */
    private function buildUri(string $fileName): string
    {
        if (!is_dir($this->targetDir)) {
            mkdir($this->targetDir, 0755, true);
        }
        
        $sep = DIRECTORY_SEPARATOR;
        $path = rtrim($this->targetDir, $sep) . $sep;
        
        return $path . $fileName;
    }
}
