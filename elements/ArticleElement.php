<?php

namespace APP\plugins\importexport\simpleXML\elements;

use APP\facades\Repo;
use APP\plugins\importexport\simpleXML\SimpleXMLPlugin;
use DOMElement;
use Illuminate\Support\Facades\DB;
use PKP\workflow\WorkflowStageDAO;

class ArticleElement {

    public $files = [];
    public $publication, $old_id, $doi_id, $dor_id;

    public $old_file_views, $old_views, $date_received, $date_accepted, $manuscript_id, $old_permalink;

    public function __construct(DOMElement $element) {

        if(!empty($element->getAttribute('visits'))) {
            $this->old_views = intval(
                str_replace(",", "", SimpleXMLPlugin::safe_value($element->getAttribute('visits'))));
        }

        if($element->getAttribute('date_received')) {
            $this->date_received = SimpleXMLPlugin::safe_value($element->getAttribute('date_received'));
        }
        if($element->getAttribute('date_accepted')) {
            $this->date_accepted = SimpleXMLPlugin::safe_value($element->getAttribute('date_accepted'));
        }
        if($element->getAttribute('manuscript_id')) {
            $this->manuscript_id = SimpleXMLPlugin::safe_value($element->getAttribute('manuscript_id'));
        }

        foreach($element->childNodes as $child) {
            switch(strval($child->nodeName)) {
                case 'id':
                    switch($child->getAttribute("type")) {
                        case 'doi':
                            $this->doi_id = SimpleXMLPlugin::safe_value($child->nodeValue);
                            break;
                        case 'other::dor':
                            $this->dor_id = SimpleXMLPlugin::safe_value($child->nodeValue);
                            break;
                        default:
                            SimpleXMLPlugin::log([ 'UID', 'articleid', $child->getAttribute('type') ]);
                    }
                    break;
                case 'publication':
                    $this->publication = new PublicationElement($child);
                    break;
                case 'submission_file':
                    $file = new FileElement($child);
                    if($file->valid()) {
                        $this->files[] = $file;
                        if($file->old_views) {
                            $this->old_file_views = $file->old_views;
                        }
                    } else {
                        SimpleXMLPlugin::log([ 'FILEWARN', 'article->submission_file', $file->file_name . ' ' . $file->file_id ]);
                    }
                    break;
                case 'old_permalink':
                    $this->old_permalink = SimpleXMLPlugin::safe_value($child->getAttribute('url'));
                    break;
                case '#text':
                    break;
                default:
                    SimpleXMLPlugin::log([ 'UE', 'article', $child->nodeName ]);
            }
        }
    }

    public function save($context, $sections, $issue) {

        $pubId = DB::select('SELECT * FROM publication_settings WHERE setting_name = "Title" AND setting_value = ?', [ $this->publication->title ]);
        $publication = null;
        if(count($pubId)) {
            $pubId = $pubId[0]->publication_id;
            $publication = Repo::publication()->get($pubId);
            $submission = Repo::submission()->get($publication->getData('submissionId'));
        } else {
            $submission = Repo::submission()->newDataObject();
        }

        $submission->setData('locale', $context->getPrimaryLocale());
        $submission->setData('submissionProgress', '' );
        $submission->setData('status', 3 );
        $submission->setData('stageId', 5 );
        $submission->setData('contextId', $context->getId());
        $submission->setData('issueId', $issue);
        // $submission->setData('currentPublicationId', -1);

        if(empty($pubId)) {
            $submissionId = Repo::submission()->dao->insert($submission);
            $submission = Repo::submission()->get($submissionId);
        }

        $publicationId = $this->publication->save($context, $submission, $publication, $sections, $issue, $this);
        $submission->setData('currentPublicationId', $publicationId);

        // Sort files
        $html_file = null;
        foreach($this->files as $k => $file) {
            if(strtoupper($file->extension) == 'HTML') {
                $html_file = $file->save($context, $submission, $publicationId);
                unset($this->files[$k]);
            }
        }

        // Add article elements in
        foreach($this->files as $file) {
            if($file->stage == 'dependent') {
                $file->parent_file = $html_file;
            }
            $file->save($context, $submission, $publicationId);
        }

        Repo::submission()->dao->update($submission);

        SimpleXMLPlugin::log([ 'ART', $submission->getId(), $this->publication->title ]);

    }

}