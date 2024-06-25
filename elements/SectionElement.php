<?php

namespace APP\plugins\importexport\simpleXML\elements;

use APP\facades\Repo;
use DOMElement;

class SectionElement {

    public $ref, $title;

    public function __construct(DOMElement $element) {
        $this->ref = $element->getAttribute("ref");

        foreach($element->childNodes as $child) {
            switch($child->nodeName) {
                case 'title':
                    $this->title = $child->nodeValue;
                    break;
                default:
                    echo "WARN: unknown nodeName for section " . $child->nodeName . "\n";
            }
        }
    }

    public function save($context) {
        $section = Repo::section()->getCollector()->filterByContextIds([$context->getId()])->filterByTitles([$this->title])->getMany()->first();
        if(!$section) {
            $section = Repo::section()->newDataObject();
            $section->setContextId($context->getId());
            $section->setTitle($this->title, 'en');
            return Repo::section()->add($section);
        }
        return $section->getId();
    }

}