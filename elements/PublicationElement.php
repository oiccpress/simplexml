<?php

namespace APP\plugins\importexport\simpleXML\elements;

use APP\facades\Repo;
use DOMElement;
use PKP\db\DAORegistry;

class PublicationElement {

    public $title, $abstract, $copyrightHolder, $copyrightYear, $pages, $datePublished, $section;
    public $authors = [];
    public $keywords = [];
    public $subjects = [];
    public $cover;

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
                case 'keywords':
                    foreach($child->childNodes as $author) {
                        if($author->nodeName == 'keyword') {
                            $this->keywords[] = trim($author->nodeValue);
                        }
                    }
                    break;
                case 'subjects':
                    foreach($child->childNodes as $author) {
                        if($author->nodeName == 'subject') {
                            $this->subjects[] = trim($author->nodeValue);
                        }
                    }
                    break;
                case 'covers':
                    foreach($child->childNodes as $c) {
                        if($c->nodeName == 'cover') {
                            $this->cover = new CoverElement($c);
                        }
                    }
                    
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

    public function save($context, $submission, $publication, $sections, $issue, $articleElement) {
        if(!$publication) {
            $publication = Repo::publication()->newDataObject();
        }

        $publication->setData('status', 3);
        $publication->setData('submissionId', $submission->getId());
        $publication->setData('copyrightYear', $this->copyrightYear);
        $publication->setData('title', $this->title, 'en');
        $publication->setData('abstract', $this->abstract, 'en');
        $publication->setData('copyrightHolder', $this->copyrightHolder, 'en');
        $publication->setData('pages', $this->pages);
        $publication->setData('datePublished', $this->datePublished);
        $publication->setData('issueId', intval($issue));

        $publication->setData('sectionId', $sections[$this->section]);

        $publication->setData('keywords', ['en' => $this->keywords ]);
        $publication->setData('subjects', ['en' => $this->subjects ]);
        if($this->cover) {
            $publication->setData('coverImage', $this->cover->save($context));
        }

        // TODO: Move this to a filter or something?
        if($articleElement->old_file_views) {
            $publication->setData('oldmetrics_pdf_views', $articleElement->old_file_views);
        }
        if($articleElement->old_views) {
            $publication->setData('oldmetrics_article_views', $articleElement->old_views);
        }
        if($articleElement->date_received) {
            $publication->setData('submission_dates__revised', $articleElement->date_received);
        }
        if($articleElement->date_accepted) {
            $publication->setData('submission_dates__accepted', $articleElement->date_accepted);
        }

        if($publication->getId()) {
            Repo::publication()->dao->update($publication);
            $publicationId = $publication->getId();
        } else {
            $publicationId = Repo::publication()->dao->insert($publication);
        }
        echo "P\t" . $publicationId . "\n";

        foreach($this->authors as $i => $author) {
            $author->save($i, $publication);
            if($author->primaryContact) {
                $publication->setData('primaryContactId', $author->id);
                Repo::publication()->dao->update($publication);
            }
        }

        return $publicationId;
    }

}