<?php

namespace APP\plugins\importexport\simplexml\elements;

use DOMElement;

class AuthorElement {

    public $givenName, $familyName, $affiliation, $country, $email;

    public function __construct(DOMElement $element) {
        foreach($element->childNodes as $child) {
            switch($child->nodeName) {
                case 'givenName':
                    $this->givenName = $child->nodeValue;
                    break;
                case 'familyName':
                    $this->familyName = $child->nodeValue;
                    break;
                case 'affiliation':
                    $this->affiliation = $child->nodeValue;
                    break;
                case 'country':
                    $this->country = $child->nodeValue;
                    break;
                case 'email':
                    $this->email = $child->nodeValue;
                    break;
                default:
                    echo "WARN: unknown nodeName for article " . $child->nodeName . "\n";
            }
        }
    }

}