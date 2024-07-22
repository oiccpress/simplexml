<?php

namespace APP\plugins\importexport\simpleXML\elements;

use APP\facades\Repo;
use APP\plugins\importexport\simpleXML\SimpleXMLPlugin;
use DOMElement;
use Illuminate\Support\Facades\DB;

class InPressElement {

    public $articles, $sections;

    public function __construct(DOMElement $element) {

        foreach($element->childNodes as $child) {
            switch(strval($child->nodeName)) {
                case 'sections':
                    $this->sections = [];
                    foreach($child->childNodes as $subnode) {
                        if($subnode->nodeName == 'section') {
                            $this->sections[] = new SectionElement($subnode);
                        }
                    }
                case 'articles':
                    $this->articles = [];
                    foreach($child->childNodes as $subnode) {
                        if($subnode->nodeName == 'article') {
                            $this->articles[] = new ArticleElement($subnode);
                        }
                    }
                    break;
                case '#text':
                    break;
                default:
                    SimpleXMLPlugin::log([ 'UE', 'issue', $child->nodeName ]);
            }
        }

    }

    public function save($context) {

        // First find the issue exists or not
        $collector = Repo::issue()->getCollector()
                ->filterByContextIds([$context->getId()])
                ->filterByTitles([ 'Articles in Press' ]); // This is our logic for finding "Articles in Press"

        $foundIssues = $collector->getMany();
        $issueId = null;
        if(count($foundIssues) == 0) {
            // Create issue
            $issue = Repo::issue()->newDataObject();
            $issue->setJournalId($context->getId());
            $issue->setTitle('Articles in Press', 'en');
            $issueId = Repo::issue()->add($issue);
        } else {
            $issue = $foundIssues->first();
            $issueId = $issue->getId();
        }

        // Filter save command down!
        $savedSections = [];
        foreach($this->sections as $section) {
            $savedSections[$section->ref] = $section->save($context);
        }
        foreach($this->articles as $article) {
            $article->save($context, $savedSections, $issueId);
        }

    }

}
