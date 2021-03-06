<?php
require_once(realpath(dirname(__FILE__)) . '/../../../include/view/widgets/FormWidget.php');
require_once (realpath(dirname(__FILE__))."/../../../include/content.inc.php");
require_once (realpath(dirname(__FILE__))."/../../../include/skinlet.inc.php");

Class Form extends Skinlet{
	public
	$labels,
	$name,
	$helpers,
	$relations,
	$method,
	$enctype,
	$elements,
	$conditions,
	$entity,
	$withPosition,
	$positions,
	$noDelete,
	$triggered,
	$triggeredForm,
	$triggeredForms,
	$moderationMode,
	$description,
	$filterRelation,

	$reportTemplate,

	$pager,
	$lastid,		  		// The pager is used to customize the report functionality

	$formHash,
	$debugmode=false,
	$reportContent;

	function requestAction()
    {
		/**
		 * Killing switch-case with class... chapeau!
		 */
        if (method_exists($this,$_REQUEST['action']))
        {
		    return $this->{$_REQUEST['action']}();
        }
	}

    /**
     * @param string $name
     * @param Entity $entity
     * @param string $method
     */
    function __construct($name, $entity, $method = "POST") {

		/* can the name of the form be given in an automated way,
		 using maybe an identified which is generated from the
		timestamp ? */

		$this->name = $name;
		$this->formHash=md5($name);

		$this->method = $method;
		$this->enctype="multipart/form-data";
		/* this is used to control the visibility of the "delete" button
		 in the form while the EDIT mode */

		$this->noDelete = false;

		/* this is used to denote that the form has a POSITION widget type */

		$this->withPosition = false;

		/* the following denotes that the current form will be
		 triggered by some other form, the invoking form is referred
		in triggerForm */

		$this->triggered = false;
		$this->triggeredForm = false;
		$this->triggeredForms = false;
		$this->moderationMode = false;

		/* the following is the DTML template path */
		$this->labels[ADD] = "Aggiungi";
		$this->labels[EDIT] = "Modifica";
		$this->labels[DELETE] = "Rimuovi";
		$this->labels['MSG_SURE'] = "Sei sicuro";
		$this->labels['MSG_UPDATE'] = "The item has been correctly updated!";

		$this->entity = $entity;

		$this->reportTemplate = "report/".$this->entity->entityName."_report";

		parent::__construct("form.html");

		$this->reportContent = new Content($entity);
        /*if( $entity->existsField('father') )
            $this->reportContent->setOrderFields('father');*/
	}

	function setReportTemplate($template)
	{
		$this->reportTemplate = $template;
	}

	function setReportContent($content)
	{
		$this->reportContent=$content;
	}


	/**
	 * This method is used to list all the occurrences of an entity in the back end according to the query_restrictions requested
	 * It uses the specific entity_name-report.html autogenerated or modified from the user
	 * @param boolean $noDelete
	 * @param integer $page
	 * @return string
	 */
	function report($noDelete=false, $page=0)
    {
		$entityTemplate = new Skinlet($this->reportTemplate);
		$entityTemplate->setContent("service_link",basename($_SERVER["SCRIPT_NAME"]));

        $entityReport = $this->reportContent;

		$entityReport->apply($entityTemplate);

		$reportAsString = $entityTemplate->get();

		if(!empty($reportAsString))
		{
			$content =$reportAsString;
		}
		else
		{
			$emptyReportTemplate = new Skinlet("empty-report");
			$content = '<div>'.$emptyReportTemplate->get().'</div>';
		}
		return $content;
	}

	/**
	 * This method is called to emit the form both for add and edit operation
	 *
	 */
	function emit()
	{
		$content="";
		if(isset($_REQUEST["preload"]))
		{
			if($_REQUEST["preload"]==1)
			{
				/**
				 *
				 * If the data preload is requested we emit an edit form
				 *
				 */
				if(isset($_REQUEST["auth-value"]))
					$query_conditions=array("id_users"=>$_REQUEST['auth-value']);
				else
					$query_conditions=array($this->entity->fields[0]->name=>$_REQUEST['value']);
				/**
				 * retrieves $this->entity instances
				 * @var unknown_type
				*/
				$this->entity->retrieveAndLink($query_conditions,$join_entities);
				$this->noDelete = $noDelete;
				$content = $this->display(EDIT,2,PRELOAD);
			}
		}
		else
		{
			/*If the data preload isn't requested we emit an add form*/
			$content = $this->display(ADD,1);
		}
		return $content;
	}

	/**
	 * This method is called to edit the selected instance of an entity with data inserted from user
	 */
	function edit($baseEntity=null) {

		if (!isset($_REQUEST['page'])) {
			$page = 0;
		} else {
			$page = $_REQUEST['page'];
		}
		/**
		 * retrieving all form elements in $_REQUEST
		 */

		foreach($this->elements as $k => $v) {
			if ($v->type == CHECKBOX) {
				$token = explode(":", $v->values[1]);
				if (!isset($_REQUEST[$token[1]])) {
					$_REQUEST[$token[1]] = '';
				}
			}else{
                foreach($_FILES as $key => $value){
                    //check if the current item of $_FILES is actually a file
                    if ($value['size'] != 0){
                            $_REQUEST[$key] = $value;
                    }
                }
            }
		}

		/*the entity that is related to the main form*/
		$baseEntity = $this->entity;
		/*selecting the one that was choosed from the report @see report()*/
		$where_conditions=array($this->entity->fields[0]->name=>$_REQUEST[$this->entity->fields[0]->name]);
		/*Trying to update*/
        if(Settings::getOperativeMode() == 'debug'){
            echo '<br>edit form<br>';
            var_dump($baseEntity->name);
            echo '<br>';
            var_dump(get_class($baseEntity));
            echo '<br> request';
            var_dump($_REQUEST);
            echo '<br>';
        }
		if (!$baseEntity->update($where_conditions, $_REQUEST)) {
			/* problems updating */
			echo Message::getInstance()->getMessage(MSG_ERROR_DATABASE_GENERIC)." (".basename(__FILE__).":".__LINE__.")";
		} else {
			foreach($this->triggeredForms as $formKey=>$form){
				$form->edit($baseEntity);
			}
		}
		if(!$this->debugmode)
			header("Location:{$_SERVER['SCRIPT_NAME']}?action=report");
		return $content;
	}

	/**
	 * This metod is called to insert a new instance of an entity in database
	 */
	function add($baseEntity=null) {

		if (!isset($_REQUEST['page'])) {
			$page = 0;
		} else {
			$page = $_REQUEST['page'];
		}

		/**
		 * retrieving all form elements in $_REQUEST
		 */

        /*
         * FIXME
         * creazione di una nuova baseEntity da legare ad una image non funziona correttamente.
         * funziona se l'immagine è da creare ma se l'immagine è già presente non funziona correttamente
         */
		foreach($this->elements as $k => $v) {
			if ($v->type == CHECKBOX) {
				$token = explode(":", $v->values[1]);
				if (!isset($_REQUEST[$token[1]])) {
					$_REQUEST[$token[1]] = '';
				}
			}
            else{
                foreach($_FILES as $key => $value){
                    $_REQUEST[$key] = $value;
                }
            }
		}
		/**
		 * the entity that is related to the main form
		 */
		$baseEntity = $this->entity;

		/**
		 * Saving
		 */
		if (! $baseEntity->save($_REQUEST) ) {
			/**
			 * problems saving
			 */
			echo Message::getInstance()->getMessage(MSG_ERROR_DATABASE_GENERIC)." (".basename(__FILE__).":".__LINE__.")";
		} else {
			/*passing the key value for the just inserted entity*/
			$_REQUEST[$this->entity->fields[0]->name] = $baseEntity->instances[0]->getKeyFieldValue();

			foreach($this->triggeredForms as $formKey=>$form)
			{
				$form->add($baseEntity);
			}
		}

        if (Settings::getOperativeMode() == "debug"){
            echo '<br />Form add debug';
            echo '<br>form var_dump $request<br>';
            var_dump($_REQUEST);
            echo '<br />$_file ';
            var_dump($_FILES);
        }

        if(!$this->debugmode)
			header("Location:{$_SERVER['SCRIPT_NAME']}?action=report");
		return $content;
	}

	/**
	 * This metod is called to delete an instance of an entity from database
	 */
	function delete()
	{
		$where_conditions = array($this->entity->fields[0]->name=>$_REQUEST['value']);
		$this->entity->delete($where_conditions);
		return $this->report(false);
	}

	function triggers($form) {

		if (version_compare(phpversion(),"5.0", "<")) {
			$relationName = "relation";
		} else {
			$relationName = "Relation";
		}

		$this->triggeredForm = $form;
		$this->triggeredForms[] = $form;

		$form->triggered = true;
	}

	function setLabel($operation, $label) {
		$this->labels[$operation] = $label;
	}

	function addSection($name, $text = "") {
		$factory=new SectionFieldFactory();
		$newField=$factory->create($this);
		$newField->name= $name;
		$newField->type = "section";
		$newField->text=$text;
		$this->elements[] = $newField;
	}
	
	function addTitleForm($name, $text = "") {
		$factory=new TitleFieldFactory();
		$newField=$factory->create($this);
		$newField->name= $name;
		$newField->type = "title";
		$newField->text=$text;
		$this->elements[] = $newField;
	}

	function addDescription($text) {
		$this->description = $text;
	}

	function addHidden($name, $value, $mainEntry=false)
    {
        $factory=new HiddenFieldFactory();
        $newField=$factory->create($this);
        $newField->name= $name;
        $newField->value = $value;
        $newField->type = "hidden";
        $newField->mainEntry= $mainEntry;

        $this->elements[] = $newField;
    }

	function addText($name, $label, $size = "20", $mandatory = "off", $maxlength = "", $mainEntry=false)
	{
		$factory=new TextFieldFactory();
		$newField=$factory->create($this);
		$newField->name= $name;
		$newField->type = "text";
		$newField->label = $label;
		$newField->size = $size;
		$newField->mandatory = $mandatory;
		$newField->maxlength = $maxlength;
		$newField->mainEntry= $mainEntry;
		$this->elements[] = $newField;
	}

	function addLink($name,$label,$size = "20",$mandatory = "off",$maxlength = "")
	{
		$factory=new LinkFieldFactory();
		$newField=$factory->create($this);
		$newField->name= $name;
		$newField->type = "link";
		$newField->label = $label;
		$newField->size = $size;
		$newField->mandatory = $mandatory;
		$newField->maxlength = $maxlength;
		$this->elements[] = $newField;
	}

	function addPassword($name,$label,$size = "20",$mandatory = "off",$maxlength = "")
	{
		$factory = new PasswordFieldFactory();
		$newField = $factory->create($this);
		$newField->name= $name;
		$newField->type = "password";
		$newField->label = $label;
		$newField->size = $size;
		$newField->mandatory = $mandatory;
		$newField->maxlength = $maxlength;
		$this->elements[] = $newField;
		$this->method = POST;
	}

	function addPosition($name, $label, $controlledField, $size = "8", $mandatory = "off",$entity=null)
	{
		if(!isset($entity))
		{
			$entity=$this->entity;
		}
		$form=new PositionForm($this->name, $entity,$this->method);
		$form->addPosition($name, $label, $controlledField,$size,$mandatory);
		$this->triggers($form);
	}

	function addHierarchicalPosition($name, $label,$mandatory="off",$entity=null)
	{
		if(!isset($entity))
		{
			$entity=$this->entity;
		}
		$form=new HierarchicalPositionForm($this->name, $this->entity,$this->method);
		$form->addHierarchicalPosition($name, $label,$mandatory,$entity);
		$this->triggers($form);
	}

	function addColor($name, $label, $preset = 'FFFFFF')
	{
		$factory=new ColorFieldFactory();
		$newField=$factory->create($this);
		$newField->name= $name;
		$newField->type = "color";
		$newField->label = $label;
		$newField->size ="7"; //#RRGGBB
		$newField->mandatory = MANDATORY;
		$newField->maxlength="7";
		$newField->preset=$preset;
		$this->elements[] = $newField;
	}

	function addRadio($name,$label)
	{
		$values = func_get_args();
		$factory=new RadioFieldFactory();
		$newField=$factory->create($this);
		$newField->name= $name;
		$newField->type = "radio";
		$newField->label = $label;
		$newField->values=$values;
		$this->elements[] = $newField;
	}

	function addDate($name, $label, $mandatory = "off")
	{
		$factory=new DateFieldFactory();
		$newField=$factory->create($this);
		$newField->name= $name;
		$newField->type = "date";
		$newField->label = $label;
		$newField->mandatory = $mandatory;
		$this->elements[] = $newField;
	}

	function addLongDate($name,$label, $mandatory = "off")
	{
		$factory=new LongDateFieldFactory();
		$newField=$factory->create($this);
		$newField->name= $name;
		$newField->type = LONGDATE;
		$newField->label = $label;
		$newField->mandatory = $mandatory;
		$this->elements[] = $newField;
	}

	function addFile($name,$label,$mandatory = "off")
	{
		$factory = new FileFieldFactory();
		$newField = $factory->create($this);
		$newField->name = $name;
		$newField->type = FILE;
		$newField->label = $label;
		$newField->mandatory = $mandatory;
		$this->elements[] = $newField;
		$this->method = "POST";
		$this->enctype = "enctype=\"multipart/form-data\"";
	}

	function addFileToFolder($name,$label,$mandatory = "off")
	{
		$factory=new File2FolderPositionFieldFactory();
		$newField=$factory->create($this);
		$newField->name= $name;
		$newField->type = FILE2FOLDER;
		$newField->label = $label;
		$newField->mandatory = $mandatory;
		$this->elements[] = $newField;
		$this->method = "POST";
		$this->enctype = "enctype=\"multipart/form-data\"";
	}

	function addImage($name,$label,$mandatory = "off")
	{
		$factory=new ImageFieldFactory();
		$newField=$factory->create($this);
		$newField->name= $name;
		$newField->type = "image";
		$newField->label = $label;
		$newField->mandatory = $mandatory;
		$newField->thumbSize="100";
		$this->elements[] = $newField;
		$this->method = "POST";
		$this->enctype = "enctype=\"multipart/form-data\"";
	}

	function addSelect($name, $label, $values, $mandatory = "no")
	{
		$factory=new SelectFieldFactory();
		$newField=$factory->create($this);
		$newField->name= $name;
		$newField->type = "select";
		$newField->label = $label;
		$newField->mandatory = $mandatory;
		$newField->values=$values;
		$this->elements[] = $newField;

	}

	function addYear($name, $label, $start = -15, $end = 1)
	{
		$year = date("Y");
		$values = "";
		for($y=$year+$start; $y<=$year+$end; $y++) {
			if ($y == $year) {
				$values .= Parser::first_comma($name,",")."${y}:{$y}:CHECKED";
		} else {
			$values .= Parser::first_comma($name,",")."{$y}:${y}";
	}
}
$this->addSelect($name, $label, $values);
}

function addSelectFromReference($entity, $name, $label , $mandatory = "no")
{
	$factory=new SelectFromReferenceFieldFactory();
	$newField=$factory->create($this);
	$newField->name= $name;
	$newField->type = "selectFromReference";
	$newField->label = $label;
	$newField->mandatory = $mandatory;
	$newField->entity=$entity;
	$this->elements[] = $newField;
}

function addSelectFromReference2($entity, $name, $label , $mandatory = "no", $disabled = null)
{
	$factory=new SelectFromReferenceFieldFactory();
	$newField=$factory->create($this);
	$newField->name= $name;
	$newField->type = "selectFromReference";
	$newField->label = $label;
	$newField->mandatory = $mandatory;
	$newField->entity=$entity;
	$this->elements[] = $newField;
}

function addSelfReferenceManager($name, $label , $position_field)
{
	$this->elements[] = array("name" => $name,
			"type" => "SelfReferenceManager",
			"label" => $label,
			"entity" => $this->entity,
			"position_field" => $position_field);
}

function addRadioFromReference($entity, $name, $label , $mandatory = "no") {
	$factory=new RadioFromReferenceFieldFactory();
	$newField=$factory->create($this);
	$newField->name= $name;
	$newField->type = RADIO_FROM_REFERENCE;
	$newField->label = $label;
	$newField->mandatory = $mandatory;
	$newField->entity=$entity;
	$this->elements[] = $newField;
}

function addCheck($name, $label)
{
	$values = func_get_args();
	$factory = new CheckBoxFieldFactory();
	$newField=$factory->create($this);
	$newField->type = RADIO_FROM_REFERENCE;
	$newField->label = $label;
	$newField->name = $name;

	$newField->values=$values;
	$this->elements[] = $newField;
}

function addTextarea($name,$label, $rows, $cols, $mandatory = "no")
{
	$factory = new TextAreaFieldFactory();
	$newField=$factory->create($this);
	$newField->type = "textarea";
	$newField->label = $label;
	$newField->rows=$rows;
	$newField->cols=$cols;
	$newField->mandatory=$mandatory;
	$this->elements[] = $newField;
	$this->method = POST;
}

function addEditor($name,$label, $rows, $cols, $mandatory = "no")
{
	$factory = new EditorFieldFactory();
	$newField=$factory->create($this);
	$newField->type = "editor";
	$newField->label = $label;
	$newField->name = $name;
	$newField->rows = $rows;
	$newField->cols = $cols;
	$newField->mandatory = $mandatory;
	$this->elements[] = $newField;
	$this->method = POST;
}

function addRelationManager2($name, $label, $orientation = RIGHT)
{
	if (get_class($this->entity) != "relation") {
		echo Message::getInstance()->getMessage(MSG_ERROR_RELATION_MANAGER)." (".basename(__FILE__).":".__LINE__.")";
		exit;
	}
	$this->elements[] = array("name" => $name,
			"label" => $label,
			"type" => "relation manager2",
			"orientation" => $orientation,
			"mandatory" => "no",
			"condition" => true
	);
}

function addRelationManager($name, $label, $orientation = RIGHT)
{

	$factory = new RelationManagerFieldFactory();
	$newField = $factory->create($this);
	$newField->type = "editor";
	$newField->label = $label;
	$newField->orientation = $orientation;
	$newField->mandatory = "no";
	$this->elements[] = $newField;
	$this->method = POST;
}

function setMandatory($name) {
	foreach($this->elements as $k =>$v) {
		if ($v['name'] == $name) {
			$this->elements[$k]['mandatory'] = "yes";
		}
	}

}

/**
 * REMOVING CANDIDATE
 */
function filterByRelation($relation, $mode = PRESENT) {
	$this->filterRelation['relation'] = $relation;
	$this->filterRelation['mode'] = $mode;
}
/**
 * REMOVING CANDIDATE
 */

function addFilter($name, $condition = true) {
	foreach($this->elements as $k => $value) {
		if ($value['name'] == $name) {
			$index = $k;
		}
	}
	$this->elements[$index]['condition'] = $condition;
}

function getElementByName($name) {
	$result = false;
	foreach($this->elements as $value) {
		if ($value->name == $name) {
			$result = $value;
		}
	}
	return $result;
}


function emitHTML($operation, $page, $preload) {

	$content = "";

	if (!$this->triggered) {
		$this->setContent("formName", $this->name);
		$this->setContent("formMethod", $this->method);
		$this->setContent("formPage", $page);

		switch($operation) {
			case ADD:
				$session_id_name = "S_".md5($this->entity->name);
				$session_id = md5(microtime());

				$_SESSION[$session_id_name] = $session_id;


				$actionHeader ='<input type="hidden" name="'.$session_id_name.'" value="'.$session_id.'" />';
				$actionHeader ='<input type="hidden" name="action" value="add" />';
				break;

			case EDIT:
				if (!isset($_REQUEST[$this->entity->fields[0]->name])) {
					$_REQUEST[$this->entity->fields[0]->name] =$_REQUEST["value"];
				}
				$actionHeader .= '<input type="hidden" name="'.$this->entity->fields[0]->name.'" value="'.$_REQUEST[$this->entity->fields[0]->name].'" />';
				$actionHeader .= '<input type="hidden" name="value" value="'.$_REQUEST[$this->entity->fields[0]->name].'"/>';
				if (!$this->moderationMode) {
					$actionHeader .= '<input type="hidden" name="action" value="edit" />';
				} else {
					$actionHeader .= '<input type="hidden" name="action" value="validate" />';
				}
				if ($this->entity->owner) {
					$actionHeader .= '<input type="hidden" name="username" value="'.$_REQUEST['username'].'" />';
				}
				break;
		}
			
		$this->setContent("actionHeader",$actionHeader);
	}

	/**
	 * Setting value for the hidden value input ( the id of the considered entity
	 */
	if(isset($_REQUEST["value"]))
	{
		$this->setContent("value",$_REQUEST["value"]);
	}
	else
		$this->setContent("value",0);


	/**
	 * Building and emitting widgets
	*/
	foreach($this->elements as $k => $v) {

		$content .= '';
			
		$content .= $v->build($preload);
			
		$content .= '';
	}

	/**
	 * Building and emitting html for triggered forms
	 */
	if ((count($this->triggeredForms)>0) and ($this->triggeredForms != "")) {
		foreach($this->triggeredForms as $k => $form) {
			$content .= $form->emitHTML($operation, $page, $preload);
		}
	}


	/**
	 * if this is not a triggered form emit the closing for the form
	 * including add and edit buttons
	 */
	if (!$this->triggered) {

		$closing .= '<div class="clear"></div><div class="closing">';

		switch ($operation) {
			case "add":

				if(isset($subcontent)) {
					$closing .= $subcontent;

					if (!isset($this->labels[EDIT])) {
						$label = Message::getInstance()->getMessage(BUTTON_EDIT);
					} else {
						$label = $this->labels[EDIT];
					}

					$closing .= '<input type="submit" value="'.$label.'" />';

				} else {
					if (!isset($this->labels[ADD])) {
						$label = Message::getInstance()->getMessage(BUTTON_ADD);
					} else {
						$label = $this->labels[ADD];
					}

					$closing .= '<input type="submit" value="'.$label.'" />';
                    $closing .= '<input type="reset" value="Azzera i campi" />';
				}
				break;
			case "edit":
				if (!$this->moderationMode) {
					$closing .= '<input type="submit" value="'.Message::getInstance()->getMessage(BUTTON_EDIT).'" />'; //onClick=\"submit_{$this->name}();\">";

					/* if (!$this->noDelete) {

						$this->noDelete = false;

						if (isset($this->labels[DELETE])) {
							$label = Message::getInstance()->getMessage(BUTTON_EDIT);
						} else {
							$label = $this->labels[DELETE];
						}

						$closing .= '<input class="ml10" type="button" value="'.Message::getInstance()->getMessage(BUTTON_DELETE).'" onClick="deleteThis('.$_REQUEST['value'].'");" />';
					} */

				} else {

					$closing .= '<input type="hidden" name="moderationResult" value="" />';
					$closing .= '<input type="button" value="'.Message::getInstance()->getMessage(BUTTON_ACCEPT).'" onClick="accept_'.$this->name.'();" />';
					$closing .= '<input type="button" value="'.Message::getInstance()->getMessage(BUTTON_REFUSE).'" onClick="refuse_'.$this->name.'();" />';
				}

				break;
		}

		$closing .= '</div>';
		$closing .= '</form>';
        $closing .= '<div class="clear"></div>';
		$closing .= '<!-- MAIN FORM END -->';
	}
	
	$this->setContent('closing', $closing);
	$this->setContent("content",$content);
	return $this->get();
}


function display($operation,$page,$preload = "") {

	$content= $this->emitHTML($operation, $page, $preload);

	return $content;
}
}