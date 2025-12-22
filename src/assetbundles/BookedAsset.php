<?php

namespace fabian\booked\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Booked frontend asset bundle
 */
class BookedAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        // Define the path that contains your asset files
        $this->sourcePath = '@fabian/booked/web';

        // The relative paths to the files that should be registered
        $this->js = [
            'js/booking-availability.js',
            'js/booking-wizard.js',
            'js/booking-catalog.js',
            'js/booking-search.js',
        ];

        $this->css = [
            'css/booked.css',
        ];

        // Register scripts in the head to ensure Alpine components are defined before Alpine initializes in app.js
        $this->jsOptions['position'] = \craft\web\View::POS_HEAD;

        parent::init();
    }
}

