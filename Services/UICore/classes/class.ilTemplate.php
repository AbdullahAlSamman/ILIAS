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
class ilTemplate extends HTML_Template_ITX
{
	/**
	 * @var ilTemplate
	 */
	protected $tpl;

	const MESSAGE_TYPE_FAILURE = 'failure';
	const MESSAGE_TYPE_INFO = "info";
	const MESSAGE_TYPE_SUCCESS = "success";
	const MESSAGE_TYPE_QUESTION = "question";
	/**
	 * @var array  available Types for Messages
	 */
	protected static $message_types = array(
		self::MESSAGE_TYPE_FAILURE,
		self::MESSAGE_TYPE_INFO,
		self::MESSAGE_TYPE_SUCCESS,
		self::MESSAGE_TYPE_QUESTION,
	);

	/**
	* variablen die immer in jedem block ersetzt werden sollen
	* @var	array
	*/
	var $vars;

	/**
	* Aktueller Block
	* Der wird gemerkt bei der berladenen Funktion setCurrentBlock, damit beim ParseBlock
	* vorher ein replace auf alle Variablen gemacht werden kann, die mit dem BLockname anfangen.
	* @var	string
	*/
	var $activeBlock;
	
	var $js_files = array(0 => "./Services/JavaScript/js/Basic.js");		// list of JS files that should be included
	var $js_files_vp = array("./Services/JavaScript/js/Basic.js" => true);	// version parameter flag
	var $js_files_batch = array("./Services/JavaScript/js/Basic.js" => 1);	// version parameter flag
	var $css_files = array();		// list of css files that should be included
	var $inline_css = array();
	

	protected static $il_cache = array();
	protected $message = array();
	
	protected $tree_flat_link = "";
	protected $page_form_action = "";
	protected $permanent_link = false;
	protected $main_content = "";
	
	protected $lightbox = array();
	protected $standard_template_loaded = false;

	protected $translation_linked = false; // fix #9992: remember if a translation link is added

	/**
	* constructor
	* @param	string	$file 		templatefile (mit oder ohne pfad)
	* @param	boolean	$flag1 		remove unknown variables
	* @param	boolean	$flag2 		remove empty blocks
	* @param	boolean	$in_module	should be set to true, if template file is in module subdirectory
	* @param	array	$vars 		variables to replace
	* @access	public
	*/
	public function __construct($file,$flag1,$flag2,$in_module = false, $vars = "DEFAULT",
		$plugin = false, $a_use_cache = true)
	{
		global $DIC;

		$this->activeBlock = "__global__";
		$this->vars = array();
		$this->addFooter = TRUE;
		
		$this->il_use_cache = $a_use_cache;
		$this->il_cur_key = $file."/".$in_module;

		$fname = $this->getTemplatePath($file, $in_module, $plugin);

		$this->tplName = basename($fname);
		$this->tplPath = dirname($fname);
		$this->tplIdentifier = $this->getTemplateIdentifier($file, $in_module);
		
		if (!file_exists($fname))
		{
			if (isset($DIC["ilErr"]))
			{
				$ilErr = $DIC["ilErr"];
				$ilErr->raiseError("template " . $fname . " was not found.", $ilErr->FATAL);
			}
			return false;
		}

		parent::__construct();
		$this->loadTemplatefile($fname, $flag1, $flag2);
		//add tplPath to replacevars
		$this->vars["TPLPATH"] = $this->tplPath;
		
		// set Options
		if (method_exists($this, "setOption"))
		{
			$this->setOption('use_preg', false);
		}
		$this->setBodyClass("std");

		return true;
	}

	/**
	 * @param string $file
	 * @param string $vers
	 */
	protected function fillJavascriptFile($file, $vers)
	{
		$this->setCurrentBlock("js_file");
		if($this->js_files_vp[$file])
		{
			$this->setVariable("JS_FILE", ilUtil::appendUrlParameterString($file, $vers));
		}
		else
		{
			$this->setVariable("JS_FILE", $file);
		}
		$this->parseCurrentBlock();
	}

	// overwrite their init function
    protected function init()
    {
        $this->free();
        $this->buildFunctionlist();
        
        $cache_hit = false;
        if ($this->il_use_cache)
        {
        	// cache hit
        	if (isset(self::$il_cache[$this->il_cur_key]) && is_array(self::$il_cache[$this->il_cur_key]))
        	{
        		$cache_hit = true;
//echo "cache hit";
        		$this->err = self::$il_cache[$this->il_cur_key]["err"];
        		$this->flagBlocktrouble = self::$il_cache[$this->il_cur_key]["flagBlocktrouble"];
        		$this->blocklist = self::$il_cache[$this->il_cur_key]["blocklist"];
        		$this->blockdata = self::$il_cache[$this->il_cur_key]["blockdata"];
        		$this->blockinner = self::$il_cache[$this->il_cur_key]["blockinner"];
        		$this->blockparents = self::$il_cache[$this->il_cur_key]["blockparents"];
        		$this->blockvariables = self::$il_cache[$this->il_cur_key]["blockvariables"];
        	}
        }
        
		if (!$cache_hit)
		{
			$this->findBlocks($this->template);
			$this->template = '';
			$this->buildBlockvariablelist();
	        if ($this->il_use_cache)
	        {
        		self::$il_cache[$this->il_cur_key]["err"] = $this->err;
        		self::$il_cache[$this->il_cur_key]["flagBlocktrouble"] = $this->flagBlocktrouble;
        		self::$il_cache[$this->il_cur_key]["blocklist"] = $this->blocklist;
        		self::$il_cache[$this->il_cur_key]["blockdata"] = $this->blockdata;
        		self::$il_cache[$this->il_cur_key]["blockinner"] = $this->blockinner;
        		self::$il_cache[$this->il_cur_key]["blockparents"] = $this->blockparents;
        		self::$il_cache[$this->il_cur_key]["blockvariables"] = $this->blockvariables;
	        }
		}
		
        // we don't need it any more
        $this->template = '';

    } // end func init

	// FOOTER
	//
	// Used in:
	//  * ilStartUPGUI
	//  * ilTestSubmissionReviewGUI
	//  * ilTestPlayerAbstractGUI
	//  * ilAssQuestionHintRequestGUI

	private $show_footer = true;
	
	/**
	 * Make the template hide the footer.
	 */
	public function hideFooter()
	{
		$this->show_footer = false;
	}
	
	/**
	 * Fill the footer area.
	 */
	private function fillFooter()
	{
		global $DIC;

		$ilSetting = $DIC->settings();

		$lng = $DIC->language();

		$ilCtrl = $DIC->ctrl();
		$ilDB = $DIC->database();

		if (!$this->show_footer)
		{
			return;
		}

		$ftpl = new ilTemplate("tpl.footer.html", true, true, "Services/UICore");

		$php = "";
		if (DEVMODE)
		{
			$php = ", PHP ".phpversion();
		}
		$ftpl->setVariable("ILIAS_VERSION", $ilSetting->get("ilias_version").$php);

		$link_items = array();

		// imprint
		include_once "Services/Imprint/classes/class.ilImprint.php";
		if($_REQUEST["baseClass"] != "ilImprintGUI" && ilImprint::isActive())
		{
			include_once "Services/Link/classes/class.ilLink.php";
			$link_items[ilLink::_getStaticLink(0, "impr")] = array($lng->txt("imprint"), true);
		}

		// system support contacts
		include_once("./Modules/SystemFolder/classes/class.ilSystemSupportContactsGUI.php");
		if (($l = ilSystemSupportContactsGUI::getFooterLink()) != "")
		{
			$link_items[$l] = array(ilSystemSupportContactsGUI::getFooterText(), false);
		}

		if (DEVMODE)
		{
			if (function_exists("tidy_parse_string"))
			{
				$link_items[ilUtil::appendUrlParameterString($_SERVER["REQUEST_URI"], "do_dev_validate=xhtml")] = array("Validate", true);
				$link_items[ilUtil::appendUrlParameterString($_SERVER["REQUEST_URI"], "do_dev_validate=accessibility")] = array("Accessibility", true);
			}
		}

        // output translation link
		include_once("Services/Language/classes/class.ilObjLanguageAccess.php");
		if (ilObjLanguageAccess::_checkTranslate() and !ilObjLanguageAccess::_isPageTranslation())
		{
			// fix #9992: remember linked translation instead of saving language usages here
			$this->translation_linked = true;
			$link_items[ilObjLanguageAccess::_getTranslationLink()] = array($lng->txt('translation'), true);
		}

        $cnt = 0;
		foreach($link_items as $url => $caption)
		{
			$cnt ++;
			if($caption[1])
			{
				$ftpl->touchBlock("blank");
			}
			if($cnt < sizeof($link_items))
			{
				$ftpl->touchBlock("item_separator");
			}

			$ftpl->setCurrentBlock("items");
			$ftpl->setVariable("URL_ITEM", ilUtil::secureUrl($url));
			$ftpl->setVariable("TXT_ITEM", $caption[0]);
			$ftpl->parseCurrentBlock();
		}

		if (DEVMODE)
		{
			// execution time
			$t1 = explode(" ", $GLOBALS['ilGlobalStartTime']);
			$t2 = explode(" ", microtime());
			$diff = $t2[0] - $t1[0] + $t2[1] - $t1[1];

			$mem_usage = array();
			if(function_exists("memory_get_usage"))
			{
				$mem_usage[] =
					"Memory Usage: ".memory_get_usage()." Bytes";
			}
			if(function_exists("xdebug_peak_memory_usage"))
			{
				$mem_usage[] =
					"XDebug Peak Memory Usage: ".xdebug_peak_memory_usage()." Bytes";
			}
			$mem_usage[] = round($diff, 4)." Seconds";

			if (sizeof($mem_usage))
			{
				$ftpl->setVariable("MEMORY_USAGE", "<br>".implode(" | ", $mem_usage));
			}

			if (!empty($_GET["do_dev_validate"]) && $ftpl->blockExists("xhtml_validation"))
			{
				require_once("Services/XHTMLValidator/classes/class.ilValidatorAdapter.php");
				$template2 = clone($this);
				$ftpl->setCurrentBlock("xhtml_validation");
				$ftpl->setVariable("VALIDATION",
					ilValidatorAdapter::validate($template2->get("DEFAULT",
					false, false, false, true), $_GET["do_dev_validate"]));
				$ftpl->parseCurrentBlock();
			}

			// controller history
			if (is_object($ilCtrl) && $ftpl->blockExists("c_entry") &&
				$ftpl->blockExists("call_history"))
			{
				$hist = $ilCtrl->getCallHistory();
				foreach($hist as $entry)
				{
					$ftpl->setCurrentBlock("c_entry");
					$ftpl->setVariable("C_ENTRY", $entry["class"]);
					if (is_object($ilDB))
					{
						$file = $ilCtrl->lookupClassPath($entry["class"]);
						$add = $entry["mode"]." - ".$entry["cmd"];
						if ($file != "")
						{
							$add.= " - ".$file;
						}
						$ftpl->setVariable("C_FILE", $add);
					}
					$ftpl->parseCurrentBlock();
				}
				$ftpl->setCurrentBlock("call_history");
				$ftpl->parseCurrentBlock();

				// debug hack
				$debug = $ilCtrl->getDebug();
				foreach($debug as $d)
				{
					$ftpl->setCurrentBlock("c_entry");
					$ftpl->setVariable("C_ENTRY", $d);
					$ftpl->parseCurrentBlock();
				}
				$ftpl->setCurrentBlock("call_history");
				$ftpl->parseCurrentBlock();
			}

			// included files
			if (is_object($ilCtrl) && $ftpl->blockExists("i_entry") &&
				$ftpl->blockExists("included_files"))
			{
				$fs = get_included_files();
				$ifiles = array();
				$total = 0;
				foreach($fs as $f)
				{
					$ifiles[] = array("file" => $f, "size" => filesize($f));
					$total += filesize($f);
				}
				$ifiles = ilUtil::sortArray($ifiles, "size", "desc", true);
				foreach($ifiles as $f)
				{
					$ftpl->setCurrentBlock("i_entry");
					$ftpl->setVariable("I_ENTRY", $f["file"]." (".$f["size"]." Bytes, ".round(100 / $total * $f["size"], 2)."%)");
					$ftpl->parseCurrentBlock();
				}
				$ftpl->setCurrentBlock("i_entry");
				$ftpl->setVariable("I_ENTRY", "Total (".$total." Bytes, 100%)");
				$ftpl->parseCurrentBlock();
				$ftpl->setCurrentBlock("included_files");
				$ftpl->parseCurrentBlock();
			}
		}

		// BEGIN Usability: Non-Delos Skins can display the elapsed time in the footer
		// The corresponding $ilBench->start invocation is in inc.header.php
		$ilBench = $DIC["ilBench"];
		$ilBench->stop("Core", "ElapsedTimeUntilFooter");
		$ftpl->setVariable("ELAPSED_TIME",
			", ".number_format($ilBench->getMeasuredTime("Core", "ElapsedTimeUntilFooter"),1).' seconds');
		// END Usability: Non-Delos Skins can display the elapsed time in the footer

		$this->setVariable("FOOTER", $ftpl->get());
	}


	// MESSAGES
	//
	// setMessage is only used in ilUtil
	// getMessageHTML has various usage locations

	/**
	 * Set a message to be displayed to the user. Please use ilUtil::sendInfo(),
	 * ilUtil::sendSuccess() and ilUtil::sendFailure()
	 *
	 * @param  string  $a_type \ilTemplate::MESSAGE_TYPE_SUCCESS,
	 *                         \ilTemplate::MESSAGE_TYPE_FAILURE,,
	 *                         \ilTemplate::MESSAGE_TYPE_QUESTION,
	 *                         \ilTemplate::MESSAGE_TYPE_INFO
	 * @param   string $a_txt  The message to be sent
	 * @param bool     $a_keep Keep this message over one redirect
     *
     * // REMOVE
	 */
	public function setOnScreenMessage($a_type, $a_txt, $a_keep = false)
	{
		if (!in_array($a_type, self::$message_types) || $a_txt == "")
		{
			return;
		}
		if ($a_type == self::MESSAGE_TYPE_QUESTION)
		{
			$a_type = "mess_question";
		}
		if (!$a_keep)
		{
			$this->message[$a_type] = $a_txt;
		}
		else
		{
			$_SESSION[$a_type] = $a_txt;
		}
	}

	/**
	 * Get HTML for a system message
     *
     * // REMOVE
	 */
	public function getMessageHTML($a_txt, $a_type = "info")
	{
		global $DIC;

		$lng = $DIC->language();
		$mtpl = new ilTemplate("tpl.message.html", true, true, "Services/Utilities");
		$mtpl->setCurrentBlock($a_type."_message");
		$mtpl->setVariable("TEXT", $a_txt);
		$mtpl->setVariable("MESSAGE_HEADING", $lng->txt($a_type."_message"));
		$mtpl->parseCurrentBlock();

		return $mtpl->get();
	}

	/**
	 * Fill message area.
     *
     * // REMOVE
	 */
	private function fillMessage()
	{
		global $DIC;

		$ms = array( self::MESSAGE_TYPE_INFO,
		             self::MESSAGE_TYPE_SUCCESS, self::MESSAGE_TYPE_FAILURE,
		             self::MESSAGE_TYPE_QUESTION
		);
		$out = "";

		foreach ($ms as $m)
		{

			if ($m == self::MESSAGE_TYPE_QUESTION)
			{
				$m = "mess_question";
			}
			$txt = $this->getMessageTextForType($m);

			if ($m == "mess_question")
			{
				$m = self::MESSAGE_TYPE_QUESTION;
			}

			if ($txt != "")
			{
				$out.= $this->getMessageHTML($txt, $m);
			}

			if ($m == self::MESSAGE_TYPE_QUESTION)
			{
				$m = "mess_question";
			}

			$request = $DIC->http()->request();
			$accept_header = $request->getHeaderLine('Accept');
			if (isset($_SESSION[$m]) && $_SESSION[$m] && ($accept_header !== 'application/json')) {
				unset($_SESSION[$m]);
			}
		}

		if ($out != "")
		{
			$this->setVariable("MESSAGE", $out);
		}
	}


	// HEADER in standard page

	protected $header_page_title = "";
	protected $title = "";
	protected $title_desc = "";
	protected $title_url = "";
	protected $title_alerts = array();
	protected $header_action;

	/**
	 * Sets title in standard template.
	 *
	 * Will override the header_page_title.
	 */
	public function setTitle($a_title)
	{
		$this->title = $a_title;
		$this->header_page_title = $a_title;
	}

	/**
	 * Sets descripton below title in standard template.
	 */
	public function setDescription($a_descr)
	{
		$this->title_desc = $a_descr;
	}

	/**
	 * Sets title url in standard template.
	 */
	public function setTitleUrl($a_url)
	{
		$this->title_url = $a_url;
	}
	
	/**
	 * set title icon
	 */
	public function setTitleIcon($a_icon_path, $a_icon_desc = "")
	{
		$this->icon_desc = $a_icon_desc;
		$this->icon_path = $a_icon_path;
	}

	/**
	 * Set alert properties
	 * @param array $a_props
	 * @return void
	 */
	public function setAlertProperties(array $a_props)
	{
		$this->title_alerts = $a_props;
	}

	/**
	 * Clear header
	 */
	public function clearHeader()
	{
		$this->setTitle("");
		$this->setTitleIcon("");
		$this->setDescription("");
		$this->setAlertProperties(array());
	}

	/**
	* Fill header
	*/
	private function fillHeader()
	{
		global $DIC;

		$lng = $DIC->language();

		$icon = false;
		if ($this->icon_path != "")
		{
			$icon = true;
			$this->setCurrentBlock("header_image");
			if ($this->icon_desc != "")
			{
				$this->setVariable("IMAGE_DESC", $lng->txt("icon")." ".$this->icon_desc);
				$this->setVariable("IMAGE_ALT", $lng->txt("icon")." ".$this->icon_desc);
			}
			
			$this->setVariable("IMG_HEADER", $this->icon_path);
			$this->parseCurrentBlock();
			$header = true;
		}

		if ($this->title != "")
		{
			$title = ilUtil::stripScriptHTML($this->title);
			$this->setVariable("HEADER", $title);
			if ($this->title_url != "")
			{
				$this->setVariable("HEADER_URL", ' href="'.$this->title_url.'"');
			}
			
			$header = true;
		}
		
		if ($header)
		{
			$this->setCurrentBlock("header_image");
			$this->parseCurrentBlock();
		}
		
		if ($this->title_desc != "")
		{
			$this->setCurrentBlock("header_desc");
			$this->setVariable("H_DESCRIPTION", $this->title_desc);
			$this->parseCurrentBlock();
		}
		
		$header = $this->getHeaderActionMenu();
		if ($header)
		{
			$this->setCurrentBlock("head_action_inner");
			$this->setVariable("HEAD_ACTION", $header);
			$this->parseCurrentBlock();
			$this->touchBlock("head_action");			
		}

		if(count((array) $this->title_alerts))
		{
			foreach($this->title_alerts as $alert)
			{
				$this->setCurrentBlock('header_alert');
				if(!($alert['propertyNameVisible'] === false))
				{
					$this->setVariable('H_PROP', $alert['property'].':');
				}
				$this->setVariable('H_VALUE', $alert['value']);
				$this->parseCurrentBlock();
			}
		}
		
		// add file upload drop zone in header
		if ($this->enable_fileupload != null)
		{
			$ref_id = $this->enable_fileupload;
			$upload_id = "dropzone_" . $ref_id;
			
			include_once("./Services/FileUpload/classes/class.ilFileUploadGUI.php");
			$upload = new ilFileUploadGUI($upload_id, $ref_id, true);
			
			$this->setVariable("FILEUPLOAD_DROPZONE_ID", " id=\"$upload_id\"");
			
			$this->setCurrentBlock("header_fileupload");
			$this->setVariable("HEADER_FILEUPLOAD_SCRIPT", $upload->getHTML());
			$this->parseCurrentBlock();
		}
	}

	/**
	 * Get header action menu
	 *
	 * @return int ref id
	 */
	private function getHeaderActionMenu()
	{
		return $this->header_action;
	}


	// LOCATOR

	/**
	* Insert locator.
	*/
	public function setLocator()
	{
		global $DIC;

		$ilMainMenu = $DIC["ilMainMenu"];
		$ilLocator = $DIC["ilLocator"];

		$ilPluginAdmin = $DIC["ilPluginAdmin"];

		// blog/portfolio
		if($ilMainMenu->getMode() == ilMainMenuGUI::MODE_TOPBAR_REDUCED ||
			$ilMainMenu->getMode() == ilMainMenuGUI::MODE_TOPBAR_ONLY)
		{						
			$this->setVariable("LOCATOR", "");
			return;
		}

		$html = "";
		if (is_object($ilPluginAdmin))
		{
			include_once("./Services/UIComponent/classes/class.ilUIHookProcessor.php");
			$uip = new ilUIHookProcessor("Services/Locator", "main_locator",
				array("locator_gui" => $ilLocator));
			if (!$uip->replaced())
			{
				$html = $ilLocator->getHTML();
			}
			$html = $uip->getHTML($html);
		}
		else
		{
			$html = $ilLocator->getHTML();
		}

		$this->setVariable("LOCATOR", $html);
	}


	// TABS

	/**
	* sets tabs in standard template
	*/
	public function setTabs($a_tabs_html)
	{
		if ($a_tabs_html != "" && $this->blockExists("tabs_outer_start"))
		{
			$this->touchBlock("tabs_outer_start");
			$this->touchBlock("tabs_outer_end");
			$this->touchBlock("tabs_inner_start");
			$this->touchBlock("tabs_inner_end");
			$this->setVariable("TABS", $a_tabs_html);
		}
	}

	/**
	* sets subtabs in standard template
	*/
	public function setSubTabs($a_tabs_html)
	{
		$this->setVariable("SUB_TABS", $a_tabs_html);
	}


	// COLUMN LAYOUT IN STANDARD TEMPLATE
	
	/**
	* Fill main content
	*/
	private function fillMainContent()
	{
		if (trim($this->main_content) != "")
		{
			$this->setVariable("ADM_CONTENT", $this->main_content);
		}
	}

	/**
	* sets content of right column
	*/
	public function setRightContent($a_html)
	{
		$this->right_content = $a_html;
	}

	/**
	* Fill right content
	*/
	private function fillRightContent()
	{
		if (trim($this->right_content) != "")
		{
			$this->setCurrentBlock("right_column");
			$this->setVariable("RIGHT_CONTENT", $this->right_content);
			$this->parseCurrentBlock();
		}
	}
	
	private function setCenterColumnClass()
	{
		if (!$this->blockExists("center_col_width"))
		{
			return;
		}
		$center_column_class = "";
		if (trim($this->right_content) != "" && trim($this->left_content) != "") {
			$center_column_class = "two_side_col";
		}
		else if (trim($this->right_content) != "" || trim($this->left_content) != "") {
			$center_column_class = "one_side_col";
		}

		switch ($center_column_class)
		{
			case "one_side_col": $center_column_class = "col-sm-9"; break;
			case "two_side_col": $center_column_class = "col-sm-6"; break;
			default: $center_column_class = "col-sm-12"; break;
		}
		if (trim($this->left_content) != "")
		{
			$center_column_class.= " col-sm-push-3";
		}

		$this->setCurrentBlock("center_col_width");
		$this->setVariable("CENTER_COL", $center_column_class);
		$this->parseCurrentBlock();
	}

	/**
	* sets content of left column
	*/
	public function setLeftContent($a_html)
	{
		$this->left_content = $a_html;
	}

	/**
	* Fill left content
	*/
	private function fillLeftContent()
	{
		if (trim($this->left_content) != "")
		{
			$this->setCurrentBlock("left_column");
			$this->setVariable("LEFT_CONTENT", $this->left_content);
			$left_col_class = (trim($this->right_content) == "")
				? "col-sm-3 col-sm-pull-9"
				: "col-sm-3 col-sm-pull-6";
			$this->setVariable("LEFT_COL_CLASS", $left_col_class);
			$this->parseCurrentBlock();
		}
	}

	/**
	 * Sets content of left navigation column
	 */
	public function setLeftNavContent($a_content)
	{
		$this->left_nav_content = $a_content;
	}

	/**
	 * Fill left navigation frame
	 */
	public function fillLeftNav()
	{
		if (trim($this->left_nav_content) != "")
		{
			$this->setCurrentBlock("left_nav");
			$this->setVariable("LEFT_NAV_CONTENT", $this->left_nav_content);
			$this->parseCurrentBlock();
			$this->touchBlock("left_nav_space");
		}
	}


	// SPECIAL REQUIREMENTS
	//
	// Stuff that is only used by a little other classes.

	/**
	 * Add current user language to meta tags
	 *
	 * @access public
	 */
	public function fillContentLanguage()
	{
		global $DIC;

		$lng = $DIC->language();
		$ilUser = $DIC->user();

		$contentLanguage = 'en';
		$rtl = array('ar','fa','ur','he');//, 'de'); //make a list of rtl languages
		/* rtl-review: add "de" for testing with ltr lang shown in rtl
		 * and set unicode-bidi to bidi-override for mirror effect */
		$textdir = 'ltr';
	 	if(is_object($ilUser))
	 	{
	 		if($ilUser->getLanguage())
	 		{
		 		$contentLanguage = $ilUser->getLanguage();
	 		}
	 		else if(is_object($lng))
	 		{
		 		$contentLanguage = $lng->getDefaultLanguage();
	 		}
	 	}
 		$this->setVariable('META_CONTENT_LANGUAGE', $contentLanguage);
		if (in_array($contentLanguage, $rtl)) { 
			$textdir = 'rtl'; 
		}
		$this->setVariable('LANGUAGE_DIRECTION', $textdir);
		return true;	 	
	}

	public function fillWindowTitle()
	{
		global $DIC;

		$ilSetting = $DIC->settings();
		
		if ($this->header_page_title != "")
		{
			$title = ilUtil::stripScriptHTML($this->header_page_title);	
			$this->setVariable("PAGETITLE", "- ".$title);
		}
		
		if ($ilSetting->get('short_inst_name') != "")
		{
			$this->setVariable("WINDOW_TITLE",
				$ilSetting->get('short_inst_name'));
		}
		else
		{
			$this->setVariable("WINDOW_TITLE",
				"ILIAS");
		}
	}

	public function fillTabs()
	{
		if ($this->blockExists("tabs_outer_start"))
		{
			$this->touchBlock("tabs_outer_start");
			$this->touchBlock("tabs_outer_end");
			$this->touchBlock("tabs_inner_start");
			$this->touchBlock("tabs_inner_end");

			if ($this->thtml != "")
			{
				$this->setVariable("TABS",$this->thtml);
			}
			$this->setVariable("SUB_TABS", $this->sthtml);
		}
	}

	public function fillJavaScriptFiles($a_force = false)
	{
		global $DIC;

		$ilSetting = $DIC->settings();

		if (is_object($ilSetting))		// maybe this one can be removed
		{
			$vers = "vers=".str_replace(array(".", " "), "-", $ilSetting->get("ilias_version"));
			
			if(DEVMODE)
			{
				$vers .= '-'.time();
			}
		}
		if ($this->blockExists("js_file"))
		{
			// three batches
			for ($i=0; $i<=3; $i++)
			{
				reset($this->js_files);
				foreach($this->js_files as $file)
				{
					if ($this->js_files_batch[$file] == $i)
					{
						if (is_file($file) || substr($file, 0, 4) == "http" || substr($file, 0, 2) == "//" || $a_force)
						{
							$this->fillJavascriptFile($file, $vers);							
						}
						else if(substr($file, 0, 2) == './') // #13962
						{
							$url_parts = parse_url($file);
							if(is_file($url_parts['path']))
							{
								$this->fillJavascriptFile($file, $vers);
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Fill in the css file tags
	 * 
	 * @param boolean $a_force
	 */
	public function fillCssFiles($a_force = false)
	{
		if (!$this->blockExists("css_file"))
		{
			return;
		}
		foreach($this->css_files as $css)
		{
			$filename = $css["file"];
			if (strpos($filename, "?") > 0) $filename = substr($filename, 0, strpos($filename, "?"));
			if (is_file($filename) || $a_force)
			{
				$this->setCurrentBlock("css_file");
				$this->setVariable("CSS_FILE", $css["file"]);
				$this->setVariable("CSS_MEDIA", $css["media"]);
				$this->parseCurrentBlock();
			}
		}
	}

	/**
	 * Fill in the inline css
	 *
	 * @param boolean $a_force
	 */
	private function fillInlineCss()
	{
		if (!$this->blockExists("css_inline"))
		{
			return;
		}
		foreach($this->inline_css as $css)
		{
			$this->setCurrentBlock("css_file");
			$this->setVariable("CSS_INLINE", $css["css"]);
			$this->parseCurrentBlock();
		}
	}

	public function setStyleSheetLocation($a_stylesheet)
	{
		$this->setVariable("LOCATION_STYLESHEET", $a_stylesheet);
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

	public function setBodyClass($a_class = "")
	{
		$this->body_class = $a_class;
	}
	
	public function fillBodyClass()
	{
		if ($this->body_class != "" && $this->blockExists("body_class"))
		{
			$this->setCurrentBlock("body_class");
			$this->setVariable("BODY_CLASS", $this->body_class);
			$this->parseCurrentBlock();
		}
	}

	public function setPageFormAction($a_action)
	{
		$this->page_form_action = $a_action;
	}

	/**
	 * Set header action menu
	 *
	 * @param string $a_gui $a_header
	 */
	public function setHeaderActionMenu($a_header)
	{
		$this->header_action = $a_header;
	}

	/**
	 * Sets the title of the page (for browser window).
	 */
	public function setHeaderPageTitle($a_title)
	{
		$this->header_page_title = $a_title;
	}

	/**
	 * Set target parameter for login (public sector).
	 * This is used by the main menu
	 */
	public function setLoginTargetPar($a_val)
	{
		$this->login_target_par = $a_val;
	}

	// TEMPLATING AND GLOBAL RENDERING
	//
	// used in a lot of places

	/**
	 * @param	string
	 * @return	string
	 */
	public function get($part = "DEFAULT") {
		global $DIC;

		if ($part == "DEFAULT")
		{
			$html = parent::get();
		}
		else
		{
			$html = parent::get($part);
		}

		// include the template output hook
		$ilPluginAdmin = $DIC["ilPluginAdmin"];
		$pl_names = $ilPluginAdmin->getActivePluginsForSlot(IL_COMP_SERVICE, "UIComponent", "uihk");
		foreach ($pl_names as $pl)
		{
			$ui_plugin = ilPluginAdmin::getPluginObject(IL_COMP_SERVICE, "UIComponent", "uihk", $pl);
			$gui_class = $ui_plugin->getUIClassInstance();

			$resp = $gui_class->getHTML("", "template_get",
					array("tpl_id" => $this->tplIdentifier, "tpl_obj" => $this, "html" => $html));

			if ($resp["mode"] != ilUIHookPluginGUI::KEEP)
			{
				$html = $gui_class->modifyHTML($html, $resp);
			}
		}

		return $html;
	}

	/**
	* Überladene Funktion, die sich hier lokal noch den aktuellen Block merkt.
	* @access	public
	* @param	string
	* @return	???
	*/
	public function setCurrentBlock ($part = "DEFAULT")
	{
		$this->activeBlock = $part;

		if ($part == "DEFAULT")
		{
			return parent::setCurrentBlock();
		}
		else
		{
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
		$count = $this->fillVars();
		$this->parseCurrentBlock();

		if ($count == 0)
		{
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
		if ($part != "DEFAULT")
		{
			$tmp = $this->activeBlock;
			$this->activeBlock = $part;
		}

		if ($part != "DEFAULT")
		{
			$this->activeBlock = $tmp;
		}

		$this->fillVars();

		$this->activeBlock = "__global__";

		if ($part == "DEFAULT")
		{
			return parent::parseCurrentBlock();
		}
		else
		{
			return parent::parseCurrentBlock($part);
		}
	}

	/**
	* overwrites ITX::addBlockFile
	* @access	public
	* @param	string
	* @param	string
	* @param	string		$tplname		template name
	* @param	boolean		$in_module		should be set to true, if template file is in module subdirectory
	* @return	boolean/string
	*/
	public function addBlockFile($var, $block, $tplname, $in_module = false)
	{
		global $DIC;

		if (DEBUG)
		{
			echo "<br/>Template '".$this->tplPath."/".$tplname."'";
		}

		$tplfile = $this->getTemplatePath($tplname, $in_module);
		if (file_exists($tplfile) == false)
		{
			echo "<br/>Template '".$tplfile."' doesn't exist! aborting...";
			return false;
		}

		$id = $this->getTemplateIdentifier($tplname, $in_module);
		$template = $this->getFile($tplfile);

		// include the template input hook
		$ilPluginAdmin = $DIC["ilPluginAdmin"];
		$pl_names = $ilPluginAdmin->getActivePluginsForSlot(IL_COMP_SERVICE, "UIComponent", "uihk");
		foreach ($pl_names as $pl)
		{
			$ui_plugin = ilPluginAdmin::getPluginObject(IL_COMP_SERVICE, "UIComponent", "uihk", $pl);
			$gui_class = $ui_plugin->getUIClassInstance();

			$resp = $gui_class->getHTML("", "template_add",
					array("tpl_id" => $id, "tpl_obj" => $this, "html" => $template));

			if ($resp["mode"] != ilUIHookPluginGUI::KEEP)
			{
				$template = $gui_class->modifyHTML($template, $resp);
			}
		}

		return $this->addBlock($var, $block, $template);
	}

	/**
	 * Get tabs HTML
	 *
	 * @param
	 * @return
	 */
	private function getTabsHTML()
	{
		global $DIC;

		$ilTabs = $DIC["ilTabs"];

		if ($this->blockExists("tabs_outer_start"))
		{
			$this->sthtml = $ilTabs->getSubTabHTML();
			$this->thtml = $ilTabs->getHTML((trim($sthtml) == ""));
		}
	}

	private function fillToolbar()
	{
		global $DIC;

		$ilToolbar = $DIC["ilToolbar"];;

		$thtml = $ilToolbar->getHTML();
		if ($thtml != "")
		{
			$this->setCurrentBlock("toolbar_buttons");
			$this->setVariable("BUTTONS", $thtml);
			$this->parseCurrentBlock();
		}
	}

	private function fillPageFormAction()
	{
		if ($this->page_form_action != "")
		{
			$this->setCurrentBlock("page_form_start");
			$this->setVariable("PAGE_FORM_ACTION", $this->page_form_action);
			$this->parseCurrentBlock();
			$this->touchBlock("page_form_end");
		}
	}


	/**
	* Fill Content Style
	*/
	private function fillNewContentStyle()
	{
		$this->setVariable("LOCATION_NEWCONTENT_STYLESHEET_TAG",
			'<link rel="stylesheet" type="text/css" href="'.
			ilUtil::getNewContentStyleSheetLocation()
			.'" />');
	}
	
	private function getMainMenu()
	{
		global $DIC;

		$ilMainMenu = $DIC["ilMainMenu"];

		if($this->variableExists('MAINMENU'))
		{
			$ilMainMenu->setLoginTargetPar($this->getLoginTargetPar());
			$this->main_menu = $ilMainMenu->getHTML();
			$this->main_menu_spacer = $ilMainMenu->getSpacerClass();
		}
	}
	
	private function fillMainMenu()
	{
		global $DIC;
		$tpl = $DIC["tpl"];
		if($this->variableExists('MAINMENU'))
		{
			$tpl->setVariable("MAINMENU", $this->main_menu);
			$tpl->setVariable("MAINMENU_SPACER", $this->main_menu_spacer);
		}
	}

	/**
	 * Init help
	 */
	private function initHelp()
	{
		include_once("./Services/Help/classes/class.ilHelpGUI.php");
		ilHelpGUI::initHelp($this);
	}
	


	/**
	* TODO: this is nice, but shouldn't be done here
	* (-> maybe at the end of ilias.php!?, alex)
	*/
	private function handleReferer()
	{
		if (((substr(strrchr($_SERVER["PHP_SELF"],"/"),1) != "error.php")
			&& (substr(strrchr($_SERVER["PHP_SELF"],"/"),1) != "adm_menu.php")
			&& (substr(strrchr($_SERVER["PHP_SELF"],"/"),1) != "chat.php")))
		{
			$_SESSION["post_vars"] = $_POST;

			// referer is modified if query string contains cmd=gateway and $_POST is not empty.
			// this is a workaround to display formular again in case of error and if the referer points to another page
			$url_parts = @parse_url($_SERVER["REQUEST_URI"]);
			if(!$url_parts)
			{
				$protocol = (isset($_SERVER['HTTPS']) ? 'https' : 'http').'://';
				$host = $_SERVER['HTTP_HOST'];
				$path = $_SERVER['REQUEST_URI'];
				$url_parts = @parse_url($protocol.$host.$path);
			}

			if (isset($url_parts["query"]) && preg_match("/cmd=gateway/",$url_parts["query"]) && (isset($_POST["cmd"]["create"])))
			{
				foreach ($_POST as $key => $val)
				{
					if (is_array($val))
					{
						$val = key($val);
					}

					$str .= "&".$key."=".$val;
				}

				$_SESSION["referer"] = preg_replace("/cmd=gateway/",substr($str,1),$_SERVER["REQUEST_URI"]);
				$_SESSION['referer_ref_id'] = (int) $_GET['ref_id'];
				
			}
			else if (isset($url_parts["query"]) && preg_match("/cmd=post/",$url_parts["query"]) && (isset($_POST["cmd"]["create"])))
			{
				foreach ($_POST as $key => $val)
				{
					if (is_array($val))
					{
						$val = key($val);
					}

					$str .= "&".$key."=".$val;
				}

				$_SESSION["referer"] = preg_replace("/cmd=post/",substr($str,1),$_SERVER["REQUEST_URI"]);
				if (isset($_GET['ref_id']))
				{
					$_SESSION['referer_ref_id'] = (int) $_GET['ref_id'];
				}
				else
				{
					$_SESSION['referer_ref_id'] = 0;
				}
							}
			else
			{
				$_SESSION["referer"] = $_SERVER["REQUEST_URI"];
				if (isset($_GET['ref_id']))
				{
					$_SESSION['referer_ref_id'] = (int) $_GET['ref_id'];
				}
				else
				{
					$_SESSION['referer_ref_id'] = 0;
				}
			}

			unset($_SESSION["error_post_vars"]);
		}
	}

	private function variableExists($a_variablename)
	{
		return (isset($this->blockvariables["content"][$a_variablename]) ? true : false);
	}

	/**
	* all template vars defined in $vars will be replaced automatically
	* without setting and parsing them with setVariable & parseCurrentBlock
	* @access	private
	* @return	integer
	*/
	private function fillVars()
	{
		$count = 0;
		reset($this->vars);

		while(list($key, $val) = each($this->vars))
		{
			if (is_array($this->blockvariables[$this->activeBlock]))
			{
				if  (array_key_exists($key, $this->blockvariables[$this->activeBlock]))
				{
					$count++;

					$this->setVariable($key, $val);
				}
			}
		}
		
		return $count;
	}

	/**
     * Reads a template file from the disk.
     *
	 * overwrites IT:loadTemplateFile to include the template input hook
	 *
     * @param    string      name of the template file
     * @param    bool        how to handle unknown variables.
     * @param    bool        how to handle empty blocks.
     * @access   public
     * @return   boolean    false on failure, otherwise true
     * @see      $template, setTemplate(), $removeUnknownVariables,
     *           $removeEmptyBlocks
     */
    public function loadTemplatefile( $filename,
                               $removeUnknownVariables = true,
                               $removeEmptyBlocks = true )
    {
    	global $DIC;

    	// copied from IT:loadTemplateFile
        $template = '';
        if (!$this->flagCacheTemplatefile ||
            $this->lastTemplatefile != $filename
        ) {
            $template = $this->getFile($filename);
        }
        $this->lastTemplatefile = $filename;
		// copied.	
        
		// new code to include the template input hook:
		$ilPluginAdmin = $DIC["ilPluginAdmin"];
		$pl_names = $ilPluginAdmin->getActivePluginsForSlot(IL_COMP_SERVICE, "UIComponent", "uihk");
		foreach ($pl_names as $pl)
		{
			$ui_plugin = ilPluginAdmin::getPluginObject(IL_COMP_SERVICE, "UIComponent", "uihk", $pl);
			$gui_class = $ui_plugin->getUIClassInstance();
			
			$resp = $gui_class->getHTML("", "template_load", 
					array("tpl_id" => $this->tplIdentifier, "tpl_obj" => $this, "html" => $template));

			if ($resp["mode"] != ilUIHookPluginGUI::KEEP)
			{
				$template = $gui_class->modifyHTML($template, $resp);
			}
		}
		// new.     
        
        // copied from IT:loadTemplateFile
        return $template != '' ?
                $this->setTemplate(
                        $template,$removeUnknownVariables, $removeEmptyBlocks
                    ) : false;
        // copied.
                    
    }
	

	/**
	* builds a full template path with template and module name
	*
	* @param	string		$a_tplname		template name
	* @param	boolean		$in_module		should be set to true, if template file is in module subdirectory
	*
	* @return	string		full template path
	*/
	protected function getTemplatePath($a_tplname, $a_in_module = false, $a_plugin = false)
	{
		global $DIC;

		$ilCtrl = null;
		if (isset($DIC["ilCtrl"]))
		{
			$ilCtrl = $DIC->ctrl();
		}
		
		$fname = "";
		
		// if baseClass functionality is used (ilias.php):
		// get template directory from ilCtrl
		if (!empty($_GET["baseClass"]) && $a_in_module === true)
		{
			$a_in_module = $ilCtrl->getModuleDir();
		}

		if (strpos($a_tplname,"/") === false)
		{
			$module_path = "";
			
			if ($a_in_module)
			{
				if ($a_in_module === true)
				{
					$module_path = ILIAS_MODULE."/";
				}
				else
				{
					$module_path = $a_in_module."/";
				}
			}

			// use ilStyleDefinition instead of account to get the current skin
			include_once "Services/Style/System/classes/class.ilStyleDefinition.php";
			if (ilStyleDefinition::getCurrentSkin() != "default")
			{
				$style = ilStyleDefinition::getCurrentStyle();

				$fname = "./Customizing/global/skin/".
						ilStyleDefinition::getCurrentSkin()."/".$style."/".$module_path
						.basename($a_tplname);

				if($fname == "" || !file_exists($fname))
				{
					$fname = "./Customizing/global/skin/".
							ilStyleDefinition::getCurrentSkin()."/".$module_path.basename($a_tplname);
				}

			}

			if($fname == "" || !file_exists($fname))
			{
				$fname = "./".$module_path."templates/default/".basename($a_tplname);
			}
		}
		else if(strpos($a_tplname,"src/UI")===0)
		{
			if (class_exists("ilStyleDefinition") // for testing
			&& ilStyleDefinition::getCurrentSkin() != "default")
			{
				$fname = "./Customizing/global/skin/".ilStyleDefinition::getCurrentSkin()."/".str_replace("src/UI/templates/default","UI",$a_tplname);
			}
			if($fname == "" || !file_exists($fname))
			{
				$fname = $a_tplname;
			}
		}
		else
		{
			$fname = $a_tplname;
		}
		
		return $fname;
	}
	
	/**
	 * get a unique template identifier
	 *
	 * The identifier is common for default or customized skins
	 * but distincts templates of different services with the same name.
	 *
	 * This is used by the UI plugin hook for template input/output
	 * 
	 * @param	string				$a_tplname		template name
	 * @param	string				$in_module		Component, e.g. "Modules/Forum"
	 * 			boolean				$in_module		or true, if component should be determined by ilCtrl
	 *
	 * @return	string				template identifier, e.g. "tpl.confirm.html"
	 */
	private function getTemplateIdentifier($a_tplname, $a_in_module = false)
	{
		global $DIC;

		$ilCtrl = null;
		if (isset($DIC["ilCtrl"]))
		{
			$ilCtrl = $DIC->ctrl();
		}


		// if baseClass functionality is used (ilias.php):
		// get template directory from ilCtrl
		if (!empty($_GET["baseClass"]) && $a_in_module === true)
		{
			$a_in_module = $ilCtrl->getModuleDir();
		}

		if (strpos($a_tplname,"/") === false)
		{
			if ($a_in_module)
			{
				if ($a_in_module === true)
				{
					$module_path = ILIAS_MODULE."/";
				}
				else
				{
					$module_path = $a_in_module."/";
				}
			}
			else
			{
				$module_path = "";
			}
			
			return $module_path.basename($a_tplname);
		}
		else
		{
			return $a_tplname;
		}
	}

	public function getStandardTemplate()
	{
		if ($this->standard_template_loaded)
		{
			return;
		}

		// always load jQuery
		include_once("./Services/jQuery/classes/class.iljQueryUtil.php");
		iljQueryUtil::initjQuery();
		iljQueryUtil::initjQueryUI();

		// always load ui framework
		include_once("./Services/UICore/classes/class.ilUIFramework.php");
		ilUIFramework::init();

		$this->addBlockFile("CONTENT", "content", "tpl.adm_content.html");
		$this->addBlockFile("STATUSLINE", "statusline", "tpl.statusline.html");

		$this->standard_template_loaded = true;
	}

	/**
	* sets content for standard template
	*/
	public function setContent($a_html)
	{
		if ($a_html != "")
		{
			$this->main_content = $a_html;
		}
	}

	/**
	 * Get target parameter for login
	 */
	private function getLoginTargetPar()
	{
		return $this->login_target_par;
	}

	/**
	* Accessibility focus for screen readers
	*/
	public function fillScreenReaderFocus()
	{
		global $DIC;

		$ilUser = $DIC->user();

		if (is_object($ilUser) && $ilUser->getPref("screen_reader_optimization") && $this->blockExists("sr_focus"))
		{
			$this->touchBlock("sr_focus");
		}
	}
	
	/**
	* Fill side icons (upper icon, tree icon, webfolder icon)
	*/
	private function fillSideIcons()
	{
		global $DIC;

		$ilSetting = $DIC->settings();

		$lng = $DIC->language();

		// tree/flat icon
		if ($this->tree_flat_link != "")
		{
			if ($this->left_nav_content != "")
			{
				$this->touchBlock("tree_lns");
			}
			
			$this->setCurrentBlock("tree_mode");
			$this->setVariable("LINK_MODE", $this->tree_flat_link);
			if ($ilSetting->get("tree_frame") == "right")
			{
				if ($this->tree_flat_mode == "tree")
				{
					$this->setVariable("IMG_TREE",ilUtil::getImagePath("icon_sidebar_on.svg"));
					$this->setVariable("RIGHT", "Right");
				}
				else
				{
					$this->setVariable("IMG_TREE",ilUtil::getImagePath("icon_sidebar_on.svg"));
					$this->setVariable("RIGHT", "Right");
				}
			}
			else
			{
				if ($this->tree_flat_mode == "tree")
				{
					$this->setVariable("IMG_TREE",ilUtil::getImagePath("icon_sidebar_on.svg"));
				}
				else
				{
					$this->setVariable("IMG_TREE",ilUtil::getImagePath("icon_sidebar_on.svg"));
				}
			}
			$this->setVariable("ALT_TREE",$lng->txt($this->tree_flat_mode."view"));
			$this->setVariable("TARGET_TREE", ilFrameTargetInfo::_getFrame("MainContent"));
			include_once("./Services/Accessibility/classes/class.ilAccessKeyGUI.php");
			$this->setVariable("TREE_ACC_KEY",
				ilAccessKeyGUI::getAttribute(($this->tree_flat_mode == "tree")
					? ilAccessKey::TREE_ON
					: ilAccessKey::TREE_OFF));
			$this->parseCurrentBlock();
		}
		
		$this->setCurrentBlock("tree_icons");
		$this->parseCurrentBlock();
	}
	
	/**
	* set tree/flat icon
	* @param	string		link target
	* @param	strong		mode ("tree" | "flat")
	*/
	public function setTreeFlatIcon($a_link, $a_mode)
	{
		$this->tree_flat_link = $a_link;
		$this->tree_flat_mode = $a_mode;
	}

	/**
	* Add a javascript file that should be included in the header.
	*/
	public function addJavaScript($a_js_file, $a_add_version_parameter = true, $a_batch = 2)
	{
		// three batches currently
		if ($a_batch < 1 || $a_batch > 3)
		{
			$a_batch = 2;
		}

		// ensure jquery files being loaded first
		if (is_int(strpos($a_js_file, "Services/jQuery")) ||
			is_int(strpos($a_js_file, "/jquery.js")) ||
			is_int(strpos($a_js_file, "/jquery-min.js")))
		{
			$a_batch = 0;
		}

		if (!in_array($a_js_file, $this->js_files))
		{
			$this->js_files[] = $a_js_file;
			$this->js_files_vp[$a_js_file] = $a_add_version_parameter;
			$this->js_files_batch[$a_js_file] = $a_batch;
		}
	}

	/**
	 * Reset javascript files
	 */
	public function resetJavascript()
	{
		$this->js_files = array();
		$this->js_files_vp = array();
		$this->js_files_batch = array();
	}
	
	/**
	 * Reset css files
	 *
	 * @param
	 * @return
	 */
	public function resetCss()
	{
		$this->css_files = array();
	}
	
	
	/**
	* Add on load code
	*/
	public function addOnLoadCode($a_code, $a_batch = 2)
	{
		// three batches currently
		if ($a_batch < 1 || $a_batch > 3)
		{
			$a_batch = 2;
		}
		$this->on_load_code[$a_batch][] = $a_code;
	}
	
	/**
	 * Add a css file that should be included in the header.
	 */
	public function addCss($a_css_file, $media = "screen")
	{
		if (!array_key_exists($a_css_file . $media, $this->css_files))
		{
			$this->css_files[$a_css_file . $media] = array("file" => $a_css_file, "media" => $media);
		}
	}

	/**
	 * Add a css file that should be included in the header.
	 */
	public function addInlineCss($a_css, $media = "screen")
	{
		$this->inline_css[] = array("css" => $a_css, "media" => $media);
	}
	
	/**
	 * Add lightbox html
	 */
	public function addLightbox($a_html, $a_id)
	{
		$this->lightbox[$a_id] = $a_html;
	}

	/**
	 * Fill lightbox content
	 *
	 * @param
	 * @return
	 */
	private function fillLightbox()
	{
		$html = "";

		foreach ($this->lightbox as $lb)
		{
			$html.= $lb;
		}
		$this->setVariable("LIGHTBOX", $html);
	}

	// ADMIN PANEL
	//
	// Only used in ilContainerGUI
	//
	// An "Admin Panel" is that toolbar thingy that could be found on top and bottom
	// of a repository listing when editing objects in a container gui.

	protected $admin_panel_commands_toolbar = null;
	protected $admin_panel_arrow = null;
	protected $admin_panel_bottom = null;
	
	/**
	 * Add admin panel commands as toolbar
	 *
	 * @param ilToolbarGUI $toolb
	 * @param bool $a_top_only
	 */
	public function addAdminPanelToolbar(ilToolbarGUI $toolb,$a_bottom_panel = true, $a_arrow = false)
	{
		$this->admin_panel_commands_toolbar = $toolb;
		$this->admin_panel_arrow = $a_arrow;
		$this->admin_panel_bottom = $a_bottom_panel;
	}
	
	/**
	* Put admin panel into template:
	* - creation selector
	* - admin view on/off button
	*/
	private function fillAdminPanel()
	{
		global $DIC;
		$lng = $DIC->language();

		if ($this->admin_panel_commands_toolbar === null) {
			return;
		}

		$toolb = $this->admin_panel_commands_toolbar;
		assert($toolbar instanceof \ilToolbarGUI);

		// Add arrow if desired.
		if($this->admin_panel_arrow)
		{
			$toolb->setLeadingImage(ilUtil::getImagePath("arrow_upright.svg"), $lng->txt("actions"));
		}

		$this->fillPageFormAction();

		// Add top admin bar.
		$this->setCurrentBlock("adm_view_components");
		$this->setVariable("ADM_PANEL1", $toolb->getHTML());
		$this->parseCurrentBlock();
		
		// Add bottom admin bar if user wants one.
		if ($this->admin_panel_bottom)
		{
			$this->setCurrentBlock("adm_view_components2");

			// Replace previously set arrow image.
			if ($this->admin_panel_arrow)
			{
				$toolb->setLeadingImage(ilUtil::getImagePath("arrow_downright.svg"), $lng->txt("actions"));
			}

			$this->setVariable("ADM_PANEL2", $toolb->getHTML());
			$this->parseCurrentBlock();
		}
	}
	
	public function setPermanentLink($a_type, $a_id, $a_append = "", $a_target = "", $a_title = "")
	{
		$this->permanent_link = array(
			"type" => $a_type,
			"id" => $a_id,
			"append" => $a_append,
			"target" => $a_target,
			"title" => $a_title);

	}
	
	/**
	* Fill in permanent link
	*/
	private function fillPermanentLink()
	{
		if (is_array($this->permanent_link))
		{
			include_once("./Services/PermanentLink/classes/class.ilPermanentLinkGUI.php");
			$plinkgui = new ilPermanentLinkGUI(
				$this->permanent_link["type"],
				$this->permanent_link["id"],
				$this->permanent_link["append"],
				$this->permanent_link["target"]);
			if ($this->permanent_link["title"] != "")
			{
				$plinkgui->setTitle($this->permanent_link["title"]);
			}
			$this->setVariable("PRMLINK", $plinkgui->getHTML());
		}
	}

	/**
	* Fill add on load code
	*/
	public function fillOnLoadCode()
	{
		for ($i = 1; $i <= 3; $i++)
		{
			if (is_array($this->on_load_code[$i]))
			{
				$this->setCurrentBlock("on_load_code");
				foreach ($this->on_load_code[$i] as $code)
				{
					$this->setCurrentBlock("on_load_code_inner");
					$this->setVariable("OLCODE", $code);
					$this->parseCurrentBlock();
				}
				$this->setCurrentBlock("on_load_code");
				$this->parseCurrentBlock();
			}
		}
	}
	
	/**
	 * Get js onload code for ajax calls
	 * 
	 * @return string
	 */
	public function getOnLoadCodeForAsynch()
	{
		$js = "";
		for ($i = 1; $i <= 3; $i++)
		{
			if (is_array($this->on_load_code[$i]))
			{
				foreach ($this->on_load_code[$i] as $code)
				{
					$js .= $code."\n";
				}
			}
		}		
		if($js)
		{
			return '<script type="text/javascript">'."\n".
				$js.
				'</script>'."\n";
		}
	}
	
	public function setBackgroundColor($a_bg_color)
	{
		// :TODO: currently inactive, JF should discuss this
		return;
		
		if($a_bg_color != "")
		{
			$this->setVariable("FRAME_BG_COLOR", " style=\"background-color: #".$a_bg_color."\"");
		}
	}

	/**
	 * Set banner
	 * 	
	 * @param string $a_img banner full path (background image)	
	 * @param int $a_width banner width
	 * @param int $a_height banner height
	 * @param bool $a_export
	 */
	public function setBanner($a_img, $a_width = 1370, $a_height = 100, $a_export = false)
	{		
		if($a_img)
		{
			if(!$a_export)
			{
				$a_img = ILIAS_HTTP_PATH."/".$a_img;
			}
			
			$this->setCurrentBlock("banner_bl");
			$this->setVariable("BANNER_WIDTH", $a_width); // currently not needed
			$this->setVariable("BANNER_HEIGHT", $a_height);
			$this->setVariable("BANNER_URL", $a_img);			
			$this->parseCurrentBlock();
		}
	}
	
	/**
	 * Reset all header properties: title, icon, description, alerts, action menu
	 */
	public function resetHeaderBlock($a_reset_header_action = true)
	{
		$this->setTitle(null);
		$this->setTitleIcon(null);
		$this->setDescription(null);
		$this->setAlertProperties(array());		
		$this->enableDragDropFileUpload(null);
		
		// see setFullscreenHeader()
		if($a_reset_header_action)
		{
			$this->setHeaderActionMenu(null);
		}
	}	
	
	/**
	 * Enables the file upload into this object by dropping a file.
	 */
	public function enableDragDropFileUpload($a_ref_id)
	{
		$this->enable_fileupload = $a_ref_id;
	}


	/**
	 * @param $m
	 *
	 * @return mixed|string
	 */
	private function getMessageTextForType($m) {
		$txt = "";
		if (isset($_SESSION[$m]) && $_SESSION[$m] != "") {
			$txt = $_SESSION[$m];
		} else {
			if (isset($this->message[$m])) {
				$txt = $this->message[$m];
			}
		}

		return $txt;
	}
}
