<?php

namespace APP\plugins\importexport\simplexml\elements;

use APP\facades\Repo;
use DOMElement;

class PublicationElement {

    public $title, $abstract, $copyrightHolder, $copyrightYear, $pages, $datePublished, $section;
    public $authors = [];

    public function __construct(DOMElement $element) {
        $this->datePublished = $element->getAttribute('date_published');
        $this->section = $element->getAttribute("section_ref");

        foreach($element->childNodes as $child) {
            switch($child->nodeName) {
                case 'title':
                    $this->title = $child->nodeValue;
                    break;
                case 'abstract':
                    $this->abstract = $child->nodeValue;
                    break;
                case 'copyrightHolder':
                    $this->copyrightHolder = $child->nodeValue;
                    break;
                case 'copyrightYear':
                    $this->copyrightYear = $child->nodeValue;
                    break;
                case 'authors':
                    foreach($child->childNodes as $author) {
                        if($author->nodeName == 'author') {
                            $this->authors[] = new AuthorElement($author);
                        }
                    }
                    break;
                case 'article_galley':
                    $this->parse_article_galley($child);
                    break;
                default:
                    echo "WARN: unknown nodeName for article " . $child->nodeName . "\n";
            }
        }
    }

    public function parse_article_galley($element) {
        foreach($element->childNodes as $child) {
            switch($child->nodeName) {
                case 'pages':
                    $this->pages = $child->nodeValue;
                    break;
                default:
                    echo "WARN: unknown nodeName for article galley " . $child->nodeName . "\n";
            }
        }
    }

    public function save($context, $submission, $publication, $sections, $issue) {
        if(!$publication) {
            $publication = Repo::publication()->newDataObject();
        }

        $publication->setData('status', 5);
        $publication->setData('submissionId', $submission->getId());
        $publication->setData('copyrightYear', $this->copyrightYear);
        $publication->setData('title', $this->title, 'en');
        $publication->setData('abstract', $this->abstract, 'en');
        $publication->setData('copyrightHolder', $this->copyrightHolder, 'en');
        $publication->setData('pages', $this->pages);
        $publication->setData('datePublished', $this->datePublished);
        $publication->setData('issueId', intval($issue));

        $publication->setData('sectionId', $sections[$this->section]);

        if($publication->getId()) {
            Repo::publication()->dao->update($publication);
            $publicationId = $publication->getId();
        } else {
            $publicationId = Repo::publication()->dao->insert($publication);
        }
        echo "pid: " . $publicationId . "\n";

        return $publicationId;
    }

}