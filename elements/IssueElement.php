<?php

namespace APP\plugins\importexport\simpleXML\elements;

use APP\facades\Repo;
use APP\plugins\importexport\simpleXML\SimpleXMLPlugin;
use DOMElement;
use Illuminate\Support\Facades\DB;

class IssueElement {

    public $volume, $year, $number, $date_published, $last_modified, $articles, $sections;

    public function __construct(DOMElement $element) {

        foreach($element->childNodes as $child) {
            switch(strval($child->nodeName)) {
                case 'issue_identification':
                    $this->process_issue_idenitification($child);
                    break;
                case 'date_published':
                    $this->date_published = $child->nodeValue;
                    break;
                case 'last_modified':
                    $this->last_modified = $child->nodeValue;
                    break;
                case 'articles':
                    $this->articles = [];
                    foreach($child->childNodes as $subnode) {
                        if($subnode->nodeName == 'article') {
                            $this->articles[] = new ArticleElement($subnode);
                        }
                    }
                    break;
                case 'sections':
                    $this->sections = [];
                    foreach($child->childNodes as $subnode) {
                        if($subnode->nodeName == 'section') {
                            $this->sections[] = new SectionElement($subnode);
                        }
                    }
                case '#text':
                    break;
                default:
                    SimpleXMLPlugin::log([ 'UE', 'issue', $child->nodeName ]);
            }
        }

    }

    public function process_issue_idenitification($element) {

        foreach($element->childNodes as $child) {
            switch($child->nodeName) {
                case 'volume':
                    $this->volume = $child->nodeValue;
                    break;
                case 'year':
                    $this->year = $child->nodeValue;
                    break;
                case 'number':
                    $this->number = $child->nodeValue;
                    break;
                default:
                    SimpleXMLPlugin::log([ 'UE', 'issueidentification', $child->nodeName ]);
            }
        }

    }

    public function save($context) {

        // First find the issue exists or not
        $collector = Repo::issue()->getCollector()
                ->filterByContextIds([$context->getId()]);
        if ($this->volume !== null) {
            $collector->filterByVolumes([$this->volume]);
        }
        if ($this->number !== null) {
            $collector->filterByNumbers([$this->number]);
        }
        if ($this->year !== null) {
            $collector->filterByYears([$this->year]);
        }

        $foundIssues = $collector->getMany();
        $issueId = null;
        if(count($foundIssues) == 0) {
            // Create issue
            $issue = Repo::issue()->newDataObject();
            $issue->setJournalId($context->getId());
        
        } else {
            $issue = $foundIssues->first();
            $issueId = $issue->getId();
        }

        $issue->setVolume($this->volume);
        $issue->setNumber($this->number);
        if($this->volume) {
            $issue->setShowVolume(true);
        }
        if($this->number) {
            $issue->setShowNumber(true);
        }
        $issue->setYear($this->year);
        $issue->setPublished(1);
        $issue->setDatePublished($this->date_published);

        if(count($foundIssues) == 0) {
            $issueId = Repo::issue()->add($issue);
        } else {
            Repo::issue()->dao->update($issue);
        }

        // Update Seqence!
        $pos = floatval( (99-$this->volume) . '.' . $this->number );
        SimpleXMLPlugin::log([ 'ISSUE', $issueId, $this->volume, $this->number ]);

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
