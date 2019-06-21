<?php

namespace owldesign\qarr\web\assets;

use craft\web\AssetBundle;

class Emails extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = __DIR__;

        $this->depends = [
//            Tippy::class
        ];

        $this->css = [
            'css/emails.css',
        ];

        $this->js = [
            'js/emails.js',
        ];

        parent::init();
    }
}