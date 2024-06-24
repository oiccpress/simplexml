<?php

namespace APP\plugins\importexport\simplexml\elements;

use APP\facades\Repo;
use DOMElement;
use Illuminate\Support\Facades\DB;
use PKP\workflow\WorkflowStageDAO;

class ArticleElement {

    public $files = [];
    public $publication, $old_id;

    public $old_file_views, $old_views, $date_received, $date_accepted, $manuscript_id;

    public function __construct(DOMElement $element) {

        if($element->getAttribute('visits')) {
            $this->old_views = $element->getAttribute('visits');
        }

        if($element->getAttribute('date_received')) {
            $this->date_received = $element->getAttribute('date_received');
        }
        if($element->getAttribute('date_accepted')) {
            $this->date_accepted = $element->getAttribute('date_accepted');
        }
        if($element->getAttribute('manuscript_id')) {
            $this->manuscript_id = $element->getAttribute('manuscript_id');
        }

        foreach($element->childNodes as $child) {
            switch($child->nodeName) {
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
                    }
                    break;
                case '#text':
                    break;
                default:
                    echo "WARN: unknown nodeName for article " . $child->nodeName . "\n";
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

        // Add article elements in
        foreach($this->files as $file) {
            $file->save($context, $submission, $publicationId);
        }

        Repo::submission()->dao->update($submission);

        echo "A\t" . $submission->getId() . "\n";

    }

}