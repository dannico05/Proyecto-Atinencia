<?php

declare(strict_types=1);

return [
    'chrome_path' => env('PDF_CHROME_PATH', env('PUPPETEER_EXECUTABLE_PATH')),
];
