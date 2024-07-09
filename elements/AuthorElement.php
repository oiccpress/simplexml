<?php

namespace APP\plugins\importexport\simpleXML\elements;

use PKP\config\Config;
use APP\facades\Repo;
use APP\plugins\importexport\simpleXML\SimpleXMLPlugin;
use DOMElement;

class AuthorElement {

    public $givenName, $familyName, $affiliation, $country, $email, $orcid;
    public $primaryContact = false;
    public $id = null;

    public function __construct(DOMElement $element) {

        $this->primaryContact = $element->getAttribute('corresponding') === 'true';

        foreach($element->childNodes as $child) {
            switch(strval($child->nodeName)) {
                case 'givenname':
                case 'givenName':
                    $this->givenName = $child->nodeValue;
                    break;
                case 'familyname':
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
                case 'orcid':
                    $this->orcid = $child->nodeValue;
                    break;
                default:
                    SimpleXMLPlugin::log([ 'UE', 'author', $child->nodeName ]);
            }
        }
    }

    public function save($i, $publication) {

        $collection = Repo::author()->getCollector()->filterByPublicationIds([  $publication->getId() ])
            ->filterByName( $this->givenName, $this->familyName );
        $foundAuthors = $collection->getMany();
        if(count($foundAuthors)) {
            $author = $foundAuthors->first();
        } else {
            $author = Repo::author()->newDataObject();
        }

        $author->setData('publicationId', $publication->getId());
        if ($this->primaryContact) {
            $author->setPrimaryContact(true);
        }
        $author->setSequence($i);

        $author->setGivenName($this->givenName, 'en');
        $author->setFamilyName($this->familyName, 'en');
        $author->setAffiliation($this->affiliation, 'en');
        $author->setEmail($this->email ?? Config::getVar('email', 'default_envelope_sender') ?? 'noreply@oiccpress.com'); // Some value is required to satisfy the system requirements
        $author->setOrcid($this->orcid);

        if(count($foundAuthors)) {
            Repo::author()->dao->update($author);
        } else {
            $this->id = Repo::author()->dao->insert($author);
        }

    }

}