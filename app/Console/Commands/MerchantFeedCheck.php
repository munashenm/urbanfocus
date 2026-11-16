<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\FeedService;
use Illuminate\Console\Command;

/**
 * merchant:feed-check [--rebuild] [--skus=A,B,C]
 * Read-only report of what the Google Merchant feed will send (effective return label, title format,
 * backorder/preorder items and their availability_date, mojibake in names, excluded SKUs, sample titles).
 * --rebuild: deletes the cached feed files (storage/app/feeds/google-merchant.xml and pricecheck.xml),
 *            builds the Google feed again and checks the result. No product data is changed.
 * --verify:  runs the same CHECK lines on the current feed file without rebuilding it.
 * Report: storage/app/merchant_feed_check.txt
 */
class MerchantFeedCheck extends Command
{
    protected $signature = 'merchant:feed-check {--rebuild : Delete the cached feed files and rebuild the Google feed} {--verify : Check the current feed file (storage/app/feeds/google-merchant.xml) without rebuilding} {--skus= : Comma-separated SKUs to show}';

    protected $description = 'Report (and optionally rebuild) the Google Merchant feed';

    public function handle(FeedService $feeds): int
    {
        $out = [];
        $out[] = '['.now()->toDateTimeString().'] merchant:feed-check'.($this->option('rebuild') ? ' --rebuild' : '').($this->option('verify') ? ' --verify' : '');
        $out[] = 'return_policy_label (effective): '.var_export(config('google-merchant.return_policy_label'), true);
        $out[] = 'title_format: '.config('google-merchant.title_format', 'clean').' | backorder_lead_days: '.var_export(config('google-merchant.backorder_lead_days'), true);
        $excludeFile = storage_path('app/'.config('google-merchant.exclude_file', 'google_feed_exclude.txt'));
        $out[] = 'exclude file: '.(is_file($excludeFile) ? count(array_filter(file($excludeFile, FILE_IGNORE_NEW_LINES) ?: [], fn ($l) => trim($l) !== '' && ! str_starts_with(trim($l), '#'))).' SKUs' : 'none');
        $excludedSkus = array_keys($feeds->googleFeedExcludedSkus());
        $out[] = 'excluded from Google feed: '.count($excludedSkus).' SKU(s): '.implode(',', $excludedSkus);

        $back = 0; $moj = 0; $changedTitles = 0; $active = 0;
        $samples = [];
        Product::query()->where('is_active', true)->with('images')->orderBy('id')->chunkById(300, function ($ps) use (&$back, &$moj, &$changedTitles, &$active, &$out, &$samples) {
            foreach ($ps as $p) {
                $active++;
                if (preg_match('/Ã.|Â|â€/u', (string) $p->name)) { $moj++; $out[] = "MOJIBAKE name {$p->sku}: ".$p->name; }
                if ($d = $p->googleFeedAvailabilityDate()) { $back++; $out[] = 'BACKORDER '.$p->sku.' availability='.$p->googleFeedAvailability().' availability_date='.$d; }
                $legacy = \Illuminate\Support\Str::limit(implode(' ', array_filter([$p->brand, $p->sku, $p->name])), 150, '');
                if ($legacy !== $p->googleFeedTitle()) { $changedTitles++; if (count($samples) < 15) { $samples[] = "TITLE {$p->sku}: '{$legacy}' -> '".$p->googleFeedTitle()."'"; } }
            }
        });
        $out[] = "active products: {$active} | backorder/preorder with availability_date: {$back} | names with mojibake: {$moj} | titles that change vs legacy format: {$changedTitles}";
        array_push($out, ...$samples);

        foreach (array_filter(array_map('trim', explode(',', (string) $this->option('skus')))) as $sku) {
            foreach (Product::where('sku', $sku)->with('images')->get() as $p) {
                $out[] = "SKU {$sku} id {$p->id} active=".(int) $p->is_active.' eligible='.(int) $p->isGoogleMerchantEligible().' avail='.$p->googleFeedAvailability().' title='.$p->googleFeedTitle().' images='.$p->images->count();
            }
        }

        $xml = null;
        $feedPath = storage_path('app/feeds/google-merchant.xml');
        if ($this->option('rebuild')) {
            foreach (['google-merchant.xml', 'pricecheck.xml'] as $f) {
                $path = storage_path('app/feeds/'.$f);
                if (is_file($path)) { @unlink($path); $out[] = "deleted cached feed {$f}"; }
            }
            $started = microtime(true);
            $xml = $feeds->googleMerchantXml();
            $out[] = sprintf('rebuilt google-merchant.xml in %.1fs', microtime(true) - $started);
        } elseif ($this->option('verify') && is_file($feedPath)) {
            $xml = (string) file_get_contents($feedPath);
            $out[] = 'checking current feed file google-merchant.xml built '.date('Y-m-d H:i:s', filemtime($feedPath));
        } elseif ($this->option('verify')) {
            $out[] = 'CHECK feed file exists: FAIL (no storage/app/feeds/google-merchant.xml; run with --rebuild)';
        }

        if ($xml !== null) {
            $items = substr_count($xml, '<item>');
            $out[] = sprintf('feed: %d items, %d bytes, return label 14: %d, 30: %d', $items, strlen($xml),
                substr_count($xml, '14 Day Returns'), substr_count($xml, '30 Day Returns'));
            // Elements may carry xmlns:g="..." attributes, so match the tag name loosely.
            $tag = fn (string $name, string $value = '.*?') => preg_match_all('#<g:'.$name.'(?:\s[^>]*)?>'.$value.'</g:'.$name.'>#s', $xml);
            preg_match_all('#<g:title(?:\s[^>]*)?>(.*?)</g:title>#s', $xml, $m);
            foreach (array_slice($m[1], 0, 5) as $title) { $out[] = 'FEED TITLE: '.html_entity_decode($title, ENT_QUOTES | ENT_XML1, 'UTF-8'); }
            $present = array_values(array_filter($excludedSkus, fn ($s) => $tag('id', preg_quote(htmlspecialchars($s, ENT_XML1), '#')) > 0));
            $ok = fn ($c) => $c ? 'OK' : 'FAIL';
            $label14 = $tag('return_policy_label', '14 Day Returns');
            $dates = $tag('availability_date');
            $back = $tag('availability', '(?:backorder|preorder)');
            $out[] = "CHECK return label '14 Day Returns' on every item ({$label14}/{$items}): ".$ok($items > 0 && $label14 === $items && substr_count($xml, '30 Day Returns') === 0);
            $doubled = count(array_filter($m[1], fn ($t) => preg_match('/^(\S+)\s+\1\b/iu', html_entity_decode($t, ENT_QUOTES | ENT_XML1, 'UTF-8'))));
            $out[] = "CHECK title format clean (titles starting with a repeated word: {$doubled}): ".$ok(config('google-merchant.title_format', 'clean') === 'clean' && $doubled < 10);
            $out[] = "CHECK backorder items have availability_date ({$dates}/{$back}): ".$ok($dates === $back);
            preg_match_all('#<g:(?:link|image_link)(?:\s[^>]*)?>(.*?)</g:(?:link|image_link)>#s', $xml, $lm);
            $badLinks = count(array_filter($lm[1], fn ($u) => ! preg_match('#^https://(www\.)?urbanfocus\.co\.za/#i', html_entity_decode($u, ENT_QUOTES | ENT_XML1, 'UTF-8'))));
            $out[] = 'FEED LINK: '.($lm[1][0] ?? '(none)');
            $out[] = 'CHECK product and image links are https://(www.)urbanfocus.co.za/... ('.count($lm[1]).' links, '.$badLinks.' bad): '.$ok($lm[1] !== [] && $badLinks === 0);
            $out[] = 'CHECK excluded SKUs absent from feed ('.implode(',', $excludedSkus).'): '.$ok($present === []).($present ? ' present: '.implode(',', $present) : '');
        }

        file_put_contents(storage_path('app/merchant_feed_check.txt'), implode("\n", $out)."\n\n", FILE_APPEND);
        foreach ($out as $l) { $this->line($l); }

        return 0;
    }
}
