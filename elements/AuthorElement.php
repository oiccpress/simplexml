<?php

namespace APP\plugins\importexport\simpleXML\elements;

use PKP\config\Config;
use APP\facades\Repo;
use APP\plugins\importexport\simpleXML\SimpleXMLPlugin;
use DOMElement;
use PKP\userGroup\UserGroup;

class AuthorElement {

    public $givenName, $familyName, $country, $email, $orcid, $userGroupName, $affiliation;
    public $primaryContact = false;
    public $affiliations = [];
    public $id = null;

    public function __construct(DOMElement $element) {

        $this->primaryContact = $element->getAttribute('corresponding') === 'true';
        $this->userGroupName = $element->getAttribute('user_group_ref');

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
                    if(method_exists(Repo::class, 'affiliation')) {
                        // 3.5
                        $this->affiliations[] = Repo::affiliation()->newDataObject([
                            'masthead' => false,
                            'name' => ['en' => $child->nodeValue ],
                        ]);
                    }
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

    public function save($context, $i, $publication) {

        $collection = Repo::author()->getCollector()->filterByPublicationIds([  $publication->getId() ])
            ->filterByName( $this->givenName, $this->familyName );
        $foundAuthors = $collection->getMany();
        if(count($foundAuthors)) {
            $author = $foundAuthors->first();
            $this->id = $author->getId();
        } else {
            $author = Repo::author()->newDataObject();
        }

        $author->setData('publicationId', $publication->getId());
        if ($this->primaryContact) {
            $author->setPrimaryContact(1);
        }
        $author->setSequence($i);

        $author->setGivenName($this->givenName, 'en');
        $author->setFamilyName($this->familyName, 'en');
        if(!$this->familyName) {
            SimpleXMLPlugin::log([ 'FN', 'author', 'Blank FamilyName may cause issues' ]);
        }
        if( method_exists( $author, 'setAffiliations' ) ) {
            // 3.5
            $author->setAffiliations($this->affiliations);
        } else {
            $author->setAffiliation($this->affiliation, 'en');
        }
        $author->setEmail($this->email ?? Config::getVar('email', 'default_envelope_sender') ?? 'noreply@example.com'); // Some value is required to satisfy the system requirements
        $author->setOrcid($this->orcid);

        if(method_exists( UserGroup::class, 'query' )) {
            // 3.5
            $userGroups = UserGroup::query()
                ->withContextIds([$context->getId()])
                ->get();

            foreach ($userGroups as $userGroup) {
                if ($this->userGroupName == $userGroup->getLocalizedData('name')) {
                    // Found a candidate; stash it.
                    $author->setUserGroupId($userGroup->id);
                    break;
                }
            }
        } else {
            // 3.4
            $userGroups = Repo::userGroup()->getCollector()
                ->filterByContextIds([$context->getId()])
                ->getMany();

            foreach ($userGroups as $userGroup) {
                if (in_array($this->userGroupName, $userGroup->getName(null))) {
                    // Found a candidate; stash it.
                    $author->setUserGroupId($userGroup->getId());
                    break;
                }
            }
        }

        if(count($foundAuthors)) {
            Repo::author()->dao->update($author);
        } else {
            $this->id = Repo::author()->dao->insert($author);
        }

        if( method_exists( $author, 'setAffiliations' ) ) {
            // 3.5
            Repo::affiliation()->saveAffiliations($author);
        }

    }

}