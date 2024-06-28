<?php

namespace APP\plugins\importexport\simpleXML\elements;

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use APP\plugins\importexport\simpleXML\SimpleXMLPlugin;
use DOMElement;
use Illuminate\Support\Facades\DB;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\file\TemporaryFileManager;
use PKP\submissionFile\SubmissionFile;

class FileElement {

    public $file_id, $file_name, $genre, $extension, $old_views, $stage, $parent_file;
    private $file_contents;

    const STAGE_MAP = [
        'submission' => SubmissionFile::SUBMISSION_FILE_SUBMISSION,
        'note' => SubmissionFile::SUBMISSION_FILE_NOTE,
        'review_file' => SubmissionFile::SUBMISSION_FILE_REVIEW_FILE,
        'review_attachment' => SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT,
        'final' => SubmissionFile::SUBMISSION_FILE_FINAL,
        'copyedit' => SubmissionFile::SUBMISSION_FILE_COPYEDIT,
        'proof' => SubmissionFile::SUBMISSION_FILE_PROOF,
        'production_ready' => SubmissionFile::SUBMISSION_FILE_PRODUCTION_READY,
        'attachment' => SubmissionFile::SUBMISSION_FILE_ATTACHMENT,
        'review_revision' => SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION,
        'dependent' => SubmissionFile::SUBMISSION_FILE_DEPENDENT,
        'query' => SubmissionFile::SUBMISSION_FILE_QUERY,
    ];

    public function __debugInfo(){
    
        $vars = get_object_vars($this);
        
        $vars['file_contents'] = '~~base64 ' . strlen($this->file_contents) . '~~';
    
        return $vars;
    }

    public function __construct(DOMElement $element) {
        $this->file_id = $element->getAttribute("id");
        $this->genre = $element->getAttribute("genre");
        $this->stage = $element->getAttribute("stage") ?? "production";

        foreach($element->childNodes as $child) {
            switch(strval($child->nodeName)) {
                case 'name':
                    $this->file_name = $child->nodeValue;
                    break;
                case 'file':
                    // expect embed
                    $this->extension = $child->getAttribute("extension");
                    foreach($child->childNodes as $c) {
                        if($c->nodeName == 'embed') {
                            $this->file_contents = $c->nodeValue;
                        } else {
                            SimpleXMLPlugin::log([ 'UE', $c->nodeName ]);
                        }
                    }
                    if($child->getAttribute("downloads")) {
                        $this->old_views = intval( $child->getAttribute("downloads") );
                    }
                    break;
                case '#text':
                    break;
                default:
                    SimpleXMLPlugin::log([ 'UE', 'file', $child->nodeName ]);
            }
        }

        if(strtoupper($this->extension) == 'HTM') {
            $this->extension = 'HTML';
        }
        $this->extension = strtoupper($this->extension);
    }

    public function valid() {
        return !empty($this->file_id) && !empty($this->file_name) &&
            (strlen($this->file_contents) > 0);
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

        if(!$this->extension) {
            $e = explode(".", $this->file_name);
            $this->extension = $e[ count($e) - 1 ];
        }

        $q = DB::select('SELECT * FROM submission_files
            INNER JOIN files ON submission_files.file_id = files.file_id WHERE genre_id = ? AND submission_id = ? AND `path` LIKE ?', [ $genreId, $submission->getId(), '%' . $this->file_name ]);
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
                $submissionDir . '/' . str_replace('.htm', '.html', $this->file_name), // uniqid() . '.' . $this->extension
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
        $submissionFile->setData('fileStage', SubmissionFile::SUBMISSION_FILE_PRODUCTION_READY); // static::STAGE_MAP[ $this->stage ] );
        $submissionFile->setData('viewable', true);
        $submissionFile->setData('name', $this->file_name, 'en');

        $subId = null;
        if($submissionFile->getId()) {
            $subId = $submissionFile->getId();
        }

        if($this->stage !== 'dependent') {

            // Create Galley!
            $assocId = null;
            $q = DB::select('SELECT * FROM publication_galleys WHERE label = ? AND publication_id = ?', [ strtoupper($this->extension), $publicationId ]);
            if(!empty($q)) {
                $assocId = $q[0]->galley_id;
            }
            if(!$assocId) {
                $assocId = DB::table('publication_galleys')->insertGetId([
                    'submission_file_id' => null,
                    'publication_id' => $publicationId,
                    'locale' => 'en',
                    'label' => strtoupper($this->extension)
                ]);
            }
            if($assocId) {
                SimpleXMLPlugin::log([ 'GALLEY', $assocId, strtoupper($this->extension) ]);
            }

            $submissionFile->setData('assocType', Application::ASSOC_TYPE_REPRESENTATION);
            $submissionFile->setData('assocId', $assocId);

        } else {
            $submissionFile->setData('assocType', Application::ASSOC_TYPE_SUBMISSION_FILE);
            $submissionFile->setData('assocId', $this->parent_file);
            $submissionFile->setData('fileStage', SubmissionFile::SUBMISSION_FILE_DEPENDENT);

        }

        if($submissionFile->getId()) {
            Repo::submissionFile()->dao->update($submissionFile);
            $subId = $submissionFile->getId();

            // Also update if already exists please
            if ($submissionFile->getData('assocType') === Application::ASSOC_TYPE_REPRESENTATION) {
                $galley = Repo::galley()->get($submissionFile->getData('assocId'));
                if (!$galley) {
                    throw new \Exception('Galley not found when adding submission file.');
                }
                if ($galley) {
                    Repo::galley()->edit($galley, ['submissionFileId' => $submissionFile->getId()]);
                }        
            }

        } else {
            $subId = Repo::submissionFile()->add($submissionFile);
        }

        return $subId;

    }

}