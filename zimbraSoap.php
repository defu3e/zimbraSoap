<?php
// TESTED ON Zimbra 8.8.15

define ("ZM_ADM_LOGIN","test_login");		 // Zimbra admin login
define ("ZM_ADM_PASS", "test_password"); // Zimbra admin password

define ("WSDL", 	   "https://mail.test.ru/service/wsdl/ZimbraAdminService.wsdl");
define ("SOAP_URL",	 "https://mail.test.ru/service/admin/soap");

define ("EVERYONE_DL", "everyone@test.ru"); // delivery list 1 

class soap extends SoapClient {
	public function __construct($wsdl, $opt)
    {
        parent::__construct($wsdl, $opt);
    }
	
	function auth ($login, $pass) 
	{
		$ns 	= 'urn:zimbra';
		$params = 
		[
			'account'	=> $login,  
			'password'	=> $pass
		];
		
		try 
		{
			$auth 	 = $this->AuthRequest($params);
			$headers = new SoapHeader($ns, 'AuthToken', $auth->authToken);
			
			parent::__setSoapHeaders($headers);
		} 
		catch (Exception $e)
		{
			throw new Exception("Error. Response: " . parent::__getLastResponse()); 
		}
	}
	
	function getAccountID ($email) 
	{
		$params = 
		[
			'account'=> 
			[
				'_'  => $email, 
				'by' => 'name' 
			]
		];
		try 
		{
			$res = parent::__soapCall("GetAccountInfoRequest", ['parameters' => $params]);
		}
		catch (SoapFault $e) 
		{	
			throw new Exception("Error. Response: " . parent::__getLastResponse()); 
			return 0;
		}
				
		return $res->a[0]->_;
	}
		
	function postXML ($method, $xml) {
		$params = new SoapVar($xml, XSD_ANYXML);
		try 
		{
			$res = parent::__soapCall($method, ['parameters' => $params]);
		}
		catch (\Exception $e)
		{
		   throw new Exception("Error. Response: " . parent::__getLastResponse()); 
		}
		return $res;
	}
	
	function getDL_id ($name) {
		$params = 
		[
			'dl'=> 
			[
				'_' => $name,
				'by'=>'name'
			]
		];
		
		$res = parent::__soapCall("GetDistributionListRequest", ['parameters' => $params]);
		return $res->dl->id;
	}
	
	function addToDL ($email, $dlName) {
		$dl_id  = $this->getDL_id($dlName);
		$params = 
		[
			'id'  => $dl_id,
			'dlm' => $email
		];
		
		parent::__soapCall("AddDistributionListMemberRequest", ['parameters' => $params]);
	}	
}

/*************************************************************************/

/** example of use **/

$opt = 
[
	'location' 		=> SOAP_URL,
	'uri' 	   		=> WSDL,
	'trace'	   		=> 1, 
	'cache_wsdl'	=> WSDL_CACHE_NONE 
];
		
$soap 	= new soap (WSDL, $opt);

$soap->auth(ZM_ADM_LOGIN, ZM_ADM_PASS);

// your data 
$mode     = $_POST['mode'];
$email    = 'test@test.ru';

// create new account
if ($mode == 'create') {
  
  $password = 'test_password';
  $company  = 'test';
	
	$xml = 	"<CreateAccountRequest xmlns=\"urn:zimbraAdmin\" name=\"$email\" password=\"$password\"> 
				  <a n=\"company\">$company</a> 
			    </CreateAccountRequest>"; 
	
	$soap->postXML ("CreateAccountRequest", $xml);
	
  // add account to distribution list
	$soap->addToDL ($email, EVERYONE_DL);
}

if ($mode == 'close') {

	$accID = $soap->getAccountID($email);

	if ($accID) {
		$xml = 	"<ModifyAccountRequest xmlns=\"urn:zimbraAdmin\">
					<id>$accID</id>
					<a n=\"zimbraAccountStatus\">closed</a>
					<a n=\"zimbraHideInGal\">TRUE</a>
				 </ModifyAccountRequest>";
		$soap->postXML ("ModifyAccountRequest", $xml);
	}
}

if ($mode == 'change_pw') {
	$new_pw = 'test_new_pw';
	
	$accID = $soap->getAccountID($email);
	
	if ($accID) {
		$xml = "<SetPasswordRequest xmlns=\"urn:zimbraAdmin\" id=\"{$accID}\" newPassword=\"{$new_pw}\" />";
		
		$soap->postXML ("ModifyAccountRequest", $xml);
	} 
}

?>
