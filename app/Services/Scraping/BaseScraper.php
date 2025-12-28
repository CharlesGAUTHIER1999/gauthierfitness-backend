<?php

namespace App\Services\Scraping;

use App\Models\ExternalProduct;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

abstract class BaseScraper
{
    protected Client $client;

    /**
     * URLs déjà traitées (évite les doublons)
     */
    protected array $visitedUrls = [];

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 15,

            // ⚠️ DEV ONLY (Windows SSL)
            'verify' => false,

            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; RNCPBot/1.0)',
                'Accept-Language' => 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
            ],
        ]);
    }

    abstract protected function source(): string;
    abstract protected function startUrls(): array;
    abstract protected function parseProduct(Crawler $crawler, string $url): array;

    /**
     * Lance le scraping
     */
    public function scrape(): int
    {
        $count = 0;

        foreach ($this->startUrls() as $startUrl) {
            try {
                $html = $this->fetch($startUrl);
                $crawler = new Crawler($html, $startUrl);

                $crawler->filter('a')->each(function (Crawler $node) use (&$count) {
                    $link = $node->attr('href');

                    $productUrl = $this->normalizeUrl($link);

                    if ($this->isProductUrl($productUrl)) {
                        if (!in_array($productUrl, $this->visitedUrls, true)) {
                            $this->visitedUrls[] = $productUrl;
                            $this->scrapeProductPage($productUrl);
                            $count++;
                        }
                    }
                });

            } catch (Throwable $e) {
                // On continue même en cas d'erreur
                logger()->error('[Scraper] Start URL failed', [
                    'source' => $this->source(),
                    'url' => $startUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Scrape une page produit
     */
    protected function scrapeProductPage(string $url): void
    {
        try {
            $html = $this->fetch($url);
            $crawler = new Crawler($html, $url);

            $data = $this->parseProduct($crawler, $url);

            ExternalProduct::updateOrCreate(
                [
                    'source' => $this->source(),
                    'source_product_id' => $data['source_product_id'],
                ],
                array_merge($data, [
                    'imported_at' => now(),
                ])
            );

        } catch (Throwable $e) {
            logger()->error('[Scraper] Product page failed', [
                'source' => $this->source(),
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Effectue une requête HTTP
     * @throws GuzzleException
     */
    protected function fetch(string $url): string
    {
        return $this->client
            ->get($url)
            ->getBody()
            ->getContents();
    }

    /**
     * Détecte une URL produit
     */
    protected function isProductUrl(?string $url): bool
    {
        return is_string($url) && str_contains($url, '/products/');
    }

    /**
     * Normalise les URLs relatives / absolues
     */
    protected function normalizeUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (str_starts_with($url, 'http')) {
            return $url;
        }

        // URL relative
        return rtrim($this->baseUrl(), '/') . '/' . ltrim($url, '/');
    }

    /**
     * Base URL selon la source
     */
    protected function baseUrl(): string
    {
        return match ($this->source()) {
            'gymshark' => 'https://www.gymshark.com',
            'tsunami'  => 'https://www.tsunaminutrition.fr',
            'rogue'    => 'https://www.roguefitness.com',
            default    => '',
        };
    }
}
