<?php

namespace APP\plugins\importexport\simplexml\elements;

use APP\core\Services;
use APP\facades\Repo;
use DOMElement;
use Illuminate\Support\Facades\DB;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\file\TemporaryFileManager;

class FileElement {

    public $file_id, $file_name, $genre, $extension, $old_views;
    private $file_contents;

    public function __debugInfo(){
    
        $vars = get_object_vars($this);
        
        $vars['file_contents'] = '~~base64~~';
    
        return $vars;
    }

    public function __construct(DOMElement $element) {
        $this->file_id = $element->getAttribute("id");
        $this->genre = $element->getAttribute("genre");

        foreach($element->childNodes as $child) {
            switch($child->nodeName) {
                case 'name':
                    $this->file_name = $child->nodeValue;
                    break;
                case 'file':
                    // expect embed
                    $this->extension = $child->getAttribute("extension");
                    if($child->childNodes[0]->nodeName == 'embed') {
                        $this->file_contents = $child->childNodes[0]->nodeValue;
                    }
                    if($child->getAttribute("downloads")) {
                        $this->old_views = intval( $child->getAttribute("downloads") );
                    }
                    break;
                case '#text':
                    break;
                default:
                    echo "WARN: unknown nodeName for file " . $child->nodeName . "\n";
            }
        }
    }

    public function valid() {
        return !empty($this->file_id) && !empty($this->file_name) &&
            !empty($this->file_contents);
    }

    public function save($context, $submission, $publicationId) {

        $genreId = null;
        // Build a cached list of genres by context ID by name
        if ($this->genre) {
            $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
            $genres = $genreDao->getByContextId($context->getId());
            while ($genre = $genres->next()) {
                foreach ($genre->getName(null) as $locale => $name) {
                    if($name == $this->genre) {
                        $genreId = $genre->getId();
                    }
                }
            }
        }

        $q = DB::select('SELECT * FROM submission_files WHERE genre_id = ? AND submission_id = ?', [ $genreId, $submission->getId() ]);
        $submissionFile = null;
        if(count($q)) {

            $submissionFile = Repo::submissionFile()->dao->get($q[0]->submission_file_id);

        } else {

            // Save temporary file into DB properly
            $temporaryFileManager = new TemporaryFileManager();
            $temporaryFilename = tempnam($temporaryFileManager->getBasePath(), 'embed');
            $content = base64_decode($this->file_contents, true);
            file_put_contents($temporaryFilename, $content);
            $fileManager = new FileManager();
            $submissionDir = Repo::submissionFile()->getSubmissionDir($submission->getData('contextId'), $submission->getId());
            $newFileId = Services::get('file')->add(
                $temporaryFilename,
                $submissionDir . '/' . uniqid() . '.' . $this->extension
            );
            $fileManager = new FileManager();
            $fileManager->deleteByPath($temporaryFilename);

            // Build up submission file and save it

            $submissionFile = Repo::submissionFile()->dao->newDataObject();
            $submissionFile->setData('fileId', $newFileId);
        }

        $submissionFile->setData('submissionId', $submission->getId());
        $submissionFile->setData('locale', $submission->getLocale());
        $submissionFile->setData('genreId', $genreId);
        $submissionFile->setData('fileStage', 2);
        $submissionFile->setData('viewable', true);
        $submissionFile->setData('name', $this->file_name, 'en');

        $submissionFile->setData('assocType', 521);
        $submissionFile->setData('assocId', $submission->getId());

        $subId = null;
        if($submissionFile->getId()) {
            Repo::submissionFile()->dao->update($submissionFile);
            $subId = $submissionFile->getId();
        } else {
            $subId = Repo::submissionFile()->add($submissionFile);
        }

        // Create Galley!
        $q = DB::select('SELECT * FROM publication_galleys WHERE submission_file_id = ? AND publication_id = ?', [ $subId, $publicationId ]);
        if(empty($q)) {
            DB::insert('INSERT INTO publication_galleys ( submission_file_id, publication_id, locale, label  ) VALUES ( ?, ?, ?, ? )',
                [ $subId, $publicationId, 'en', strtoupper($this->extension) ]);
        }

        return $subId;

    }

}