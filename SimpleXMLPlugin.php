<?php
/**
 * Main class for simple xml plugin page plugin
 * 
 * @author Joe Simpson
 * 
 * @class SimpleXMLPlugin
 *
 */

namespace APP\plugins\importexport\simplexml;

use APP\core\Application;
use APP\plugins\importexport\simplexml\elements\IssueElement;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\file\TemporaryFileManager;
use PKP\plugins\GenericPlugin;
 use PKP\plugins\Hook;
use PKP\plugins\importexport\native\PKPNativeImportExportCLIDeployment;
use PKP\plugins\ImportExportPlugin;
use PKP\plugins\PluginRegistry;

 class SimpleXMLPlugin extends ImportExportPlugin {

    protected $opType;
    protected $isResultManaged = false;
    protected $result = null;

    public function getName() {
        return 'SimpleXMLPlugin';
    }

    /**
     * Provide a name for this plugin
     *
     * The name will appear in the Plugin Gallery where editors can
     * install, enable and disable plugins.
     */
    public function getDisplayName()
    {
        return 'Simple XML';
    }

    /**
     * Provide a description for this plugin
     *
     * The description will appear in the Plugin Gallery where editors can
     * install, enable and disable plugins.
     */
    public function getDescription()
    {
        return 'Simple XML import [InvisibleDragon]';
    }

    public function display($args, $request)
    {

        $templateMgr = TemplateManager::getManager($request);
        parent::display($args, $request);

        $context = $request->getContext();
        $user = $request->getUser();
        // $deployment = $this->getAppSpecificDeployment($context, $user);
        // $this->setDeployment($deployment);

        $this->opType = array_shift($args);
        switch ($this->opType) {
            case 'index':
            case '':
                $apiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'submissions');
                $submissionsListPanel = new \APP\components\listPanels\SubmissionsListPanel(
                    'submissions',
                    __('common.publications'),
                    [
                        'apiUrl' => $apiUrl,
                        'count' => 100,
                        'getParams' => new \stdClass(),
                        'lazyLoad' => true,
                    ]
                );
                $submissionsConfig = $submissionsListPanel->getConfig();
                $submissionsConfig['addUrl'] = '';
                $submissionsConfig['filters'] = array_slice($submissionsConfig['filters'], 1);
                $templateMgr->setState([
                    'components' => [
                        'submissions' => $submissionsConfig,
                    ],
                ]);
                $templateMgr->assign([
                    'pageComponent' => 'ImportExportPage',
                ]);

                $templateMgr->display($this->getTemplateResource('index.tpl'));

                $this->isResultManaged = true;
                break;
            case 'uploadImportXML':
                $temporaryFileManager = new TemporaryFileManager();
                $temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
                if ($temporaryFile) {
                    $json = new JSONMessage(true);
                    $json->setAdditionalAttributes([
                        'temporaryFileId' => $temporaryFile->getId()
                    ]);
                } else {
                    $json = new JSONMessage(false, __('common.uploadFailed'));
                }
                header('Content-Type: application/json');

                $this->result = $json->getString();
                $this->isResultManaged = true;

                break;
            case 'importBounce':
                $tempFileId = $request->getUserVar('temporaryFileId');

                if (empty($tempFileId)) {
                    $this->result = new JSONMessage(false);
                    $this->isResultManaged = true;
                    break;
                }

                $tab = $this->getBounceTab(
                    $request,
                    __('plugins.importexport.native.results'),
                    'import',
                    ['temporaryFileId' => $tempFileId]
                );

                $this->result = $tab;
                $this->isResultManaged = true;
                break;
            case 'import':
                if (!$request->checkCSRF()) {
                    throw new \Exception('CSRF mismatch!');
                }
                $temporaryFilePath = $this->getImportedFilePath($request->getUserVar('temporaryFileId'), $user);
                
                $result = $this->getImportTemplateResult($filter, $xmlString, $this->getDeployment(), $templateMgr);

                $this->result = $result;
                $this->isResultManaged = true;

                break;
        }

    }

    public function executeCLI($scriptName, &$args)
    {

        $contextDao = Application::getContextDAO();
        $cliDeployment = new PKPNativeImportExportCLIDeployment($scriptName, $args);

        $contextPath = $cliDeployment->contextPath;
        $context = $contextDao->getByPath($contextPath);

        if (!$context) {
            if ($contextPath != '') {
                $this->cliToolkit->echoCLIError(__('plugins.importexport.common.error.unknownContext', ['contextPath' => $contextPath]));
            }
            $this->usage($scriptName);
            return true;
        }

        PluginRegistry::loadCategory('pubIds', true, $context->getId());

        $xmlFile = $cliDeployment->xmlFile;
        if ($xmlFile && $this->isRelativePath($xmlFile)) {
            $xmlFile = PWD . '/' . $xmlFile;
        }

        switch ($cliDeployment->command) {
            case 'import':
                $user = Application::get()->getRequest()->getUser();

                if (!$user) {
                    $this->cliToolkit->echoCLIError(__('plugins.importexport.native.error.unknownUser'));
                    $this->usage($scriptName);
                    return true;
                }

                if (!file_exists($xmlFile)) {
                    $this->cliToolkit->echoCLIError(__('plugins.importexport.common.export.error.inputFileNotReadable', ['param' => $xmlFile]));

                    $this->usage($scriptName);
                    return true;
                }

                $issues = $this->readFile($xmlFile);
                $issues->save( $context );

                $this->cliToolkit->getCLIImportResult($deployment);
                $this->cliToolkit->getCLIProblems($deployment);
                return true;
            default:
                $this->usage($scriptName);
                return true;
        }
    }

    public function readFile($xmlFile) {
        $dom = new \DOMDocument();
        $dom->load($xmlFile);
        if($dom->getRootNode()->nodeName == 'issue') {
            $issue = new IssueElement($dom->getRootNode());
            return $issue;
        } else {
            foreach($dom->childNodes as $ch) {
                if($ch->nodeName == "issue") {
                    $issue = new IssueElement($ch);
                    return $issue;
                }
            }
        }
    }

    public function usage($scriptName)
    {
        echo __('plugins.importexport.simplexml.cliUsage', [
            'scriptName' => $scriptName,
            'pluginName' => $this->getName()
        ]) . "\n";
    }

 }