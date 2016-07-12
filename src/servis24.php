<?php

/*
Copyright (c) 2015, Adam Schubert <adam.schubert@sg1-game.net>
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
1. Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.
3. All advertising materials mentioning features or use of this software
   must display the following acknowledgement:
   This product includes software developed by the <organization>.
4. Neither the name of the <organization> nor the
   names of its contributors may be used to endorse or promote products
   derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY <COPYRIGHT HOLDER> ''AS IS'' AND ANY
EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

namespace Salamek;

class Servis24
{
    /** @var string */
    private $clientId;

    /** @var string */
    private $password;

    /** @var string */
    private $securedStorage;

    /** @var \DOMXPath */
    private $lastData;

    /** @var string */
    private $lastUrl;

    /** @var HttpRequest */
    private $httpRequest;


    const TRANSACTION_ALL = 0;
    const TRANSACTION_INCOME = 1;
    const TRANSACTION_EXPENSES = 2;


    /**
     * Servis24 constructor.
     * @param string $clientId Client ID
     * @param string $password Password
     * @param string $securedStorage path to directory where TMP data will be stored, this directory should be protected from unauthorized access!!!
     * @throws \Exception
     */
    public function __construct($clientId, $password, $securedStorage)
    {
        $this->clientId = $clientId;
        $this->password = $password;
        $this->securedStorage = $securedStorage;

        if (!is_dir($securedStorage))
        {
            throw new \Exception('Secured storage '.$securedStorage.' not found!');
        }

        if (!is_writable($securedStorage))
        {
            throw new \Exception('Secured storage '.$securedStorage.' is not writable!');
        }

        $this->httpRequest = new HttpRequest($securedStorage.'/cookiejar.txt');
    }

    /**
     *
     */
    public function __destruct()
    {
        //When we try to login again withnout proper logout... their web is doing weird things
        //!FIXME $this->signOut();
    }

    /**
     * Sign out currently logged user
     */
    private function signOut()
    {
        //SignOut form is on every page so we use lastData variable for parsing
        $xpath = $this->lastData;
        //$formId = 'j_id_w';
        $formId = 'j_id_y';
        $signoutForm = $xpath->query('//*[@id="' . $formId . '"]');
        $hiddens = $xpath->query('//*[@id="' . $formId . '"]//input[@type=\'hidden\']');

        //Call only when signout option is in code
        if ($signoutForm->item(0)) {
            $postData = array();
            foreach ($hiddens AS $hidden) {
                $postData[$hidden->getAttribute('name')] = $hidden->getAttribute('value');
            }

            $postData['source'] = 'logoutButton';

            $url = HttpRequest::absolutizeHtmlUrl($this->lastUrl, $signoutForm->item(0)->getAttribute('action'));

            $this->httpRequest->post($url, $postData);
        }
    }

    private function checkSignedIn()
    {
        if (!$this->isSignedIn())
        {
            $this->signIn();
        }
    }


    /**
     * @return bool
     * @throws \Exception
     */
    private function isSignedIn()
    {
        $httpResponse = $this->httpRequest->get('https://www.servis24.cz/ebanking-s24/ib/base/inf/productlist/home?execution=e2s1');
        $xpath = $httpResponse->getBody(HttpResponse::FORMAT_HTML);
        $logoutButton = $xpath->query('//button[@id="logoutButton"]');
        return ($logoutButton->length > 0);
    }

    /**
     * Sign in
     * @throws \Exception
     */
    public function signIn()
    {
        //Load SignIn page
        $this->httpRequest->setMaxRedirections(5); //There is big chance this will fail sometimes... cos servis24 is piece of shit full of inifinite redirects
        $httpResponse = $this->httpRequest->get('https://www.servis24.cz/ebanking-s24/ib/base/usr/aut/login');
        $this->httpRequest->setMaxRedirections(10);
        $xpath = $httpResponse->getBody(HttpResponse::FORMAT_HTML);
        $lastUrl = $httpResponse->getLastUrl();
        $this->lastData = $xpath;
        $this->lastUrl = $lastUrl;

        //Find needed data from signIn page as form action and hidden fields
        $loginForm = $xpath->query('//*[@id="loginForm"]');
        $hiddens = $xpath->query('//*[@id="loginForm"]//input[@type=\'hidden\']');

        if ($loginForm->item(0)) {
            $postData = array();
            $postData['id_clientid'] = $this->clientId;
            $postData['id_password'] = $this->password;

            foreach ($hiddens AS $hidden) {
                $postData[$hidden->getAttribute('name')] = $hidden->getAttribute('value');
            }

            //This fields are filled by JS before real POST... i think... so lets check if they are empty first
            if (!$postData['id_digest_pwd']) {
                //They do not send password in plaintext, password is replaced by * and sha1 of it in hex is used in id_digest_pwd
                //So we will do this
                $mix1 = sha1($postData['id_clientid'] . $postData['id_password']);
                $mix2 = sha1($mix1 . $postData['id_digest_nonce']);
                $postData['id_digest_pwd'] = $mix2;

                /*Not sure if this is needed, but...*/
                $postData['id_password'] = str_repeat('*', strlen($postData['id_password']));
            }

            if (!$postData['source']) {
                $postData['source'] = 'doLogin';
            }

            //Do signIN (Post form)
            $url = HttpRequest::absolutizeHtmlUrl($lastUrl, $loginForm->item(0)->getAttribute('action'));

            $httpResponse = $this->httpRequest->post($url, $postData);

            $this->lastData = $httpResponse->getBody(HttpResponse::FORMAT_HTML);

            $errors = $this->lastData->query('//li[@class="msgError"]');
            if ($errors->length) {
                foreach ($errors AS $childNode) {
                    throw new \Exception(sprintf('Failed to login: %s', $childNode->textContent));
                }
            }
            
            $this->lastUrl = $httpResponse->getLastUrl();
        } else {
            throw new \Exception('Failed to load login form');
        }
    }

    /**
     * Returns list of transactions
     * @param string $account bank account to fetch
     * @param int $category category
     * @return array
     * @throws \Exception
     */
    public function getTransactionsOld($account, $category = 0)
    {
        //We need to be signed in for this action
        $this->checkSignedIn();


        $url = 'https://www.servis24.cz/ebanking-s24/ib/base/pas/th/get?execution=e3s1';

        $httpResponse = $this->httpRequest->get($url, ['execution' => 'e3s1']);
        $xpath = $httpResponse->getBody(HttpResponse::FORMAT_HTML);

        $lastUrl = $httpResponse->getLastUrl();

        $this->lastData = $xpath;
        $this->lastUrl = $lastUrl;
        $transactionForm = $xpath->query('//*[@id="form_basePasThGet_trn"]');
        $hiddens = $xpath->query('//*[@id="form_basePasThGet_trn"]//input[@type=\'hidden\']');
        $accountsSelect = $xpath->query('//select[@id="formattedaccount"]/option');

        if ($transactionForm->item(0))//We loaded transactionForm successfully
        {

            $postData = array();
            foreach ($hiddens AS $hidden) {
                $postData[$hidden->getAttribute('name')] = $hidden->getAttribute('value');
            }

            //Find AccountID
            $accountId = null;
            foreach ($accountsSelect AS $options) {
                if (strpos((string)$options->nodeValue, (string)$account) !== false) {
                    $accountId = $options->getAttribute('value');
                    break;
                }
            }

            $postData['formattedaccount'] = $accountId;
            $postData['trncategory'] = $category; //ID from:

            $postData['timeIntervalRadio'] = 'timeIntervalRadio_lastXdays'; //Limit by number of days:
            $postData['timeIntervalRadio_lastXdays_input'] = 30;
            $postData['userpayees'] = '';
            $postData['recaccountnumber'] = '';
            $postData['recbankcode'] = '';
            $postData['cardnumber'] = '';
            $postData['amountfrom'] = '';
            $postData['amountto'] = '';
            $postData['payervariablesymbol'] = '';
            $postData['constantsymbol'] = '';
            $postData['recspecificsymbol'] = '';
            $postData['textvalue'] = '';

            $postData['source'] = 'doSearch';

            $url = HttpRequest::absolutizeHtmlUrl($lastUrl, $transactionForm->item(0)->getAttribute('action'));

            $httpResponse = $this->httpRequest->post($url, $postData);
            $xpath = $httpResponse->getBody(HttpResponse::FORMAT_HTML);
            $lastUrl = $httpResponse->getLastUrl();

            $this->lastData = $xpath;
            $this->lastUrl = $lastUrl;

            // Parse data for CSV export form
            $exportForm = $xpath->query('//*[@id="form_basePasThGet_lst"]');
            $hiddens = $xpath->query('//*[@id="form_basePasThGet_lst"]//input[@type=\'hidden\']');
            $inputs = $xpath->query('//*[@id="form_basePasThGet_lst"]//input[@type=\'text\']');

            if ($exportForm->item(0))//We loaded exportForm successfully
            {
                $postData = array();
                foreach ($hiddens AS $hidden) {
                    $postData[$hidden->getAttribute('name')] = $hidden->getAttribute('value');
                }

                foreach ($inputs AS $input) {
                    $postData[$input->getAttribute('name')] = $input->getAttribute('value');
                }

                $postData['hideBalances'] = 'false';
                $postData['downloadformat'] = 2;
                //Finds source
                //<button type="button" title="Ulo&#382;it" onclick="return _chain('disable_load_window();','submitForm(\'form_basePasThGet_lst\',1,{source:\'j_id_p1\'});return false;',this,event,true)" class="button af_commandButton">Ulo&#382;it</button>
                $regexpSource = "/\<button\s+type=\"button\".+?onclick=\".+?\{source\:\S'(\S+)\S'\}\);/si";
                $matches = array();
                if (preg_match($regexpSource, $httpResponse->getBody(HttpResponse::FORMAT_RAW), $matches)) {
                    $postData['source'] = $matches[1];
                } else {
                    $postData['source'] = 'j_id_p1';
                }
                $postData['state'] = '';
                $postData['value'] = '';

                $url = HttpRequest::absolutizeHtmlUrl($lastUrl, $exportForm->item(0)->getAttribute('action'));

                $httpResponse = $this->httpRequest->post($url, $postData);

                $xpath = $httpResponse->getBody(HttpResponse::FORMAT_HTML);
                $lastUrl = $httpResponse->getLastUrl();
                $this->lastData = $xpath;
                $this->lastUrl = $lastUrl;
                //OMS (something like OMG) they use CP1250!!! for export!!! Fuck them! Fuck me! Fuck you! Fuck everything!
                $dataUTF8 = iconv("CP1250", "UTF-8", $httpResponse->getBody(HttpResponse::FORMAT_RAW)); //Convert that shit

                //Parse CSV
                $rows = str_getcsv($dataUTF8, "\n");
                unset($rows[0]); //Ignore first line
                $csv = array();
                foreach ($rows AS $row) {
                    list(
                        $type,
                        $datePostings,
                        $var2,
                        $s24_base_pas_th_get_csv_ca_accAmount,
                        $accountCurrency,
                        $bankAccount,
                        $dateProcessed,
                        $var1,
                        $ammount,
                        $currency,
                        $bankAccountName,
                        $const,
                        $spec,
                        $storno,
                        $messageForTecipient,
                        $note,
                        $paymentReference,
                        $crap
                        ) = str_getcsv($row);

                    $csv[] = array(
                        'type' => $type,
                        'datePostings' => new \DateTime($datePostings),
                        'var2' => $var2,
                        'ammount2' => $s24_base_pas_th_get_csv_ca_accAmount,
                        'accountCurrency' => $accountCurrency,
                        'bankAccount' => $bankAccount,
                        'dateProcessed' => new \DateTime($dateProcessed),
                        'var1' => $var1,
                        'ammount' => $ammount,
                        'currency' => $currency,
                        'bankAccountName' => $bankAccountName,
                        'const' => $const,
                        'spec' => $spec,
                        'storno' => ($storno == 'Ano'),
                        'messageForTecipient' => $messageForTecipient,
                        'note' => $note,
                        'paymentReference' => $paymentReference
                    );
                }

                return $csv;
            } else {
                throw new \Exception('Failed to load export form');
            }
        } else {
            throw new \Exception('Failed to load transaction form');
        }
    }


    public function getTransactions($account, \DateTime $fromDate = null, \DateTime $toDate = null)
    {
        //We need to be signed in for this action
        $this->checkSignedIn();

        //Process dates
        $now = new \DateTime;
        if ($fromDate > $now)
        {
            $fromDate = $now;
        }

        if ($toDate > $now)
        {
            $toDate = $now;
        }

        if ($fromDate > $toDate)
        {
            $toDate = $fromDate;
        }

        $interval = new \DateInterval('P1D');
        $daterange = new \DatePeriod($fromDate, $interval ,$toDate);

        //Get list of extract transactions for account
        $url = 'https://www.servis24.cz/ebanking-s24/ib/base/pas/statementlist/get/acc';

        $httpResponse = $this->httpRequest->get($url);

        $xpath = $httpResponse->getBody(HttpResponse::FORMAT_HTML);
        $extractForm = $xpath->query('//*[@id="ib_trn_base_pas_statementlist_get"]');

        if (!$extractForm->item(0))
        {
            throw new \Exception('Failed to load extract form');
        }

        $hiddens = $xpath->query('//*[@id="ib_trn_base_pas_statementlist_get"]//input[@type=\'hidden\']');
        $accountsSelect = $xpath->query('//select[@id="accountnumber"]/option');

        $postData = array();
        foreach ($hiddens AS $hidden) {
            $postData[$hidden->getAttribute('name')] = $hidden->getAttribute('value');
        }

        //Find AccountID
        $accountId = null;
        foreach ($accountsSelect AS $options) {
            if (strpos((string)$options->nodeValue, (string)$account) !== false) {
                $accountId = $options->getAttribute('value');
                break;
            }
        }

        $postData['accountnumber'] = $accountId;
        $postData['statementType'] = 'dataStatementsRadioId';
        $postData['source'] = 'doSearch';

        $urlPost = HttpRequest::absolutizeHtmlUrl($httpResponse->getLastUrl(), $extractForm->item(0)->getAttribute('action'));

        $httpResponse = $this->httpRequest->post($urlPost, $postData);

        $xpath = $httpResponse->getBody(HttpResponse::FORMAT_HTML);

        $table = $xpath->query('//a[starts-with(@id,"table_base_pas_statementlist_daily_data_get_ib_table")]');
        $extractList = [];
        if ($table->length) {
            /** @var \DOMNode $childNode */
            foreach ($table AS $childNode) {
                list($name, $id, $mess) = explode(':', $childNode->getAttribute('id'));
                $extractList[trim($id)] = new \DateTime(implode('-', array_reverse(explode('.', trim($childNode->textContent)))));
            }
        }

        //Try to download additional extracts not listed in small list
        /*!FIXME not working $maxId = max(array_keys($extractList));

        foreach($daterange AS $date)
        {
            if ($date->format('Y-m-d') == $now->format('Y-m-d'))
            {
                continue;
            }

            $maxId++;
            $extractList[$maxId] = $date;
        }*/

        //Compare it with local list, and download anything new
        $downloadForm = $xpath->query('//*[@id="form_cicExpGet_lst"]');

        if (!$downloadForm->item(0))
        {
            throw new \Exception('Failed to load download form');
        }

        $hiddens = $xpath->query('//*[@id="form_cicExpGet_lst"]//input[@type=\'hidden\']');
        $urlPost = HttpRequest::absolutizeHtmlUrl($url, $downloadForm->item(0)->getAttribute('action'));
        foreach ($extractList AS $id => $date)
        {
            $tmpFileName = $this->securedStorage.'/'.$date->format('Y-m-d').'.json';
            if (!is_file($tmpFileName))
            {
                $postData = [];

                foreach ($hiddens AS $hidden) {
                    $postData[$hidden->getAttribute('name')] = $hidden->getAttribute('value');
                }

                $postData['source'] = 'table_base_pas_statementlist_daily_data_get_ib_table:'.$id.':j_id_a0';

                $httpResponse = $this->httpRequest->post($urlPost, $postData);

                $headers = $httpResponse->getHeaders();

                if (strpos($headers['all']['Content-Type'], 'text/csv') === false)
                {
                    //Proccess only CSV files
                    throw new \Exception('Not a CSV file');
                }

                $dataUTF8 = iconv("CP1250", "UTF-8", $httpResponse->getBody(HttpResponse::FORMAT_RAW)); //Convert that shit

                //Parse CSV
                $rows = str_getcsv($dataUTF8, "\n");

                //Ignore first X lines
                foreach(range(0, 13) AS $i)
                {
                    unset($rows[$i]);
                }
                
                $csv = [];

                foreach ($rows AS $row)
                {
                    /*[1] => Array
                    (
                        [0] => Datum splatnosti
                        [1] => Položka
                        [2] => Číslo protiúčtu
                        [3] => Obrat
                        [4] => Měna
                        [5] => Datum odpisu
                        [6] => Informace k platbě
                        [7] => Název protiúčtu
                        [8] => Var.symb.1
                        [9] => Bankovní věta
                        [10] => Konst.symb.
                        [11] => Spec.symb.
                        [12] => Var.symb.2
                        [13] => Částka obratu ISO
                        [14] => Měna
                        [15] => Kurz měny obratu
                        [16] => Kurz měny účtu
                        [17] => Reference platby
                        [18] => Kód příkazce
                        [19] => Kód příjemce
                        [20] => -
                    )*/

                    list(
                        $dueDate,
                        $type,
                        $accountNumber,
                        $veer,
                        $currency,
                        $dateOfAttribution,
                        $paymentInfo,
                        $accountName,
                        $bankIdentifier,
                        $bankWord,
                        $constantSymbol,
                        $specSymbol,
                        $bankIdentifierSecond,
                        $veerIso,
                        $currencyIso,
                        $tradeTurnoverRate,
                        $exchangeRateAccount,
                        $paymentReference,
                        $payerCode,
                        $recipientCode,
                        $foo,
                        ) = $all = str_getcsv($row, ";");


                    switch ($type)
                    {
                        case 'Úhrada':
                        case 'Došlá platba':
                        case 'Úhrada ze zahraničí':
                        case 'Úrok kredit':
                            $typeInt = self::TRANSACTION_INCOME;
                            break;

                        case 'Platba kartou':
                        case 'Domácí platba - S24/IB':
                        case 'Výběr z bankomatu ČS':
                        case 'Výběr z bankomatu jiné banky':
                        case 'Cena - zahraniční úhrada':
                        case 'Úrok debet':
                        case 'Poplatek':
                            $typeInt = self::TRANSACTION_EXPENSES;
                            break;

                        default:
                            $typeInt = '?';
                            break;
                    }

                    $csv[] = [
                        'bankIdentifier' => trim($bankIdentifier),
                        'amount' => floatval(strtr(trim($veer), [' ' => '', ',' => '.'])),
                        'currency' => $currency,
                        'type' => $typeInt,
                        'all' => $all
                    ];
                }

                file_put_contents($tmpFileName, json_encode($csv, JSON_PRETTY_PRINT));
            }
        }

        //Procces downloaded data

        $return = [];
        foreach($daterange AS $date)
        {
            $tmpFileName = $this->securedStorage.'/'.$date->format('Y-m-d').'.json';
            if (is_file($tmpFileName))
            {
                $content = @file_get_contents($tmpFileName);
                if ($content !== false)
                {
                    $decoded = json_decode($content);
                    if ($decoded !== false)
                    {
                        foreach($decoded AS $row)
                        {
                            $return[] = $row;
                        }
                    }
                }
            }
        }

        return $return;
    }
}

