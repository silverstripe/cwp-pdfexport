<?php

namespace CWP\PDFExport\Extensions;

use SilverStripe\Control\Director;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Versioned\Versioned;

class PdfExportExtension extends DataExtension
{
    /**
     * @config
     * @var bool
     */
    private static $pdf_export = false;

    /**
     * Domain to generate PDF's from, DOES not include protocol
     * i.e. google.com not http://google.com
     * @config
     * @var string
     */
    private static $pdf_base_url = '';

    /**
     * Allow custom overriding of the path to the WKHTMLTOPDF binary, in cases
     * where multiple versions of the binary are available to choose from. This
     * should be the full path to the binary (e.g. /usr/local/bin/wkhtmltopdf)
     * @see BasePage_Controller::generatePDF();
     *
     * @config
     * @var string|null
     */
    private static $wkhtmltopdf_binary = null;

    /**
     * Where to store generated PDF files
     *
     * @config
     * @var string
     */
    private static $generated_pdf_path = 'assets/_generated_pdfs';

    /**
     * Return the full filename of the pdf file, including path & extension
     */
    public function getPdfFilename()
    {
        $baseName = sprintf('%s-%s', $this->owner->URLSegment, $this->owner->ID);

        $folderPath = $this->owner->config()->get('generated_pdf_path');
        if ($folderPath[0] != '/') {
            $folderPath = BASE_PATH . '/' . $folderPath;
        }

        return sprintf('%s/%s.pdf', $folderPath, $baseName);
    }

    /**
     * Build pdf link for template.
     */
    public function PdfLink()
    {
        if (!$this->owner->config()->get('pdf_export')) {
            return false;
        }

        $path = $this->getPdfFilename();

        if ((Versioned::get_stage() === Versioned::LIVE) && file_exists($path)) {
            return Director::baseURL() . preg_replace('#^/#', '', Director::makeRelative($path));
        }
        return $this->owner->Link('downloadpdf');
    }

    /**
     * Remove linked pdf when publishing the page, as it would be out of date.
     */
    public function onAfterPublish()
    {
        $filepath = $this->getPdfFilename();
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    /**
     * Remove linked pdf when unpublishing the page, so it's no longer valid.
     */
    public function onAfterUnpublish()
    {
        $filepath = $this->getPdfFilename();
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
}
