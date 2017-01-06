<?php

namespace MoneticoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    
	public $sVersion;	// Version du TPE - EPT Version (Ex : 3.0)
	public $sNumero;	// Numero du TPE - EPT Number (Ex : 1234567)
	public $sCodeSociete;	// Code Societe - Company code (Ex : companyname)
	public $sLangue;	// Langue - Language (Ex : FR, DE, EN, ..)
	public $sUrlOK;		// Url de retour OK - Return URL OK
	public $sUrlKO;		// Url de retour KO - Return URL KO
	public $sUrlPaiement;	// Url du serveur de paiement - Payment Server URL (Ex : https://p.monetico-services.com/paiement.cgi)
	public $sDevise;
	
	private $_sCle;
	
	function init(){
		$params = $this->getParameter('monetico.params');
		$this->sVersion = $params['monetico.version'];	// Version du TPE - EPT Version (Ex : 3.0)
		$this->sNumero = $params['monetico.eptnumber'];	// Numero du TPE - EPT Number (Ex : 1234567)
		$this->sCodeSociete = $params['monetico.companycode'];	// Code Societe - Company code (Ex : companyname)
		$this->sLangue = $params['monetico.locale'];	// Langue - Language (Ex : FR, DE, EN, ..)
		$this->sUrlOK = 'http://'.$this->getParameter('database_host').$this->generateUrl('monetico_ok');		// Url de retour OK - Return URL OK
		$this->sUrlKO = 'http://'.$this->getParameter('database_host').$this->generateUrl('monetico_ko');		// Url de retour KO - Return URL KO
		$this->sUrlPaiement = $params['monetico.urlserver'].$params['monetico.urlpayment'];	// Url du serveur de paiement - Payment Server URL (Ex : https://p.monetico-services.com/paiement.cgi)
		$this->sDevise = $params['monetico.devise'];
		
		$this->_sCle = $params['monetico.key'];		// La cl� - The Key
	}
	
	public function indexAction()
    {
		$this->init();
		return $this->render('MoneticoBundle:Default:index.html.twig', array('sVersion' => $this->getParameter('monetico.params')));
    }
	
	public function getCle()
	{
		return $this->_sCle;
	}
	
	public function goAction()
	{
		$this->init();
		
		$pannier = array(
			'produits' => array(
				
			), 
			'prixT' => 1.01,
		);
		
		$oHmac = new MoneticoPaiement_Hmac($this);
		$sMontant = $pannier['prixT'];
		$sDate = date("d/m/Y:H:i:s");
		$sReference = "ref".date('His');
		$sTexteLibre = "";
		$sEmail = $this -> getParameter("monetico.params")["monetico.email"];
		$sOptions = null;
		
		$phase1go_fields = sprintf("%s*%s*%s%s*%s*%s*%s*%s*%s*%s*%s",
												$this->sNumero,
                                                $sDate,
                                                $sMontant,
                                                $this->sDevise,
                                                $sReference,
                                                $sTexteLibre,
                                                $this->sVersion,
                                                $this->sLangue,
                                                $this->sCodeSociete, 
                                                $sEmail,
                                                $sOptions);

		
		$sMac = $oHmac -> computeHmac($phase1go_fields); 
		
		return $this->render('MoneticoBundle:Default:phaseGo.html.twig', 
			array(
				'pannier' => $pannier,
				'sMac' => $sMac,
				'sTexteLibre' => $sTexteLibre,
				'sDate' => $sDate,
				'sEmail' => $sEmail,
				'sReference' => $sReference,
				'sMontant' => $sMontant,
				'sDevise'  => $this -> sDevise,
				'urlPayment' => $this->sUrlPaiement,
				'sVersion' => $this -> sVersion,	// Version du TPE - EPT Version (Ex : 3.0)
				'sNumero' => $this->sNumero,	// Numero du TPE - EPT Number (Ex : 1234567)
				'sCodeSociete' => $this -> sCodeSociete,	// Code Societe - Company code (Ex : companyname)
				'sLangue' => $this -> sLangue,	// Langue - Language (Ex : FR, DE, EN, ..)
				'sUrlOK' => $this -> sUrlOK,		// Url de retour OK - Return URL OK
				'sUrlKO' => $this -> sUrlKO,		// Url de retour KO - Return URL KO
			)
		);
	}
	
	public function backOkAction()
	{
		$this -> init();
		
		$MoneticoPaiement_bruteVars = getMethode();
		$oHmac = new MoneticoPaiement_Hmac($this);
		
		$phase2back_fields = sprintf("%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*", $this->sNumero,
                        $MoneticoPaiement_bruteVars["date"],
                        $MoneticoPaiement_bruteVars['montant'],
                        $MoneticoPaiement_bruteVars['reference'],
                        $MoneticoPaiement_bruteVars['texte-libre'],
                        $this->sVersion,
                        $MoneticoPaiement_bruteVars['code-retour'],
                        $MoneticoPaiement_bruteVars['cvx'],
                        $MoneticoPaiement_bruteVars['vld'],
                        $MoneticoPaiement_bruteVars['brand'],
                        $MoneticoPaiement_bruteVars['status3ds'],
                        $MoneticoPaiement_bruteVars['numauto'],
                        $MoneticoPaiement_bruteVars['motifrefus'],
                        $MoneticoPaiement_bruteVars['originecb'],
                        $MoneticoPaiement_bruteVars['bincb'],
                        $MoneticoPaiement_bruteVars['hpancb'],
                        $MoneticoPaiement_bruteVars['ipclient'],
                        $MoneticoPaiement_bruteVars['originetr'],
                        $MoneticoPaiement_bruteVars['veres'],
                        $MoneticoPaiement_bruteVars['pares']
					);
					
		if ($oHmac->computeHmac($phase2back_fields) == strtolower($MoneticoPaiement_bruteVars['MAC'])){
			
			switch($MoneticoPaiement_bruteVars['code-retour']) {

				case "Annulation" :
				
					return $this->backKoAction();
					
					break;

				case "payetest":
					// Paiement accepté sur le serveur de test
					// Insérez votre code ici (envoi d'email / mise à jour base de données)
					//
					// Payment has been accepted on the test server
					// put your code here (email sending / Database update)
					break;

				case "paiement":
					// Paiement accepté sur le serveur de production
					// Insérez votre code ici (envoi d'email / mise à jour base de données)
					//
					// Payment has been accepted on the productive server
					// put your code here (email sending / Database update)
					break;

			}
		}
		
		return $this->render('MoneticoBundle:Default:phaseBack.html.twig', array());
	}
	
	public function backKoAction()
	{
		return $this->render('MoneticoBundle:Default:phaseBackKo.html.twig');
	}
}

/*****************************************************************************
*
* Classe / Class : MoneticoPaiement_Hmac
*
*****************************************************************************/

class MoneticoPaiement_Hmac {

	private $_sUsableKey;	// La cl� du TPE en format op�rationnel / The usable TPE key

	// ----------------------------------------------------------------------------
	//
	// Constructeur / Constructor
	//
	// ----------------------------------------------------------------------------

	function __construct($oEpt) {
		
		$this->_sUsableKey = $this->_getUsableKey($oEpt);
	}

	// ----------------------------------------------------------------------------
	//
	// Fonction / Function : _getUsableKey
	//
	// Renvoie la cl� dans un format utilisable par la certification hmac
	// Return the key to be used in the hmac function
	//
	// ----------------------------------------------------------------------------

	private function _getUsableKey($oEpt){

		$hexStrKey  = substr($oEpt->getCle(), 0, 38);
		$hexFinal   = "" . substr($oEpt->getCle(), 38, 2) . "00";
    
		$cca0=ord($hexFinal); 

		if ($cca0>70 && $cca0<97) 
			$hexStrKey .= chr($cca0-23) . substr($hexFinal, 1, 1);
		else { 
			if (substr($hexFinal, 1, 1)=="M") 
				$hexStrKey .= substr($hexFinal, 0, 1) . "0"; 
			else 
				$hexStrKey .= substr($hexFinal, 0, 2);
		}


		return pack("H*", $hexStrKey);
	}

	// ----------------------------------------------------------------------------
	//
	// Fonction / Function : computeHmac
	//
	// Renvoie le sceau HMAC d'une chaine de donn�es
	// Return the HMAC for a data string
	//
	// ----------------------------------------------------------------------------

	public function computeHmac($sData) {

		return strtolower(hash_hmac("sha1", $sData, $this->_sUsableKey));

		// If you don't have PHP 5 >= 5.1.2 and PECL hash >= 1.1 
		// you may use the hmac_sha1 function defined below
		//return strtolower($this->hmac_sha1($this->_sUsableKey, $sData));
	}

	// ----------------------------------------------------------------------------
	//
	// Fonction / Function : hmac_sha1
	//
	// RFC 2104 HMAC implementation for PHP >= 4.3.0 - Creates a SHA1 HMAC.
	// Eliminates the need to install mhash to compute a HMAC
	// Adjusted from the md5 version by Lance Rushing .
	//
	// Impl�mentation RFC 2104 HMAC pour PHP >= 4.3.0 - Cr�ation d'un SHA1 HMAC.
	// Elimine l'installation de mhash pour le calcul d'un HMAC
	// Adapt�e de la version MD5 de Lance Rushing.
	//
	// ----------------------------------------------------------------------------

	public function hmac_sha1 ($key, $data) {
		
		$length = 64; // block length for SHA1
		if (strlen($key) > $length) { $key = pack("H*",sha1($key)); }
		$key  = str_pad($key, $length, chr(0x00));
		$ipad = str_pad('', $length, chr(0x36));
		$opad = str_pad('', $length, chr(0x5c));
		$k_ipad = $key ^ $ipad ;
		$k_opad = $key ^ $opad;

		return sha1($k_opad  . pack("H*",sha1($k_ipad . $data)));
	}

}

// ----------------------------------------------------------------------------
// function getMethode 
//
// IN: 
// OUT: Donn�es soumises par GET ou POST / Data sent by GET or POST
// description: Renvoie le tableau des donn�es / Send back the data array
// ----------------------------------------------------------------------------

function getMethode()
{
    if ($_SERVER["REQUEST_METHOD"] == "GET")  
        return $_GET; 

    if ($_SERVER["REQUEST_METHOD"] == "POST")
	return $_POST;

    die ('Invalid REQUEST_METHOD (not GET, not POST).');
}

// ----------------------------------------------------------------------------
// function HtmlEncode
//
// IN:  chaine a encoder / String to encode
// OUT: Chaine encod�e / Encoded string
//
// Description: Encode special characters under HTML format
//                           ********************
//              Encodage des caract�res sp�ciaux au format HTML
// ----------------------------------------------------------------------------
function HtmlEncode ($data)
{
    $SAFE_OUT_CHARS = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890._-";
    $encoded_data = "";
    $result = "";
    for ($i=0; $i<strlen($data); $i++)
    {
        if (strchr($SAFE_OUT_CHARS, $data{$i})) {
            $result .= $data{$i};
        }
        else if (($var = bin2hex(substr($data,$i,1))) <= "7F"){
            $result .= "&#x" . $var . ";";
        }
        else
            $result .= $data{$i};
            
    }
    return $result;
}
