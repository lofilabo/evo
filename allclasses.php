<?php


/*
Let's show off with a design pattern!
(Actually, in this little demo, it's almost valueless.)
The Singleton class allows one and only one instance of itself.
If there is no instance, it makes one.  If there is already one,
it returns a reference to it.  In the case of database connections,
it's possible for them to pile up quickly, which is problematic if
our DB server is limited to 10 connections, or if we have to pay
commercial licences per-connection....
*/

Class DBStart{

	static $conn = null;
	private static $database_connection_info = null;
    private static $instances = [];

    protected function __clone() { }//these abominations should die silently and alone.

    public function __wakeup(){
    	throw new \Exception("BISMILACH, NO!");//we shouldn't be able to recover from a string.
    }

	function __construct($database_connection_info) {
		$this->database_connection_info = $database_connection_info;
	}

    public static function getInstance($database_connection_info): DBStart{
        $cls = static::class;
    	/*
		The actual innards of the Singleton.
		If and only if there are no instances already, we make a new one.
		If one exists (because something else made it), we return that.
    	*/
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static($database_connection_info);
        }
        return self::$instances[$cls];
    }

	public function conn(){
		/*
		Is this the nicest and most canonical way to let the 
		connection-handler know about our DB details?  Almost 
		certainly not, but lacking an application-wide .env
		or S{ENV}, we'll do this for now.
		*/
		$conn = new mysqli(	$this->database_connection_info['host'], 
							$this->database_connection_info['username'], 
							$this->database_connection_info['password'], 
							$this->database_connection_info['database']  
		);
		return $conn;		
	}
}

Abstract Class SuperFormMaker{

	/*
	Thin wrapper around the connector class to get an instance 
	and then make a connection object from it.
	Abstract so that it can't be invoked, only inherited-from.
	Future developers only have to know that if they Extend this
	class, they'll get a $conn to work with.
	*/

	protected $conn = null;

	function __construct($database_connection_info) {
			$s1 = DBStart::getInstance($database_connection_info);
			$this->conn = $s1->conn();
	}	
}

Class FormMaker extends SuperFormMaker{

	/*
	A mere 100 lines in, and our program starts to do something.
	Ain't object-orientation great?
	(sing along with me: 'that's three lines of NodeJS...')
	*/

	public $allCols = array();

	function get_table_metadata($tableName){
		

		$sql = "SHOW COLUMNS FROM " . $tableName;
		$stmt = $this->conn->prepare($sql);
		
		$stmt->execute();
		$result = $stmt->get_result();
		if($result->num_rows === 0) return(array());
		
		/*
		Regrettably, nothing about mySQLi gets around PHP's tedious habit of returning 
		a cusror / file pointer to an internal data structure, which is a happy memory of no-one 
		of the wonderful world of Win16 ODBC drivers and star networks made from wet string.  
		Seriously, folks?  PHP, you've come so far!  Can't we have objects returned from our
		databases please?
		*/
		while($row = mysqli_fetch_array($result,MYSQLI_NUM)) {
			$this->allCols[] = $row;
		}
		/*
		CAN HAS array with teh datas in him?
		*/
		$stmt->close();
		return($this->allCols);
	}
}


Class FormStuffer extends SuperFormMaker{

	function grabFromForm($db){
		/*
		These sessions are set by the Page which draws the form.
		To validate, the unique ID stored in the 
		session and the hidden field on the form must match.

		The tablename is also set in the page which invokes the form - 
		it determines which table is used to derive the form.
		*/
		$tableName = $_SESSION['tablename'];
		$checkVtoken = $_SESSION['vtoken'];
		$postedVtoken = $_POST['vtoken'];


		if($checkVtoken != $postedVtoken ){
			return("Bad VToken.  Refresh + fill in the form again.");
		}

		$fm = new FormMaker($db);
		$tableinfo = $fm->get_table_metadata($tableName);

		$intermediateData = array();

		/*
		The reason we're actually here at all!
		Get the data from the submitted form.

		Rather disappointingly, it turns out to only take one line.

		Remember that the input NAMEs in the form are derived from
		the database field names, so if we make an associative array
		whereby
		(database-field-name = form-input-name) => (form-input-value = future-database-value)
		then we have only a very simple data structure to work with when it comes to 
		bodging data into the table.
		*/			
		foreach($tableinfo as $tableinfomembers){
			$intermediateData[$tableinfomembers[0]] = $_POST[ $tableinfomembers[0] ];
		}
		
		return $this->stuff_into_table($tableName, $intermediateData);
	}


	function stuff_into_table($tablename,$intermediateData){

		$allkeys = array();
		$allvals = array();
		/*
		we start off with an associative array of keys and values:
		keys ==HTML form names == MySQL table field names
		and
		the values we want to put into the table.
		Loop through this array, separating out the values into a 1-d
		array of their own.  We need to do this because we'll be using the
		...$array operator below to emulate the $var1,$var2,$var3 behavious that
		bind_param wants.  In this case, $array needs to be 1-d and simple.
		*/
		foreach($intermediateData as $intermediateKey=>$intermediateValue){
			/*we also ignore the ID field.*/
			if($intermediateKey != 'id'){
				$allkeys[] = $intermediateKey;
				/*
				The logic here is a bit sketchy, but it should work...
				Empty fields and unselected pulldowns return an empty string.
				The only time we ever get a null is from an unchecked checkbox.
				Unchecked checkboxes do not even appear in the POST request (or even the 
				GET request), but their corresponding database table column is
				a non-null tinyint, meaning that it must get SOMEthing.

				(There are two other cases that we do not need to handle here:
				Pulldowns map to not-null fields (so there will never be an empty
				Value), and Radio Button Groups map to null-allowed fields. (so if it's
				empty, that's OK.))

				So we can be pretty certain that the only time we encounter
				a null (not an ''), destined for a field which does not allow a `null`,
				the only interpretation is that it's an Un-Checked checkbox.

				Because this is the only such case, we are safe to always add in a "0"  
				in place of the null.  MySQLi will take care of the
				typecasting, making sure that a 0 gets passed into the DB field,
				which in turn demands an int between 0 and f (a '' is no good)
				*/
				if(null !== $intermediateValue){
					$allvals[] = $intermediateValue;
				}else{
					$allvals[] = "0";
				}
			}
		}
		//var_dump($allvals);
		$allvalscount = sizeof($allvals);
		/*
		make a string of question marks and commas, a la: ?,?,?,?
		the string has to be one-less than the array, and then have a final qm added
		to it so that the string does not illegally end with a comma.
		*/
		$questionmarks= str_repeat('?,', $allvalscount - 1) . '?'; 
		/*
		same thing but simpler - just a string of s's
		Note that mySQLi will eat strings and mutatetypecastiblob them into 
		mySQL types itself.  This turns out to be even better type assertion
		than we can probably do by ourselves.
		*/
		$esses = str_repeat('s', $allvalscount);
		
		/*
		'Ello 'ello 'ello' what's all this, then?
		Are we interpolating directly into an SQL string and thus squandering all the
		security advantages of Pre-Preparation...?
		Not quite.  
		Table names do not seem to take well to being ?ised into
		strings.  But, if we make sure that the $tablename value is never set 
		by user input, there is no risk.
		*/
		$sql = "INSERT INTO ". $tablename ." VALUES(null,$questionmarks)";
		/*
		WHAT'S THIS null??
		we want to use the SQL expression without a list of fields - we are only ever filling all
		the fillable fields, many of which have non-null properties anyway.
		But - the first field has to be an autoincrement id, which we can neither set nor ignore.
		So - we assign a value of null to it.  This satisfies the SQL parser, but the SQL engine
		then determines that the value can't be null, and autoincrements it.
		*/

		/*
		CITE: Smith & Shifflet 2020.(para)
		
		The business of pre-preparation basically:
			1. Inserts a blank record.
			2. Recovers it.
			3. Updates it with a new value.
			4. Never parses this value or tries to execute it.
		So the old "type 'drop all' into a text area" SQLi trick will literally just 
		save 'drop all' into the database field. 

		With that in mind, our approach to data security is going to be pretty much 'use mySQLi'.
		We are not doing typechecking by hand for the following reasons:
		1. All HTML form data is a string (or binary).  We can't have an HTML form give us 
		an 'int' or a 'string' (and as JSP users know, there is no guarantee that your Language's
		types are the same as your Database engine's types!)
		2. There is absolutely no point in checking if a certain value can or cannot be
		squished into an integer or a bool.  MySQLi will accept strings (s-type inserts)
		and squish them according to the rules of MySQL.  Adding a bit of duct tape over the
		three-bolt truss plate does not make a better joint.  It makes it ugly and hard to see, and 
		leaves adhesive residue all over the nice steelwork.
		3. We might actually want store real, executable SQL or JS into a database table.  In this case,
		we don't want to mangle it.
		4. Modern practice says 'Filter on Entry; Encode on Exit, and never use the word "sanitisation"'.
		We want to preserve as far as we can what the user put in.  If that is HTML or JavaScript,
		we encode that at the time that we display it on screen.  mySQLi allows us to store
		and recover the values without the risk of executing them.
		*/
		$stmt = $this->conn->prepare($sql);				
		/*
		and the ... thingy?
		that's how to unpack an array into a set of variables.
		*/
		$stmt->bind_param($esses, ...$allvals );
		$rez = $stmt->execute();
		
		return $rez;
	}
}



class FormBuilder{

	function acceptRowsAndMake($allrows){
		
		/*
		What's going on here?
		In a cursory attempt to stop people hitting the endpoint
		with a CURL or Postman instance, we make a validation-token
		and simultaneously store to both a session and a form field.

		We check these again at the other end of the process, and make sure
		that they match.
		            /-----In Browser-----}HTTP{----\____CHECK SAME
		V-Token----|                                |   WHEN REQUEST
		            \-----On Server------}CALL{----/    RECIEVED.

		In this case, we are NOT allowing a user to keep the same session across
		many requests.  If we did, someone could View the source of the form and
		discover the token, which could then be used to validate a CURL etc etc.

		Of course, if the user abandons their browser with a form on-screen,
		that's still a problem....

		In test mode, we'll show the token on screen.  The user can then copy it
		to use CURL to test, or change it to make sure that the validation works.
		*/
		$vtoken = date("Y-m-d--H-i-s---") . uniqid();
		$_SESSION['vtoken'] = $vtoken;


		/*
		We are following the ancient and ignoble tradition of building up a string
		of HTML as we go along, which means that Templating becomes a nightmare.

		In production, we'd make sure that each form element had a class as well as
		an ID, meaning that the folks wrangling the CSS would have a fighting chance
		of making sure that it looks nice.
		*/
		$allhtml = "";
		$allhtml = $allhtml . "<form method='post' action='end.php'>";
		$allhtml = $allhtml . "FOR TESTING ONLY.  Copy the token for a direct call, or break it to test validation. Name = vtoken<br/>";
		$allhtml = $allhtml . "<input type='text' name='vtoken' value='".$_SESSION['vtoken']."' size=100/><br/><br/>";

		foreach($allrows as $row){
			/*
			of course, there is a way to get MySQLi to return an associative array
			with named values.  But these never change. Entry 0 is always the name; 
			Entry 1 is always going to be the type, Entry 2 is always YES or NO for 
			'null allowed' or 'null not allowed'
			*/
			$name = $row[0]; 
			$basictype = $row[1];
			$nullAllowed = $row[2];
			//var_dump(strpos( $basictype , 'char' ));			
			$rowtype = $row[1];
			/*
			If the field is an ennumerated type, the possible values are added to the 
			type name field like this: enum, ('acceptablevalue1', 'acceptablevalue2', 'acceptablevalue3')
			we'll use a little regexp (slow...and maybe an instr would have done) to check if the 
			type string contains 'enum'; if it does, we use the remainder of the type field
			to derive an array of acceptable values. 
			*/
			preg_match('/enum\((.*)\)$/', $rowtype, $grepmatch);
			/*
			simple data types will yield 1 acceptable value.
			THEY DO NOT YIELD 0 acceptable values.
			*/
			$possibleenumvalues = explode(',', $grepmatch[1]);
			$numberofpossibleenumvalues = sizeof($possibleenumvalues);
			
			if($name != 'id'){
				if($numberofpossibleenumvalues > 1){
					//It's an Enum
					$thisHTML = $this->handleEnum($name, $possibleenumvalues, $nullAllowed);
					$allhtml = $allhtml . $thisHTML;
				}else{
					//it's a simple data type.
					//switch on the data type to make the correct kind of form field.
									
					if(  strpos( $basictype , 'char' ) !== false ){
						$thisHTML = $this->makeTextField($name, $nullAllowed);
						$allhtml = $allhtml . $thisHTML;
					}elseif(  strpos( $basictype , 'tinyint' ) !== false ){
						$thisHTML = $this->makeChkboxField($name, $nullAllowed);
						$allhtml = $allhtml . $thisHTML;
					}elseif(  strpos( $basictype , 'int' ) !== false ){
						$thisHTML = $this->makeNumberField($name, $nullAllowed);
						$allhtml = $allhtml . $thisHTML;
					}elseif(  strpos( $basictype , 'text' ) !== false ){
						$thisHTML = $this->makeBigTextField($name, $nullAllowed);
						$allhtml = $allhtml . $thisHTML;
					}else{
						//something else.  Forget.
					}
				}
			}
			
		}
		$allhtml = $allhtml . "</br><input type='submit'></form>";
		return($allhtml);
	}

	function makeTextField($name, $nullAllowed){

		$html = "";
		$html = $html . $name . " : <input type='text' name='".$name."' id='".$name."' size='40'";
		
		if($nullAllowed == 'NO'){
			$html = $html . " required ";
		}
		
		$html = $html . "/>";

		if($nullAllowed == 'NO'){
			$html = $html . "[REQ]";
		}

		$html = $html . "</br>";
		//echo($html);
		return $html;
	}

	function makeNumberField($name, $nullAllowed){
		
		$html = "";
		$html = $html . $name . " : <input type='number' name='".$name."' id='".$name."' size='10'";

		if($nullAllowed == 'NO'){
			$html = $html . " required ";
		}

		$html = $html . "/>";
		
		if($nullAllowed == 'NO'){
			$html = $html . "[REQ]";
		}

		$html = $html . "</br>";		
		return $html;
		
	}

	function makeChkboxField($name, $nullAllowed){
		/*
		What is the value of this field if not selected?
		Answer? There isn't even a field!
		If we don't select it, the FORM doesn't have any record of it.
		Which means a POST request against its NAME returns NULL.

		This is the only instance I can think of, of an object which exists
		in HTML not yielding a POST or GET value at all, and being just absent.
		*/
		$html = "";
		$html = $html . $name . " : ";
		$html = $html . "<input type='checkbox' id='".$name."' name='".$name."' value='1' />";
		$html = $html . "<br/>";
		return $html;
		
	}

	function makeBigTextField($name, $nullAllowed){

		$html = "";
		$html = $html . $name . "<br/><textarea rows='8' cols='40' name='".$name."' id='".$name."' size='40'";
		$html = $html . "";
		$html = $html . "></textarea><br/>";
		return $html;
		
	}

	function handleEnum($name, $possibleenumvalues, $nullAllowed){
		/*
		Fine, so we know it's an Enum.
		But that can be 2 possible HTML things.  We'll make a sketchy rule:
		1. Null Not Allowed - pulldown
		2. Null allowed - radio button group.
		*/

			if ($nullAllowed == "YES"){
				return $this->handleRadiobuttons($name, $possibleenumvalues);
			}else{
				return $this->handlePulldown($name, $possibleenumvalues);
			}

		
	}

	function handlePulldown($name, $possibleenumvalues){
		$html = $name . " (select) ";
		$html = $html . "<select name='".$name."' id='".$name."'>";
		/*
		WAIT!!  Isn't it convention to start all pulldown selectors with a 
		'nothing' option?  Not in this case!!
		Selects are derived as a consequence of not-null Enum fields 
		and so (i) the selected option can't be empty and (ii) mustn't be 
		a made-up value like 'none' because this would not be one of the
		enumerated values.
		*/
		//$html = $html . "<option value='null'>--</option>";
		foreach($possibleenumvalues as $possibleenumvalue){
			$html = $html . "<option value=".$possibleenumvalue.">".$possibleenumvalue."</option>";
		}
		$html = $html . "</select></br>";
		return $html;		
	}

	function handleRadiobuttons($name, $possibleenumvalues){
		/*
		WAIT!! Isn't it convention to preselect a radio button (following
		the original metaphor)?  Not in this case!
		Radiobutton Groups are derived as a consequence of a null-allowed Enum
		field, so we are not intended to Force the user to a decision.  But the user
		is not obliged to select any radio button either.

		It's not our fault that radiobutton groups can't be set-to-nothing
		once one button is pushed, unlike actual pushbuttons on old car radios, where 
		you could gently push any other two buttons at once to deselect the whole group.

		I guess that's why they call it 'digital radio'.
		*/
		$html = $name . " <br/> ";
		foreach($possibleenumvalues as $possibleenumvalue){
			$html = $html .  "<input type='radio' id='".$name."' name='".$name."' value=".$possibleenumvalue.">". $possibleenumvalue ."<br/>";
		}
		$html = $html . "</br>";
		return $html;
	}

}


?>