<?php
/*
     Copyright 2013 Stéphane Depierrepont (aka Toorop) toorop@toorop.fr

    Licensed under the Apache License, Version 2.0 (the "License"); you may not
    use this file except in compliance with the License. You may obtain a copy of
    the License at

    http://www.apache.org/licenses/LICENSE-2.0

    Unless required by applicable law or agreed to in writing, software
    distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
    WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
    License for the specific language governing permissions and limitations under
    the License.

 */


/*
 * Usage :
 *  - Edit config section and run :
 *      php OVHBilling.php
 */



/*
 * Config
 */

define('OVH_NIC','You OVH nic');
define('OVH_PASSWD','Your OVH password');
define('DEST_PATH','/path/where/you/want/to/download/bills');
define('DATE_START','2012-01-01 00:00:00');
define('DATE_STOP','2012-12-31 23:59:59');


#######################################################
#   DO NOT EDIT AFTER
#######################################################
date_default_timezone_set('Europe/Paris'); // keeps this setting, OVH is located in France.


class OVHBilling {
    private $_fact_url_tpl="https://www.ovh.com/cgi-bin/order/facture.pdf?reference=[%fact_ref%]&passwd=[%fact_passwd%]";
    private $_ovh_api=false;
    private $_ovh_session=false;
    private $_date_start=false;
    private $_date_stop=false;


    function __construct($start=false,$stop=false){
        if($start===false)$start='0-0-0';
        if($stop===false)$stop='2012-12-21 21:12:21';
        $this->_date_start= new dateTime($start);
        $this->_date_stop= new dateTime($stop);
        $this->_initAPI();
    }

    function __destruct(){
        $this->_destAPI();
    }

    /**
     * SOAP init
     */
    private function _initAPI(){
        print 'Initialisation SOAP en cours...';
        $this->_ovh_api = new SoapClient("https://www.ovh.com/soapi/soapi-re-1.13.wsdl");
        $this->_ovh_session = $this->_ovh_api->login(OVH_NIC, OVH_PASSWD,"fr", false);
        print "OK\n";
    }


    private function _destAPI(){
        $this->_ovh_api->logout($this->_ovh_session);
        print "\nWorks done, see you next year...\n";
    }

    /**
     * Main
     */
    public function run($start=false,$stop=false){
        $invoices=false;
        $invoices=$this->_ovh_api->billingInvoiceList($this->_ovh_session);
        $good_ref=array();
        foreach ($invoices as $i){
            $d=new dateTime($i->date);
            if($d>=$this->_date_start && $d<=$this->_date_stop)$good_ref[]=$i->billnum;
        }
        unset($invoices);

        /*Get bills */
        print "\nDownload in progress...";
        foreach ($good_ref as $ref){
            $r=$this->_ovh_api->billingInvoiceInfo($this->_ovh_session, $ref, "", "");
            $fact_passwd=$r->password;
            $fact_date=$r->payment->paymentdate;
            $save_as=DEST_PATH.'/'.$fact_date.' - '.$ref.'.pdf';

            // Creation de l'url de DL
            $fact_url=str_replace("[%fact_ref%]",$ref,$this->_fact_url_tpl);
            $fact_url=str_replace("[%fact_passwd%]",$fact_passwd,$fact_url);

            // download
            $pdf='';
            $h = fopen($fact_url, "rb");
            while (!feof($h)) {
                $pdf.= fread($h, 8192);
            }
            fclose($h);

            //save
            $h=fopen($save_as,"wb");
            fwrite($h,$pdf);
        }
        print "OK\n";
    }
}

/* GO */
$ob= new OVHBilling(DATE_START,DATE_STOP);
$ob->run();