<?php

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Core\Config\Config;

class AnalyticsJSTest extends FunctionalTest
{

    public function setUp()
    {
        parent::setUp();
        Config::inst()->update('Axllent\AnalyticsJS\AnalyticsJS', 'tracker', [['create','UA-DEV-1','auto']]);
        Config::inst()->update('Axllent\AnalyticsJS\AnalyticsJS', 'track_links',  true);

        if (!ContentController::has_extension('Axllent\AnalyticsJS\AnalyticsJS')) {
            ContentController::add_extension('Axllent\AnalyticsJS\AnalyticsJS');
        }
    }

    public function testHomePage()
    {
        $page = $this->get('/');
        $body = $page->getBody();
        $this->assertContains('("create","UA-DEV-1","auto")', $body);
        $this->assertContains('function _guaLt(e)', $body);
    }
}
