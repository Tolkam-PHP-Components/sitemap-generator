<?php declare(strict_types=1);

namespace Tolkam\Sitemap;

use DateTimeInterface;
use InvalidArgumentException;

class Url
{
    const FREQUENCIES = [
        'always',
        'hourly',
        'daily',
        'weekly',
        'monthly',
        'yearly',
        'never',
    ];
    
    /**
     * @var string
     */
    protected string $location;
    
    /**
     * @var DateTimeInterface|null
     */
    protected ?DateTimeInterface $lastModified = null;
    
    /**
     * @var string|null
     */
    protected ?string $changeFrequency = null;
    
    /**
     * @var float|null
     */
    protected ?float $priority = null;
    
    /**
     * @param string $location
     */
    public function __construct(string $location)
    {
        $this->setLocation($location);
    }
    
    /**
     * Gets the location
     *
     * @return string
     */
    public function getLocation(): string
    {
        return $this->location;
    }
    
    /**
     * Gets the lastModified
     *
     * @return DateTimeInterface|null
     */
    public function getLastModified(): ?DateTimeInterface
    {
        return $this->lastModified;
    }
    
    /**
     * Gets the changeFrequency
     *
     * @return string|null
     */
    public function getChangeFrequency(): ?string
    {
        return $this->changeFrequency;
    }
    
    /**
     * Gets the priority
     *
     * @return string
     */
    public function getPriority(): ?string
    {
        return $this->priority ? number_format($this->priority, 1, '.', '') : null;
    }
    
    /**
     * Sets the lastModified
     *
     * @param DateTimeInterface $lastModified
     *
     * @return Url
     */
    public function setLastModified(DateTimeInterface $lastModified): Url
    {
        $this->lastModified = $lastModified;
        
        return $this;
    }
    
    /**
     * Sets the changeFrequency
     *
     * @param string $changeFrequency
     *
     * @return Url
     */
    public function setChangeFrequency(string $changeFrequency): Url
    {
        if (!in_array($changeFrequency, self::FREQUENCIES)) {
            throw new InvalidArgumentException('Invalid change frequency');
        }
        
        $this->changeFrequency = $changeFrequency;
        
        return $this;
    }
    
    /**
     * Sets the priority
     *
     * @param float $priority
     *
     * @return Url
     */
    public function setPriority(float $priority): Url
    {
        if ($priority < 0 || $priority > 1) {
            throw new InvalidArgumentException('Invalid priority');
        }
        
        $this->priority = $priority;
        
        return $this;
    }
    
    /**
     * Sets the location
     *
     * @param string $location
     *
     * @return void
     */
    private function setLocation(string $location): void
    {
        if (mb_strlen($location) > 2048) {
            throw new InvalidArgumentException('Location url is too long');
        }
        
        $this->location = $location;
    }
}
