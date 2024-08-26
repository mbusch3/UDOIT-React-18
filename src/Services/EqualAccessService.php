<?php

namespace App\Services;

use App\Entity\ContentItem;
use CidiLabs\PhpAlly\PhpAllyIssue;
use CidiLabs\PhpAlly\PhpAllyReport;

use DOMDocument;
use DOMXPath;

// bridge between udoit and equalaccess

class EqualAccessService {

    /** @var App\Service\HtmlService */
    protected $htmlService;

    // Rule mappings from Equal Access names to generic UDOIT names
    // TODO: Verify rules, some Equal Access rules may also be 
    private $ruleMappings = array(
        // Image Alt
        "img_alt_misuse" =>  "ImageAltIsDifferent",
        "img_alt_valid" => "ImageHasAlt",
        // Links
        "a_text_purpose" => "AnchorMustContainText",
        // Media
        "caption_track_exists" => "VideoProvidesCaptions", // need to have kind="captions" in <track>
        // Tables
        "table_headers_exist" => "TableDataShouldHaveTableHeader",
        // Deprecated Elements (seems like all of these are overwritten by Canvas?)
        "blink_elem_deprecated" => "BlinkIsNotUsed", // also maybe blink_css_review?
        "marquee_elem_avoid" => "MarqueeIsNotUsed",
        // Objects
        "object_text_exists" => "ObjectMustContainText",
        // Headings
        "heading_content_exists" => "HeadersHaveText",
        "text_block_heading" => "ParagraphNotUsedAsHeader",
        // Color Contrast
        "text_contrast_sufficient" => "CssTextHasContrast",

    );

    public function scanContentItem(ContentItem $contentItem) {
        $html = HtmlService::clean($contentItem->getBody());

        if (!$html) {
            return;
        }

        $data = $this->checkMany($html, [], []); 

        return $data;
    }

    public function logToServer(string $message) {
        $options = [
            'http' => [
                'header' => "Content-type: text/html\r\n",
                'method' => 'POST',
                'content' => $message,
            ],
        ];
        
        $context = stream_context_create($options);
        file_get_contents("http://host.docker.internal:3000/log", false, $context);
    }

    public function postData(string $url, string $html) {
        $options = [
            'http' => [
                'header' => "Content-type: text/html\r\n",
                'method' => 'POST',
                'content' => $html,
            ],
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        return $result;
    }

    public function checkMany($content, $ruleIds = [], $options = []) {
        $document = $this->getDomDocument($content);
        $response = $this->postData("http://host.docker.internal:3000/check", $document->saveHTML());
        $json = json_decode($response, true);
        $report = $this->generateReport($json, $document);
        return $report;
    }

    public function scanHtml($html, $rules = [], $options = []) {
        $html = HtmlService::clean($html);

        return $this->checkMany($html, [], []);
    }

    public function xpathToSnippet($domXPath, $xpathQuery): \DOMElement {
        // Query the document and save the results into an array
        // In a perfect world this array should only have one element
        $xpathResults = $domXPath->query($xpathQuery);
        $htmlSnippet = null;

        // TODO: For now, if there are multiple results we're just
        // going to choose the "last" one
        if (!is_null($xpathResults)) {
            foreach ($xpathResults as $xpathResult) {
                $htmlSnippet = $xpathResult;
            }
        }

        return $htmlSnippet;
    }

    // Generate a UDOIT-style JSON report from the output of Equal Access
    public function generateReport($json, $document) {
        $report = new PhpAllyReport();
        $xpath = new DOMXPath($document);

        $issues = array();
        $issueCounts = array();

        foreach ($json["results"] as $results) {
            $equalAccessRule = $results["ruleId"];
            $xpathQuery = $results["path"]["dom"];

            // Map the Equal Access rule name to a UDOIT-style rule name
            $udoitRule = $this->ruleMappings[$equalAccessRule] ?? "UnknownRule";
            
            $ruleMapString = $equalAccessRule . " " . $udoitRule;
            $this->logToServer($ruleMapString);

            if ($udoitRule != "UnknownRule") {
                if(array_key_exists($udoitRule, $issueCounts)) {
                    $issueCounts[$udoitRule]++;
                }
                else {
                    $issueCounts[$udoitRule] = 1;
                }

                // UDOIT database has 'html' and 'preview_html',
                // where 'preview_html' is the parent of the offending html
                $issueHtml = $this->xpathToSnippet($xpath, $xpathQuery);
                $parentIssueHtml = $issueHtml->parentNode;

                // Catch if the parent was already the root element
                // TODO: If there's an <html> or <body> tag already in the page (somehow), then this could break?
                // if ($parentIssueHtml->tagName === "body" || $parentIssueHtml->tagName === "html") {
                //     $nodeList = $xpath->query("/html[1]/body[1]/*");
                //     $dom = new DOMDocument('1.0', 'utf-8');
                //     foreach ($nodeList as $node) {
                //         $dom->appendChild($dom->importNode($node, true));
                //     }

                //     $this->logToServer("parent is root, printing entire document:");
                //     $this->logToServer($dom->saveHtml());

                //     $parentIssueHtml = $dom;
                // }


                $issue = new PhpAllyIssue($udoitRule, $issueHtml, $parentIssueHtml, null);

                $report->setIssueCounts($udoitRule, $issueCounts[$udoitRule], -1);

                array_push($issues, $issue);

                $report->setErrors([]);
            }


        }

        $report->setIssues($issues);

        $this->logToServer("REPORT:");
        $this->logToServer(json_encode($report, JSON_PRETTY_PRINT));

        return $report;

    }

    public function getDomDocument($html)
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        if (strpos($html, '<?xml encoding="utf-8"') !== false) {
            $dom->loadHTML("<html><body>{$html}</body></html>", LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        } else {
            $dom->loadHTML("<?xml encoding=\"utf-8\" ?><html><body>{$html}</body></html>", LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        }

        return $dom;

    }
}
