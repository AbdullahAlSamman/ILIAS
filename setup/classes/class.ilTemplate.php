<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/UICore/lib/html-it/IT.php");
include_once("./Services/UICore/lib/html-it/ITX.php");

/**
* special template class to simplify handling of ITX/PEAR
* @author	Stefan Kesseler <skesseler@databay.de>
* @author	Sascha Hofmann <shofmann@databay.de>
* @version	$Id$
*/
class ilTemplate extends HTML_Template_ITX implements ilGlobalTemplateInterface
{
    /**
    * variablen die immer in jedem block ersetzt werden sollen
    * @var	array
    */
    public $vars;
    //var $js_files = array(0 => "./Services/JavaScript/js/Basic.js");		// list of JS files that should be included
    public $js_files = array();		// list of JS files that should be included
    public $css_files = array();		// list of css files that should be included

    /**
    * Aktueller Block
    * Der wird gemerkt bei der berladenen Funktion setCurrentBlock, damit beim ParseBlock
    * vorher ein replace auf alle Variablen gemacht werden kann, die mit dem BLockname anfangen.
    * @var	string
    */
    public $activeBlock;

    /**
    * constructor
    * @param	string	$file 		templatefile (mit oder ohne pfad)
    * @param	boolean	$flag1 		remove unknown variables
    * @param	boolean	$flag2 		remove empty blocks
    * @param	boolean	$in_module	should be set to true, if template file is in module subdirectory
    * @param	array	$vars 		variables to replace
    * @access	public
    */
    /*function ilTemplate($root)
    {

        $this->callConstructor();

        $this->setRoot($root);

        return true;
    }*/
    public function __construct($file, $flag1, $flag2, $in_module = false, $vars = "DEFAULT")
    {
        $this->activeBlock = "__global__";
        $this->vars = array();

        $fname = $this->getTemplatePath($file, $in_module);

        $this->tplName = basename($fname);
        $this->tplPath = dirname($fname);
        // set default content-type to text/html
        $this->contenttype = "text/html";
        if (!file_exists($fname)) {
            die("template " . $fname . " was not found.");
            return false;
        }

        //$this->IntegratedTemplateExtension(dirname($fname));
        parent::__construct();
        //$this->loadTemplatefile(basename($fname), $flag1, $flag2);
        $this->loadTemplatefile($fname, $flag1, $flag2);
        //add tplPath to replacevars
        $this->vars["TPLPATH"] = $this->tplPath;

        // set Options
        if (method_exists($this, "setOption")) {
            $this->setOption('use_preg', false);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function printToString() : string
    {
        throw new ilException('not implemented');
    }


    /**
    * builds a full template path with template and module name
    *
    * @param	string		$a_tplname		template name
    * @param	boolean		$in_module		should be set to true, if template file is in module subdirectory
    *
    * @return	string		full template path
    */
    public function getTemplatePath($a_tplname, $a_in_module = false, $a_plugin = false)
    {
        global $ilias, $ilCtrl;

        // if baseClass functionality is used (ilias.php):
        // get template directory from ilCtrl
        if (!empty($_GET["baseClass"]) && $a_in_module === true) {
            $a_in_module = $ilCtrl->getModuleDir();
        }

        if (strpos($a_tplname, "/") === false) {
            $module_path = "";

            //$fname = $ilias->tplPath;
            if ($a_in_module) {
                if ($a_in_module === true) {
                    $module_path = ILIAS_MODULE . "/";
                } else {
                    $module_path = $a_in_module . "/";
                }
            }

            if ($fname == "" || !file_exists($fname)) {
                if ($a_in_module == "setup") {
                    $fname = "./" . $module_path . "templates/" . basename($a_tplname);
                } else {
                    $fname = "./" . $module_path . "templates/default/" . basename($a_tplname);
                }
            }
        } else {
            $fname = $a_tplname;
        }

        return $fname;
    }

    public function addBlockFile($var, $block, $tplname, $in_module = false)
    {
        if (DEBUG) {
            echo "<br/>Template '" . $this->tplPath . "/" . $tplname . "'";
        }

        $tplfile = $this->getTemplatePath($tplname, $in_module);
        if (file_exists($tplfile) == false) {
            echo "<br/>Template '" . $tplfile . "' doesn't exist! aborting...";
            return false;
        }

        return parent::addBlockFile($var, $block, $tplfile);
    }

    /**
    * @access	public
    * @param	string
    */
    public function show($part = "DEFAULT")
    {
        header('Content-type: text/html; charset=UTF-8');

        $this->fillJavaScriptFiles();
        $this->fillCssFiles();

        // ERROR HANDLER SETS $_GET["message"] IN CASE OF $error_obj->MESSAGE
        $ms = array("info", "success", "failure", "question");
        $out = "";

        foreach ($ms as $m) {
            if ($m == "question") {
                $m = "mess_question";
            }

            $txt = (ilSession::get($m) != "")
                ? ilSession::get($m)
                : $this->message[$m];

            if ($m == "mess_question") {
                $m = "question";
            }

            if ($txt != "") {
                $out .= $this->getMessageHTML($txt, $m);
            }

            if ($m == "question") {
                $m = "mess_question";
            }

            if (ilSession::get($m)) {
                ilSession::clear($m);
            }
        }

        if ($this->blockExists("MESSAGE") && $out != "") {
            $this->setVariable("MESSAGE", $out);
        }

        if ($part == "DEFAULT") {
            parent::show();
        } else {
            parent::show($part);
        }

        if (((substr(strrchr($_SERVER["PHP_SELF"], "/"), 1) != "error.php")
            && (substr(strrchr($_SERVER["PHP_SELF"], "/"), 1) != "adm_menu.php"))) {
            ilSession::set("post_vars", $_POST);

            // referer is modified if query string contains cmd=gateway and $_POST is not empty.
            // this is a workaround to display formular again in case of error and if the referer points to another page
            $url_parts = parse_url($_SERVER["REQUEST_URI"]);
            if (!$url_parts) {
                $protocol = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://';
                $host = $_SERVER['HTTP_HOST'];
                $path = $_SERVER['REQUEST_URI'];
                $url_parts = @parse_url($protocol . $host . $path);
            }

            if (preg_match("/cmd=gateway/", $url_parts["query"])) {
                foreach ($_POST as $key => $val) {
                    if (is_array($val)) {
                        $val = key($val);
                    }

                    $str .= "&" . $key . "=" . $val;
                }

                ilSession::set(
                    "referer",
                    preg_replace("/cmd=gateway/", substr($str, 1), $_SERVER["REQUEST_URI"])
                );
                ilSession::set(
                    "referer_ref_id",
                    (int) $_GET['ref_id']
                );
            } else {
                ilSession::set("referer", $_SERVER["REQUEST_URI"]);
                ilSession::set(
                    "referer_ref_id",
                    (int) $_GET['ref_id']
                );
            }

            ilSession::clear("error_post_vars");
        }
    }

    /**
    * Get HTML for a system message
    */
    public function getMessageHTML($a_txt, $a_type = "info")
    {
        global $lng;

        $mtpl = new ilTemplate("tpl.message.html", true, true, "Services/Utilities");
        $mtpl->setCurrentBlock($a_type . "_message");
        $mtpl->setVariable("TEXT", $a_txt);
        $mtpl->setVariable("MESSAGE_HEADING", $lng->txt($a_type . "_message"));
        $mtpl->parseCurrentBlock();

        return $mtpl->get();
    }

    /**
    * Überladene Funktion, die sich hier lokal noch den aktuellen Block merkt.
    * @access	public
    * @param	string
    * @return	???
    */
    public function setCurrentBlock($part = "DEFAULT")
    {
        $this->activeBlock = $part;

        if ($part == "DEFAULT") {
            return parent::setCurrentBlock();
        } else {
            return parent::setCurrentBlock($part);
        }
    }

    /**
    * overwrites ITX::touchBlock.
    * @access	public
    * @param	string
    * @return	???
    */
    public function touchBlock($block)
    {
        $this->setCurrentBlock($block);
        //$count = $this->fillVars();
        $this->parseCurrentBlock();

        if ($count == 0) {
            parent::touchBlock($block);
        }
    }

    /**
    * Überladene Funktion, die auf den aktuelle Block vorher noch ein replace ausführt
    * @access	public
    * @param	string
    * @return	string
    */
    public function parseCurrentBlock($part = "DEFAULT")
    {
        // Hier erst noch ein replace aufrufen
        if ($part != "DEFAULT") {
            $tmp = $this->activeBlock;
            $this->activeBlock = $part;
        }

        if ($part != "DEFAULT") {
            $this->activeBlock = $tmp;
        }

        //$this->fillVars();

        $this->activeBlock = "__global__";

        if ($part == "DEFAULT") {
            return parent::parseCurrentBlock();
        } else {
            return parent::parseCurrentBlock($part);
        }
    }
    /**
    * Set message. Please use ilUtil::sendInfo(), ilUtil::sendSuccess()
    * and ilUtil::sendFailure()
    */
    public function setOnScreenMessage($a_type, $a_txt, $a_keep = false)
    {
        if (!in_array($a_type, array("info", "success", "failure", "question")) || $a_txt == "") {
            return;
        }
        if ($a_type == "question") {
            $a_type = "mess_question";
        }
        if (!$a_keep) {
            $this->message[$a_type] = $a_txt;
        } else {
            ilSession::set($a_type, $a_txt);
        }
    }

    public function fillMessage()
    {
        global $lng;

        $ms = array("info", "success", "failure", "question");
        $out = "";

        foreach ($ms as $m) {
            if ($m == "question") {
                $m = "mess_question";
            }

            $txt = (ilSession::get($m) != "")
                ? ilSession::get($m)
                : $this->message[$m];

            if ($m == "mess_question") {
                $m = "question";
            }

            if ($txt != "") {
                $mtpl = new ilTemplate("tpl.message.html", true, true, "Services/Utilities");
                $mtpl->setCurrentBlock($m . "_message");
                $mtpl->setVariable("TEXT", $txt);
                $mtpl->setVariable("MESSAGE_HEADING", $lng->txt($m . "_message"));
                $mtpl->parseCurrentBlock();
                $out .= $mtpl->get();
            }

            if ($m == "question") {
                $m = "mess_question";
            }

            if (ilSession::get($m)) {
                ilSession::clear($m);
            }
        }

        if ($out != "") {
            $this->setVariable("MESSAGE", $out);
        }
    }

    /**
    * check if block exists in actual template
    * @access	private
    * @param string blockname
    * @return	boolean
    */
    public function blockExists($a_blockname)
    {
        // added second evaluation to the return statement because the first one only works for the content block (Helmut Schottmüller, 2007-09-14)
        return (isset($this->blockvariables["content"][$a_blockname]) ? true : false) | (isset($this->blockvariables[$a_blockname]) ? true : false);
    }

    /**
    * Add a javascript file that should be included in the header.
    */
    public function addJavaScript($a_js_file, $a_add_version_parameter = true, $a_batch = 2)
    {
        if (!in_array($a_js_file, $this->js_files)) {
            $this->js_files[] = $a_js_file;
        }
    }

    public function fillJavaScriptFiles($force = true)
    {
        global $ilias,$ilTabs;
        if ($this->blockExists("js_file")) {
            foreach ($this->js_files as $file) {
                if (is_file($file) || substr($file, 0, 4) == "http") {
                    $this->setCurrentBlock("js_file");
                    $this->setVariable("JS_FILE", $file);
                    $this->parseCurrentBlock();
                }
            }
        }
    }

    /**
     * Add a css file that should be included in the header.
     */
    public function addCss($a_css_file, $media = "screen")
    {
        if (!array_key_exists($a_css_file . $media, $this->css_files)) {
            $this->css_files[$a_css_file . $media] = array("file" => $a_css_file, "media" => $media);
        }
    }

    /**
     * Fill in the css file tags
     *
     * @param boolean $a_force
     */
    public function fillCssFiles($a_force = false)
    {
        if (!$this->blockExists("css_file")) {
            return;
        }
        foreach ($this->css_files as $css) {
            $filename = $css["file"];
            if (strpos($filename, "?") > 0) {
                $filename = substr($filename, 0, strpos($filename, "?"));
            }
            if (is_file($filename) || $a_force) {
                $this->setCurrentBlock("css_file");
                $this->setVariable("CSS_FILE", $css["file"]);
                $this->setVariable("CSS_MEDIA", $css["media"]);
                $this->parseCurrentBlock();
            }
        }
    }


    public function get($part = "DEFAULT")
    {
        if ($part == "DEFAULT") {
            return parent::get();
        } else {
            return parent::get($part);
        }
    }


    /**
     * @inheritDoc
     */
    public function hideFooter()
    {
        // TODO: Implement hideFooter() method.
    }





    /**
     * @inheritDoc
     */
    public function addOnLoadCode($a_code, $a_batch = 2)
    {
        // TODO: Implement addOnLoadCode() method.
    }


    /**
     * @inheritDoc
     */
    public function getOnLoadCodeForAsynch()
    {
        // TODO: Implement getOnLoadCodeForAsynch() method.
    }


    /**
     * @inheritDoc
     */
    public function resetJavascript()
    {
        // TODO: Implement resetJavascript() method.
    }


    /**
     * @inheritDoc
     */
    public function addInlineCss($a_css, $media = "screen")
    {
        // TODO: Implement addInlineCss() method.
    }


    public function setBodyClass($a_class = "")
    {
        // TODO: Implement setBodyClass() method.
    }


    /**
     * @inheritDoc
     */
    public function loadStandardTemplate()
    {
        // TODO: Implement loadStandardTemplate() method.
    }


    /**
     * @inheritDoc
     */
    public function setTitle($a_title, $hidden = false)
    {
        // TODO: Implement setTitle() method.
    }


    /**
     * @inheritDoc
     */
    public function setDescription($a_descr)
    {
        // TODO: Implement setDescription() method.
    }


    /**
     * @inheritDoc
     */
    public function setTitleIcon($a_icon_path, $a_icon_desc = "")
    {
        // TODO: Implement setTitleIcon() method.
    }


    /**
     * @inheritDoc
     */
    public function setAlertProperties(array $a_props)
    {
        // TODO: Implement setAlertProperties() method.
    }


    /**
     * @inheritDoc
     */
    public function clearHeader()
    {
        // TODO: Implement clearHeader() method.
    }


    /**
     * @inheritDoc
     */
    public function setHeaderActionMenu($a_header)
    {
        // TODO: Implement setHeaderActionMenu() method.
    }


    /**
     * @inheritDoc
     */
    public function setHeaderPageTitle($a_title)
    {
        // TODO: Implement setHeaderPageTitle() method.
    }


    /**
     * @inheritDoc
     */
    public function setLocator()
    {
        // TODO: Implement setLocator() method.
    }


    /**
     * @inheritDoc
     */
    public function setTabs($a_tabs_html)
    {
        // TODO: Implement setTabs() method.
    }


    /**
     * @inheritDoc
     */
    public function setSubTabs($a_tabs_html)
    {
        // TODO: Implement setSubTabs() method.
    }


    /**
     * @inheritDoc
     */
    public function setContent($a_html)
    {
        // TODO: Implement setContent() method.
    }


    /**
     * @inheritDoc
     */
    public function setLeftContent($a_html)
    {
        // TODO: Implement setLeftContent() method.
    }


    /**
     * @inheritDoc
     */
    public function setLeftNavContent($a_content)
    {
        // TODO: Implement setLeftNavContent() method.
    }


    /**
     * @inheritDoc
     */
    public function setRightContent($a_html)
    {
        // TODO: Implement setRightContent() method.
    }


    public function setPageFormAction($a_action)
    {
        // TODO: Implement setPageFormAction() method.
    }


    /**
     * @inheritDoc
     */
    public function setLoginTargetPar($a_val)
    {
        // TODO: Implement setLoginTargetPar() method.
    }


    /**
     * @inheritDoc
     */
    public function getSpecial($part = "DEFAULT", $add_error_mess = false, $handle_referer = false, $add_ilias_footer = false, $add_standard_elements = false, $a_main_menu = true, $a_tabs = true)
    {
        // TODO: Implement getSpecial() method.
    }


    /**
     * @inheritDoc
     */
    public function printToStdout($part = "DEFAULT", $a_fill_tabs = true, $a_skip_main_menu = false)
    {
        // TODO: Implement printToStdout() method.
    }


    /**
     * @inheritDoc
     */
    public function setTreeFlatIcon($a_link, $a_mode)
    {
        // TODO: Implement setTreeFlatIcon() method.
    }


    /**
     * @inheritDoc
     */
    public function addLightbox($a_html, $a_id)
    {
        // TODO: Implement addLightbox() method.
    }


    /**
     * @inheritDoc
     */
    public function addAdminPanelToolbar(ilToolbarGUI $toolb, $a_bottom_panel = true, $a_arrow = false)
    {
        // TODO: Implement addAdminPanelToolbar() method.
    }


    public function setPermanentLink($a_type, $a_id, $a_append = "", $a_target = "", $a_title = "")
    {
        // TODO: Implement setPermanentLink() method.
    }


    /**
     * @inheritDoc
     */
    public function resetHeaderBlock($a_reset_header_action = true)
    {
        // TODO: Implement resetHeaderBlock() method.
    }


    /**
     * @inheritDoc
     */
    public function enableDragDropFileUpload($a_ref_id)
    {
        // TODO: Implement enableDragDropFileUpload() method.
    }
}
