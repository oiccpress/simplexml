<?php

namespace APP\plugins\importexport\simpleXML\elements;

use APP\facades\Repo;
use APP\plugins\importexport\simpleXML\SimpleXMLPlugin;
use DOMElement;

class SectionElement {

    public $ref, $title, $pos, $abbrv;

    public function __construct(DOMElement $element) {
        $this->ref = $element->getAttribute("ref");
        $this->pos = $element->getAttribute("seq");

        foreach($element->childNodes as $child) {
            switch($child->nodeName) {
                case 'abbrev':
                    $this->abbrv = $child->nodeValue;
                    break;
                case 'title':
                    $this->title = $child->nodeValue;
                    break;
                default:
                    SimpleXMLPlugin::log([ 'UE', 'section', $child->nodeName ]);
            }
        }
    }

    public function save($issue, $context) {
        $section = Repo::section()->getCollector()->filterByContextIds([$context->getId()])->filterByTitles([$this->title])->getMany()->first();
        if(!$section) {
            $section = Repo::section()->newDataObject();
            $section->setContextId($context->getId());
            $section->setTitle($this->title, 'en');
            if($this->abbrv) {
                $section->setAbbrev( $this->abbrv, 'en' );
            }
            $section = Repo::section()->add($section);
        } else {
            if($this->abbrv != $section->getAbbrev('en')) {
                $section->setAbbrev($this->abbrv, 'en');
                Repo::section()->edit($section, []);
            }
            $section = $section->getId();
        }
        if($this->pos) {
            Repo::section()->upsertCustomSectionOrder($issue->getId(), $section, $this->pos);
        }
        return $section;
    }

}