<?php

namespace App\Services;

use CidiLabs\PhpAlly\PhpAllyIssue;
use CidiLabs\PhpAlly\PhpAllyReport;

use App\Entity\ContentItem;

use DOMDocument;
use DOMElement;
use DOMXPath;

/*
    Given a JSON report generated by accessibility-checker,
    parse the JSON for all failed rules (according to Equal Access)
    and put them in a phpAlly report

    TODO:
        - check for phpally-ignore on html snippets and ignore them
        - think about how to migrate old database data to equal access
        - find way to skip rules in aws perhaps(?)
        - check for phpally-ignore in lambda function instead
*/

class EqualAccessService {

    // probably should disable rules in equal access itself, this is temporary hopefully
    private $skipRules = array(
        "html_lang_exists",
        "html_skipnav_exists",
        "page_title_exists",
        "skip_main_exists",
        "style_highcontrast_visible",
        "style_viewport_resizable",
        "aria_accessiblename_exists",
        "aria_content_in_landmark", 
        "a_target_warning",
    );

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

    public function xpathToSnippet($domXPath, $xpathQuery) {
        // Query the document and save the results into an array
        // In a perfect world this array should only have one element

        $xpathResults = $domXPath->query($xpathQuery);
        $htmlSnippet = null;

        // TODO: For now, if there are multiple results we're just
        // going to choose the first one
        if ($xpathResults) {
            foreach ($xpathResults as $xpathResult) {
                $htmlSnippet = $xpathResult;
            }
        }

        // If no results are found, return null (meaning nothing was found)
        return $htmlSnippet;
    }

    public function checkforIgnoreClass($htmlSnippet) {
        // Assume no phpAllyIgnore by default
        $phpAllyIgnore = false;

        if ($htmlSnippet) {
            $classes = $htmlSnippet->getAttribute("class");

            if (strlen($classes) > 0 && str_contains($classes, "phpally-ignore")) {
                $phpAllyIgnore = true;
            } 
        }

        return $phpAllyIgnore;
    }

    // Generate a UDOIT-style JSON report from the output of Equal Access
    public function generateReport($json, $document) {
        $this->logToServer("Generating report in EqualAccessService!");
        $report = new PhpAllyReport();
        $xpath = new DOMXPath($document);

        $issues = array();
        $issueCounts = array();

        // $this->logToServer(json_encode($json["results"]));
        foreach ($json["results"] as $results) {
            $equalAccessRule = $results["ruleId"];

            // $this->logToServer($equalAccessRule);
            $xpathQuery = $results["path"]["dom"];
            // $this->logToServer($xpathQuery);
            $issueHtml = $this->xpathToSnippet($xpath, $xpathQuery);
            $metadata = null;

            // First check if the HTML has phpally-ignore and also check if the rule isn't one we skip.
            if (!$this->checkForIgnoreClass($issueHtml) && !in_array($equalAccessRule, $this->skipRules)) {
                // Populate the issue counts field with how many total issues
                // with the specific rule are found
                if (array_key_exists($equalAccessRule, $issueCounts)) {
                    $issueCounts[$equalAccessRule]++;
                }
                else {
                    $issueCounts[$equalAccessRule] = 1;
                }

                // Check for specific rules (mostly about contrast)
                // so we can add CSS metadata to database
                // TODO: nicer error checking? currently it just
                // checks if the array elements at index 3 and 4 exist lol,
                // also should check if ruleID is a CSS related one,
                // since some messageArgs also are just blank
                $reasonId = $results["reasonId"];
                $message = $results["message"];
                $messageArgs = $results["messageArgs"];

                $metadata = $this->createMetadata($reasonId, $message, $messageArgs);

                // Check for null (aka no XPath result was found) and skip.
                // Otherwise, create a new issue with the HTML from the XPath query.
                if ($issueHtml) {
                    // UDOIT database has 'html' and 'preview_html',
                    // where 'preview_html' is the parent of the offending html
                    $parentIssueHtml = $issueHtml->parentNode;
                }  
                else {
                    continue;
                }
                
                $issue = new PhpAllyIssue($equalAccessRule, $issueHtml, $parentIssueHtml, $metadata);
                $report->setIssueCounts($equalAccessRule, $issueCounts[$equalAccessRule], -1);
                array_push($issues, $issue);
                $report->setErrors([]);
            }
        }

        $report->setIssues($issues);

        // Debug
        // $this->logToServer("REPORT:");
        // $this->logToServer(json_encode($report, JSON_PRETTY_PRINT));

        return $report;
    }

    public function createMetadata($reasonId, $message, $messageArgs) {
        // The Equal Access report has a "messageArgs" section
        // which has any dynamic content (color contrast ratios, specific text it wants to mark)
        // that we can then use on UFIXIT to generate specific messages

        $metadata = array(
            "reasonId" => $reasonId,
            "message" => $message,
            "messageArgs" => $messageArgs,
        );

        return json_encode($metadata);
    }

    public function getDomDocument($html)
    {
        // Load the HTML string into a DOMDocument that PHP can parse.
        // TODO: checks for if <html>, <body>, or <head> and <style> exist? technically canvas will always remove them if they are present in the HTML editor
        // but you never know, also the loadHTML string is pretty long and kinda unreadable, could individually load in each element maybe
        $dom = new DOMDocument('1.0', 'utf-8');
        libxml_use_internal_errors(true); // this might not be the best idea, we use this to stop udoit from crashing when it sees an html5 element

        // Set the default background color and text color in the DOMDocument's <style>
        $envBackgroundColor = $_ENV['BACKGROUND_COLOR'];
        $envTextColor = $_ENV['TEXT_COLOR'];

        if (strpos($html, '<?xml encoding="utf-8"') !== false) {
            $dom->loadHTML("<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"><title>Placeholder Page Title</title></head><body><div role=\"main\"><h1>Placeholder Page Title</h1>{$html}</div></body></html>", LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        } else {
            $dom->loadHTML("<?xml encoding=\"utf-8\" ?><!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"><title>Placeholder Page Title</title></head><body><div role=\"main\"><h1>Placeholder Page Title</h1>{$html}</div></body></html>", LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        }

        return $dom;

    }
}
