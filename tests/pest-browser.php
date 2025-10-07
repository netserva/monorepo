<?php

use function Pest\Browser\browserOptions;

// CachyOS-specific browser configuration
browserOptions()
    ->timeout(30)
    ->headless() // Set to false if you want to see the browser
    ->withoutSandbox() // Important for Linux
    ->windowSize(1920, 1080)
    ->addArguments([
        '--disable-gpu',
        '--disable-dev-shm-usage',
        '--disable-setuid-sandbox',
        '--no-sandbox',
        '--disable-web-security',
        '--disable-features=VizDisplayCompositor',
        '--disable-software-rasterizer',
    ])
    ->setChromeBinary('/usr/bin/chromium'); // CachyOS Chromium path
