<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$symbol = strtoupper(trim((string) ($_GET['symbol'] ?? '')));
$instrumentData = null;
$instrumentError = null;
try {
    $instrumentData = (new \App\Market\InstrumentService())->detail($symbol);
} catch (\Throwable $exception) {
    $instrumentError = $exception instanceof \DomainException
        ? $exception->getMessage()
        : 'Market detail is temporarily unavailable.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Market detail | Marve Invest</title><link rel="stylesheet" href="public/assets/css/app.css"><link rel="stylesheet" href="public/assets/css/brand.css"><link rel="stylesheet" href="public/assets/css/market-detail.css"></head><body class="instrument-page" data-instrument="<?= htmlspecialchars(base64_encode(json_encode($instrumentData, JSON_THROW_ON_ERROR)), ENT_QUOTES, 'UTF-8') ?>" data-instrument-error="<?= htmlspecialchars((string) $instrumentError, ENT_QUOTES, 'UTF-8') ?>">
<header class="instrument-nav"><a class="brand" href="/marve-stock/" aria-label="Marve Invest home"><img class="brand-mark" src="public/assets/brand/marve-mark.svg" alt=""><span class="brand-name">marve<span>.</span></span></a><button class="menu-toggle" id="menuToggle" type="button" aria-expanded="false" aria-controls="mobileMenu"><span></span><span></span><span></span><i class="sr-only">Open menu</i></button><nav id="mobileMenu" aria-label="Primary"><a href="/marve-stock/#markets">Markets</a><a href="/marve-stock/#platform">Platform</a><a href="/marve-stock/#about">About</a><a href="/marve-stock/">Home</a></nav></header><script src="public/assets/js/market-bootstrap.js?v=20260918a"></script>
<main><a class="back-link" href="/marve-stock/#markets">Back to market board</a><section class="instrument-header"><div><p class="caption">NGX SECURITY</p><h1 id="instrumentName">Loading security</h1><p id="instrumentSymbol" class="instrument-symbol"></p></div><div class="quote-summary"><span>Last price</span><strong id="lastPrice">--</strong><small id="quoteChange">Waiting for authorised data</small></div></section><section class="chart-section"><div class="chart-header"><div><p class="caption">PRICE HISTORY</p><h2>Recent market movement</h2></div><span id="chartMeta">Loading</span></div><div class="chart-shell"><canvas id="priceChart" role="img" aria-label="Price history chart"></canvas><p class="chart-empty" id="chartEmpty" hidden></p></div><div class="chart-key"><span><i></i> Authorised quote snapshots</span><span id="quoteStatus"></span></div></section><section class="instrument-facts"><article><span>Data source</span><strong id="dataSource">--</strong></article><article><span>Market status</span><strong id="dataStatus">--</strong></article><article><span>Last updated</span><strong id="observedAt">--</strong></article></section></main><script>window.MARVE_SYMBOL = <?= json_encode($symbol, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script><script src="public/assets/js/market-detail.js"></script></body></html>
