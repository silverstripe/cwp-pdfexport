<?php

namespace CWP\PDFExport\Extensions;

use SilverStripe\Assets\Filesystem;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Extension;
use SilverStripe\Versioned\Versioned;

class PdfExportControllerExtension extends Extension
{
    private static $allowed_actions = [
        'downloadpdf',
    ];

    /**
     * Serve the page rendered as PDF.
     *
     * @return HTTPResponse|false
     */
    public function downloadpdf()
    {
        if (!$this->owner->data()->config()->get('pdf_export')) {
            return false;
        }

        // We only allow producing live pdf. There is no way to secure the draft files.
        Versioned::set_stage(Versioned::LIVE);

        $path = $this->owner->data()->getPdfFilename();
        if (!file_exists($path)) {
            $this->owner->generatePDF();
        }

        return HTTPRequest::send_file(file_get_contents($path), basename($path), 'application/pdf');
    }

    /**
     * This will return either pdf_base_url from YML, CWP_SECURE_DOMAIN from _ss_environment, or blank. In that
     * order of importance.
     *
     * @return string
     */
    public function getPDFBaseURL()
    {
        // if base url YML is defined in YML, use that
        if ($this->owner->data()->config()->get('pdf_base_url')) {
            $pdfBaseUrl = $this->owner->data()->config()->get('pdf_base_url').'/';
            // otherwise, if we are CWP use the secure domain
        } elseif (Environment::getEnv('CWP_SECURE_DOMAIN')) {
            $pdfBaseUrl = Environment::getEnv('CWP_SECURE_DOMAIN') . '/';
            // or if neither, leave blank
        } else {
            $pdfBaseUrl = '';
        }
        return $pdfBaseUrl;
    }

    /**
     * Don't use the proxy if the pdf domain is the CWP secure domain or if we aren't on a CWP server
     *
     * @return string
     */
    public function getPDFProxy($pdfBaseUrl)
    {
        if (!Environment::getEnv('CWP_SECURE_DOMAIN')
            || $pdfBaseUrl == Environment::getEnv('CWP_SECURE_DOMAIN') . '/'
        ) {
            $proxy = '';
        } else {
            $proxy = ' --proxy ' . Environment::getEnv('SS_OUTBOUND_PROXY')
                . ':' . Environment::getEnv('SS_OUTBOUND_PROXY_PORT');
        }
        return $proxy;
    }

    /**
     * Render the page as PDF using wkhtmltopdf.
     *
     * @return HTTPResponse|false
     */
    public function generatePDF()
    {
        if (!$this->owner->data()->config()->get('pdf_export')) {
            return false;
        }

        $binaryPath = $this->owner->data()->config()->get('wkhtmltopdf_binary');
        if (!$binaryPath || !is_executable($binaryPath)) {
            if (Environment::getEnv('WKHTMLTOPDF_BINARY')
                && is_executable(Environment::getEnv('WKHTMLTOPDF_BINARY'))
            ) {
                $binaryPath = Environment::getEnv('WKHTMLTOPDF_BINARY');
            }
        }

        if (!$binaryPath) {
            user_error('Neither WKHTMLTOPDF_BINARY nor BasePage.wkhtmltopdf_binary are defined', E_USER_ERROR);
        }

        if (Versioned::get_reading_mode() == 'Stage.Stage') {
            user_error('Generating PDFs on draft is not supported', E_USER_ERROR);
        }

        set_time_limit(60);

        // prepare the paths
        $pdfFile = $this->owner->data()->getPdfFilename();
        $bodyFile = str_replace('.pdf', '_pdf.html', $pdfFile);
        $footerFile = str_replace('.pdf', '_pdffooter.html', $pdfFile);

        // make sure the work directory exists
        if (!file_exists(dirname($pdfFile))) {
            Filesystem::makeFolder(dirname($pdfFile));
        }

        //decide the domain to use in generation
        $pdfBaseUrl = $this->owner->getPDFBaseURL();

        // Force http protocol on CWP - fetching from localhost without using the proxy, SSL terminates on gateway.
        if (Environment::getEnv('CWP_ENVIRONMENT')) {
            Config::modify()->set(Director::class, 'alternate_protocol', 'http');
            //only set alternate protocol if CWP_SECURE_DOMAIN is defined OR pdf_base_url is
            if ($pdfBaseUrl) {
                Config::modify()->set(Director::class, 'alternate_base_url', 'http://' . $pdfBaseUrl);
            }
        }

        $bodyViewer = $this->owner->getViewer('pdf');

        // write the output of this page to HTML, ready for conversion to PDF
        file_put_contents($bodyFile, $bodyViewer->process($this->owner));

        // get the viewer for the current template with _pdffooter
        $footerViewer = $this->owner->getViewer('pdffooter');

        // write the output of the footer template to HTML, ready for conversion to PDF
        file_put_contents($footerFile, $footerViewer->process($this->owner));

        //decide what the proxy should look like
        $proxy = $this->owner->getPDFProxy($pdfBaseUrl);

        // finally, generate the PDF
        $command = $binaryPath . $proxy . ' --outline -B 40pt -L 20pt -R 20pt -T 20pt --encoding utf-8 '
            . '--orientation Portrait --disable-javascript --quiet --print-media-type ';
        $retVal = 0;
        $output = [];
        exec(
            $command . " --footer-html \"$footerFile\" \"$bodyFile\" \"$pdfFile\" &> /dev/stdout",
            $output,
            $retVal
        );

        // remove temporary file
        unlink($bodyFile);
        unlink($footerFile);

        // output any errors
        if ($retVal != 0) {
            user_error('wkhtmltopdf failed: ' . implode("\n", $output), E_USER_ERROR);
        }

        // serve the generated file
        return HTTPRequest::send_file(file_get_contents($pdfFile), basename($pdfFile), 'application/pdf');
    }
}
