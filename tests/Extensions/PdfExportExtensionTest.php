<?php

namespace CWP\PDFExport\Tests\Extensions;

use CWP\CWP\PageTypes\BasePage;
use CWP\PDFExport\Extensions\PdfExportExtension;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

class PdfExportExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'PdfExportExtensionTest.yml';

    protected static $required_extensions = [
        BasePage::class => [
            PdfExportExtension::class,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Config::modify()
            ->set(BasePage::class, 'pdf_export', true)
            ->set(BasePage::class, 'generated_pdf_path', 'assets/_generated_pdfs');
    }

    /**
     * @param string $publicDir
     * @param string $expected
     * @dataProvider pdfFilenameProvider
     */
    public function testPdfFilenameWithoutPublicDirectory($publicDir, $expected)
    {
        Director::config()->set('alternate_public_dir', $publicDir);

        /** @var BasePage|PdfExportExtension $page $page */
        $page = $this->objFromFixture(BasePage::class, 'test-page-one');
        $this->assertStringContainsString($expected, $page->getPdfFilename());
    }

    /**
     * @return array[]
     */
    public function pdfFilenameProvider()
    {
        return [
            'no public folder' => ['', 'assets/_generated_pdfs/test-page-one-1.pdf'],
            'public folder' => ['public', 'public/assets/_generated_pdfs/test-page-one-1.pdf'],
        ];
    }

    public function testPdfLink()
    {
        $page = $this->objFromFixture(BasePage::class, 'test-page-one');
        $this->assertStringContainsString('test-page-one/downloadpdf', $page->PdfLink(), 'Link to download PDF');
    }

    public function testHomePagePdfLink()
    {
        $page = $this->objFromFixture(BasePage::class, 'home-page');
        $this->assertStringContainsString('home/downloadpdf', $page->PdfLink(), 'Link to download PDF');
    }

    public function testPdfLinkDisabled()
    {
        Config::modify()->set(BasePage::class, 'pdf_export', false);
        $page = $this->objFromFixture(BasePage::class, 'test-page-one');
        $this->assertFalse($page->PdfLink(), 'No PDF link as the functionality is disabled');
    }
}
